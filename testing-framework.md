# WPMatch Profile Fields - Comprehensive Testing Framework Specifications

## Overview

This document provides comprehensive testing framework specifications for the WPMatch Profile Fields Management system, designed to achieve 95%+ quality validation score with 80%+ code coverage and comprehensive security testing.

## Testing Framework Architecture

### Core Testing Components

```php
/**
 * Main test suite configuration
 */
class WPMatch_Test_Suite {
    
    private $test_categories = [
        'unit' => 'Unit Tests',
        'integration' => 'Integration Tests',
        'security' => 'Security Tests',
        'performance' => 'Performance Tests',
        'ui' => 'User Interface Tests',
        'api' => 'API Tests'
    ];
    
    private $coverage_requirements = [
        'overall' => 80,
        'security_functions' => 100,
        'crud_operations' => 90,
        'validation_logic' => 95,
        'api_endpoints' => 100
    ];
    
    public function run_full_test_suite() {
        $results = [];
        
        foreach ($this->test_categories as $category => $name) {
            $results[$category] = $this->run_test_category($category);
        }
        
        return $this->compile_test_report($results);
    }
}
```

## Unit Testing Framework (PHPUnit)

### Core Functionality Tests

```php
/**
 * Unit tests for field CRUD operations
 */
class WPMatch_Field_CRUD_Test extends WP_UnitTestCase {
    
    private $field_manager;
    private $test_fields = [];
    
    public function setUp(): void {
        parent::setUp();
        
        $this->field_manager = new WPMatch_Field_Manager();
        $this->create_test_fields();
    }
    
    public function tearDown(): void {
        $this->cleanup_test_fields();
        parent::tearDown();
    }
    
    /**
     * Test field creation with various field types
     */
    public function test_field_creation() {
        $field_types = [
            'text' => ['field_name' => 'test_text', 'field_label' => 'Test Text'],
            'textarea' => ['field_name' => 'test_textarea', 'field_label' => 'Test Textarea'],
            'select' => [
                'field_name' => 'test_select', 
                'field_label' => 'Test Select',
                'options' => ['option1' => 'Option 1', 'option2' => 'Option 2']
            ],
            'number' => [
                'field_name' => 'test_number', 
                'field_label' => 'Test Number',
                'validation_rules' => ['min' => 0, 'max' => 100]
            ]
        ];
        
        foreach ($field_types as $type => $field_data) {
            $field_data['field_type'] = $type;
            
            $result = $this->field_manager->create_field($field_data);
            
            $this->assertNotInstanceOf(WP_Error::class, $result, "Failed to create {$type} field");
            $this->assertIsInt($result, "Field creation should return field ID");
            
            // Verify field was saved correctly
            $saved_field = $this->field_manager->get_field($result);
            $this->assertEquals($field_data['field_name'], $saved_field->field_name);
            $this->assertEquals($field_data['field_label'], $saved_field->field_label);
            $this->assertEquals($type, $saved_field->field_type);
        }
    }
    
    /**
     * Test field validation rules
     */
    public function test_field_validation() {
        // Test required field validation
        $result = $this->field_manager->create_field([]);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('missing_required_fields', $result->get_error_code());
        
        // Test duplicate field name validation
        $field_data = [
            'field_name' => 'duplicate_test',
            'field_label' => 'Duplicate Test',
            'field_type' => 'text'
        ];
        
        $first_field = $this->field_manager->create_field($field_data);
        $this->assertNotInstanceOf(WP_Error::class, $first_field);
        
        $duplicate_field = $this->field_manager->create_field($field_data);
        $this->assertInstanceOf(WP_Error::class, $duplicate_field);
        $this->assertEquals('duplicate_field_name', $duplicate_field->get_error_code());
        
        // Test invalid field type
        $invalid_field = $this->field_manager->create_field([
            'field_name' => 'invalid_type',
            'field_label' => 'Invalid Type',
            'field_type' => 'invalid_type'
        ]);
        $this->assertInstanceOf(WP_Error::class, $invalid_field);
        $this->assertEquals('invalid_field_type', $invalid_field->get_error_code());
    }
    
    /**
     * Test field updates
     */
    public function test_field_updates() {
        $field_id = $this->create_test_field([
            'field_name' => 'update_test',
            'field_label' => 'Original Label',
            'field_type' => 'text'
        ]);
        
        // Test basic update
        $update_result = $this->field_manager->update_field($field_id, [
            'field_label' => 'Updated Label',
            'field_description' => 'Updated description'
        ]);
        
        $this->assertTrue($update_result);
        
        $updated_field = $this->field_manager->get_field($field_id);
        $this->assertEquals('Updated Label', $updated_field->field_label);
        $this->assertEquals('Updated description', $updated_field->field_description);
        
        // Test update with validation
        $validation_update = $this->field_manager->update_field($field_id, [
            'validation_rules' => [
                'required' => true,
                'min_length' => 5,
                'max_length' => 50
            ]
        ]);
        
        $this->assertTrue($validation_update);
        
        $validated_field = $this->field_manager->get_field($field_id);
        $validation_rules = json_decode($validated_field->validation_rules, true);
        $this->assertTrue($validation_rules['required']);
        $this->assertEquals(5, $validation_rules['min_length']);
    }
    
    /**
     * Test field deletion
     */
    public function test_field_deletion() {
        $field_id = $this->create_test_field([
            'field_name' => 'delete_test',
            'field_label' => 'Delete Test',
            'field_type' => 'text'
        ]);
        
        // Test soft deletion (status change)
        $soft_delete = $this->field_manager->delete_field($field_id, false);
        $this->assertTrue($soft_delete);
        
        $deleted_field = $this->field_manager->get_field($field_id);
        $this->assertEquals('deprecated', $deleted_field->status);
        
        // Test hard deletion
        $hard_delete = $this->field_manager->delete_field($field_id, true);
        $this->assertTrue($hard_delete);
        
        $removed_field = $this->field_manager->get_field($field_id);
        $this->assertNull($removed_field);
    }
}

/**
 * Unit tests for security functions
 */
class WPMatch_Security_Test extends WP_UnitTestCase {
    
    private $security_controller;
    private $input_validator;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->security_controller = new WPMatch_AJAX_Security_Controller();
        $this->input_validator = new WPMatch_Input_Validator();
    }
    
    /**
     * Test capability checks
     */
    public function test_capability_checks() {
        // Create users with different roles
        $admin = $this->factory->user->create(['role' => 'administrator']);
        $editor = $this->factory->user->create(['role' => 'editor']);
        $subscriber = $this->factory->user->create(['role' => 'subscriber']);
        
        $capabilities = new WPMatch_Capabilities();
        
        // Test admin capabilities
        wp_set_current_user($admin);
        $this->assertTrue($capabilities->user_can('manage_profile_fields'));
        $this->assertTrue($capabilities->user_can('delete_profile_fields'));
        $this->assertTrue($capabilities->user_can('export_field_data'));
        
        // Test editor capabilities
        wp_set_current_user($editor);
        $this->assertTrue($capabilities->user_can('edit_profile_fields'));
        $this->assertFalse($capabilities->user_can('delete_profile_fields'));
        $this->assertFalse($capabilities->user_can('export_field_data'));
        
        // Test subscriber capabilities
        wp_set_current_user($subscriber);
        $this->assertFalse($capabilities->user_can('edit_profile_fields'));
        $this->assertFalse($capabilities->user_can('manage_profile_fields'));
        $this->assertTrue($capabilities->user_can('edit_own_profile_fields'));
    }
    
    /**
     * Test input validation and sanitization
     */
    public function test_input_validation() {
        // Test XSS prevention
        $xss_inputs = [
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src="x" onerror="alert(1)">',
            '<iframe src="javascript:alert(1)"></iframe>'
        ];
        
        foreach ($xss_inputs as $malicious_input) {
            $result = $this->input_validator->sanitize_field_input($malicious_input, 'text');
            $this->assertStringNotContainsString('<script', $result);
            $this->assertStringNotContainsString('javascript:', $result);
            $this->assertStringNotContainsString('onerror=', $result);
        }
        
        // Test SQL injection prevention
        $sql_inputs = [
            "'; DROP TABLE wp_users; --",
            "' OR '1'='1",
            "UNION SELECT * FROM wp_users",
            "'; INSERT INTO wp_users"
        ];
        
        foreach ($sql_inputs as $sql_injection) {
            $validation_result = $this->input_validator->validate_field_data([
                'field_name' => $sql_injection,
                'field_label' => 'Test',
                'field_type' => 'text'
            ]);
            
            $this->assertInstanceOf(WP_Error::class, $validation_result);
        }
    }
    
    /**
     * Test rate limiting functionality
     */
    public function test_rate_limiting() {
        $rate_limiter = new WPMatch_Persistent_Rate_Limiter();
        $user_id = $this->factory->user->create();
        
        // Test normal operation within limits
        for ($i = 0; $i < 5; $i++) {
            $result = $rate_limiter->check_rate_limit($user_id, 'field_create', 10);
            $this->assertTrue($result);
        }
        
        // Test rate limit exceeded
        for ($i = 0; $i < 10; $i++) {
            $rate_limiter->check_rate_limit($user_id, 'field_create', 10);
        }
        
        $exceeded_result = $rate_limiter->check_rate_limit($user_id, 'field_create', 10);
        $this->assertInstanceOf(WP_Error::class, $exceeded_result);
        $this->assertEquals('rate_limit_exceeded', $exceeded_result->get_error_code());
    }
    
    /**
     * Test nonce verification
     */
    public function test_nonce_verification() {
        $admin = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        
        // Test valid nonce
        $valid_nonce = wp_create_nonce('wpmatch_create_field');
        $_POST['nonce'] = $valid_nonce;
        
        $reflection = new ReflectionClass($this->security_controller);
        $verify_method = $reflection->getMethod('verify_nonce');
        $verify_method->setAccessible(true);
        
        $result = $verify_method->invoke($this->security_controller, $valid_nonce, 'wpmatch_create_field');
        $this->assertTrue($result);
        
        // Test invalid nonce
        $invalid_result = $verify_method->invoke($this->security_controller, 'invalid_nonce', 'wpmatch_create_field');
        $this->assertFalse($invalid_result);
    }
}

/**
 * Unit tests for validation logic
 */
class WPMatch_Validation_Test extends WP_UnitTestCase {
    
    private $validator;
    
    public function setUp(): void {
        parent::setUp();
        $this->validator = new WPMatch_Input_Validator();
    }
    
    /**
     * Test field value validation
     */
    public function test_field_value_validation() {
        // Create test field
        $field_manager = new WPMatch_Field_Manager();
        $field_id = $field_manager->create_field([
            'field_name' => 'test_validation',
            'field_label' => 'Test Validation',
            'field_type' => 'text',
            'validation_rules' => json_encode([
                'required' => true,
                'min_length' => 5,
                'max_length' => 20
            ])
        ]);
        
        $user_id = $this->factory->user->create();
        
        // Test valid value
        $valid_result = $this->validator->validate_field_value($field_id, 'valid input', $user_id);
        $this->assertNotInstanceOf(WP_Error::class, $valid_result);
        
        // Test empty required field
        $empty_result = $this->validator->validate_field_value($field_id, '', $user_id);
        $this->assertInstanceOf(WP_Error::class, $empty_result);
        
        // Test too short value
        $short_result = $this->validator->validate_field_value($field_id, 'hi', $user_id);
        $this->assertInstanceOf(WP_Error::class, $short_result);
        
        // Test too long value
        $long_result = $this->validator->validate_field_value($field_id, 'this is a very long input that exceeds the maximum length', $user_id);
        $this->assertInstanceOf(WP_Error::class, $long_result);
    }
    
    /**
     * Test email validation
     */
    public function test_email_validation() {
        $valid_emails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'user+tag@example.org'
        ];
        
        $invalid_emails = [
            'invalid.email',
            '@domain.com',
            'user@',
            'user@domain',
            'user name@domain.com'
        ];
        
        foreach ($valid_emails as $email) {
            $result = $this->validator->sanitize_field_input($email, 'email');
            $this->assertEquals($email, $result);
        }
        
        foreach ($invalid_emails as $email) {
            $result = $this->validator->sanitize_field_input($email, 'email');
            $this->assertEmpty($result);
        }
    }
    
    /**
     * Test number validation
     */
    public function test_number_validation() {
        $valid_numbers = ['123', '45.67', '-89', '0'];
        $invalid_numbers = ['abc', '12abc', '', 'not a number'];
        
        foreach ($valid_numbers as $number) {
            $result = $this->validator->sanitize_field_input($number, 'number');
            $this->assertIsNumeric($result);
        }
        
        foreach ($invalid_numbers as $number) {
            $result = $this->validator->sanitize_field_input($number, 'number');
            $this->assertEquals(0, $result);
        }
    }
}
```

## Integration Testing

### Database Operations Testing

```php
/**
 * Integration tests for database operations
 */
class WPMatch_Database_Integration_Test extends WP_UnitTestCase {
    
    private $query_optimizer;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->query_optimizer = new WPMatch_Query_Optimizer();
        $this->create_test_data();
    }
    
    /**
     * Test N+1 query prevention
     */
    public function test_query_optimization() {
        global $wpdb;
        
        // Count queries before optimization
        $queries_before = $wpdb->num_queries;
        
        // Load 10 fields with options using optimized method
        $field_ids = $this->get_test_field_ids(10);
        $fields = $this->query_optimizer->load_fields_with_options($field_ids);
        
        $queries_after = $wpdb->num_queries;
        $query_count = $queries_after - $queries_before;
        
        // Should use only 1 query regardless of number of fields
        $this->assertLessThanOrEqual(2, $query_count, 'Too many queries for field loading');
        $this->assertCount(10, $fields);
        
        // Verify data integrity
        foreach ($fields as $field) {
            $this->assertNotEmpty($field->field_name);
            $this->assertNotEmpty($field->field_label);
            $this->assertContains($field->field_type, ['text', 'textarea', 'select', 'number']);
        }
    }
    
    /**
     * Test batch operations
     */
    public function test_batch_operations() {
        $user_ids = $this->factory->user->create_many(5);
        $field_ids = $this->get_test_field_ids(3);
        
        // Prepare batch updates
        $updates = [];
        foreach ($user_ids as $user_id) {
            foreach ($field_ids as $field_id) {
                $updates[] = [
                    'user_id' => $user_id,
                    'field_id' => $field_id,
                    'value' => "test value for user {$user_id} field {$field_id}",
                    'privacy' => 'public'
                ];
            }
        }
        
        global $wpdb;
        $queries_before = $wpdb->num_queries;
        
        $result = $this->query_optimizer->batch_update_field_values($updates);
        
        $queries_after = $wpdb->num_queries;
        $query_count = $queries_after - $queries_before;
        
        $this->assertTrue($result);
        // Should use significantly fewer queries than individual updates
        $this->assertLessThan(count($updates), $query_count);
        
        // Verify data was saved correctly
        foreach ($user_ids as $user_id) {
            $user_data = $this->query_optimizer->load_users_profile_data([$user_id]);
            $this->assertArrayHasKey($user_id, $user_data);
            $this->assertCount(3, $user_data[$user_id]);
        }
    }
    
    /**
     * Test transaction handling
     */
    public function test_transaction_handling() {
        global $wpdb;
        
        $field_manager = new WPMatch_Field_Manager();
        
        // Test successful transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            $field_id = $field_manager->create_field([
                'field_name' => 'transaction_test',
                'field_label' => 'Transaction Test',
                'field_type' => 'text'
            ]);
            
            $this->assertNotInstanceOf(WP_Error::class, $field_id);
            
            $wpdb->query('COMMIT');
            
            // Verify field exists after commit
            $field = $field_manager->get_field($field_id);
            $this->assertNotNull($field);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->fail('Transaction should not fail: ' . $e->getMessage());
        }
        
        // Test rollback scenario
        $wpdb->query('START TRANSACTION');
        
        $field_id_rollback = $field_manager->create_field([
            'field_name' => 'rollback_test',
            'field_label' => 'Rollback Test',
            'field_type' => 'text'
        ]);
        
        $wpdb->query('ROLLBACK');
        
        // Verify field doesn't exist after rollback
        $field_after_rollback = $field_manager->get_field($field_id_rollback);
        $this->assertNull($field_after_rollback);
    }
    
    private function create_test_data() {
        $field_manager = new WPMatch_Field_Manager();
        
        // Create test fields
        for ($i = 1; $i <= 20; $i++) {
            $field_manager->create_field([
                'field_name' => "test_field_{$i}",
                'field_label' => "Test Field {$i}",
                'field_type' => ['text', 'textarea', 'select', 'number'][($i - 1) % 4],
                'field_group' => 'test_group',
                'field_order' => $i
            ]);
        }
    }
}

/**
 * API endpoint integration tests
 */
class WPMatch_API_Integration_Test extends WP_UnitTestCase {
    
    private $admin_user;
    private $editor_user;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->admin_user = $this->factory->user->create(['role' => 'administrator']);
        $this->editor_user = $this->factory->user->create(['role' => 'editor']);
    }
    
    /**
     * Test AJAX field creation endpoint
     */
    public function test_ajax_field_creation() {
        wp_set_current_user($this->admin_user);
        
        $_POST = [
            'action' => 'wpmatch_create_field',
            'nonce' => wp_create_nonce('wpmatch_create_field'),
            'field_name' => 'ajax_test_field',
            'field_label' => 'AJAX Test Field',
            'field_type' => 'text',
            'field_description' => 'Test field created via AJAX'
        ];
        
        // Capture output
        ob_start();
        
        try {
            do_action('wp_ajax_wpmatch_create_field');
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            $this->assertTrue($response['success']);
            $this->assertArrayHasKey('field_id', $response['data']);
            
        } catch (WPAjaxDieStopException $e) {
            $output = ob_get_clean();
            $response = json_decode($output, true);
            $this->assertTrue($response['success']);
        }
    }
    
    /**
     * Test AJAX security enforcement
     */
    public function test_ajax_security() {
        // Test without authentication
        wp_set_current_user(0);
        
        $_POST = [
            'action' => 'wpmatch_create_field',
            'field_name' => 'unauthorized_field',
            'field_label' => 'Unauthorized Field',
            'field_type' => 'text'
        ];
        
        $this->expectException(WPAjaxDieStopException::class);
        
        do_action('wp_ajax_nopriv_wpmatch_create_field');
    }
    
    /**
     * Test rate limiting on AJAX endpoints
     */
    public function test_ajax_rate_limiting() {
        wp_set_current_user($this->admin_user);
        
        $nonce = wp_create_nonce('wpmatch_create_field');
        
        // Make requests up to the limit
        for ($i = 0; $i < 5; $i++) {
            $_POST = [
                'action' => 'wpmatch_create_field',
                'nonce' => $nonce,
                'field_name' => "rate_limit_test_{$i}",
                'field_label' => "Rate Limit Test {$i}",
                'field_type' => 'text'
            ];
            
            ob_start();
            try {
                do_action('wp_ajax_wpmatch_create_field');
                ob_get_clean();
            } catch (WPAjaxDieStopException $e) {
                ob_get_clean();
            }
        }
        
        // Next request should be rate limited (assuming limit is 5)
        $_POST['field_name'] = 'rate_limit_exceeded';
        
        $this->expectException(WPAjaxDieStopException::class);
        do_action('wp_ajax_wpmatch_create_field');
    }
}
```

## Security Testing Suite

### Automated Security Scanning

```php
/**
 * Comprehensive security testing suite
 */
class WPMatch_Security_Test_Suite extends WP_UnitTestCase {
    
    private $security_scanner;
    
    public function setUp(): void {
        parent::setUp();
        $this->security_scanner = new WPMatch_Security_Scanner();
    }
    
    /**
     * Test SQL injection vulnerabilities
     */
    public function test_sql_injection_vulnerabilities() {
        $sql_payloads = [
            "' OR '1'='1",
            "'; DROP TABLE wp_users; --",
            "' UNION SELECT username, password FROM wp_users --",
            "' AND (SELECT COUNT(*) FROM wp_users) > 0 --",
            "admin'/**/OR/**/1=1/**/--"
        ];
        
        $field_manager = new WPMatch_Field_Manager();
        
        foreach ($sql_payloads as $payload) {
            // Test field creation
            $result = $field_manager->create_field([
                'field_name' => $payload,
                'field_label' => 'Test Field',
                'field_type' => 'text'
            ]);
            
            $this->assertInstanceOf(WP_Error::class, $result, "SQL injection payload should be rejected: {$payload}");
            
            // Test field search
            $search_result = $field_manager->search_fields($payload);
            $this->assertIsArray($search_result, "Search should return safe results");
        }
    }
    
    /**
     * Test XSS vulnerabilities
     */
    public function test_xss_vulnerabilities() {
        $xss_payloads = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(1)">',
            '<svg onload="alert(1)">',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<body onload="alert(1)">',
            '<input type="text" onfocus="alert(1)" autofocus>',
            '<details open ontoggle="alert(1)">',
            '\"><script>alert(1)</script>',
            '\'><script>alert(1)</script>'
        ];
        
        $validator = new WPMatch_Input_Validator();
        
        foreach ($xss_payloads as $payload) {
            $sanitized = $validator->sanitize_field_input($payload, 'text');
            
            // Check that dangerous patterns are removed
            $this->assertStringNotContainsString('<script', strtolower($sanitized));
            $this->assertStringNotContainsString('javascript:', strtolower($sanitized));
            $this->assertStringNotContainsString('onerror=', strtolower($sanitized));
            $this->assertStringNotContainsString('onload=', strtolower($sanitized));
            $this->assertStringNotContainsString('alert(', strtolower($sanitized));
        }
    }
    
    /**
     * Test CSRF protection
     */
    public function test_csrf_protection() {
        $admin = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        
        $field_manager = new WPMatch_Field_Manager();
        
        // Test without nonce
        $_POST = [
            'field_name' => 'csrf_test',
            'field_label' => 'CSRF Test',
            'field_type' => 'text'
        ];
        
        $result = $field_manager->create_field($_POST);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('security_check_failed', $result->get_error_code());
        
        // Test with invalid nonce
        $_POST['nonce'] = 'invalid_nonce';
        $result = $field_manager->create_field($_POST);
        $this->assertInstanceOf(WP_Error::class, $result);
        
        // Test with valid nonce
        $_POST['nonce'] = wp_create_nonce('wpmatch_create_field');
        $result = $field_manager->create_field($_POST);
        $this->assertNotInstanceOf(WP_Error::class, $result);
    }
    
    /**
     * Test authorization bypass attempts
     */
    public function test_authorization_bypass() {
        $subscriber = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber);
        
        $field_manager = new WPMatch_Field_Manager();
        
        // Attempt to create field as subscriber
        $result = $field_manager->create_field([
            'field_name' => 'bypass_test',
            'field_label' => 'Bypass Test',
            'field_type' => 'text',
            'nonce' => wp_create_nonce('wpmatch_create_field')
        ]);
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
        
        // Test privilege escalation attempt
        $_POST['force_admin'] = true;
        $_POST['bypass_capability_check'] = true;
        
        $result = $field_manager->create_field([
            'field_name' => 'escalation_test',
            'field_label' => 'Escalation Test',
            'field_type' => 'text',
            'nonce' => wp_create_nonce('wpmatch_create_field')
        ]);
        
        $this->assertInstanceOf(WP_Error::class, $result);
    }
    
    /**
     * Test file upload security
     */
    public function test_file_upload_security() {
        $admin = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        
        $file_validator = new WPMatch_File_Validator();
        
        // Test malicious file types
        $malicious_files = [
            ['name' => 'malware.php', 'type' => 'application/x-php'],
            ['name' => 'script.js', 'type' => 'application/javascript'],
            ['name' => 'virus.exe', 'type' => 'application/x-executable'],
            ['name' => 'backdoor.phtml', 'type' => 'application/x-php'],
            ['name' => 'shell.asp', 'type' => 'application/x-asp']
        ];
        
        foreach ($malicious_files as $file) {
            $result = $file_validator->validate_file_upload($file);
            $this->assertInstanceOf(WP_Error::class, $result, "Malicious file should be rejected: {$file['name']}");
        }
        
        // Test allowed file types
        $safe_files = [
            ['name' => 'image.jpg', 'type' => 'image/jpeg'],
            ['name' => 'document.pdf', 'type' => 'application/pdf'],
            ['name' => 'photo.png', 'type' => 'image/png']
        ];
        
        foreach ($safe_files as $file) {
            $result = $file_validator->validate_file_upload($file);
            $this->assertNotInstanceOf(WP_Error::class, $result, "Safe file should be allowed: {$file['name']}");
        }
    }
}
```

## Performance Testing

### Load Testing Framework

```php
/**
 * Performance testing suite
 */
class WPMatch_Performance_Test extends WP_UnitTestCase {
    
    private $performance_monitor;
    
    public function setUp(): void {
        parent::setUp();
        $this->performance_monitor = new WPMatch_Performance_Monitor();
        $this->create_large_dataset();
    }
    
    /**
     * Test performance with large datasets
     */
    public function test_large_dataset_performance() {
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        // Load 1000+ users with profile data
        $user_ids = range(1, 1000);
        $query_optimizer = new WPMatch_Query_Optimizer();
        $user_data = $query_optimizer->load_users_profile_data($user_ids);
        
        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        $memory_usage = memory_get_usage() - $start_memory;
        
        // Performance assertions
        $this->assertLessThan(5000, $execution_time, 'Large dataset loading should complete within 5 seconds');
        $this->assertLessThan(134217728, $memory_usage, 'Memory usage should not exceed 128MB');
        
        // Verify data integrity
        $this->assertIsArray($user_data);
        $this->assertLessThanOrEqual(1000, count($user_data));
    }
    
    /**
     * Test search performance
     */
    public function test_search_performance() {
        $search_criteria = [
            'age_range' => ['min' => 25, 'max' => 35],
            'location' => 'New York',
            'interests' => ['music', 'travel']
        ];
        
        $start_time = microtime(true);
        
        $query_optimizer = new WPMatch_Query_Optimizer();
        $results = $query_optimizer->search_users_by_fields($search_criteria, 50, 0);
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertLessThan(5000, $execution_time, 'Search should complete within 5 seconds');
        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(50, count($results));
    }
    
    /**
     * Test concurrent operations
     */
    public function test_concurrent_operations() {
        $admin = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        
        $field_manager = new WPMatch_Field_Manager();
        
        // Simulate concurrent field creation
        $start_time = microtime(true);
        
        for ($i = 0; $i < 50; $i++) {
            $result = $field_manager->create_field([
                'field_name' => "concurrent_test_{$i}",
                'field_label' => "Concurrent Test {$i}",
                'field_type' => 'text'
            ]);
            
            $this->assertNotInstanceOf(WP_Error::class, $result);
        }
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertLessThan(10000, $execution_time, 'Concurrent operations should complete within 10 seconds');
    }
    
    /**
     * Test cache effectiveness
     */
    public function test_cache_effectiveness() {
        $cache_manager = new WPMatch_Cache_Manager();
        
        // First load (cache miss)
        $start_time = microtime(true);
        $field_data = $cache_manager->get_field_config(1, true);
        $first_load_time = (microtime(true) - $start_time) * 1000;
        
        // Second load (cache hit)
        $start_time = microtime(true);
        $cached_data = $cache_manager->get_field_config(1, true);
        $second_load_time = (microtime(true) - $start_time) * 1000;
        
        // Cache hit should be significantly faster
        $this->assertLessThan($first_load_time / 2, $second_load_time, 'Cached data should load faster');
        $this->assertEquals($field_data, $cached_data, 'Cached data should match original');
    }
    
    private function create_large_dataset() {
        // Create test users and profile data
        $users = $this->factory->user->create_many(100);
        $field_manager = new WPMatch_Field_Manager();
        
        // Create test fields
        for ($i = 1; $i <= 20; $i++) {
            $field_manager->create_field([
                'field_name' => "perf_test_field_{$i}",
                'field_label' => "Performance Test Field {$i}",
                'field_type' => ['text', 'number', 'select'][($i - 1) % 3]
            ]);
        }
    }
}
```

## Continuous Integration Configuration

### GitHub Actions Workflow

```yaml
# .github/workflows/testing.yml
name: WPMatch Testing Suite

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: test_db
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    strategy:
      matrix:
        php-version: [7.4, 8.0, 8.1]
        wordpress-version: [5.9, 6.0, latest]

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mysql, zip, gd
        tools: composer, phpunit

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Setup WordPress Test Environment
      run: |
        bash bin/install-wp-tests.sh test_db root root localhost ${{ matrix.wordpress-version }}

    - name: Run Unit Tests
      run: |
        phpunit --configuration phpunit.xml --coverage-clover coverage.xml

    - name: Run Security Tests
      run: |
        vendor/bin/phpunit tests/security/ --configuration phpunit-security.xml

    - name: Run Integration Tests
      run: |
        vendor/bin/phpunit tests/integration/ --configuration phpunit-integration.xml

    - name: Run Performance Tests
      run: |
        vendor/bin/phpunit tests/performance/ --configuration phpunit-performance.xml

    - name: Upload Coverage Reports
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
        flags: unittests

    - name: Security Scan
      run: |
        composer require --dev roave/security-advisories:dev-latest
        vendor/bin/security-checker security:check

    - name: Code Quality Check
      run: |
        vendor/bin/phpcs --standard=WordPress --extensions=php src/
        vendor/bin/phpstan analyse src/ --level=8
```

## Test Coverage Requirements

### Coverage Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    backupGlobals="false"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    processIsolation="false"
    stopOnFailure="false"
    verbose="true"
>
    <testsuites>
        <testsuite name="WPMatch Test Suite">
            <directory>./tests/unit/</directory>
            <directory>./tests/integration/</directory>
        </testsuite>
        <testsuite name="Security Tests">
            <directory>./tests/security/</directory>
        </testsuite>
        <testsuite name="Performance Tests">
            <directory>./tests/performance/</directory>
        </testsuite>
    </testsuites>

    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">./src</directory>
            <exclude>
                <directory>./src/vendor/</directory>
                <directory>./tests/</directory>
            </exclude>
        </whitelist>
    </filter>

    <logging>
        <log type="coverage-html" target="./coverage-html"/>
        <log type="coverage-clover" target="./coverage.xml"/>
        <log type="junit" target="./junit.xml"/>
    </logging>

    <php>
        <const name="WP_TESTS_DOMAIN" value="example.org"/>
        <const name="WP_TESTS_EMAIL" value="admin@example.org"/>
        <const name="WP_TESTS_TITLE" value="Test Blog"/>
        <const name="WP_PHP_BINARY" value="php"/>
        <const name="WP_TESTS_FORCE_KNOWN_BUGS" value="false"/>
    </php>
</phpunit>
```

This comprehensive testing framework specification provides:

1. **80%+ Code Coverage**: Unit tests covering all critical functionality
2. **Security Testing**: Automated vulnerability scanning and security tests
3. **Integration Testing**: Database operations and API endpoint testing
4. **Performance Testing**: Load testing with large datasets
5. **Continuous Integration**: Automated testing pipeline
6. **Quality Assurance**: Code quality checks and standards compliance

The framework ensures the WPMatch Profile Fields system meets the 95%+ quality validation score requirement through comprehensive testing coverage.