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
        $start = ADPW_Import_Start_Service::start($file, $settings);
        if (!empty($start['error_general'])) {
            return $start;
        }

        $job = $start['job'];
        self::save_job($job);
        self::schedule_next_batch($job['id']);

        return $start['response'];
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
            ADPW_Ajax_Handler_Utils::handle_unexpected_exception($e, 'Excepción en start_import: ');
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
            ADPW_Ajax_Handler_Utils::handle_unexpected_exception($e, 'Excepción en import_status: ');
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
            ADPW_Ajax_Handler_Utils::handle_unexpected_exception($e, 'Excepción en run_batch: ');
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
