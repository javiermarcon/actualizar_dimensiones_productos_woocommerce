<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Import_Job_Summary {
    public static function build_summary($job) {
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

        $summary = ADPW_Background_Job_Utils::build_summary($job, $label, $processed, $total);

        if ($status === 'completed') {
            $summary['progress'] = 100;
        } elseif ($status === 'running') {
            $summary['progress'] = max(1, min(100, $progress));
        } else {
            $summary['progress'] = max(0, min(100, $progress));
        }

        return $summary;
    }
}
