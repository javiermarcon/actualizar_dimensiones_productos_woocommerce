<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Import_Queue_Manager {
    private const OPTION_JOB = 'adpw_import_job';
    private const CRON_HOOK = 'adpw_process_import_batch';
    private const AJAX_NONCE_ACTION = 'adpw_import_ajax';

    public static function register_hooks() {
        add_action('wp_ajax_adpw_start_import', [self::class, 'ajax_start_import']);
        add_action('wp_ajax_adpw_import_status', [self::class, 'ajax_import_status']);
        add_action('wp_ajax_adpw_import_run_batch', [self::class, 'ajax_run_batch']);
        add_action(self::CRON_HOOK, [self::class, 'process_batch'], 10, 1);
    }

    public static function get_job_snapshot() {
        $job = self::get_job();
        if (!$job) {
            return null;
        }

        return self::build_summary($job);
    }

    public static function run_batch_now() {
        $job = self::get_job();
        if (!$job || ($job['status'] ?? '') !== 'running' || empty($job['id'])) {
            return ['error_general' => 'No hay job en ejecución para procesar.'];
        }

        self::process_batch($job['id']);
        $updated = self::get_job();
        return $updated ? self::build_summary($updated) : ['error_general' => 'No se pudo recuperar estado tras procesar lote.'];
    }

    public static function start_import_job($file, $settings) {
        $batch_size = max(1, (int) ($settings['categorias_por_lote'] ?? 20));
        $init = ADPW_Excel_Import_Service::initialize_job($file, $settings, $batch_size);
        if (!empty($init['error_general'])) {
            return [
                'error_general' => $init['error_general'],
                'detalles' => isset($init['detalles']) && is_array($init['detalles']) ? $init['detalles'] : [],
                'debug_lines' => isset($init['debug_lines']) && is_array($init['debug_lines']) ? $init['debug_lines'] : [],
            ];
        }

        $job = [
            'id' => wp_generate_uuid4(),
            'status' => 'running',
            'stage' => 'parse_sheet',
            'created_at' => time(),
            'updated_at' => time(),
            'batch_size' => $init['batch_size'],
            'settings' => [
                'actualizar_si' => !empty($settings['actualizar_si']),
                'actualizar_tam' => !empty($settings['actualizar_tam']),
                'actualizar_cat' => !empty($settings['actualizar_cat']),
            ],
            'mode' => $init['mode'],
            'uploaded_file_path' => $init['uploaded_file_path'],
            'categories_data_file' => $init['categories_data_file'],
            'columns' => $init['columns'],
            'cursor_row' => $init['cursor_row'],
            'highest_row' => $init['highest_row'],
            'empty_row_count' => $init['empty_row_count'],
            'processed_rows' => $init['processed_rows'],
            'total_rows' => $init['total_rows'],
            'category_ids' => $init['category_ids'],
            'category_cursor' => $init['category_cursor'],
            'product_queue' => $init['product_queue'],
            'product_cursor' => $init['product_cursor'],
            'results' => $init['results'],
            'error_general' => '',
            'debug_log' => [],
        ];

        self::append_debug($job, 'Job creado. stage=parse_sheet, total_rows=' . (int) $job['total_rows']);
        if (!empty($init['debug_lines']) && is_array($init['debug_lines'])) {
            foreach ($init['debug_lines'] as $line) {
                self::append_debug($job, 'INIT ' . (string) $line);
            }
        }
        if (!empty($init['warnings']) && is_array($init['warnings'])) {
            foreach ($init['warnings'] as $warning) {
                self::append_debug($job, 'WARNING: ' . $warning);
                if (isset($job['results']['detalles']) && is_array($job['results']['detalles'])) {
                    $job['results']['detalles'][] = (string) $warning;
                }
            }
        }
        self::save_job($job);
        self::schedule_next_batch($job['id']);

        return [
            'job_id' => $job['id'],
            'stage' => $job['stage'],
            'batch_size' => $job['batch_size'],
        ];
    }

    public static function ajax_start_import() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'No tenés permisos para iniciar la importación.']);
            }

            check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

            $settings = ADPW_Settings::get();
            $file = $_FILES['archivo_excel'] ?? null;
            $start = self::start_import_job($file, $settings);
            if (!empty($start['error_general'])) {
                wp_send_json_error($start);
            }
            wp_send_json_success($start);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'error_general' => 'Excepción en start_import: ' . $e->getMessage(),
            ]);
        }
    }

    public static function ajax_import_status() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'No tenés permisos para consultar estado.']);
            }

            check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

            $job = self::get_job();
            if (!$job) {
                wp_send_json_success([
                    'status' => 'idle',
                    'progress' => 0,
                    'stage' => 'idle',
                    'results' => null,
                    'debug_log' => [],
                ]);
            }

            if (($job['status'] ?? '') === 'running' && function_exists('spawn_cron')) {
                spawn_cron(time());
            }

            wp_send_json_success(self::build_summary($job));
        } catch (\Throwable $e) {
            wp_send_json_error([
                'error_general' => 'Excepción en import_status: ' . $e->getMessage(),
            ]);
        }
    }

    public static function ajax_run_batch() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'No tenés permisos para ejecutar lotes.']);
            }

            check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');
            $summary = self::run_batch_now();
            if (!empty($summary['error_general'])) {
                wp_send_json_error($summary);
            }
            wp_send_json_success($summary);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'error_general' => 'Excepción en run_batch: ' . $e->getMessage(),
            ]);
        }
    }

    public static function process_batch($job_id) {
        $job = self::get_job();

        if (!$job || ($job['status'] ?? '') !== 'running' || ($job['id'] ?? '') !== $job_id) {
            return;
        }

        self::append_debug($job, 'Batch start stage=' . $job['stage']);

        try {
            ADPW_Excel_Import_Service::process_job_batch($job);
        } catch (\Throwable $e) {
            $job['status'] = 'failed';
            $job['error_general'] = 'Excepción en batch: ' . $e->getMessage();
            self::append_debug($job, 'ERROR ' . $job['error_general']);
        }

        $job['updated_at'] = time();
        self::append_debug($job, 'Batch end stage=' . $job['stage'] . ' status=' . $job['status']);

        self::save_job($job);

        if (($job['status'] ?? '') === 'running') {
            self::schedule_next_batch($job['id']);
        }
    }

    private static function build_summary($job) {
        $stage = (string) ($job['stage'] ?? 'unknown');
        $status = (string) ($job['status'] ?? 'unknown');

        if ($stage === 'parse_sheet') {
            $total = max(1, (int) ($job['total_rows'] ?? 1));
            $processed = min($total, (int) ($job['processed_rows'] ?? 0));
            $progress = (int) floor(($processed / $total) * 33);
            $label = 'Leyendo planilla y generando temporal';
        } elseif ($stage === 'save_category_meta') {
            $total = max(1, count((array) ($job['category_ids'] ?? [])));
            $processed = min($total, (int) ($job['category_cursor'] ?? 0));
            $progress = 33 + (int) floor(($processed / $total) * 33);
            $label = 'Guardando metadata de categorías';
        } else {
            $total = max(1, count((array) ($job['product_queue'] ?? [])));
            $processed = min($total, (int) ($job['product_cursor'] ?? 0));
            $progress = 66 + (int) floor(($processed / $total) * 34);
            $label = 'Actualizando productos';
        }

        if ($status === 'completed') {
            $progress = 100;
        }
        if ($status === 'running') {
            $progress = max(1, $progress);
        }

        return [
            'status' => $status,
            'stage' => $stage,
            'stage_label' => $label,
            'progress' => max(0, min(100, $progress)),
            'processed_entries' => $processed,
            'total_entries' => $total,
            'updated_at' => (int) ($job['updated_at'] ?? 0),
            'results' => $job['results'] ?? null,
            'error_general' => $job['error_general'] ?? '',
            'debug_log' => array_slice((array) ($job['debug_log'] ?? []), -40),
        ];
    }

    private static function append_debug(&$job, $message) {
        if (!isset($job['debug_log']) || !is_array($job['debug_log'])) {
            $job['debug_log'] = [];
        }

        $job['debug_log'][] = '[' . gmdate('H:i:s') . '] ' . $message;

        if (count($job['debug_log']) > 300) {
            $job['debug_log'] = array_slice($job['debug_log'], -300);
        }
    }

    private static function get_job() {
        $job = get_option(self::OPTION_JOB, null);
        return is_array($job) ? $job : null;
    }

    private static function save_job($job) {
        update_option(self::OPTION_JOB, $job, false);
    }

    private static function schedule_next_batch($job_id) {
        $next = wp_next_scheduled(self::CRON_HOOK, [$job_id]);
        if ($next) {
            return;
        }

        wp_schedule_single_event(time() + 2, self::CRON_HOOK, [$job_id]);
    }
}
