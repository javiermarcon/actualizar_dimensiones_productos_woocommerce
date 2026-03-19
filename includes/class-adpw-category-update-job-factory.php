<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Update_Job_Factory {
    public static function create_job($category_ids, $batch_size, $queue) {
        $job = [
            'id' => wp_generate_uuid4(),
            'status' => empty($queue) ? 'completed' : 'running',
            'stage' => 'update_products',
            'created_at' => time(),
            'updated_at' => time(),
            'batch_size' => max(1, (int) $batch_size),
            'category_ids' => array_values($category_ids),
            'product_queue' => array_values($queue),
            'product_cursor' => 0,
            'results' => [
                'totales' => 0,
                'errores' => 0,
                'detalles' => [],
                'productos_modificados' => [],
            ],
            'runtime' => [],
            'error_general' => '',
            'debug_log' => [],
        ];

        ADPW_Category_Update_Job_Store::append_debug($job, 'Job árbol creado. products=' . count($queue));

        return $job;
    }
}
