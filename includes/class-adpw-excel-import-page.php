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
            self::render_box('Error', $error_content, '#b32d2e', '#fff1f1');
        }
        if ($start_message !== '') {
            self::render_box('Inicio', $start_message, '#1d2327', '#effff0');
        }
        if ($manual_message !== '') {
            self::render_box('Batch Manual', $manual_message, '#1d2327', '#eef6ff');
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

        echo '<div id="adpw-progress-wrapper" style="margin-top:16px;max-width:760px;display:none;">';
        echo '<div style="background:#e5e7eb;border-radius:6px;overflow:hidden;height:18px;">';
        echo '<div id="adpw-progress-bar" style="height:18px;width:0;background:#2271b1;transition:width .25s ease;"></div>';
        echo '</div>';
        echo '<p id="adpw-progress-text" style="margin:8px 0 0;">Preparando...</p>';
        echo '</div>';

        echo '<div id="adpw-result" style="margin-top:16px;"></div>';

        $snapshot = ADPW_Import_Queue_Manager::get_job_snapshot();
        self::render_status_snapshot($snapshot);
        self::render_box('Debug Request', implode("\n", $request_debug), '#1d2327', '#f6f7f7');

        echo '<div id="adpw-debug" style="margin-top:16px;display:none;">';
        echo '<h3>Debug (AJAX)</h3>';
        echo '<pre id="adpw-debug-log" style="background:#f6f7f7;border:1px solid #dcdcde;padding:10px;max-height:260px;overflow:auto;"></pre>';
        echo '</div>';

        self::render_js($ajax_nonce);
        echo '</div>';
    }

    private static function render_status_snapshot($snapshot) {
        if (!$snapshot) {
            self::render_box('Estado Actual', 'No hay job activo.', '#1d2327', '#f6f7f7');
            return;
        }

        $lines = [];
        $lines[] = 'status: ' . ($snapshot['status'] ?? 'N/A');
        $lines[] = 'stage: ' . ($snapshot['stage'] ?? 'N/A');
        $lines[] = 'stage_label: ' . ($snapshot['stage_label'] ?? 'N/A');
        $lines[] = 'progress: ' . (string) ($snapshot['progress'] ?? 0) . '%';
        $lines[] = 'processed_entries: ' . (string) ($snapshot['processed_entries'] ?? 0);
        $lines[] = 'total_entries: ' . (string) ($snapshot['total_entries'] ?? 0);

        if (!empty($snapshot['error_general'])) {
            $lines[] = 'error_general: ' . $snapshot['error_general'];
        }

        if (!empty($snapshot['results']) && is_array($snapshot['results'])) {
            $lines[] = 'results.totales: ' . (string) ($snapshot['results']['totales'] ?? 0);
            $lines[] = 'results.parciales: ' . (string) ($snapshot['results']['parciales'] ?? 0);
            $lines[] = 'results.errores: ' . (string) ($snapshot['results']['errores'] ?? 0);
        }
        if (!empty($snapshot['updated_at'])) {
            $lines[] = 'updated_at: ' . gmdate('Y-m-d H:i:s', (int) $snapshot['updated_at']) . ' UTC';
        }

        if (!empty($snapshot['debug_log']) && is_array($snapshot['debug_log'])) {
            $lines[] = '';
            $lines[] = '--- debug_log ---';
            foreach ($snapshot['debug_log'] as $line) {
                $lines[] = (string) $line;
            }
        }

        echo '<div style="margin-top:16px;border:1px solid #1d2327;background:#eef6ff;padding:10px;">';
        echo '<strong>Estado Actual</strong>';
        echo '<pre id="adpw-status-snapshot" style="white-space:pre-wrap;margin:8px 0 0;">' . esc_html(implode("\n", $lines)) . '</pre>';
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

    private static function render_box($title, $content, $border_color, $bg_color) {
        echo '<div style="margin-top:16px;border:1px solid ' . esc_attr($border_color) . ';background:' . esc_attr($bg_color) . ';padding:10px;">';
        echo '<strong>' . esc_html($title) . '</strong>';
        echo '<pre style="white-space:pre-wrap;margin:8px 0 0;">' . esc_html($content) . '</pre>';
        echo '</div>';
    }

    private static function render_js($nonce) {
        $ajax_url = admin_url('admin-ajax.php');

        echo '<script>';
        echo '(function(){';
        echo 'var form = document.getElementById("adpw-import-form");';
        echo 'if (!form) { return; }';
        echo 'var fileInput = document.getElementById("adpw-archivo-excel");';
        echo 'var startBtn = document.getElementById("adpw-start-import");';
        echo 'var progressWrap = document.getElementById("adpw-progress-wrapper");';
        echo 'var progressBar = document.getElementById("adpw-progress-bar");';
        echo 'var progressText = document.getElementById("adpw-progress-text");';
        echo 'var resultBox = document.getElementById("adpw-result");';
        echo 'var debugBox = document.getElementById("adpw-debug");';
        echo 'var debugLog = document.getElementById("adpw-debug-log");';
        echo 'var statusSnapshot = document.getElementById("adpw-status-snapshot");';
        echo 'var ajaxUrl = ' . wp_json_encode($ajax_url) . ';';
        echo 'var nonce = ' . wp_json_encode($nonce) . ';';
        echo 'var pollingTimer = null;';
        echo 'var stagnantPolls = 0;';
        echo 'var lastSignature = "";';
        echo 'var batchKickInFlight = false;';

        echo 'function decodeAjaxResponse(response){';
        echo 'return response.text().then(function(text){';
        echo 'var data = null;';
        echo 'try { data = JSON.parse(text); } catch (e) { data = null; }';
        echo 'return { ok: response.ok, data: data, raw: text };';
        echo '});';
        echo '}';

        echo 'function setProgress(value, text){';
        echo 'progressWrap.style.display = "block";';
        echo 'progressBar.style.width = String(Math.max(0, Math.min(100, value))) + "%";';
        echo 'progressText.textContent = text || "Procesando...";';
        echo '}';

        echo 'function renderDebug(lines){';
        echo 'if (!debugBox || !debugLog) { return; }';
        echo 'debugBox.style.display = "block";';
        echo 'debugLog.textContent = Array.isArray(lines) ? lines.join("\\n") : "";';
        echo '}';

        echo 'function renderResult(summary){';
        echo 'if (!summary || !summary.results) { return; }';
        echo 'var html = "<h3>Resultado</h3><ul>";';
        echo 'html += "<li>Actualizaciones Totales: " + (summary.results.totales || 0) + "</li>";';
        echo 'html += "<li>Actualizaciones Parciales: " + (summary.results.parciales || 0) + "</li>";';
        echo 'html += "<li>Errores: " + (summary.results.errores || 0) + "</li>";';
        echo 'html += "</ul>";';
        echo 'resultBox.innerHTML = html;';
        echo '}';

        echo 'function renderStatusSnapshot(summary){';
        echo 'if (!statusSnapshot || !summary) { return; }';
        echo 'var lines = [];';
        echo 'lines.push("status: " + (summary.status || "N/A"));';
        echo 'lines.push("stage: " + (summary.stage || "N/A"));';
        echo 'lines.push("stage_label: " + (summary.stage_label || "N/A"));';
        echo 'lines.push("progress: " + (summary.progress || 0) + "%");';
        echo 'lines.push("processed_entries: " + (summary.processed_entries || 0));';
        echo 'lines.push("total_entries: " + (summary.total_entries || 0));';
        echo 'if (summary.results) {';
        echo 'lines.push("results.totales: " + (summary.results.totales || 0));';
        echo 'lines.push("results.parciales: " + (summary.results.parciales || 0));';
        echo 'lines.push("results.errores: " + (summary.results.errores || 0));';
        echo '}';
        echo 'if (summary.updated_at) {';
        echo 'var d = new Date(summary.updated_at * 1000);';
        echo 'lines.push("updated_at: " + d.toISOString());';
        echo '}';
        echo 'if (summary.error_general) { lines.push("error_general: " + summary.error_general); }';
        echo 'if (Array.isArray(summary.debug_log) && summary.debug_log.length) {';
        echo 'lines.push("");';
        echo 'lines.push("--- debug_log ---");';
        echo 'summary.debug_log.forEach(function(line){ lines.push(String(line)); });';
        echo '}';
        echo 'statusSnapshot.textContent = lines.join("\\n");';
        echo '}';

        echo 'function kickBatchIfStalled(){';
        echo 'if (batchKickInFlight) { return; }';
        echo 'batchKickInFlight = true;';
        echo 'var body = new URLSearchParams();';
        echo 'body.set("action", "adpw_import_run_batch");';
        echo 'body.set("nonce", nonce);';
        echo 'fetch(ajaxUrl, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: body.toString() })';
        echo '.then(decodeAjaxResponse)';
        echo '.then(function(resp){';
        echo 'batchKickInFlight = false;';
        echo 'if (!resp.data || !resp.data.success || !resp.data.data) { return; }';
        echo 'renderStatusSnapshot(resp.data.data);';
        echo 'renderDebug(resp.data.data.debug_log || []);';
        echo '})';
        echo '.catch(function(){ batchKickInFlight = false; });';
        echo '}';

        echo 'function stopPolling(){';
        echo 'if (pollingTimer) { window.clearInterval(pollingTimer); pollingTimer = null; }';
        echo '}';

        echo 'function pollStatus(){';
        echo 'var url = ajaxUrl + "?action=adpw_import_status&nonce=" + encodeURIComponent(nonce);';
        echo 'fetch(url, { credentials: "same-origin" })';
        echo '.then(decodeAjaxResponse)';
        echo '.then(function(resp){';
        echo 'if (!resp.data) { return; }';
        echo 'var payload = resp.data;';
        echo 'if (!payload.success || !payload.data) { return; }';
        echo 'var data = payload.data;';
        echo 'if (data.status === "idle") { return; }';
        echo 'renderStatusSnapshot(data);';
        echo 'setProgress(data.progress || 0, (data.stage_label || "Procesando") + " (" + (data.processed_entries || 0) + " de " + (data.total_entries || 0) + ")");';
        echo 'renderDebug(data.debug_log || []);';
        echo 'var signature = [data.status, data.stage, data.processed_entries, data.total_entries, data.updated_at].join("|");';
        echo 'if (signature === lastSignature && data.status === "running") {';
        echo 'stagnantPolls += 1;';
        echo '} else {';
        echo 'stagnantPolls = 0;';
        echo 'lastSignature = signature;';
        echo '}';
        echo 'if (data.status === "running" && stagnantPolls >= 3) {';
        echo 'kickBatchIfStalled();';
        echo 'stagnantPolls = 0;';
        echo '}';
        echo 'if (data.status === "failed") {';
        echo 'var msg = data.error_general || "La importación falló.";';
        echo 'resultBox.innerHTML = "<div style=\"border:1px solid #b32d2e;background:#fff1f1;padding:8px;\"><p>" + msg + "</p></div>";';
        echo 'startBtn.disabled = false;';
        echo 'stopPolling();';
        echo 'return;';
        echo '}';
        echo 'if (data.status === "completed") {';
        echo 'setProgress(100, "Importación completada");';
        echo 'renderStatusSnapshot(data);';
        echo 'renderResult(data);';
        echo 'startBtn.disabled = false;';
        echo 'stopPolling();';
        echo '}';
        echo '})';
        echo '.catch(function(){ /* noop */ });';
        echo '}';

        echo 'function beginPolling(){';
        echo 'stopPolling();';
        echo 'pollStatus();';
        echo 'pollingTimer = window.setInterval(pollStatus, 2500);';
        echo '}';

        echo 'form.addEventListener("submit", function(ev){';
        echo 'resultBox.innerHTML = "";';
        echo 'if (!fileInput || !fileInput.files || fileInput.files.length === 0) {';
        echo 'ev.preventDefault();';
        echo 'resultBox.innerHTML = "<div style=\"border:1px solid #b32d2e;background:#fff1f1;padding:8px;\"><p>Seleccioná un archivo Excel.</p></div>";';
        echo 'return;';
        echo '}';
        echo 'startBtn.disabled = true;';
        echo 'setProgress(0, "Subiendo archivo e iniciando importación...");';
        echo '});';

        echo 'beginPolling();';
        echo '})();';
        echo '</script>';
    }
}
