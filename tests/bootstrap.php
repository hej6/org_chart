<?php
/**
 * PHPUnit bootstrap: runs once before the tests (see phpunit.xml).
 */

// Database errors throw mysqli_sql_exception, so tests can check for them
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

define('PROJECT_ROOT', dirname(__DIR__));

// php/path.php builds its paths from DOCUMENT_ROOT, which is empty on the command line
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(PROJECT_ROOT);
}

// Database settings (DB_HOST, DB_USER, DB_PASSWORD, DB_NAME).
// DatabaseConnectionTest reports clearly if this file is missing.
if (is_file(PROJECT_ROOT . '/php/config.local.php')) {
    require_once PROJECT_ROOT . '/php/config.local.php';
}
