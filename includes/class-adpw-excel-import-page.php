<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Import_Page {
    private const START_NONCE_ACTION = 'adpw_start_import_form';
    private const START_NONCE_FIELD = 'adpw_start_import_form_nonce';
    private const MANUAL_NONCE_ACTION = 'adpw_manual_batch';
    private const MANUAL_NONCE_FIELD = 'adpw_manual_batch_nonce';

    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>Importar Excel</h1>';

        $settings = ADPW_Settings::get();
        $ajax_nonce = wp_create_nonce('adpw_import_ajax');

        $request_debug = self::build_request_debug();
        $start_message = '';
        $start_error = '';
        $start_error_details = [];
        $manual_message = '';

        $is_start_post = (
            isset($_SERVER['REQUEST_METHOD']) &&
            strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST' &&
            isset($_POST[self::START_NONCE_FIELD])
        );

        if ($is_start_post) {
            $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::START_NONCE_FIELD])), self::START_NONCE_ACTION);
            if (!$nonce_valid) {
                $start_error = 'POST detectado pero nonce inválido en inicio de importación.';
            } else {
                $start = ADPW_Import_Queue_Manager::start_import_job($_FILES['archivo_excel'] ?? null, $settings);
                if (!empty($start['error_general'])) {
                    $start_error = $start['error_general'];
                    if (!empty($start['detalles']) && is_array($start['detalles'])) {
                        $start_error_details = $start['detalles'];
                    }
                    if (!empty($start['debug_lines']) && is_array($start['debug_lines'])) {
                        foreach ($start['debug_lines'] as $line) {
                            $start_error_details[] = 'debug: ' . (string) $line;
                        }
                    }
                } else {
                    $start_message = 'Importación iniciada en segundo plano. Job ID: ' . ($start['job_id'] ?? 'N/A');
                }
            }
        }

        if (
            isset($_POST['adpw_run_manual_batch']) &&
            isset($_POST[self::MANUAL_NONCE_FIELD]) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::MANUAL_NONCE_FIELD])), self::MANUAL_NONCE_ACTION)
        ) {
            $manual = ADPW_Import_Queue_Manager::run_batch_now();
            if (!empty($manual['error_general'])) {
                $start_error = $manual['error_general'];
            } else {
                $manual_message = 'Se ejecutó manualmente un lote de importación.';
            }
        }

        if ($start_error !== '') {
            $error_content = $start_error;
            if (!empty($start_error_details)) {
                $error_content .= "\n\n" . implode("\n", array_map(static function ($line) {
                    return '- ' . (string) $line;
                }, $start_error_details));
            }
            ADPW_Admin_Job_Progress_UI::render_box('Error', $error_content, '#b32d2e', '#fff1f1');
        }
        if ($start_message !== '') {
            ADPW_Admin_Job_Progress_UI::render_box('Inicio', $start_message, '#1d2327', '#effff0');
        }
        if ($manual_message !== '') {
            ADPW_Admin_Job_Progress_UI::render_box('Batch Manual', $manual_message, '#1d2327', '#eef6ff');
        }

        echo '<p><strong>Configuración activa:</strong> ';
        echo 'Actualizar siempre: ' . (!empty($settings['actualizar_si']) ? 'Sí' : 'No') . ' | ';
        echo 'Actualizar dimensiones: ' . (!empty($settings['actualizar_tam']) ? 'Sí' : 'No') . ' | ';
        echo 'Actualizar tamaño: ' . (!empty($settings['actualizar_cat']) ? 'Sí' : 'No') . ' | ';
        echo 'Tamaño de lote: ' . esc_html((string) $settings['categorias_por_lote']);
        echo '. <a href="' . esc_url(admin_url('admin.php?page=adpw-settings')) . '">Cambiar configuración</a></p>';

        echo '<form id="adpw-import-form" method="post" enctype="multipart/form-data">';
        wp_nonce_field(self::START_NONCE_ACTION, self::START_NONCE_FIELD);
        echo '<input type="file" name="archivo_excel" id="adpw-archivo-excel" required> <br /><br />';
        echo '<button type="submit" class="button button-primary" id="adpw-start-import" name="adpw_start_import_form_submit" value="1">Iniciar importación</button>';
        echo '</form>';

        echo '<form method="post" style="margin-top:8px;">';
        wp_nonce_field(self::MANUAL_NONCE_ACTION, self::MANUAL_NONCE_FIELD);
        echo '<button type="submit" class="button" name="adpw_run_manual_batch" value="1">Procesar siguiente lote ahora</button>';
        echo '</form>';

        ADPW_Admin_Job_Progress_UI::render_progress_markup('adpw-import');

        $snapshot = ADPW_Import_Queue_Manager::get_job_snapshot();
        ADPW_Admin_Job_Progress_UI::render_status_snapshot('Estado Actual', $snapshot, 'adpw-import');
        ADPW_Admin_Job_Progress_UI::render_box('Debug Request', implode("\n", $request_debug), '#1d2327', '#f6f7f7');

        ADPW_Admin_Job_Progress_UI::render_polling_js([
            'prefix' => 'adpw-import',
            'nonce' => $ajax_nonce,
            'statusAction' => 'adpw_import_status',
            'runBatchAction' => 'adpw_import_run_batch',
            'startFormId' => 'adpw-import-form',
            'startButtonId' => 'adpw-start-import',
            'validateInputId' => 'adpw-archivo-excel',
            'startText' => 'Subiendo archivo e iniciando importación...',
            'completedText' => 'Importación completada',
            'errorText' => 'La importación falló.',
            'emptyInputMessage' => 'Seleccioná un archivo Excel.',
        ]);
        echo '</div>';
    }

    private static function build_request_debug() {
        $lines = [];
        $lines[] = 'method=' . (isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'N/A');
        $lines[] = 'post_keys=' . implode(', ', array_keys($_POST));
        $lines[] = 'files_keys=' . implode(', ', array_keys($_FILES));

        if (isset($_POST[self::START_NONCE_FIELD])) {
            $valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::START_NONCE_FIELD])), self::START_NONCE_ACTION) ? 'yes' : 'no';
            $lines[] = 'start_nonce_present=yes';
            $lines[] = 'start_nonce_valid=' . $valid;
        } else {
            $lines[] = 'start_nonce_present=no';
        }

        if (isset($_FILES['archivo_excel'])) {
            $f = $_FILES['archivo_excel'];
            $lines[] = 'file.name=' . (string) ($f['name'] ?? '');
            $lines[] = 'file.size=' . (string) ($f['size'] ?? 0);
            $lines[] = 'file.error=' . (string) ($f['error'] ?? -1);
            $lines[] = 'file.tmp_name=' . (string) ($f['tmp_name'] ?? '');
        }

        return $lines;
    }
}
