<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Admin_Job_Progress_UI {
    public static function render_progress_markup($prefix) {
        echo '<div id="' . esc_attr($prefix) . '-progress-wrapper" style="margin-top:16px;max-width:760px;display:none;">';
        echo '<div style="background:#e5e7eb;border-radius:6px;overflow:hidden;height:18px;">';
        echo '<div id="' . esc_attr($prefix) . '-progress-bar" style="height:18px;width:0;background:#2271b1;transition:width .25s ease;"></div>';
        echo '</div>';
        echo '<p id="' . esc_attr($prefix) . '-progress-text" style="margin:8px 0 0;">Preparando...</p>';
        echo '</div>';
        echo '<div id="' . esc_attr($prefix) . '-result" style="margin-top:16px;"></div>';
        echo '<div id="' . esc_attr($prefix) . '-debug" style="margin-top:16px;display:none;">';
        echo '<h3>Debug (AJAX)</h3>';
        echo '<pre id="' . esc_attr($prefix) . '-debug-log" style="background:#f6f7f7;border:1px solid #dcdcde;padding:10px;max-height:260px;overflow:auto;"></pre>';
        echo '</div>';
    }

    public static function render_status_snapshot($title, $snapshot, $prefix) {
        if (!$snapshot) {
            self::render_box($title, 'No hay job activo.', '#1d2327', '#f6f7f7', $prefix . '-status-snapshot');
            return;
        }

        $lines = self::build_snapshot_lines($snapshot);
        self::render_box($title, implode("\n", $lines), '#1d2327', '#eef6ff', $prefix . '-status-snapshot');
    }

    public static function render_box($title, $content, $border_color, $bg_color, $pre_id = '') {
        echo '<div style="margin-top:16px;border:1px solid ' . esc_attr($border_color) . ';background:' . esc_attr($bg_color) . ';padding:10px;">';
        echo '<strong>' . esc_html($title) . '</strong>';
        $id_attr = ($pre_id !== '') ? ' id="' . esc_attr($pre_id) . '"' : '';
        echo '<pre' . $id_attr . ' style="white-space:pre-wrap;margin:8px 0 0;">' . esc_html($content) . '</pre>';
        echo '</div>';
    }

    public static function render_polling_js($config) {
        $ajax_url = admin_url('admin-ajax.php');
        $config['ajaxUrl'] = $ajax_url;

        echo '<script>';
        echo '(function(){';
        echo 'var cfg = ' . wp_json_encode($config) . ';';
        echo 'var startForm = cfg.startFormId ? document.getElementById(cfg.startFormId) : null;';
        echo 'var startButton = cfg.startButtonId ? document.getElementById(cfg.startButtonId) : null;';
        echo 'var validateInput = cfg.validateInputId ? document.getElementById(cfg.validateInputId) : null;';
        echo 'var progressWrap = document.getElementById(cfg.prefix + "-progress-wrapper");';
        echo 'var progressBar = document.getElementById(cfg.prefix + "-progress-bar");';
        echo 'var progressText = document.getElementById(cfg.prefix + "-progress-text");';
        echo 'var resultBox = document.getElementById(cfg.prefix + "-result");';
        echo 'var debugBox = document.getElementById(cfg.prefix + "-debug");';
        echo 'var debugLog = document.getElementById(cfg.prefix + "-debug-log");';
        echo 'var statusSnapshot = document.getElementById(cfg.prefix + "-status-snapshot");';
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
        echo 'if (!progressWrap || !progressBar || !progressText) { return; }';
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
        echo 'if (!resultBox || !summary || !summary.results) { return; }';
        echo 'var html = "<h3>Resultado</h3><ul>";';
        echo 'html += "<li>Actualizaciones Totales: " + (summary.results.totales || 0) + "</li>";';
        echo 'if (typeof summary.results.parciales !== "undefined") { html += "<li>Actualizaciones Parciales: " + (summary.results.parciales || 0) + "</li>"; }';
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
        echo 'if (typeof summary.results.parciales !== "undefined") { lines.push("results.parciales: " + (summary.results.parciales || 0)); }';
        echo 'lines.push("results.errores: " + (summary.results.errores || 0));';
        echo '}';
        echo 'if (summary.updated_at) { lines.push("updated_at: " + new Date(summary.updated_at * 1000).toISOString()); }';
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
        echo 'body.set("action", cfg.runBatchAction);';
        echo 'body.set("nonce", cfg.nonce);';
        echo 'fetch(cfg.ajaxUrl, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: body.toString() })';
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
        echo 'var url = cfg.ajaxUrl + "?action=" + encodeURIComponent(cfg.statusAction) + "&nonce=" + encodeURIComponent(cfg.nonce);';
        echo 'fetch(url, { credentials: "same-origin" })';
        echo '.then(decodeAjaxResponse)';
        echo '.then(function(resp){';
        echo 'if (!resp.data || !resp.data.success || !resp.data.data) { return; }';
        echo 'var data = resp.data.data;';
        echo 'if (data.status === "idle") { return; }';
        echo 'renderStatusSnapshot(data);';
        echo 'setProgress(data.progress || 0, (data.stage_label || "Procesando") + " (" + (data.processed_entries || 0) + " de " + (data.total_entries || 0) + ")");';
        echo 'renderDebug(data.debug_log || []);';
        echo 'var signature = [data.status, data.stage, data.processed_entries, data.total_entries, data.updated_at].join("|");';
        echo 'if (signature === lastSignature && data.status === "running") { stagnantPolls += 1; } else { stagnantPolls = 0; lastSignature = signature; }';
        echo 'if (data.status === "running" && stagnantPolls >= 3) { kickBatchIfStalled(); stagnantPolls = 0; }';
        echo 'if (data.status === "failed") {';
        echo 'if (resultBox) { resultBox.innerHTML = "<div style=\"border:1px solid #b32d2e;background:#fff1f1;padding:8px;\"><p>" + (data.error_general || cfg.errorText) + "</p></div>"; }';
        echo 'if (startButton) { startButton.disabled = false; }';
        echo 'stopPolling();';
        echo 'return;';
        echo '}';
        echo 'if (data.status === "completed") {';
        echo 'setProgress(100, cfg.completedText);';
        echo 'renderStatusSnapshot(data);';
        echo 'renderResult(data);';
        echo 'if (startButton) { startButton.disabled = false; }';
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

        echo 'if (startForm) {';
        echo 'startForm.addEventListener("submit", function(ev){';
        echo 'if (resultBox) { resultBox.innerHTML = ""; }';
        echo 'if (validateInput && cfg.emptyInputMessage && ((!validateInput.files) || validateInput.files.length === 0)) {';
        echo 'ev.preventDefault();';
        echo 'if (resultBox) { resultBox.innerHTML = "<div style=\"border:1px solid #b32d2e;background:#fff1f1;padding:8px;\"><p>" + cfg.emptyInputMessage + "</p></div>"; }';
        echo 'return;';
        echo '}';
        echo 'if (startButton) { startButton.disabled = true; }';
        echo 'setProgress(0, cfg.startText);';
        echo '});';
        echo '}';

        echo 'beginPolling();';
        echo '})();';
        echo '</script>';
    }

    private static function build_snapshot_lines($snapshot) {
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
            if (array_key_exists('parciales', $snapshot['results'])) {
                $lines[] = 'results.parciales: ' . (string) ($snapshot['results']['parciales'] ?? 0);
            }
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

        return $lines;
    }
}
