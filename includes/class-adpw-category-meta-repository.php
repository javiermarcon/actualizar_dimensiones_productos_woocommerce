<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Meta_Repository {
    public static function get_category_meta_values($category_id) {
        return [
            'clase_envio' => (string) get_term_meta($category_id, ADPW_Category_Metadata_Manager::META_CLASS, true),
            'peso' => (string) get_term_meta($category_id, ADPW_Category_Metadata_Manager::META_WEIGHT, true),
            'alto' => (string) get_term_meta($category_id, ADPW_Category_Metadata_Manager::META_HEIGHT, true),
            'ancho' => (string) get_term_meta($category_id, ADPW_Category_Metadata_Manager::META_WIDTH, true),
            'profundidad' => (string) get_term_meta($category_id, ADPW_Category_Metadata_Manager::META_DEPTH, true),
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
                delete_term_meta($term_id, ADPW_Category_Metadata_Manager::META_CLASS);
            } elseif (in_array($shipping_slug, $valid_shipping_slugs, true)) {
                update_term_meta($term_id, ADPW_Category_Metadata_Manager::META_CLASS, $shipping_slug);
            }
        }

        if (array_key_exists('peso', $metadata)) {
            self::save_numeric_meta($term_id, ADPW_Category_Metadata_Manager::META_WEIGHT, self::normalize_number($metadata['peso']));
        }
        if (array_key_exists('alto', $metadata)) {
            self::save_numeric_meta($term_id, ADPW_Category_Metadata_Manager::META_HEIGHT, self::normalize_number($metadata['alto']));
        }
        if (array_key_exists('ancho', $metadata)) {
            self::save_numeric_meta($term_id, ADPW_Category_Metadata_Manager::META_WIDTH, self::normalize_number($metadata['ancho']));
        }
        if (array_key_exists('profundidad', $metadata)) {
            self::save_numeric_meta($term_id, ADPW_Category_Metadata_Manager::META_DEPTH, self::normalize_number($metadata['profundidad']));
        }
    }

    private static function save_numeric_meta($term_id, $meta_key, $value) {
        if ($value === '') {
            delete_term_meta($term_id, $meta_key);
            return;
        }

        update_term_meta($term_id, $meta_key, $value);
    }
}
