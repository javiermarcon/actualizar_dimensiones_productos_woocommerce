<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Update_Job_Summary {
    public static function build_summary($job) {
        $total = count((array) ($job['product_queue'] ?? []));
        $processed = (int) ($job['product_cursor'] ?? 0);

        return ADPW_Background_Job_Utils::build_summary(
            $job,
            'Actualizando productos desde árbol de categorías',
            $processed,
            $total
        );
    }
}
