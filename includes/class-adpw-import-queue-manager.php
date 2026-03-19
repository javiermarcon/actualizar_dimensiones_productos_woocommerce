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

        $job = ADPW_Import_Job_Factory::create_job($init, $settings);
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
            ADPW_Ajax_Handler_Utils::ensure_manage_options('No tenés permisos para iniciar la importación.');
            ADPW_Ajax_Handler_Utils::verify_nonce(self::AJAX_NONCE_ACTION, 'nonce');

            $settings = ADPW_Settings::get();
            $file = $_FILES['archivo_excel'] ?? null;
            $start = self::start_import_job($file, $settings);
            if (!empty($start['error_general'])) {
                ADPW_Ajax_Handler_Utils::error($start);
            }
            ADPW_Ajax_Handler_Utils::success($start);
        } catch (\Throwable $e) {
            ADPW_Ajax_Handler_Utils::rethrow_test_json_exception($e);
            ADPW_Ajax_Handler_Utils::error([
                'error_general' => 'Excepción en start_import: ' . $e->getMessage(),
            ]);
        }
    }

    public static function ajax_import_status() {
        try {
            ADPW_Ajax_Handler_Utils::ensure_manage_options('No tenés permisos para consultar estado.');
            ADPW_Ajax_Handler_Utils::verify_nonce(self::AJAX_NONCE_ACTION, 'nonce');

            $job = self::get_job();
            if (!$job) {
                ADPW_Ajax_Handler_Utils::success(ADPW_Ajax_Handler_Utils::idle_payload());
            }

            if (($job['status'] ?? '') === 'running' && function_exists('spawn_cron')) {
                spawn_cron(time());
            }

            ADPW_Ajax_Handler_Utils::success(self::build_summary($job));
        } catch (\Throwable $e) {
            ADPW_Ajax_Handler_Utils::rethrow_test_json_exception($e);
            ADPW_Ajax_Handler_Utils::error([
                'error_general' => 'Excepción en import_status: ' . $e->getMessage(),
            ]);
        }
    }

    public static function ajax_run_batch() {
        try {
            ADPW_Ajax_Handler_Utils::ensure_manage_options('No tenés permisos para ejecutar lotes.');
            ADPW_Ajax_Handler_Utils::verify_nonce(self::AJAX_NONCE_ACTION, 'nonce');
            $summary = self::run_batch_now();
            if (!empty($summary['error_general'])) {
                ADPW_Ajax_Handler_Utils::error($summary);
            }
            ADPW_Ajax_Handler_Utils::success($summary);
        } catch (\Throwable $e) {
            ADPW_Ajax_Handler_Utils::rethrow_test_json_exception($e);
            ADPW_Ajax_Handler_Utils::error([
                'error_general' => 'Excepción en run_batch: ' . $e->getMessage(),
            ]);
        }
    }

    public static function process_batch($job_id) {
        $job = self::get_job();

        if (!$job || ($job['status'] ?? '') !== 'running' || ($job['id'] ?? '') !== $job_id) {
            return;
        }

        $job = ADPW_Import_Batch_Runner::process($job);
        self::save_job($job);

        if (($job['status'] ?? '') === 'running') {
            self::schedule_next_batch($job['id']);
        }
    }

    private static function build_summary($job) {
        return ADPW_Import_Job_Summary::build_summary($job);
    }

    private static function append_debug(&$job, $message) {
        ADPW_Import_Job_Store::append_debug($job, $message);
    }

    private static function get_job() {
        return ADPW_Import_Job_Store::get_job(self::OPTION_JOB);
    }

    private static function save_job($job) {
        ADPW_Import_Job_Store::save_job(self::OPTION_JOB, $job);
    }

    private static function schedule_next_batch($job_id) {
        ADPW_Import_Job_Store::schedule_next_batch(self::CRON_HOOK, $job_id);
    }
}
