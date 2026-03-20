<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Save_Category_Meta_Batch_Service {
    public static function process(&$job) {
        $batch_size = max(1, (int) ($job['batch_size'] ?? 1));
        $cursor = (int) ($job['category_cursor'] ?? 0);
        $category_ids = isset($job['category_ids']) && is_array($job['category_ids']) ? $job['category_ids'] : [];

        if ($cursor >= count($category_ids)) {
            $job['category_cursor'] = 0;
            self::prepare_product_queue($job);
            $job['stage'] = 'update_products';
            return;
        }

        $category_map = ADPW_Excel_Import_Temp_Store::load_json_file((string) $job['categories_data_file']);
        $valid_shipping_slugs = ADPW_Category_Metadata_Manager::get_valid_shipping_slugs();
        if (!is_array($valid_shipping_slugs)) {
            $job['status'] = 'failed';
            $job['error_general'] = 'No se pudieron validar clases de envío.';
            return;
        }

        $processed = 0;
        while ($cursor < count($category_ids) && $processed < $batch_size) {
            $category_id = (int) $category_ids[$cursor];
            $cursor++;
            $processed++;

            $entry = $category_map[(string) $category_id] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            $metadata = self::build_metadata_from_entry($entry, (array) ($job['columns'] ?? []));

            ADPW_Category_Metadata_Manager::save_category_metadata($category_id, $metadata, $valid_shipping_slugs);
        }

        $job['category_cursor'] = $cursor;

        if ($cursor >= count($category_ids)) {
            $job['category_cursor'] = 0;
            self::prepare_product_queue($job);
            $job['stage'] = 'update_products';
        }
    }

    public static function prepare_product_queue(&$job) {
        $category_ids = isset($job['category_ids']) && is_array($job['category_ids']) ? $job['category_ids'] : [];
        $job['product_queue'] = ADPW_Category_Metadata_Manager::build_product_queue_for_categories($category_ids);
        $job['product_cursor'] = 0;
    }

    private static function build_metadata_from_entry(array $entry, array $columns): array {
        $metadata = [];
        $has_column_map = !empty($columns);

        if ((!$has_column_map || ($columns['tamano'] ?? false) !== false)) {
            $tamano = trim((string) ($entry['tamano'] ?? ''));
            if ($tamano !== '') {
                $metadata['clase_envio'] = $tamano;
            }
        }

        self::append_numeric_metadata($metadata, $columns, 'peso', 'peso', $entry, $has_column_map);
        self::append_numeric_metadata($metadata, $columns, 'profundidad', 'alto', $entry, $has_column_map);
        self::append_numeric_metadata($metadata, $columns, 'ancho', 'ancho', $entry, $has_column_map);
        self::append_numeric_metadata($metadata, $columns, 'largo', 'profundidad', $entry, $has_column_map);

        return $metadata;
    }

    private static function append_numeric_metadata(array &$metadata, array $columns, string $column_key, string $meta_key, array $entry, bool $has_column_map): void {
        if ($has_column_map && ($columns[$column_key] ?? false) === false) {
            return;
        }

        $value = (float) ($entry[$column_key] ?? 0);
        if ($value <= 0) {
            return;
        }

        $metadata[$meta_key] = (string) $value;
    }
}
