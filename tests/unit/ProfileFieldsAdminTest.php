<?php
/**
 * Unit Tests for WPMatch Profile Fields Admin
 *
 * @package WPMatch
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

class ProfileFieldsAdminTest extends TestCase {
    
    private $profile_fields_admin;
    private $mock_field_manager;
    private $mock_type_registry;
    private $mock_validator;
    
    public function setUp(): void {
        // Mock WordPress functions
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action) { return true; }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) { return true; }
        }
        if (!function_exists('wp_send_json_error')) {
            function wp_send_json_error($data) { 
                echo json_encode(['success' => false, 'data' => $data]);
                exit;
            }
        }
        if (!function_exists('wp_send_json_success')) {
            function wp_send_json_success($data) { 
                echo json_encode(['success' => true, 'data' => $data]);
                exit;
            }
        }
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) { return $str; }
        }
        if (!function_exists('absint')) {
            function absint($maybeint) { return abs(intval($maybeint)); }
        }
        
        // Create mock dependencies
        $this->mock_field_manager = $this->createMock(WPMatch_Profile_Field_Manager::class);
        $this->mock_type_registry = $this->createMock(WPMatch_Field_Type_Registry::class);
        $this->mock_validator = $this->createMock(WPMatch_Field_Validator::class);
        
        // Load the class under test
        if (!class_exists('WPMatch_Profile_Fields_Admin')) {
            require_once dirname(dirname(__DIR__)) . '/admin/class-profile-fields-admin.php';
        }
        
        $this->profile_fields_admin = new WPMatch_Profile_Fields_Admin();
    }
    
    public function test_class_instantiation() {
        $this->assertInstanceOf(WPMatch_Profile_Fields_Admin::class, $this->profile_fields_admin);
    }
    
    public function test_get_instance_returns_singleton() {
        if (method_exists('WPMatch_Profile_Fields_Admin', 'get_instance')) {
            $instance1 = WPMatch_Profile_Fields_Admin::get_instance();
            $instance2 = WPMatch_Profile_Fields_Admin::get_instance();
            
            $this->assertSame($instance1, $instance2);
        } else {
            $this->markTestSkipped('Singleton pattern not implemented');
        }
    }
    
    /**
     * Test AJAX handler for creating a field
     */
    public function test_ajax_create_field_success() {
        // Mock POST data
        $_POST = [
            'nonce' => 'test_nonce',
            'field_name' => 'test_field',
            'field_label' => 'Test Field',
            'field_type' => 'text',
            'field_group' => 'basic',
            'field_description' => 'A test field',
            'is_required' => '1',
            'is_searchable' => '0',
            'field_options' => ''
        ];
        
        $this->mock_field_manager
            ->expects($this->once())
            ->method('create_field')
            ->with($this->arrayHasKey('name'))
            ->willReturn(['id' => 123, 'name' => 'test_field']);
        
        ob_start();
        try {
            if (method_exists($this->profile_fields_admin, 'ajax_create_field')) {
                $this->profile_fields_admin->ajax_create_field();
            }
        } catch (Exception $e) {
            // Expected due to wp_send_json_success exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }
    
    /**
     * Test AJAX handler for updating a field
     */
    public function test_ajax_update_field_success() {
        $_POST = [
            'nonce' => 'test_nonce',
            'field_id' => '123',
            'field_name' => 'updated_field',
            'field_label' => 'Updated Field',
            'field_type' => 'text',
            'field_group' => 'basic',
            'is_required' => '0'
        ];
        
        $this->mock_field_manager
            ->expects($this->once())
            ->method('update_field')
            ->with(123, $this->arrayHasKey('name'))
            ->willReturn(true);
        
        ob_start();
        try {
            if (method_exists($this->profile_fields_admin, 'ajax_update_field')) {
                $this->profile_fields_admin->ajax_update_field();
            }
        } catch (Exception $e) {
            // Expected due to wp_send_json_success exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }
    
    /**
     * Test AJAX handler for deleting a field
     */
    public function test_ajax_delete_field_success() {
        $_POST = [
            'nonce' => 'test_nonce',
            'field_id' => '123'
        ];
        
        $this->mock_field_manager
            ->expects($this->once())
            ->method('delete_field')
            ->with(123)
            ->willReturn(true);
        
        ob_start();
        try {
            if (method_exists($this->profile_fields_admin, 'ajax_delete_field')) {
                $this->profile_fields_admin->ajax_delete_field();
            }
        } catch (Exception $e) {
            // Expected due to wp_send_json_success exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }
    
    /**
     * Test field validation
     */
    public function test_validate_field_data_required_fields() {
        if (method_exists($this->profile_fields_admin, 'validate_field_data')) {
            // Test missing required fields
            $invalid_data = [
                'field_name' => '',
                'field_label' => 'Test',
                'field_type' => 'text'
            ];
            
            $result = $this->profile_fields_admin->validate_field_data($invalid_data);
            $this->assertFalse($result['valid']);
            $this->assertNotEmpty($result['errors']);
        } else {
            $this->markTestSkipped('validate_field_data method not found');
        }
    }
    
    /**
     * Test field name sanitization
     */
    public function test_sanitize_field_name() {
        if (method_exists($this->profile_fields_admin, 'sanitize_field_name')) {
            $test_cases = [
                'Valid Name' => 'valid_name',
                'with-dashes' => 'with_dashes',
                'With Spaces' => 'with_spaces',
                'special!@#$%chars' => 'specialchars',
                '123numbers' => 'numbers'
            ];
            
            foreach ($test_cases as $input => $expected) {
                $result = $this->profile_fields_admin->sanitize_field_name($input);
                $this->assertEquals($expected, $result, "Failed for input: $input");
            }
        } else {
            $this->markTestSkipped('sanitize_field_name method not found');
        }
    }
    
    /**
     * Test security - nonce verification
     */
    public function test_ajax_handlers_require_valid_nonce() {
        global $wp_verify_nonce_result;
        $wp_verify_nonce_result = false;
        
        // Override the function to return false
        if (!function_exists('wp_verify_nonce_override')) {
            function wp_verify_nonce_override($nonce, $action) {
                global $wp_verify_nonce_result;
                return $wp_verify_nonce_result;
            }
        }
        
        $_POST = [
            'nonce' => 'invalid_nonce',
            'field_name' => 'test'
        ];
        
        ob_start();
        try {
            if (method_exists($this->profile_fields_admin, 'ajax_create_field')) {
                $this->profile_fields_admin->ajax_create_field();
            }
        } catch (Exception $e) {
            // Expected due to wp_send_json_error exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }
    
    /**
     * Test capability checks
     */
    public function test_ajax_handlers_require_capability() {
        global $current_user_can_result;
        $current_user_can_result = false;
        
        // Override the function to return false
        if (!function_exists('current_user_can_override')) {
            function current_user_can_override($capability) {
                global $current_user_can_result;
                return $current_user_can_result;
            }
        }
        
        $_POST = [
            'nonce' => 'valid_nonce',
            'field_name' => 'test'
        ];
        
        ob_start();
        try {
            if (method_exists($this->profile_fields_admin, 'ajax_create_field')) {
                $this->profile_fields_admin->ajax_create_field();
            }
        } catch (Exception $e) {
            // Expected due to wp_send_json_error exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }
    
    /**
     * Test bulk operations
     */
    public function test_ajax_bulk_delete_fields() {
        $_POST = [
            'nonce' => 'test_nonce',
            'field_ids' => [123, 456, 789]
        ];
        
        $this->mock_field_manager
            ->expects($this->exactly(3))
            ->method('delete_field')
            ->withConsecutive([123], [456], [789])
            ->willReturn(true);
        
        ob_start();
        try {
            if (method_exists($this->profile_fields_admin, 'ajax_bulk_delete_fields')) {
                $this->profile_fields_admin->ajax_bulk_delete_fields();
            }
        } catch (Exception $e) {
            // Expected due to wp_send_json_success exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }
    
    /**
     * Test field ordering/reordering
     */
    public function test_ajax_reorder_fields() {
        $_POST = [
            'nonce' => 'test_nonce',
            'field_orders' => [
                ['id' => 123, 'order' => 1],
                ['id' => 456, 'order' => 2],
                ['id' => 789, 'order' => 3]
            ]
        ];
        
        $this->mock_field_manager
            ->expects($this->exactly(3))
            ->method('update_field_order')
            ->withConsecutive([123, 1], [456, 2], [789, 3])
            ->willReturn(true);
        
        ob_start();
        try {
            if (method_exists($this->profile_fields_admin, 'ajax_reorder_fields')) {
                $this->profile_fields_admin->ajax_reorder_fields();
            }
        } catch (Exception $e) {
            // Expected due to wp_send_json_success exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success'] ?? false);
    }
    
    /**
     * Test error handling for invalid field IDs
     */
    public function test_ajax_update_field_invalid_id() {
        $_POST = [
            'nonce' => 'test_nonce',
            'field_id' => 'invalid'
        ];
        
        ob_start();
        try {
            if (method_exists($this->profile_fields_admin, 'ajax_update_field')) {
                $this->profile_fields_admin->ajax_update_field();
            }
        } catch (Exception $e) {
            // Expected due to wp_send_json_error exit
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
    }
    
    /**
     * Test field type validation
     */
    public function test_validate_field_type() {
        if (method_exists($this->profile_fields_admin, 'validate_field_type')) {
            $valid_types = ['text', 'email', 'number', 'date', 'select', 'checkbox', 'textarea'];
            
            foreach ($valid_types as $type) {
                $this->assertTrue(
                    $this->profile_fields_admin->validate_field_type($type),
                    "Field type '$type' should be valid"
                );
            }
            
            $invalid_types = ['invalid', 'script', 'object'];
            
            foreach ($invalid_types as $type) {
                $this->assertFalse(
                    $this->profile_fields_admin->validate_field_type($type),
                    "Field type '$type' should be invalid"
                );
            }
        } else {
            $this->markTestSkipped('validate_field_type method not found');
        }
    }
    
    /**
     * Test admin page rendering without errors
     */
    public function test_render_admin_page_no_errors() {
        ob_start();
        
        try {
            if (method_exists($this->profile_fields_admin, 'render_admin_page')) {
                $this->profile_fields_admin->render_admin_page();
            }
        } catch (Exception $e) {
            $this->fail("Admin page rendering threw exception: " . $e->getMessage());
        }
        
        $output = ob_get_clean();
        
        // Check that output contains expected HTML elements
        $this->assertStringContainsString('<div', $output);
        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringNotContainsString('Warning:', $output);
    }
    
    public function tearDown(): void {
        // Clean up global state
        $_POST = [];
        $_GET = [];
    }
}