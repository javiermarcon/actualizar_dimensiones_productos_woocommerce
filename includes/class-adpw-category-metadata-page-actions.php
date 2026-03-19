<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Metadata_Page_Actions {
    public static function handle_manual_batch($manual_nonce_action, $manual_nonce_field) {
        if (
            !isset($_POST['adpw_run_category_tree_batch']) ||
            !isset($_POST[$manual_nonce_field]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$manual_nonce_field])), $manual_nonce_action)
        ) {
            return [
                'manual_message' => '',
                'manual_error' => '',
            ];
        }

        $manual = ADPW_Category_Update_Queue_Manager::run_batch_now();
        if (!empty($manual['error_general'])) {
            return [
                'manual_message' => '',
                'manual_error' => $manual['error_general'],
            ];
        }

        return [
            'manual_message' => 'Se ejecutó manualmente un lote de actualización del árbol.',
            'manual_error' => '',
        ];
    }

    public static function handle_save_request($nonce_action, $nonce_field, $post_field_metadata) {
        if (!current_user_can('manage_options')) {
            return ['error' => 'No tenés permisos para guardar metadata.'];
        }

        if (
            !isset($_POST[$nonce_field]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), $nonce_action)
        ) {
            return ['error' => 'No se pudo validar la solicitud.'];
        }

        $metadata_by_category = isset($_POST[$post_field_metadata]) ? (array) $_POST[$post_field_metadata] : [];
        if (!empty($_POST['adpw_no_changes']) || empty($metadata_by_category)) {
            return ['mensaje' => 'No hubo cambios para guardar.'];
        }

        return ADPW_Category_Metadata_Save_Service::save_from_request($metadata_by_category, ADPW_Settings::get());
    }
}
