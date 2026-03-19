<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/wp-stubs.php';
require_once dirname(__DIR__) . '/includes/class-adpw-background-job-utils.php';
require_once dirname(__DIR__) . '/includes/class-adpw-admin-job-progress-ui.php';
require_once dirname(__DIR__) . '/includes/class-adpw-ajax-handler-utils.php';
require_once dirname(__DIR__) . '/includes/class-adpw-import-job-store.php';
require_once dirname(__DIR__) . '/includes/class-adpw-import-job-summary.php';
require_once dirname(__DIR__) . '/includes/class-adpw-import-job-factory.php';
require_once dirname(__DIR__) . '/includes/class-adpw-import-batch-runner.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-meta-repository.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-product-queue-builder.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-product-metadata-applier.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-metadata-manager.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-update-queue-manager.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-metadata-save-service.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-metadata-page-actions.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-update-job-store.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-update-job-summary.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-update-job-factory.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-update-batch-runner.php';
require_once dirname(__DIR__) . '/includes/class-adpw-category-metadata-page.php';
require_once dirname(__DIR__) . '/includes/class-adpw-import-queue-manager.php';
require_once dirname(__DIR__) . '/includes/class-adpw-settings.php';
require_once dirname(__DIR__) . '/includes/class-adpw-excel-import-support.php';
require_once dirname(__DIR__) . '/includes/class-adpw-excel-product-update-service.php';
require_once dirname(__DIR__) . '/includes/class-adpw-excel-import-service.php';
require_once dirname(__DIR__) . '/includes/class-adpw-excel-import-page-actions.php';
require_once dirname(__DIR__) . '/includes/class-adpw-excel-import-page.php';
