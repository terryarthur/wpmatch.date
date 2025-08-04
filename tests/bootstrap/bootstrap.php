<?php
/**
 * PHPUnit bootstrap file for WPMatch Plugin Tests
 *
 * @package WPMatch
 * @subpackage Tests
 */

// Composer autoloader
if (file_exists(dirname(dirname(__DIR__)) . '/vendor/autoload.php')) {
    require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
}

// Test environment configuration
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
    exit(1);
}

// WordPress test environment
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    require dirname(dirname(__DIR__)) . '/wpmatch.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Test helper functions
require_once __DIR__ . '/test-helpers.php';

// Test fixtures
require_once dirname(__DIR__) . '/fixtures/test-fixtures.php';