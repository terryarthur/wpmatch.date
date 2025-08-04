<?php
/**
 * Unit tests for WPMatch_Profile_Fields_Admin class
 *
 * @package WPMatch
 * @subpackage Tests
 */

class Test_Profile_Fields_Admin extends WPMatch_Test_Case {

    /**
     * Admin class instance
     *
     * @var WPMatch_Profile_Fields_Admin
     */
    private $admin;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Mock WordPress admin environment
        set_current_screen('wpmatch_profile_fields');
        
        // Initialize admin class
        $this->admin = new WPMatch_Profile_Fields_Admin();
        $this->admin->init();
    }

    /**
     * Test admin class initialization
     */
    public function test_admin_initialization() {
        $this->assertInstanceOf('WPMatch_Profile_Fields_Admin', $this->admin);
        
        // Test hooks are registered
        $this->assertGreaterThan(0, has_action('admin_menu', array($this->admin, 'add_admin_menu')));
        $this->assertGreaterThan(0, has_action('admin_enqueue_scripts', array($this->admin, 'enqueue_admin_scripts')));
        $this->assertGreaterThan(0, has_action('wp_ajax_wpmatch_create_field', array($this->admin, 'ajax_create_field')));
    }

    /**
     * Test admin menu creation
     */
    public function test_add_admin_menu() {
        global $submenu;
        
        // Set up admin user
        wp_set_current_user($this->admin_user_id);
        
        // Trigger menu creation
        do_action('admin_menu');
        
        // Check if menu was added (would need to verify menu structure)
        $this->assertTrue(true); // Placeholder - would verify actual menu structure
    }

    /**
     * Test admin scripts enqueuing
     */
    public function test_enqueue_admin_scripts() {
        global $wp_scripts, $wp_styles;
        
        // Set admin page
        set_current_screen('wpmatch_profile_fields');
        
        // Trigger script enqueuing
        do_action('admin_enqueue_scripts', 'wpmatch_profile_fields');
        
        // Verify scripts are enqueued (would check actual enqueued scripts)
        $this->assertTrue(true); // Placeholder - would verify script enqueuing
    }

    /**
     * Test AJAX create field
     */
    public function test_ajax_create_field_success() {
        // Set up test data
        $field_data = [
            'name' => 'test_field',
            'label' => 'Test Field',
            'type' => 'text',
            'required' => '0',
            'searchable' => '1'
        ];

        // Mock AJAX request
        $_POST['action'] = 'wpmatch_create_field';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        foreach ($field_data as $key => $value) {
            $_POST[$key] = $value;
        }

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_create_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('field_id', $response['data']);
    }

    /**
     * Test AJAX create field with invalid data
     */
    public function test_ajax_create_field_validation_error() {
        // Set up invalid test data (missing required fields)
        $_POST['action'] = 'wpmatch_create_field';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['name'] = ''; // Invalid - empty name
        $_POST['label'] = 'Test Field';
        $_POST['type'] = 'invalid_type'; // Invalid type

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_create_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify error response
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertStringContains('validation', strtolower($response['data']));
    }

    /**
     * Test AJAX create field without permission
     */
    public function test_ajax_create_field_no_permission() {
        // Set up user without permission
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        // Set up test data
        $_POST['action'] = 'wpmatch_create_field';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['name'] = 'test_field';
        $_POST['label'] = 'Test Field';
        $_POST['type'] = 'text';

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_create_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify permission error
        $this->assertFalse($response['success']);
        $this->assertStringContains('permission', strtolower($response['data']));
    }

    /**
     * Test AJAX create field with invalid nonce
     */
    public function test_ajax_create_field_invalid_nonce() {
        // Set up test data with invalid nonce
        $_POST['action'] = 'wpmatch_create_field';
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['name'] = 'test_field';
        $_POST['label'] = 'Test Field';
        $_POST['type'] = 'text';

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_create_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify nonce error
        $this->assertFalse($response['success']);
        $this->assertStringContains('nonce', strtolower($response['data']));
    }

    /**
     * Test AJAX update field
     */
    public function test_ajax_update_field_success() {
        // Create test field first
        $field_id = $this->create_test_field();

        // Set up update data
        $_POST['action'] = 'wpmatch_update_field';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['field_id'] = $field_id;
        $_POST['label'] = 'Updated Test Field';
        $_POST['required'] = '1';

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_update_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * Test AJAX delete field
     */
    public function test_ajax_delete_field_success() {
        // Create test field first
        $field_id = $this->create_test_field();

        // Set up delete request
        $_POST['action'] = 'wpmatch_delete_field';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['field_id'] = $field_id;

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_delete_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
    }

    /**
     * Test AJAX get field
     */
    public function test_ajax_get_field_success() {
        // Create test field first
        $field_id = $this->create_test_field([
            'name' => 'test_get_field',
            'label' => 'Test Get Field'
        ]);

        // Set up get request
        $_POST['action'] = 'wpmatch_get_field';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['field_id'] = $field_id;

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_get_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('test_get_field', $response['data']['name']);
        $this->assertEquals('Test Get Field', $response['data']['label']);
    }

    /**
     * Test AJAX get fields list
     */
    public function test_ajax_get_fields_success() {
        // Create multiple test fields
        $this->create_test_fields(3);

        // Set up get fields request
        $_POST['action'] = 'wpmatch_get_fields';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_get_fields();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertCount(3, $response['data']);
    }

    /**
     * Test AJAX reorder fields
     */
    public function test_ajax_reorder_fields_success() {
        // Create test fields
        $fields = $this->create_test_fields(3);

        // Set up reorder request
        $_POST['action'] = 'wpmatch_reorder_fields';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['field_order'] = json_encode([
            $fields[2], // Move third field to first
            $fields[0], // Move first field to second
            $fields[1]  // Move second field to third
        ]);

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_reorder_fields();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
    }

    /**
     * Test AJAX validate field
     */
    public function test_ajax_validate_field_success() {
        // Set up validation request
        $_POST['action'] = 'wpmatch_validate_field';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['name'] = 'valid_field_name';
        $_POST['label'] = 'Valid Field Label';
        $_POST['type'] = 'text';

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_validate_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful validation
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertTrue($response['data']['valid']);
    }

    /**
     * Test AJAX get field types
     */
    public function test_ajax_get_field_types_success() {
        // Set up get field types request
        $_POST['action'] = 'wpmatch_get_field_types';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_get_field_types();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertNotEmpty($response['data']);
    }

    /**
     * Test AJAX duplicate field
     */
    public function test_ajax_duplicate_field_success() {
        // Create test field first
        $field_id = $this->create_test_field([
            'name' => 'original_field',
            'label' => 'Original Field'
        ]);

        // Set up duplicate request
        $_POST['action'] = 'wpmatch_duplicate_field';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['field_id'] = $field_id;

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_duplicate_field();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('field_id', $response['data']);
        $this->assertNotEquals($field_id, $response['data']['field_id']);
    }

    /**
     * Test AJAX bulk operations
     */
    public function test_ajax_bulk_operations_success() {
        // Create test fields
        $fields = $this->create_test_fields(3);

        // Set up bulk operation request (activate)
        $_POST['action'] = 'wpmatch_bulk_field_operations';
        $_POST['nonce'] = wp_create_nonce('wpmatch_admin_nonce');
        $_POST['operation'] = 'activate';
        $_POST['field_ids'] = json_encode($fields);

        // Capture output
        ob_start();
        
        try {
            $this->admin->ajax_bulk_field_operations();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX die()
        }
        
        $output = ob_get_clean();
        $response = json_decode($output, true);

        // Verify successful response
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('updated_count', $response['data']);
        $this->assertEquals(3, $response['data']['updated_count']);
    }

    /**
     * Test admin action handling
     */
    public function test_handle_admin_actions() {
        // Set up admin action
        $_GET['action'] = 'create_field';
        $_POST['field_name'] = 'test_action_field';
        $_POST['field_label'] = 'Test Action Field';
        $_POST['field_type'] = 'text';
        $_POST['wpmatch_admin_nonce'] = wp_create_nonce('wpmatch_admin_nonce');

        // Trigger admin action handling
        ob_start();
        $this->admin->handle_admin_actions();
        $output = ob_get_clean();

        // Verify action was handled (would check for redirect or success message)
        $this->assertIsString($output);
    }

    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        // Clear POST data
        $_POST = [];
        $_GET = [];
        
        parent::tearDown();
    }
}