<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Update_Queue_Manager {
    private const OPTION_JOB = 'adpw_category_update_job';
    private const CRON_HOOK = 'adpw_process_category_update_batch';
    private const AJAX_NONCE_ACTION = 'adpw_category_update_ajax';

    public static function register_hooks() {
        add_action(self::CRON_HOOK, [self::class, 'process_batch'], 10, 1);
        add_action('wp_ajax_adpw_category_update_status', [self::class, 'ajax_status']);
        add_action('wp_ajax_adpw_category_update_run_batch', [self::class, 'ajax_run_batch']);
    }

    public static function start_job($category_ids, $batch_size) {
        $category_ids = array_values(array_unique(array_map('absint', (array) $category_ids)));
        if (empty($category_ids)) {
            return ['error_general' => 'No hay categorías para actualizar en segundo plano.'];
        }

        $existing = self::get_job();
        if ($existing && ($existing['status'] ?? '') === 'running') {
            return ['error_general' => 'Ya hay una actualización del árbol en ejecución. Esperá a que termine antes de iniciar otra.'];
        }

        $queue = ADPW_Category_Metadata_Manager::build_product_queue_for_categories($category_ids);
        $job = [
            'id' => wp_generate_uuid4(),
            'status' => empty($queue) ? 'completed' : 'running',
            'stage' => 'update_products',
            'created_at' => time(),
            'updated_at' => time(),
            'batch_size' => max(1, (int) $batch_size),
            'category_ids' => $category_ids,
            'product_queue' => $queue,
            'product_cursor' => 0,
            'results' => [
                'totales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'runtime' => [],
            'error_general' => '',
            'debug_log' => [],
        ];

        ADPW_Background_Job_Utils::append_debug($job, 'Job árbol creado. products=' . count($queue));
        self::save_job($job);
        if (($job['status'] ?? '') === 'running') {
            self::schedule_next_batch($job['id']);
        }

        return [
            'job_id' => $job['id'],
            'batch_size' => $job['batch_size'],
            'total_entries' => count($queue),
            'status' => $job['status'],
        ];
    }

    public static function run_batch_now() {
        $job = self::get_job();
        if (!$job || ($job['status'] ?? '') !== 'running' || empty($job['id'])) {
            return ['error_general' => 'No hay job del árbol en ejecución para procesar.'];
        }

        self::process_batch($job['id']);
        $updated = self::get_job();
        return $updated ? self::build_summary($updated) : ['error_general' => 'No se pudo recuperar estado tras procesar lote del árbol.'];
    }

    public static function get_job_snapshot() {
        $job = self::get_job();
        if (!$job) {
            return null;
        }

        if (($job['status'] ?? '') === 'running' && function_exists('spawn_cron')) {
            spawn_cron(time());
        }

        return self::build_summary($job);
    }

    public static function ajax_status() {
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
            if (class_exists('ADPW_Test_Json_Response_Exception') && $e instanceof ADPW_Test_Json_Response_Exception) {
                throw $e;
            }
            wp_send_json_error([
                'error_general' => 'Excepción en category_update_status: ' . $e->getMessage(),
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
            if (class_exists('ADPW_Test_Json_Response_Exception') && $e instanceof ADPW_Test_Json_Response_Exception) {
                throw $e;
            }
            wp_send_json_error([
                'error_general' => 'Excepción en category_update_run_batch: ' . $e->getMessage(),
            ]);
        }
    }

    public static function process_batch($job_id) {
        $job = self::get_job();
        if (!$job || ($job['status'] ?? '') !== 'running' || ($job['id'] ?? '') !== $job_id) {
            return;
        }

        ADPW_Background_Job_Utils::append_debug($job, 'Batch árbol start stage=' . $job['stage']);

        try {
            ADPW_Category_Metadata_Manager::process_product_queue_batch($job);
        } catch (\Throwable $e) {
            $job['status'] = 'failed';
            $job['error_general'] = 'Excepción en batch del árbol: ' . $e->getMessage();
            ADPW_Background_Job_Utils::append_debug($job, 'ERROR ' . $job['error_general']);
        }

        $job['updated_at'] = time();
        ADPW_Background_Job_Utils::append_debug($job, 'Batch árbol end status=' . $job['status']);

        self::save_job($job);

        if (($job['status'] ?? '') === 'running') {
            self::schedule_next_batch($job['id']);
        }
    }

    private static function build_summary($job) {
        $total = count((array) ($job['product_queue'] ?? []));
        $processed = (int) ($job['product_cursor'] ?? 0);
        return ADPW_Background_Job_Utils::build_summary($job, 'Actualizando productos desde árbol de categorías', $processed, $total);
    }

    private static function get_job() {
        return ADPW_Background_Job_Utils::get_job(self::OPTION_JOB);
    }

    private static function save_job($job) {
        ADPW_Background_Job_Utils::save_job(self::OPTION_JOB, $job);
    }

    private static function schedule_next_batch($job_id) {
        ADPW_Background_Job_Utils::schedule_next_batch(self::CRON_HOOK, $job_id);
    }
}
