<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Import_Batch_Runner {
    public static function process($job) {
        ADPW_Import_Job_Store::append_debug($job, 'Batch start stage=' . ($job['stage'] ?? 'unknown'));

        try {
            ADPW_Excel_Import_Service::process_job_batch($job);
        } catch (\Throwable $e) {
            $job['status'] = 'failed';
            $job['error_general'] = 'Excepción en batch: ' . $e->getMessage();
            ADPW_Import_Job_Store::append_debug($job, 'ERROR ' . $job['error_general']);
        }

        $job['updated_at'] = time();
        ADPW_Import_Job_Store::append_debug($job, 'Batch end stage=' . ($job['stage'] ?? 'unknown') . ' status=' . ($job['status'] ?? 'unknown'));

        return $job;
    }
}
