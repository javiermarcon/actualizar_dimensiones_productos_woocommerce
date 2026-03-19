<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Product_Metadata_Applier {
    public static function update_products_using_most_specific_category($category_ids) {
        $queue = ADPW_Category_Product_Queue_Builder::build_product_queue_for_categories($category_ids);
        $shipping_class_cache = [];
        $updated_products = 0;
        $results = null;

        foreach ($queue as $queue_item) {
            if (self::apply_category_metadata_to_product_id((int) $queue_item['product_id'], (int) $queue_item['category_id'], $shipping_class_cache, $results)) {
                $updated_products++;
            }
        }

        return $updated_products;
    }

    public static function process_product_queue_batch(&$job) {
        $batch_size = max(1, (int) ($job['batch_size'] ?? 1));
        $cursor = (int) ($job['product_cursor'] ?? 0);
        $product_queue = isset($job['product_queue']) && is_array($job['product_queue']) ? $job['product_queue'] : [];

        if ($cursor >= count($product_queue)) {
            $job['status'] = 'completed';
            return;
        }

        if (!isset($job['runtime']) || !is_array($job['runtime'])) {
            $job['runtime'] = [];
        }
        if (!isset($job['runtime']['shipping_class_cache']) || !is_array($job['runtime']['shipping_class_cache'])) {
            $job['runtime']['shipping_class_cache'] = [];
        }

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

            self::apply_category_metadata_to_product_id(
                $product_id,
                $category_id,
                $job['runtime']['shipping_class_cache'],
                $job['results']
            );
        }

        $job['product_cursor'] = $cursor;

        if ($cursor >= count($product_queue)) {
            $job['status'] = 'completed';
        }
    }

    private static function apply_category_metadata_to_product_id($product_id, $category_id, &$shipping_class_cache, &$results = null) {
        $product = wc_get_product($product_id);
        if (!$product) {
            if (is_array($results)) {
                $results['errores'] = (int) ($results['errores'] ?? 0) + 1;
                self::append_limited($results['detalles'], 'No se pudo cargar el producto con ID ' . $product_id . '.');
            }
            return false;
        }

        $updated = self::apply_category_metadata_to_product($product, $category_id, $shipping_class_cache);
        if ($updated && is_array($results)) {
            $results['totales'] = (int) ($results['totales'] ?? 0) + 1;
            self::append_limited($results['productos_modificados'], 'Metadata aplicada a ' . $product->get_name() . ' (ID: ' . $product_id . ') usando categoría ' . $category_id . '.');
        }

        return $updated;
    }

    private static function apply_category_metadata_to_product($product, $category_id, &$shipping_class_cache) {
        $meta = ADPW_Category_Meta_Repository::get_category_meta_values($category_id);
        $has_changes = false;

        $new_shipping_class_id = self::resolve_shipping_class_id($meta['clase_envio'], $shipping_class_cache);
        if ((int) $product->get_shipping_class_id() !== $new_shipping_class_id) {
            $product->set_shipping_class_id($new_shipping_class_id);
            $has_changes = true;
        }

        $has_changes = self::set_product_numeric_if_needed($product, 'weight', $meta['peso']) || $has_changes;
        $has_changes = self::set_product_numeric_if_needed($product, 'height', $meta['alto']) || $has_changes;
        $has_changes = self::set_product_numeric_if_needed($product, 'width', $meta['ancho']) || $has_changes;
        $has_changes = self::set_product_numeric_if_needed($product, 'length', $meta['profundidad']) || $has_changes;

        if ($has_changes) {
            $product->save();
        }

        return $has_changes;
    }

    private static function set_product_numeric_if_needed($product, $field, $new_value) {
        if ($new_value === '') {
            return false;
        }

        $getter = 'get_' . $field;
        $setter = 'set_' . $field;
        $current_value = (string) $product->{$getter}();
        $new_value = (string) $new_value;

        if ($current_value === $new_value) {
            return false;
        }

        $product->{$setter}($new_value);
        return true;
    }

    private static function resolve_shipping_class_id($shipping_slug, &$shipping_class_cache) {
        if ($shipping_slug === '') {
            return 0;
        }

        if (!array_key_exists($shipping_slug, $shipping_class_cache)) {
            $shipping_class = get_term_by('slug', $shipping_slug, 'product_shipping_class');
            $shipping_class_cache[$shipping_slug] = ($shipping_class && !is_wp_error($shipping_class))
                ? (int) $shipping_class->term_id
                : 0;
        }

        return $shipping_class_cache[$shipping_slug];
    }

    private static function append_limited(&$target, $message, $limit = 250) {
        if (!is_array($target)) {
            $target = [];
        }
        if (count($target) >= $limit) {
            return;
        }
        $target[] = $message;
    }
}
