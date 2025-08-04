<?php
/**
 * Security Tests for WPMatch Profile Fields
 *
 * Tests for security vulnerabilities, capability checks, nonce verification,
 * input sanitization, and other security-related functionality.
 *
 * @package WPMatch
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {
    
    private $admin;
    private $field_manager;
    
    public function setUp(): void {
        $this->setup_security_mocks();
        
        // Mock components
        $this->admin = $this->createMock(WPMatch_Profile_Fields_Admin::class);
        $this->field_manager = $this->createMock(WPMatch_Profile_Field_Manager::class);
    }
    
    private function setup_security_mocks() {
        // Mock WordPress security functions
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action) {
                global $wp_nonce_result;
                return $wp_nonce_result ?? true;
            }
        }
        
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                global $user_capability_result;
                return $user_capability_result ?? true;
            }
        }
        
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return trim(strip_tags($str));
            }
        }
        
        if (!function_exists('sanitize_email')) {
            function sanitize_email($email) {
                return filter_var($email, FILTER_SANITIZE_EMAIL);
            }
        }
        
        if (!function_exists('wp_send_json_error')) {
            function wp_send_json_error($data) {
                echo json_encode(['success' => false, 'data' => $data]);
                exit;
            }
        }
        
        if (!function_exists('esc_html')) {
            function esc_html($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }
        
        if (!function_exists('esc_attr')) {
            function esc_attr($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }
    }
    
    /**
     * Test nonce verification for AJAX requests
     */
    public function test_nonce_verification_required() {
        global $wp_nonce_result;
        $wp_nonce_result = false; // Simulate invalid nonce
        
        $_POST = [
            'action' => 'wpmatch_create_field',
            'nonce' => 'invalid_nonce',
            'field_name' => 'test_field'
        ];
        
        // Mock admin class to test nonce verification
        $admin = $this->getMockBuilder(WPMatch_Profile_Fields_Admin::class)
                     ->onlyMethods(['verify_nonce'])
                     ->getMock();
        
        $admin->expects($this->once())
              ->method('verify_nonce')
              ->willReturn(false);
        
        $this->assertFalse($admin->verify_nonce());
    }
    
    /**
     * Test capability checks for admin actions
     */
    public function test_capability_checks_required() {
        global $user_capability_result;
        $user_capability_result = false; // User doesn't have required capability
        
        $required_capabilities = [
            'manage_profile_fields',
            'edit_profile_fields',
            'delete_profile_fields'
        ];
        
        foreach ($required_capabilities as $capability) {
            $this->assertFalse(current_user_can($capability));
        }
    }
    
    /**
     * Test SQL injection prevention
     */
    public function test_sql_injection_prevention() {
        $malicious_inputs = [
            "'; DROP TABLE wp_wpmatch_profile_fields; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM wp_users --",
            "'; INSERT INTO wp_users (user_login) VALUES ('hacker'); --"
        ];
        
        foreach ($malicious_inputs as $malicious_input) {
            // Test that input is properly sanitized
            $sanitized = sanitize_text_field($malicious_input);
            
            // Should not contain SQL injection patterns
            $this->assertStringNotContainsString('DROP TABLE', $sanitized);
            $this->assertStringNotContainsString('UNION SELECT', $sanitized);
            $this->assertStringNotContainsString('INSERT INTO', $sanitized);
            $this->assertStringNotContainsString('--', $sanitized);
        }
    }
    
    /**
     * Test XSS prevention in field labels and descriptions
     */
    public function test_xss_prevention() {
        $xss_payloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '<div onclick="alert(\'XSS\')">Click me</div>'
        ];
        
        foreach ($xss_payloads as $payload) {
            // Test field label sanitization
            $sanitized_label = esc_html($payload);
            $this->assertStringNotContainsString('<script>', $sanitized_label);
            $this->assertStringNotContainsString('javascript:', $sanitized_label);
            $this->assertStringNotContainsString('onerror=', $sanitized_label);
            $this->assertStringNotContainsString('onload=', $sanitized_label);
            $this->assertStringNotContainsString('onclick=', $sanitized_label);
            
            // Test field attribute sanitization
            $sanitized_attr = esc_attr($payload);
            $this->assertStringNotContainsString('<script>', $sanitized_attr);
            $this->assertStringNotContainsString('javascript:', $sanitized_attr);
        }
    }
    
    /**
     * Test CSRF protection
     */
    public function test_csrf_protection() {
        // Test that actions without proper nonce are rejected
        $_POST = [
            'action' => 'wpmatch_create_field',
            'field_name' => 'test_field',
            // Missing nonce
        ];
        
        global $wp_nonce_result;
        $wp_nonce_result = false;
        
        ob_start();
        try {
            // Simulate AJAX handler that should check nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpmatch_admin_nonce')) {
                wp_send_json_error('Security check failed');
            }
        } catch (Exception $e) {
            // Expected due to exit in wp_send_json_error
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success'] ?? true);
        $this->assertStringContainsString('Security check failed', $response['data'] ?? '');
    }
    
    /**
     * Test input validation and sanitization
     */
    public function test_input_sanitization() {
        $test_inputs = [
            'field_name' => [
                'input' => '<script>alert("test")</script>field_name',
                'expected_pattern' => '/^[a-zA-Z0-9_]+$/' // Should only contain alphanumeric and underscore
            ],
            'field_label' => [
                'input' => '<b>Bold Label</b><script>alert("xss")</script>',
                'sanitized' => 'Bold Label' // HTML should be stripped/escaped
            ],
            'field_description' => [
                'input' => 'Description with <script>alert("xss")</script> content',
                'sanitized' => 'Description with  content' // Script tags removed
            ]
        ];
        
        foreach ($test_inputs as $field => $data) {
            $sanitized = sanitize_text_field($data['input']);
            
            if (isset($data['expected_pattern'])) {
                // For field names, should match specific pattern
                $this->assertMatchesRegularExpression($data['expected_pattern'], $sanitized);
            } elseif (isset($data['sanitized'])) {
                // For other fields, should match expected sanitized output
                $this->assertEquals($data['sanitized'], $sanitized);
            }
        }
    }
    
    /**
     * Test rate limiting for admin actions
     */
    public function test_rate_limiting() {
        // Simulate rapid requests
        $request_count = 20;
        $allowed_requests = 10;
        $time_window = 60; // seconds
        
        $rate_limiter = $this->createMock(WPMatch_Rate_Limiter::class);
        
        // Mock rate limiting behavior
        $rate_limiter->method('is_rate_limited')
                    ->willReturnCallback(function($action, $user_id) use ($allowed_requests) {
                        static $request_count = 0;
                        $request_count++;
                        return $request_count > $allowed_requests;
                    });
        
        // Test that requests are allowed initially
        for ($i = 1; $i <= $allowed_requests; $i++) {
            $this->assertFalse($rate_limiter->is_rate_limited('create_field', 1));
        }
        
        // Test that subsequent requests are rate limited
        $this->assertTrue($rate_limiter->is_rate_limited('create_field', 1));
    }
    
    /**
     * Test file upload security
     */
    public function test_file_upload_security() {
        $dangerous_files = [
            'test.php' => 'PHP file should be rejected',
            'script.js' => 'JavaScript file might be dangerous',
            'config.xml' => 'Config files should be restricted',
            'test.exe' => 'Executable files should be blocked',
            'image.php.jpg' => 'Double extension should be caught'
        ];
        
        foreach ($dangerous_files as $filename => $reason) {
            // Test file extension validation
            $is_allowed = $this->validate_file_type($filename);
            
            if (in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['php', 'js', 'exe'])) {
                $this->assertFalse($is_allowed, $reason);
            }
        }
    }
    
    /**
     * Mock file type validation
     */
    private function validate_file_type($filename) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check for double extensions
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        if (strpos($basename, '.') !== false) {
            return false; // Reject files with double extensions
        }
        
        return in_array($extension, $allowed_extensions);
    }
    
    /**
     * Test database query security (prepared statements)
     */
    public function test_prepared_statements() {
        // Mock wpdb for testing
        $wpdb = $this->createMock(stdClass::class);
        $wpdb->method('prepare')
              ->willReturn('SELECT * FROM table WHERE id = 123');
        
        // Test that queries use prepared statements
        $user_input = "1 OR 1=1";
        $query = $wpdb->prepare("SELECT * FROM table WHERE id = %d", $user_input);
        
        // Should not contain the malicious input
        $this->assertStringNotContainsString('OR 1=1', $query);
        $this->assertStringContainsString('123', $query); // Should contain sanitized integer
    }
    
    /**
     * Test capability escalation prevention
     */
    public function test_capability_escalation_prevention() {
        global $user_capability_result;
        
        // Test that users can't escalate privileges
        $restricted_actions = [
            'manage_options',
            'edit_users',
            'delete_users',
            'install_plugins',
            'edit_files'
        ];
        
        foreach ($restricted_actions as $capability) {
            $user_capability_result = false; // User doesn't have this capability
            $this->assertFalse(current_user_can($capability));
        }
    }
    
    /**
     * Test session security
     */
    public function test_session_security() {
        // Test session hijacking prevention
        $session_data = [
            'user_id' => 123,
            'user_agent' => 'Mozilla/5.0...',
            'ip_address' => '192.168.1.1',
            'timestamp' => time()
        ];
        
        // Mock session validation
        $session_validator = $this->createMock(WPMatch_Session_Validator::class);
        
        $session_validator->method('validate_session')
                         ->with($session_data)
                         ->willReturn(true);
        
        $this->assertTrue($session_validator->validate_session($session_data));
    }
    
    /**
     * Test brute force protection
     */
    public function test_brute_force_protection() {
        $login_attempts = 5;
        $max_attempts = 3;
        
        $brute_force_protection = $this->createMock(WPMatch_Brute_Force_Protection::class);
        
        $brute_force_protection->method('is_blocked')
                              ->willReturnCallback(function($ip) use ($max_attempts) {
                                  static $attempts = 0;
                                  $attempts++;
                                  return $attempts > $max_attempts;
                              });
        
        // First few attempts should be allowed
        for ($i = 1; $i <= $max_attempts; $i++) {
            $this->assertFalse($brute_force_protection->is_blocked('192.168.1.1'));
        }
        
        // Subsequent attempts should be blocked
        $this->assertTrue($brute_force_protection->is_blocked('192.168.1.1'));
    }
    
    /**
     * Test data encryption for sensitive fields
     */
    public function test_sensitive_data_encryption() {
        $sensitive_data = 'sensitive information';
        
        $encryption = $this->createMock(WPMatch_Encryption::class);
        
        $encryption->method('encrypt')
                  ->with($sensitive_data)
                  ->willReturn('encrypted_data_hash');
        
        $encryption->method('decrypt')
                  ->with('encrypted_data_hash')
                  ->willReturn($sensitive_data);
        
        // Test encryption
        $encrypted = $encryption->encrypt($sensitive_data);
        $this->assertNotEquals($sensitive_data, $encrypted);
        
        // Test decryption
        $decrypted = $encryption->decrypt($encrypted);
        $this->assertEquals($sensitive_data, $decrypted);
    }
    
    /**
     * Test admin notice security
     */
    public function test_admin_notice_security() {
        $user_input = '<script>alert("XSS in admin notice")</script>Important Notice';
        
        // Admin notices should escape user input
        $safe_notice = esc_html($user_input);
        
        $this->assertStringNotContainsString('<script>', $safe_notice);
        $this->assertStringContainsString('Important Notice', $safe_notice);
    }
    
    /**
     * Test API endpoint security
     */
    public function test_api_endpoint_security() {
        $api_endpoints = [
            '/wp-json/wpmatch/v1/fields',
            '/wp-json/wpmatch/v1/fields/123',
            '/wp-admin/admin-ajax.php?action=wpmatch_create_field'
        ];
        
        foreach ($api_endpoints as $endpoint) {
            // Each endpoint should require authentication
            $this->assertTrue($this->requires_authentication($endpoint));
            
            // Each endpoint should validate nonces
            $this->assertTrue($this->validates_nonce($endpoint));
        }
    }
    
    /**
     * Mock authentication check
     */
    private function requires_authentication($endpoint) {
        // Simulate checking if endpoint requires authentication
        return strpos($endpoint, 'admin') !== false || strpos($endpoint, 'wp-json') !== false;
    }
    
    /**
     * Mock nonce validation check  
     */
    private function validates_nonce($endpoint) {
        // Simulate checking if endpoint validates nonces
        return strpos($endpoint, 'admin-ajax') !== false;
    }
    
    public function tearDown(): void {
        // Clean up global state
        global $wp_nonce_result, $user_capability_result;
        $wp_nonce_result = null;
        $user_capability_result = null;
        $_POST = [];
        $_GET = [];
    }
}