<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Ajax_Handler_Utils {
    public static function ensure_manage_options($message) {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => $message]);
        }
    }

    public static function verify_nonce($action, $field = 'nonce') {
        $valid = check_ajax_referer($action, $field, false);
        if ($valid === false) {
            wp_send_json_error(['message' => 'Nonce inválido.']);
        }
    }

    public static function success($payload) {
        wp_send_json_success($payload);
    }

    public static function error($payload) {
        wp_send_json_error($payload);
    }

    public static function rethrow_test_json_exception($e) {
        if (class_exists('ADPW_Test_Json_Response_Exception') && $e instanceof ADPW_Test_Json_Response_Exception) {
            throw $e;
        }
    }

    public static function idle_payload() {
        return [
            'status' => 'idle',
            'progress' => 0,
            'stage' => 'idle',
            'results' => null,
            'debug_log' => [],
        ];
    }
}
