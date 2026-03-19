<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Import_Page_Actions {
    public static function handle_requests($settings, $start_nonce_action, $start_nonce_field, $manual_nonce_action, $manual_nonce_field) {
        $result = [
            'start_message' => '',
            'start_error' => '',
            'start_error_details' => [],
            'manual_message' => '',
        ];

        $is_start_post = (
            isset($_SERVER['REQUEST_METHOD']) &&
            strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST' &&
            isset($_POST[$start_nonce_field])
        );

        if ($is_start_post) {
            $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$start_nonce_field])), $start_nonce_action);
            if (!$nonce_valid) {
                $result['start_error'] = 'POST detectado pero nonce inválido en inicio de importación.';
            } else {
                $start = ADPW_Import_Queue_Manager::start_import_job($_FILES['archivo_excel'] ?? null, $settings);
                if (!empty($start['error_general'])) {
                    $result['start_error'] = $start['error_general'];
                    if (!empty($start['detalles']) && is_array($start['detalles'])) {
                        $result['start_error_details'] = $start['detalles'];
                    }
                    if (!empty($start['debug_lines']) && is_array($start['debug_lines'])) {
                        foreach ($start['debug_lines'] as $line) {
                            $result['start_error_details'][] = 'debug: ' . (string) $line;
                        }
                    }
                } else {
                    $result['start_message'] = 'Importación iniciada en segundo plano. Job ID: ' . ($start['job_id'] ?? 'N/A');
                }
            }
        }

        if (
            isset($_POST['adpw_run_manual_batch']) &&
            isset($_POST[$manual_nonce_field]) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$manual_nonce_field])), $manual_nonce_action)
        ) {
            $manual = ADPW_Import_Queue_Manager::run_batch_now();
            if (!empty($manual['error_general'])) {
                $result['start_error'] = $manual['error_general'];
            } else {
                $result['manual_message'] = 'Se ejecutó manualmente un lote de importación.';
            }
        }

        return $result;
    }
}
