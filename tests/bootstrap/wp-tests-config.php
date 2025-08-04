<?php
/**
 * WordPress test configuration file
 *
 * @package WPMatch
 * @subpackage Tests
 */

// Test database settings
define('DB_NAME', getenv('WP_TESTS_DB_NAME') ?: 'wpmatch_test');
define('DB_USER', getenv('WP_TESTS_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WP_TESTS_DB_PASS') ?: '');
define('DB_HOST', getenv('WP_TESTS_DB_HOST') ?: 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// WordPress table prefix
$table_prefix = 'wptests_';

// WordPress debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// WordPress memory limits
define('WP_MEMORY_LIMIT', '256M');

// WordPress authentication unique keys and salts
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

// WordPress language
define('WPLANG', '');

// WordPress absolute path
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}