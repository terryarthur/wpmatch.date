<?php
/**
 * Test helper functions for WPMatch plugin tests
 *
 * @package WPMatch
 * @subpackage Tests
 */

/**
 * Base test case class for WPMatch plugin tests
 */
class WPMatch_Test_Case extends WP_UnitTestCase {

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Clear all caches
        wp_cache_flush();
        
        // Reset database tables
        $this->reset_wpmatch_tables();
        
        // Set up admin user
        $this->admin_user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($this->admin_user_id);
    }

    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clear caches
        wp_cache_flush();
        
        // Reset user
        wp_set_current_user(0);
        
        parent::tearDown();
    }

    /**
     * Reset WPMatch database tables
     */
    protected function reset_wpmatch_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'wpmatch_profile_fields',
            $wpdb->prefix . 'wpmatch_field_groups',
            $wpdb->prefix . 'wpmatch_user_profiles'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE {$table}");
        }
    }

    /**
     * Create test profile field
     */
    protected function create_test_field($args = []) {
        $defaults = [
            'name' => 'test_field_' . wp_rand(1000, 9999),
            'label' => 'Test Field',
            'type' => 'text',
            'required' => 0,
            'searchable' => 1,
            'status' => 'active',
            'field_order' => 0,
            'options' => '',
            'validation_rules' => '',
            'help_text' => '',
            'placeholder' => ''
        ];
        
        $field_data = wp_parse_args($args, $defaults);
        
        $field_manager = new WPMatch_Profile_Field_Manager();
        return $field_manager->create_field($field_data);
    }

    /**
     * Create multiple test fields
     */
    protected function create_test_fields($count = 5) {
        $fields = [];
        for ($i = 0; $i < $count; $i++) {
            $fields[] = $this->create_test_field([
                'name' => "test_field_{$i}",
                'label' => "Test Field {$i}",
                'field_order' => $i
            ]);
        }
        return $fields;
    }

    /**
     * Assert field data matches expected values
     */
    protected function assertFieldDataMatches($expected, $actual) {
        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $actual[$key], "Field {$key} does not match");
        }
    }

    /**
     * Mock AJAX request
     */
    protected function mock_ajax_request($action, $data = [], $nonce_action = null) {
        $_POST['action'] = $action;
        
        if ($nonce_action) {
            $_POST['nonce'] = wp_create_nonce($nonce_action);
        }
        
        foreach ($data as $key => $value) {
            $_POST[$key] = $value;
        }
        
        // Set up AJAX environment
        add_action('wp_ajax_' . $action, [$this, 'capture_ajax_response']);
        
        try {
            do_action('wp_ajax_' . $action);
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX responses
        }
    }

    /**
     * Capture AJAX response for testing
     */
    public function capture_ajax_response() {
        $this->ajax_response = ob_get_contents();
    }

    /**
     * Create test user with profile data
     */
    protected function create_test_user_with_profile($profile_data = []) {
        $user_id = $this->factory->user->create();
        
        if (!empty($profile_data)) {
            foreach ($profile_data as $field_name => $value) {
                update_user_meta($user_id, 'wpmatch_' . $field_name, $value);
            }
        }
        
        return $user_id;
    }

    /**
     * Assert security headers are present
     */
    protected function assertSecurityHeaders() {
        $headers = headers_list();
        $security_headers = ['X-Content-Type-Options', 'X-Frame-Options', 'X-XSS-Protection'];
        
        foreach ($security_headers as $header) {
            $found = false;
            foreach ($headers as $sent_header) {
                if (strpos($sent_header, $header) === 0) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Security header {$header} not found");
        }
    }
}

/**
 * Mock WordPress functions for testing
 */
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test_nonce_' . md5($action);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return $nonce === 'test_nonce_' . md5($action);
    }
}

/**
 * Database helper functions
 */
function get_test_db_connection() {
    global $wpdb;
    return $wpdb;
}

/**
 * Performance testing helpers
 */
function start_performance_timer() {
    return microtime(true);
}

function end_performance_timer($start_time) {
    return microtime(true) - $start_time;
}

function assert_performance_under($actual_time, $max_time, $message = '') {
    if ($actual_time > $max_time) {
        throw new Exception($message ?: "Performance test failed: {$actual_time}s > {$max_time}s");
    }
}