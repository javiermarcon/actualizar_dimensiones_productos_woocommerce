<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Metadata_Manager {
    public const META_CLASS = '_adpw_categoria_clase_envio';
    public const META_WEIGHT = '_adpw_categoria_peso';
    public const META_HEIGHT = '_adpw_categoria_alto';
    public const META_WIDTH = '_adpw_categoria_ancho';
    public const META_DEPTH = '_adpw_categoria_profundidad';

    public static function get_category_meta_values($category_id) {
        return [
            'clase_envio' => (string) get_term_meta($category_id, self::META_CLASS, true),
            'peso' => (string) get_term_meta($category_id, self::META_WEIGHT, true),
            'alto' => (string) get_term_meta($category_id, self::META_HEIGHT, true),
            'ancho' => (string) get_term_meta($category_id, self::META_WIDTH, true),
            'profundidad' => (string) get_term_meta($category_id, self::META_DEPTH, true),
        ];
    }

    public static function get_valid_shipping_slugs() {
        $terms = get_terms([
            'taxonomy' => 'product_shipping_class',
            'hide_empty' => false,
            'fields' => 'slugs',
        ]);

        if (is_wp_error($terms)) {
            return null;
        }

        return array_map('strval', $terms);
    }

    public static function normalize_number($value) {
        $text = trim(wp_unslash((string) $value));
        if ($text === '') {
            return '';
        }

        $text = str_replace(',', '.', $text);
        if (!is_numeric($text)) {
            return '';
        }

        return (string) max(0, (float) $text);
    }

    public static function save_category_metadata($term_id, $metadata, $valid_shipping_slugs) {
        if (array_key_exists('clase_envio', $metadata)) {
            $shipping_slug = sanitize_title(wp_unslash((string) $metadata['clase_envio']));

            if ($shipping_slug === '') {
                delete_term_meta($term_id, self::META_CLASS);
            } elseif (in_array($shipping_slug, $valid_shipping_slugs, true)) {
                update_term_meta($term_id, self::META_CLASS, $shipping_slug);
            }
        }

        if (array_key_exists('peso', $metadata)) {
            self::save_numeric_meta($term_id, self::META_WEIGHT, self::normalize_number($metadata['peso']));
        }
        if (array_key_exists('alto', $metadata)) {
            self::save_numeric_meta($term_id, self::META_HEIGHT, self::normalize_number($metadata['alto']));
        }
        if (array_key_exists('ancho', $metadata)) {
            self::save_numeric_meta($term_id, self::META_WIDTH, self::normalize_number($metadata['ancho']));
        }
        if (array_key_exists('profundidad', $metadata)) {
            self::save_numeric_meta($term_id, self::META_DEPTH, self::normalize_number($metadata['profundidad']));
        }
    }

    public static function update_products_using_most_specific_category($category_ids) {
        $queue = self::build_product_queue_for_categories($category_ids);
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

    public static function build_product_queue_for_categories($category_ids) {
        $category_ids = array_values(array_unique(array_map('absint', $category_ids)));
        if (empty($category_ids)) {
            return [];
        }

        $product_ids = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [[
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category_ids,
                'include_children' => false,
            ]],
        ]);

        $depth_cache = [];
        $queue = [];

        foreach ($product_ids as $product_id) {
            $product_term_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            if (is_wp_error($product_term_ids) || empty($product_term_ids)) {
                continue;
            }

            $candidate_ids = array_values(array_intersect($category_ids, array_map('intval', $product_term_ids)));
            if (empty($candidate_ids)) {
                continue;
            }

            $selected_category_id = self::pick_deepest_category($candidate_ids, $depth_cache);
            if ($selected_category_id <= 0) {
                continue;
            }

            $queue[] = [
                'product_id' => (int) $product_id,
                'category_id' => $selected_category_id,
            ];
        }

        return $queue;
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

    private static function save_numeric_meta($term_id, $meta_key, $value) {
        if ($value === '') {
            delete_term_meta($term_id, $meta_key);
            return;
        }

        update_term_meta($term_id, $meta_key, $value);
    }

    private static function pick_deepest_category($category_ids, &$depth_cache) {
        $selected_id = 0;
        $max_depth = -1;

        foreach ($category_ids as $category_id) {
            if (!isset($depth_cache[$category_id])) {
                $depth_cache[$category_id] = count(get_ancestors($category_id, 'product_cat', 'taxonomy'));
            }

            if ($depth_cache[$category_id] > $max_depth) {
                $max_depth = $depth_cache[$category_id];
                $selected_id = (int) $category_id;
            }
        }

        return $selected_id;
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
        $meta = self::get_category_meta_values($category_id);
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
