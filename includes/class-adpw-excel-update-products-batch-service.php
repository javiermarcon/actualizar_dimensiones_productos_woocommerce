<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Update_Products_Batch_Service {
    public static function process(&$job) {
        $batch_size = max(1, (int) ($job['batch_size'] ?? 1));
        $cursor = (int) ($job['product_cursor'] ?? 0);
        $product_queue = isset($job['product_queue']) && is_array($job['product_queue']) ? $job['product_queue'] : [];

        if ($cursor >= count($product_queue)) {
            $job['status'] = 'completed';
            ADPW_Excel_Import_Temp_Store::cleanup_job_files($job);
            return;
        }

        $category_map = ADPW_Excel_Import_Temp_Store::load_json_file((string) $job['categories_data_file']);
        $processed = 0;

        while ($cursor < count($product_queue) && $processed < $batch_size) {
            $queue_item = $product_queue[$cursor] ?? [];
            $cursor++;
            $processed++;

            $product_id = isset($queue_item['product_id']) ? (int) $queue_item['product_id'] : 0;
            $category_id = isset($queue_item['category_id']) ? (int) $queue_item['category_id'] : 0;
            if ($product_id <= 0 || $category_id <= 0) {
                continue;
            }

            $entry = $category_map[(string) $category_id] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            ADPW_Excel_Product_Update_Service::process_product_update($product_id, $entry, $job['settings'], $job['mode'], $job['results']);
        }

        $job['product_cursor'] = $cursor;

        if ($cursor >= count($product_queue)) {
            $job['status'] = 'completed';
            ADPW_Excel_Import_Temp_Store::cleanup_job_files($job);
        }
    }
}
