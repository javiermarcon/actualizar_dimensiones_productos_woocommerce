<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/wp-stubs.php';
require_once dirname(__DIR__) . '/includes/class-adpw-background-job-utils.php';
require_once dirname(__DIR__) . '/includes/class-adpw-excel-import-service.php';
