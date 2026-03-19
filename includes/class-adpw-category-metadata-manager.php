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
        return ADPW_Category_Meta_Repository::get_category_meta_values($category_id);
    }

    public static function get_valid_shipping_slugs() {
        return ADPW_Category_Meta_Repository::get_valid_shipping_slugs();
    }

    public static function normalize_number($value) {
        return ADPW_Category_Meta_Repository::normalize_number($value);
    }

    public static function save_category_metadata($term_id, $metadata, $valid_shipping_slugs) {
        ADPW_Category_Meta_Repository::save_category_metadata($term_id, $metadata, $valid_shipping_slugs);
    }

    public static function update_products_using_most_specific_category($category_ids) {
        return ADPW_Category_Product_Metadata_Applier::update_products_using_most_specific_category($category_ids);
    }

    public static function build_product_queue_for_categories($category_ids) {
        return ADPW_Category_Product_Queue_Builder::build_product_queue_for_categories($category_ids);
    }

    public static function process_product_queue_batch(&$job) {
        ADPW_Category_Product_Metadata_Applier::process_product_queue_batch($job);
    }
}
