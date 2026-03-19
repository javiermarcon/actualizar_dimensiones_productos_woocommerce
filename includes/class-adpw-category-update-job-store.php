<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Update_Job_Store {
    public static function get_job($option_key) {
        return ADPW_Background_Job_Utils::get_job($option_key);
    }

    public static function save_job($option_key, $job) {
        ADPW_Background_Job_Utils::save_job($option_key, $job);
    }

    public static function append_debug(&$job, $message) {
        ADPW_Background_Job_Utils::append_debug($job, $message);
    }

    public static function schedule_next_batch($hook, $job_id) {
        ADPW_Background_Job_Utils::schedule_next_batch($hook, $job_id);
    }
}
