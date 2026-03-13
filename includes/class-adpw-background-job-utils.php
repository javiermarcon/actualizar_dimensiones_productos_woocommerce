<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Background_Job_Utils {
    public static function get_job($option_key) {
        $job = get_option($option_key, null);
        return is_array($job) ? $job : null;
    }

    public static function save_job($option_key, $job) {
        update_option($option_key, $job, false);
    }

    public static function append_debug(&$job, $message) {
        if (!isset($job['debug_log']) || !is_array($job['debug_log'])) {
            $job['debug_log'] = [];
        }

        $job['debug_log'][] = '[' . gmdate('H:i:s') . '] ' . $message;

        if (count($job['debug_log']) > 300) {
            $job['debug_log'] = array_slice($job['debug_log'], -300);
        }
    }

    public static function schedule_next_batch($hook, $job_id) {
        $next = wp_next_scheduled($hook, [$job_id]);
        if ($next) {
            return;
        }

        wp_schedule_single_event(time() + 2, $hook, [$job_id]);
    }

    public static function build_summary($job, $label, $processed, $total) {
        $status = (string) ($job['status'] ?? 'unknown');
        $progress = ($total > 0) ? (int) floor(($processed / $total) * 100) : 100;

        if ($status === 'completed') {
            $progress = 100;
        } elseif ($status === 'running') {
            $progress = max(1, $progress);
        }

        return [
            'status' => $status,
            'stage' => (string) ($job['stage'] ?? 'update_products'),
            'stage_label' => $label,
            'progress' => max(0, min(100, $progress)),
            'processed_entries' => min($total, $processed),
            'total_entries' => $total,
            'updated_at' => (int) ($job['updated_at'] ?? 0),
            'results' => $job['results'] ?? null,
            'error_general' => $job['error_general'] ?? '',
            'debug_log' => array_slice((array) ($job['debug_log'] ?? []), -40),
        ];
    }
}
