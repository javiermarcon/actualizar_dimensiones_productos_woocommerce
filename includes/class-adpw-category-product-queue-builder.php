<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Product_Queue_Builder {
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
}
