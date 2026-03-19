<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Import_Service {
    private static $uploaded_file_validator = null;
    private static $uploaded_file_mover = null;
    private static $upload_dir_provider = null;
    private static $mkdir_p_callback = null;

    public static function initialize_job($file, $settings, $batch_size) {
        if (empty($file['tmp_name']) || !self::is_valid_uploaded_file((string) $file['tmp_name'])) {
            return ['error_general' => 'No se seleccionó ningún archivo válido.'];
        }

        $upload = self::get_upload_dir();
        if (!empty($upload['error'])) {
            return ['error_general' => 'No se pudo acceder al directorio de uploads.'];
        }

        $target_dir = trailingslashit($upload['basedir']) . 'adpw-imports';
        if (!self::create_directory($target_dir)) {
            return ['error_general' => 'No se pudo crear el directorio temporal de importación.'];
        }

        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'xlsx';
        }

        $uploaded_file_path = trailingslashit($target_dir) . 'adpw-upload-' . wp_generate_uuid4() . '.' . $ext;
        if (!self::move_uploaded_file_to_target((string) $file['tmp_name'], $uploaded_file_path)) {
            return ['error_general' => 'No se pudo mover el archivo subido.'];
        }

        try {
            $spreadsheet = ADPW_Excel_Import_Temp_Store::load_spreadsheet($uploaded_file_path);
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ADPW_Excel_Import_Support::get_headers($sheet);
            $columns = ADPW_Excel_Import_Support::build_columns($headers);

            $actualizar_tam = !empty($settings['actualizar_tam']);
            $actualizar_cat = !empty($settings['actualizar_cat']);
            $mode_config = ADPW_Excel_Import_Support::build_mode($settings, $columns);
            $mode = $mode_config['mode'];
            $faltan_dimensiones = $mode_config['faltan_dimensiones'];

            $debug_lines = [];
            $debug_lines[] = 'headers=' . wp_json_encode(array_values(array_filter($headers, static function ($value) {
                return $value !== '';
            })));
            $debug_lines[] = 'columns=' . wp_json_encode($columns);
            $debug_lines[] = 'settings=' . wp_json_encode([
                'actualizar_tam' => $actualizar_tam,
                'actualizar_cat' => $actualizar_cat,
            ]);
            $debug_lines[] = 'mode=' . wp_json_encode($mode);

            $validation = ADPW_Excel_Import_Support::validate_headers($columns, $headers, $actualizar_tam, $actualizar_cat, $mode['actualizar_tam_dimensiones'], $faltan_dimensiones);
            if (!empty($validation['error_general'])) {
                @unlink($uploaded_file_path);
                $validation['debug_lines'] = $debug_lines;
                return $validation;
            }

            $categories_data_file = trailingslashit($target_dir) . 'adpw-categories-' . wp_generate_uuid4() . '.json';
            ADPW_Excel_Import_Temp_Store::save_json_file($categories_data_file, []);

            $highest_row = (int) $sheet->getHighestRow();

            return [
                'uploaded_file_path' => $uploaded_file_path,
                'categories_data_file' => $categories_data_file,
                'columns' => $columns,
                'mode' => $mode,
                'batch_size' => max(1, (int) $batch_size),
                'cursor_row' => 2,
                'highest_row' => $highest_row,
                'empty_row_count' => 0,
                'processed_rows' => 0,
                'total_rows' => max(0, $highest_row - 1),
                'category_ids' => [],
                'category_cursor' => 0,
                'product_queue' => [],
                'product_cursor' => 0,
                'results' => [
                    'totales' => 0,
                    'parciales' => 0,
                    'errores' => 0,
                    'detalles' => [],
                    'productos_modificados' => [],
                ],
                'warnings' => $validation['warnings'] ?? [],
                'debug_lines' => $debug_lines,
            ];
        } catch (\Exception $e) {
            @unlink($uploaded_file_path);
            return ['error_general' => 'Error preparando el Excel: ' . $e->getMessage()];
        }
    }

    public static function process_job_batch(&$job) {
        $stage = (string) ($job['stage'] ?? '');

        if ($stage === 'parse_sheet') {
            ADPW_Excel_Parse_Sheet_Batch_Service::process($job);
            return;
        }

        if ($stage === 'save_category_meta') {
            ADPW_Excel_Save_Category_Meta_Batch_Service::process($job);
            return;
        }

        if ($stage === 'update_products') {
            ADPW_Excel_Update_Products_Batch_Service::process($job);
            return;
        }

        $job['status'] = 'failed';
        $job['error_general'] = 'Etapa de importación desconocida: ' . $stage;
    }

    private static function is_valid_uploaded_file($path) {
        if (is_callable(self::$uploaded_file_validator)) {
            return (bool) call_user_func(self::$uploaded_file_validator, $path);
        }

        return is_uploaded_file($path);
    }

    private static function move_uploaded_file_to_target($source, $destination) {
        if (is_callable(self::$uploaded_file_mover)) {
            return (bool) call_user_func(self::$uploaded_file_mover, $source, $destination);
        }

        return move_uploaded_file($source, $destination);
    }

    private static function get_upload_dir() {
        if (is_callable(self::$upload_dir_provider)) {
            $upload = call_user_func(self::$upload_dir_provider);
            return is_array($upload) ? $upload : [];
        }

        return wp_upload_dir();
    }

    private static function create_directory($target_dir) {
        if (is_callable(self::$mkdir_p_callback)) {
            return (bool) call_user_func(self::$mkdir_p_callback, $target_dir);
        }

        return wp_mkdir_p($target_dir);
    }

}
