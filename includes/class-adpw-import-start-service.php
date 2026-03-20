<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Import_Start_Service {
    public static function start($file, $settings) {
        $batch_size = max(1, (int) ($settings['categorias_por_lote'] ?? 20));
        $init = ADPW_Excel_Import_Service::initialize_job($file, $settings, $batch_size);
        if (!empty($init['error_general'])) {
            return [
                'error_general' => $init['error_general'],
                'detalles' => isset($init['detalles']) && is_array($init['detalles']) ? $init['detalles'] : [],
                'debug_lines' => isset($init['debug_lines']) && is_array($init['debug_lines']) ? $init['debug_lines'] : [],
            ];
        }

        $job = ADPW_Import_Job_Factory::create_job($init, $settings);

        return [
            'job' => $job,
            'response' => [
                'job_id' => $job['id'],
                'stage' => $job['stage'],
                'batch_size' => $job['batch_size'],
            ],
        ];
    }
}
