<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Import_Job_Factory {
    public static function create_job($init, $settings) {
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

        ADPW_Import_Job_Store::append_debug($job, 'Job creado. stage=parse_sheet, total_rows=' . (int) $job['total_rows']);
        self::append_init_debug($job, $init);
        self::append_warnings($job, $init);

        return $job;
    }

    private static function append_init_debug(&$job, $init) {
        if (empty($init['debug_lines']) || !is_array($init['debug_lines'])) {
            return;
        }

        foreach ($init['debug_lines'] as $line) {
            ADPW_Import_Job_Store::append_debug($job, 'INIT ' . (string) $line);
        }
    }

    private static function append_warnings(&$job, $init) {
        if (empty($init['warnings']) || !is_array($init['warnings'])) {
            return;
        }

        foreach ($init['warnings'] as $warning) {
            ADPW_Import_Job_Store::append_debug($job, 'WARNING: ' . $warning);
            if (isset($job['results']['detalles']) && is_array($job['results']['detalles'])) {
                $job['results']['detalles'][] = (string) $warning;
            }
        }
    }
}
