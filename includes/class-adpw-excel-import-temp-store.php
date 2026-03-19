<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Import_Temp_Store {
    public static function cleanup_job_files($job) {
        if (!empty($job['uploaded_file_path']) && file_exists($job['uploaded_file_path'])) {
            @unlink($job['uploaded_file_path']);
        }
        if (!empty($job['categories_data_file']) && file_exists($job['categories_data_file'])) {
            @unlink($job['categories_data_file']);
        }
    }

    public static function load_json_file($path) {
        if (!is_string($path) || $path === '' || !file_exists($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function save_json_file($path, $data) {
        file_put_contents($path, wp_json_encode($data));
    }

    public static function load_spreadsheet($path) {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        return $reader->load($path);
    }
}
