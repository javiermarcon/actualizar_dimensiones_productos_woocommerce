<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Update_Batch_Runner {
    public static function process($job) {
        ADPW_Category_Update_Job_Store::append_debug($job, 'Batch árbol start stage=' . ($job['stage'] ?? 'unknown'));

        try {
            ADPW_Category_Metadata_Manager::process_product_queue_batch($job);
        } catch (\Throwable $e) {
            $job['status'] = 'failed';
            $job['error_general'] = 'Excepción en batch del árbol: ' . $e->getMessage();
            ADPW_Category_Update_Job_Store::append_debug($job, 'ERROR ' . $job['error_general']);
        }

        $job['updated_at'] = time();
        ADPW_Category_Update_Job_Store::append_debug($job, 'Batch árbol end status=' . ($job['status'] ?? 'unknown'));

        return $job;
    }
}
