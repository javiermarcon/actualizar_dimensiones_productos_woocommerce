<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Update_Start_Service {
    public static function start($category_ids, $batch_size, $existing_job) {
        $category_ids = array_values(array_unique(array_map('absint', (array) $category_ids)));
        if (empty($category_ids)) {
            return ['error_general' => 'No hay categorías para actualizar en segundo plano.'];
        }

        if ($existing_job && (($existing_job['status'] ?? '') === 'running')) {
            return ['error_general' => 'Ya hay una actualización del árbol en ejecución. Esperá a que termine antes de iniciar otra.'];
        }

        $queue = ADPW_Category_Metadata_Manager::build_product_queue_for_categories($category_ids);
        $job = ADPW_Category_Update_Job_Factory::create_job($category_ids, $batch_size, $queue);

        return [
            'job' => $job,
            'response' => [
                'job_id' => $job['id'],
                'batch_size' => $job['batch_size'],
                'total_entries' => count($queue),
                'status' => $job['status'],
            ],
        ];
    }
}
