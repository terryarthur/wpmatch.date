# Testing Framework Architecture

## Executive Summary

This document outlines the comprehensive testing strategy for WPMatch to achieve 80%+ code coverage and ensure robust, reliable functionality. The testing framework encompasses unit tests, integration tests, security tests, performance tests, and automated testing pipelines designed to maintain code quality and prevent regressions.

## Testing Strategy Overview

### Testing Pyramid Structure
```
    ┌─────────────────────┐
    │   E2E Tests (5%)    │  Browser automation, user workflows
    ├─────────────────────┤
    │ Integration (25%)   │  Database, API, WordPress integration
    ├─────────────────────┤
    │   Unit Tests (70%)  │  Individual functions, classes, methods
    └─────────────────────┘
```

### Coverage Targets
- **Overall Code Coverage**: 80%+
- **Critical Path Coverage**: 95%+
- **Security Function Coverage**: 100%
- **Database Operation Coverage**: 90%+
- **API Endpoint Coverage**: 95%+

## PHPUnit Testing Framework Setup

### 1. Base Test Infrastructure
```php
<?php
/**
 * Base test class for WPMatch testing
 */
abstract class WPMatch_Test_Case extends WP_UnitTestCase {
    
    protected $plugin_instance;
    protected $test_users = [];
    protected $test_profiles = [];
    protected $test_conversations = [];
    protected $original_user;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Store original user
        $this->original_user = wp_get_current_user();
        
        // Initialize plugin
        $this->plugin_instance = WPMatch_Plugin::get_instance();
        
        // Create test data
        $this->create_test_users();
        $this->create_test_profiles();
        
        // Clear all caches
        wp_cache_flush();
        $this->clear_wpmatch_caches();
        
        // Reset error tracking
        WPMatch_Error_Monitor::reset_for_testing();
    }
    
    /**
     * Clean up after test
     */
    public function tearDown(): void {
        // Restore original user
        wp_set_current_user($this->original_user->ID);
        
        // Clean up test data
        $this->cleanup_test_data();
        
        // Clear caches
        wp_cache_flush();
        $this->clear_wpmatch_caches();
        
        parent::tearDown();
    }
    
    /**
     * Create test users with different roles and capabilities
     */
    protected function create_test_users() {
        // Create standard users
        for ($i = 1; $i <= 10; $i++) {
            $user_id = $this->factory->user->create([
                'user_login' => "testuser{$i}",
                'user_email' => "test{$i}@example.com",
                'user_pass' => 'testpassword123',
                'role' => 'subscriber',
                'meta_input' => [
                    'first_name' => "Test{$i}",
                    'last_name' => 'User',
                    'description' => "Test user {$i} for WPMatch testing"
                ]
            ]);
            
            $this->test_users[] = $user_id;
        }
        
        // Create admin user
        $admin_id = $this->factory->user->create([
            'user_login' => 'testadmin',
            'user_email' => 'admin@example.com',
            'role' => 'administrator'
        ]);
        $this->test_users['admin'] = $admin_id;
        
        // Create moderator user
        $moderator_id = $this->factory->user->create([
            'user_login' => 'testmoderator',
            'user_email' => 'moderator@example.com',
            'role' => 'editor'
        ]);
        $this->test_users['moderator'] = $moderator_id;
    }
    
    /**
     * Create test profiles with varied data
     */
    protected function create_test_profiles() {
        global $wpdb;
        
        $genders = ['male', 'female', 'non_binary'];
        $cities = ['Los Angeles', 'New York', 'Chicago', 'Miami', 'Seattle'];
        
        foreach ($this->test_users as $i => $user_id) {
            if (is_string($i)) continue; // Skip admin/moderator
            
            $profile_data = [
                'user_id' => $user_id,
                'display_name' => "TestUser{$i}",
                'about_me' => "This is test user {$i} looking for meaningful connections.",
                'age' => rand(18, 65),
                'gender' => $genders[$i % 3],
                'looking_for' => $genders[($i + 1) % 3],
                'location_city' => $cities[$i % 5],
                'location_state' => 'CA',
                'location_country' => 'US',
                'latitude' => 34.0522 + (rand(-100, 100) / 1000),
                'longitude' => -118.2437 + (rand(-100, 100) / 1000),
                'is_active' => 1,
                'profile_completeness' => rand(50, 100),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            $table = $wpdb->prefix . 'wpmatch_profiles';
            $wpdb->insert($table, $profile_data);
            $this->test_profiles[$user_id] = $wpdb->insert_id;
        }
    }
    
    /**
     * Helper method to create test photo
     */
    protected function create_test_photo($user_id, $is_primary = false) {
        global $wpdb;
        
        $photo_data = [
            'user_id' => $user_id,
            'original_filename' => 'test_photo.jpg',
            'file_path' => '/uploads/wpmatch/test_photo.jpg',
            'cdn_url' => 'https://cdn.example.com/test_photo.jpg',
            'file_size' => 1024000,
            'width' => 800,
            'height' => 600,
            'mime_type' => 'image/jpeg',
            'is_primary' => $is_primary ? 1 : 0,
            'is_approved' => 1,
            'display_order' => 1,
            'created_at' => current_time('mysql')
        ];
        
        $table = $wpdb->prefix . 'wpmatch_photos';
        $wpdb->insert($table, $photo_data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Assert that user has specific capability
     */
    protected function assertUserCan($capability, $user_id = null) {
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            $this->assertTrue($user->has_cap($capability), 
                "User {$user_id} should have capability: {$capability}");
        } else {
            $this->assertTrue(current_user_can($capability), 
                "Current user should have capability: {$capability}");
        }
    }
    
    /**
     * Assert database table has specific record
     */
    protected function assertDatabaseHas($table, $conditions) {
        global $wpdb;
        
        $where_parts = [];
        $values = [];
        
        foreach ($conditions as $column => $value) {
            $where_parts[] = "{$column} = %s";
            $values[] = $value;
        }
        
        $where_clause = implode(' AND ', $where_parts);
        $query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        $prepared = $wpdb->prepare($query, ...$values);
        $count = $wpdb->get_var($prepared);
        
        $this->assertGreaterThan(0, $count, 
            "Database table {$table} should contain record matching conditions");
    }
    
    /**
     * Clear WPMatch specific caches
     */
    protected function clear_wpmatch_caches() {
        // Clear object cache groups
        $cache_groups = ['wpmatch_profiles', 'wpmatch_matches', 'wpmatch_messages', 'wpmatch_interactions'];
        
        foreach ($cache_groups as $group) {
            wp_cache_flush_group($group);
        }
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpmatch_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpmatch_%'");
    }
    
    /**
     * Clean up test data
     */
    protected function cleanup_test_data() {
        global $wpdb;
        
        // Clean up profiles
        if (!empty($this->test_profiles)) {
            $profile_ids = implode(',', array_map('intval', $this->test_profiles));
            $wpdb->query("DELETE FROM {$wpdb->prefix}wpmatch_profiles WHERE id IN ({$profile_ids})");
        }
        
        // Clean up users
        foreach ($this->test_users as $user_id) {
            if (is_numeric($user_id)) {
                wp_delete_user($user_id);
            }
        }
        
        // Clean up photos
        $wpdb->query("DELETE FROM {$wpdb->prefix}wpmatch_photos WHERE user_id IN (SELECT ID FROM {$wpdb->users} WHERE user_login LIKE 'test%')");
        
        // Clean up messages and conversations
        $wpdb->query("DELETE FROM {$wpdb->prefix}wpmatch_messages WHERE sender_id IN (SELECT ID FROM {$wpdb->users} WHERE user_login LIKE 'test%')");
        $wpdb->query("DELETE FROM {$wpdb->prefix}wpmatch_conversations WHERE user1_id IN (SELECT ID FROM {$wpdb->users} WHERE user_login LIKE 'test%')");
    }
}
```

### 2. Security Testing Suite
```php
<?php
/**
 * Security testing for WPMatch
 */
class WPMatch_Security_Test extends WPMatch_Test_Case {
    
    /**
     * Test rate limiting functionality
     */
    public function test_rate_limiting_messages() {
        $user_id = $this->test_users[0];
        wp_set_current_user($user_id);
        
        // Test message rate limiting (30 messages per hour)
        $success_count = 0;
        $blocked_count = 0;
        
        for ($i = 0; $i < 35; $i++) {
            $result = WPMatch_Enhanced_Security::check_persistent_rate_limit(
                'send_message', 
                $user_id, 
                30, 
                3600
            );
            
            if ($result) {
                $success_count++;
            } else {
                $blocked_count++;
            }
        }
        
        $this->assertEquals(30, $success_count, 'Should allow exactly 30 messages');
        $this->assertEquals(5, $blocked_count, 'Should block 5 excess messages');
    }
    
    /**
     * Test progressive penalty system
     */
    public function test_progressive_penalties() {
        $user_id = $this->test_users[0];
        
        // Trigger rate limit multiple times
        for ($i = 0; $i < 3; $i++) {
            // Exceed rate limit
            for ($j = 0; $j < 35; $j++) {
                WPMatch_Enhanced_Security::check_persistent_rate_limit(
                    'send_message', 
                    $user_id, 
                    30, 
                    3600
                );
            }
            
            // Check penalty duration increases
            $penalty_key = "wpmatch_penalty_{$user_id}";
            $penalty_count = get_transient($penalty_key);
            $this->assertGreaterThan($i, $penalty_count, 'Penalty should increase with violations');
        }
    }
    
    /**
     * Test capability-based access control
     */
    public function test_capability_checks() {
        // Test subscriber capabilities
        $user_id = $this->test_users[0];
        wp_set_current_user($user_id);
        
        $this->assertUserCan('wpmatch_view_profiles');
        $this->assertUserCan('wpmatch_send_messages');
        $this->assertFalse(current_user_can('wpmatch_moderate_content'));
        $this->assertFalse(current_user_can('wpmatch_admin_settings'));
        
        // Test admin capabilities
        wp_set_current_user($this->test_users['admin']);
        
        $this->assertUserCan('wpmatch_view_profiles');
        $this->assertUserCan('wpmatch_send_messages');
        $this->assertUserCan('wpmatch_moderate_content');
        $this->assertUserCan('wpmatch_admin_settings');
    }
    
    /**
     * Test input sanitization
     */
    public function test_input_sanitization() {
        $test_cases = [
            // XSS attempts
            [
                'input' => '<script>alert("xss")</script>Hello World',
                'type' => 'text',
                'expected' => 'Hello World'
            ],
            [
                'input' => 'javascript:alert("xss")',
                'type' => 'url',
                'expected' => ''
            ],
            // SQL injection attempts
            [
                'input' => "'; DROP TABLE users; --",
                'type' => 'text',
                'expected' => "'; DROP TABLE users; --" // Should be escaped, not removed
            ],
            // Email validation
            [
                'input' => 'test@example.com',
                'type' => 'email',
                'expected' => 'test@example.com'
            ],
            [
                'input' => 'invalid-email',
                'type' => 'email',
                'expected' => ''
            ],
            // HTML content
            [
                'input' => '<p>Valid <strong>HTML</strong> content</p>',
                'type' => 'html',
                'expected' => '<p>Valid <strong>HTML</strong> content</p>'
            ],
            [
                'input' => '<p>Valid content</p><script>alert("bad")</script>',
                'type' => 'html',
                'expected' => '<p>Valid content</p>alert("bad")'
            ]
        ];
        
        foreach ($test_cases as $test_case) {
            $result = WPMatch_Security::sanitize_input($test_case['input'], $test_case['type']);
            $this->assertEquals(
                $test_case['expected'], 
                $result, 
                "Failed sanitizing: {$test_case['input']} as {$test_case['type']}"
            );
        }
    }
    
    /**
     * Test file upload validation
     */
    public function test_file_upload_validation() {
        // Valid image file
        $valid_file = [
            'name' => 'test.jpg',
            'tmp_name' => '/tmp/test.jpg',
            'size' => 1024000, // 1MB
            'error' => UPLOAD_ERR_OK
        ];
        
        // Mock wp_check_filetype_and_ext
        add_filter('wp_check_filetype_and_ext', function($data, $file, $filename) {
            if ($filename === 'test.jpg') {
                return ['type' => 'image/jpeg', 'ext' => 'jpg'];
            }
            return ['type' => false, 'ext' => false];
        }, 10, 3);
        
        $result = WPMatch_Security::validate_file_upload($valid_file);
        $this->assertTrue($result, 'Valid image should pass validation');
        
        // Test file too large
        $large_file = $valid_file;
        $large_file['size'] = 10485760; // 10MB
        
        $result = WPMatch_Security::validate_file_upload($large_file);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('file_too_large', $result->get_error_code());
        
        // Test invalid file type
        $invalid_file = [
            'name' => 'malicious.exe',
            'tmp_name' => '/tmp/malicious.exe',
            'size' => 1024,
            'error' => UPLOAD_ERR_OK
        ];
        
        $result = WPMatch_Security::validate_file_upload($invalid_file);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_file_type', $result->get_error_code());
    }
    
    /**
     * Test CSRF protection
     */
    public function test_csrf_protection() {
        $user_id = $this->test_users[0];
        wp_set_current_user($user_id);
        
        // Mock AJAX request without nonce
        $_POST['action'] = 'wpmatch_send_message';
        $_POST['message'] = 'Test message';
        
        $this->expectException('WPDieException');
        WPMatch_Security::verify_nonce();
        
        // Mock AJAX request with valid nonce
        $_POST['wpmatch_nonce'] = wp_create_nonce('wpmatch_send_message');
        
        // Should not throw exception
        $this->expectNotToPerformAssertions();
        WPMatch_Security::verify_nonce();
    }
    
    /**
     * Test password security
     */
    public function test_password_security() {
        $password = 'SecureTestPassword123!';
        
        // Test password hashing
        $hash = WPMatch_Security::hash_password($password);
        $this->assertNotEquals($password, $hash, 'Password should be hashed');
        $this->assertGreaterThan(50, strlen($hash), 'Hash should be sufficiently long');
        
        // Test password verification
        $this->assertTrue(
            WPMatch_Security::verify_password($password, $hash),
            'Should verify correct password'
        );
        
        $this->assertFalse(
            WPMatch_Security::verify_password('wrongpassword', $hash),
            'Should reject incorrect password'
        );
    }
}
```

### 3. Database Integration Tests
```php
<?php
/**
 * Database integration tests
 */
class WPMatch_Database_Integration_Test extends WPMatch_Test_Case {
    
    /**
     * Test profile creation and retrieval
     */
    public function test_profile_creation_and_retrieval() {
        $user_id = $this->test_users[0];
        
        $profile_data = [
            'display_name' => 'Test Profile',
            'about_me' => 'This is a test profile for integration testing.',
            'age' => 28,
            'gender' => 'male',
            'looking_for' => 'female',
            'location_city' => 'San Francisco',
            'location_state' => 'CA',
            'latitude' => 37.7749,
            'longitude' => -122.4194
        ];
        
        // Test profile creation
        $profile_service = new WPMatch_Profile_Service();
        $result = $profile_service->create_profile($user_id, $profile_data);
        
        $this->assertIsNumeric($result, 'Profile creation should return profile ID');
        $this->assertGreaterThan(0, $result, 'Profile ID should be positive');
        
        // Verify in database
        $this->assertDatabaseHas(
            $GLOBALS['wpdb']->prefix . 'wpmatch_profiles',
            ['user_id' => $user_id, 'display_name' => 'Test Profile']
        );
        
        // Test profile retrieval
        $retrieved_profile = $profile_service->get_profile($user_id);
        
        $this->assertNotNull($retrieved_profile, 'Profile should be retrievable');
        $this->assertEquals($profile_data['display_name'], $retrieved_profile->display_name);
        $this->assertEquals($profile_data['age'], $retrieved_profile->age);
        $this->assertEquals($profile_data['location_city'], $retrieved_profile->location_city);
    }
    
    /**
     * Test message system functionality
     */
    public function test_messaging_system() {
        $sender_id = $this->test_users[0];
        $recipient_id = $this->test_users[1];
        
        // Test sending message
        $messaging_service = new WPMatch_Messaging_Service();
        $message_id = $messaging_service->send_message(
            $sender_id, 
            $recipient_id, 
            'Hello! How are you today?'
        );
        
        $this->assertIsNumeric($message_id, 'Send message should return message ID');
        $this->assertGreaterThan(0, $message_id, 'Message ID should be positive');
        
        // Verify conversation was created
        $conversation = $messaging_service->get_conversation($sender_id, $recipient_id);
        $this->assertNotNull($conversation, 'Conversation should be created');
        $this->assertIsArray($conversation->messages, 'Conversation should have messages array');
        $this->assertCount(1, $conversation->messages, 'Should have exactly one message');
        
        // Test message content
        $message = $conversation->messages[0];
        $this->assertEquals($sender_id, $message->sender_id, 'Sender ID should match');
        $this->assertEquals($recipient_id, $message->recipient_id, 'Recipient ID should match');
        $this->assertEquals('Hello! How are you today?', $message->message_content);
        $this->assertEquals(0, $message->is_read, 'Message should be unread initially');
        
        // Test marking message as read
        $result = $messaging_service->mark_message_read($message_id, $recipient_id);
        $this->assertTrue($result, 'Should successfully mark message as read');
        
        // Verify message is marked as read
        $updated_conversation = $messaging_service->get_conversation($sender_id, $recipient_id);
        $this->assertEquals(1, $updated_conversation->messages[0]->is_read, 'Message should be marked as read');
    }
    
    /**
     * Test interaction system (likes, passes, matches)
     */
    public function test_interaction_system() {
        $user1_id = $this->test_users[0];
        $user2_id = $this->test_users[1];
        
        $interaction_service = new WPMatch_Interaction_Service();
        
        // Test liking a profile
        $result = $interaction_service->record_interaction($user1_id, $user2_id, 'like');
        $this->assertTrue($result, 'Should successfully record like interaction');
        
        // Verify interaction in database
        $this->assertDatabaseHas(
            $GLOBALS['wpdb']->prefix . 'wpmatch_interactions',
            [
                'user_id' => $user1_id,
                'target_user_id' => $user2_id,
                'interaction_type' => 'like'
            ]
        );
        
        // Test mutual like (creating a match)
        $result = $interaction_service->record_interaction($user2_id, $user1_id, 'like');
        $this->assertTrue($result, 'Should successfully record mutual like');
        
        // Check if match was created
        $is_match = $interaction_service->is_mutual_match($user1_id, $user2_id);
        $this->assertTrue($is_match, 'Should detect mutual match');
        
        // Test getting user's matches
        $matches = $interaction_service->get_user_matches($user1_id);
        $this->assertIsArray($matches, 'Should return array of matches');
        $this->assertCount(1, $matches, 'Should have exactly one match');
        $this->assertEquals($user2_id, $matches[0]->user_id, 'Match should be the correct user');
    }
    
    /**
     * Test photo management
     */
    public function test_photo_management() {
        $user_id = $this->test_users[0];
        
        // Create test photos
        $photo1_id = $this->create_test_photo($user_id, true); // Primary photo
        $photo2_id = $this->create_test_photo($user_id, false); // Secondary photo
        
        $media_service = new WPMatch_Media_Service();
        
        // Test getting user photos
        $photos = $media_service->get_user_photos($user_id);
        $this->assertIsArray($photos, 'Should return array of photos');
        $this->assertCount(2, $photos, 'Should have 2 photos');
        
        // Test primary photo identification
        $primary_photo = $media_service->get_primary_photo($user_id);
        $this->assertNotNull($primary_photo, 'Should have a primary photo');
        $this->assertEquals($photo1_id, $primary_photo->id, 'Primary photo should match');
        
        // Test photo deletion
        $result = $media_service->delete_photo($photo2_id, $user_id);
        $this->assertTrue($result, 'Should successfully delete photo');
        
        // Verify photo is deleted
        $updated_photos = $media_service->get_user_photos($user_id);
        $this->assertCount(1, $updated_photos, 'Should have 1 photo after deletion');
    }
    
    /**
     * Test search functionality
     */
    public function test_profile_search() {
        $user_id = $this->test_users[0];
        wp_set_current_user($user_id);
        
        $search_service = new WPMatch_Search_Service();
        
        // Test basic search
        $search_criteria = [
            'gender' => 'female',
            'min_age' => 18,
            'max_age' => 35,
            'location_radius' => 50
        ];
        
        $results = $search_service->search_profiles($user_id, $search_criteria);
        
        $this->assertIsArray($results, 'Search should return array');
        $this->assertArrayHasKey('profiles', $results, 'Results should have profiles key');
        $this->assertArrayHasKey('total_count', $results, 'Results should have total count');
        
        // Verify search filters
        foreach ($results['profiles'] as $profile) {
            $this->assertEquals('female', $profile->gender, 'All results should match gender filter');
            $this->assertGreaterThanOrEqual(18, $profile->age, 'Age should be >= min_age');
            $this->assertLessThanOrEqual(35, $profile->age, 'Age should be <= max_age');
            $this->assertNotEquals($user_id, $profile->user_id, 'Should not include current user');
        }
    }
}
```

### 4. Performance Testing Suite
```php
<?php
/**
 * Performance testing for WPMatch
 */
class WPMatch_Performance_Test extends WPMatch_Test_Case {
    
    private $performance_thresholds = [
        'profile_search' => 0.5, // 500ms
        'message_send' => 0.2,   // 200ms
        'conversation_load' => 0.3, // 300ms
        'profile_update' => 0.4    // 400ms
    ];
    
    /**
     * Test profile search performance
     */
    public function test_profile_search_performance() {
        // Create additional test data for performance testing
        $this->create_large_dataset();
        
        $user_id = $this->test_users[0];
        $search_service = new WPMatch_Search_Service();
        
        $search_criteria = [
            'gender' => 'female',
            'min_age' => 20,
            'max_age' => 40,
            'location_radius' => 25
        ];
        
        // Measure performance
        $start_time = microtime(true);
        $results = $search_service->search_profiles($user_id, $search_criteria);
        $execution_time = microtime(true) - $start_time;
        
        $this->assertLessThan(
            $this->performance_thresholds['profile_search'],
            $execution_time,
            "Profile search took {$execution_time}s, should be under {$this->performance_thresholds['profile_search']}s"
        );
        
        // Verify results quality
        $this->assertGreaterThan(0, count($results['profiles']), 'Should return some results');
        $this->assertLessThanOrEqual(20, count($results['profiles']), 'Should respect pagination limit');
    }
    
    /**
     * Test message sending performance
     */
    public function test_message_sending_performance() {
        $sender_id = $this->test_users[0];
        $recipient_id = $this->test_users[1];
        $messaging_service = new WPMatch_Messaging_Service();
        
        $start_time = microtime(true);
        $message_id = $messaging_service->send_message(
            $sender_id,
            $recipient_id,
            'Performance test message'
        );
        $execution_time = microtime(true) - $start_time;
        
        $this->assertLessThan(
            $this->performance_thresholds['message_send'],
            $execution_time,
            "Message sending took {$execution_time}s, should be under {$this->performance_thresholds['message_send']}s"
        );
        
        $this->assertIsNumeric($message_id, 'Should return valid message ID');
    }
    
    /**
     * Test batch operations performance
     */
    public function test_batch_operations_performance() {
        $user_ids = array_slice($this->test_users, 0, 5);
        
        // Test batch profile loading
        $start_time = microtime(true);
        $profiles = WPMatch_Query_Optimizer::batch_load_profiles($user_ids);
        $execution_time = microtime(true) - $start_time;
        
        $this->assertLessThan(0.1, $execution_time, 'Batch profile loading should be fast');
        $this->assertEquals(count($user_ids), count($profiles), 'Should load all requested profiles');
    }
    
    /**
     * Test caching effectiveness
     */
    public function test_caching_performance() {
        $user_id = $this->test_users[0];
        $profile_service = new WPMatch_Profile_Service();
        
        // First request (cache miss)
        wp_cache_flush();
        $start_time = microtime(true);
        $profile1 = $profile_service->get_profile($user_id);
        $cache_miss_time = microtime(true) - $start_time;
        
        // Second request (cache hit)
        $start_time = microtime(true);
        $profile2 = $profile_service->get_profile($user_id);
        $cache_hit_time = microtime(true) - $start_time;
        
        // Cache hit should be significantly faster
        $this->assertLessThan($cache_miss_time * 0.5, $cache_hit_time, 
            'Cached request should be at least 50% faster');
        
        // Verify same data
        $this->assertEquals($profile1, $profile2, 'Cached data should match original');
    }
    
    /**
     * Test memory usage
     */
    public function test_memory_usage() {
        $initial_memory = memory_get_usage(true);
        
        // Perform memory-intensive operations
        $user_id = $this->test_users[0];
        $search_service = new WPMatch_Search_Service();
        
        for ($i = 0; $i < 10; $i++) {
            $results = $search_service->search_profiles($user_id, [
                'gender' => 'female',
                'min_age' => 18,
                'max_age' => 50
            ]);
        }
        
        $final_memory = memory_get_usage(true);
        $memory_increase = $final_memory - $initial_memory;
        
        // Memory increase should be reasonable (less than 10MB for 10 searches)
        $this->assertLessThan(10 * 1024 * 1024, $memory_increase, 
            'Memory usage should remain reasonable');
    }
    
    /**
     * Create large dataset for performance testing
     */
    private function create_large_dataset() {
        global $wpdb;
        
        // Create 100 additional test profiles
        for ($i = 1; $i <= 100; $i++) {
            $user_id = $this->factory->user->create([
                'user_login' => "perftest{$i}",
                'user_email' => "perftest{$i}@example.com"
            ]);
            
            $profile_data = [
                'user_id' => $user_id,
                'display_name' => "PerfTest{$i}",
                'age' => rand(18, 60),
                'gender' => $i % 2 ? 'male' : 'female',
                'looking_for' => $i % 2 ? 'female' : 'male',
                'location_city' => 'Test City',
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ];
            
            $wpdb->insert($wpdb->prefix . 'wpmatch_profiles', $profile_data);
        }
    }
}
```

### 5. API Endpoint Testing
```php
<?php
/**
 * API endpoint testing
 */
class WPMatch_API_Test extends WPMatch_Test_Case {
    
    private $api_base_url;
    
    public function setUp(): void {
        parent::setUp();
        $this->api_base_url = rest_url('wpmatch/v1/');
    }
    
    /**
     * Test profile API endpoints
     */
    public function test_profile_api_endpoints() {
        $user_id = $this->test_users[0];
        wp_set_current_user($user_id);
        
        // Test GET profile
        $request = new WP_REST_Request('GET', '/wpmatch/v1/profile');
        $response = rest_do_request($request);
        
        $this->assertEquals(200, $response->get_status(), 'Profile GET should return 200');
        $data = $response->get_data();
        $this->assertArrayHasKey('user_id', $data, 'Response should include user_id');
        
        // Test UPDATE profile
        $update_data = [
            'display_name' => 'Updated Name',
            'about_me' => 'Updated bio',
            'age' => 30
        ];
        
        $request = new WP_REST_Request('POST', '/wpmatch/v1/profile');
        $request->set_body_params($update_data);
        $response = rest_do_request($request);
        
        $this->assertEquals(200, $response->get_status(), 'Profile UPDATE should return 200');
        $data = $response->get_data();
        $this->assertEquals('Updated Name', $data['display_name'], 'Display name should be updated');
    }
    
    /**
     * Test messaging API endpoints
     */
    public function test_messaging_api_endpoints() {
        $sender_id = $this->test_users[0];
        $recipient_id = $this->test_users[1];
        
        wp_set_current_user($sender_id);
        
        // Test sending message
        $request = new WP_REST_Request('POST', '/wpmatch/v1/messages');
        $request->set_body_params([
            'recipient_id' => $recipient_id,
            'message_content' => 'Test API message'
        ]);
        $response = rest_do_request($request);
        
        $this->assertEquals(201, $response->get_status(), 'Send message should return 201');
        $data = $response->get_data();
        $this->assertArrayHasKey('message_id', $data, 'Response should include message_id');
        
        // Test getting conversations
        $request = new WP_REST_Request('GET', '/wpmatch/v1/conversations');
        $response = rest_do_request($request);
        
        $this->assertEquals(200, $response->get_status(), 'Get conversations should return 200');
        $data = $response->get_data();
        $this->assertIsArray($data, 'Conversations should be an array');
        $this->assertCount(1, $data, 'Should have one conversation');
    }
    
    /**
     * Test search API endpoints
     */
    public function test_search_api_endpoints() {
        $user_id = $this->test_users[0];
        wp_set_current_user($user_id);
        
        $request = new WP_REST_Request('GET', '/wpmatch/v1/search');
        $request->set_query_params([
            'gender' => 'female',
            'min_age' => 18,
            'max_age' => 35,
            'page' => 1,
            'per_page' => 10
        ]);
        $response = rest_do_request($request);
        
        $this->assertEquals(200, $response->get_status(), 'Search should return 200');
        $data = $response->get_data();
        $this->assertArrayHasKey('profiles', $data, 'Response should include profiles');
        $this->assertArrayHasKey('total_count', $data, 'Response should include total_count');
        $this->assertArrayHasKey('page', $data, 'Response should include page info');
    }
    
    /**
     * Test API authentication and authorization
     */
    public function test_api_authentication() {
        // Test unauthenticated request
        wp_set_current_user(0);
        
        $request = new WP_REST_Request('GET', '/wpmatch/v1/profile');
        $response = rest_do_request($request);
        
        $this->assertEquals(401, $response->get_status(), 'Unauthenticated request should return 401');
        
        // Test authenticated but unauthorized request
        $user_id = $this->test_users[0];
        wp_set_current_user($user_id);
        
        $request = new WP_REST_Request('GET', '/wpmatch/v1/admin/users');
        $response = rest_do_request($request);
        
        $this->assertEquals(403, $response->get_status(), 'Unauthorized request should return 403');
    }
    
    /**
     * Test API rate limiting
     */
    public function test_api_rate_limiting() {
        $user_id = $this->test_users[0];
        wp_set_current_user($user_id);
        
        // Make many rapid requests
        $success_count = 0;
        $rate_limited_count = 0;
        
        for ($i = 0; $i < 60; $i++) {
            $request = new WP_REST_Request('GET', '/wpmatch/v1/profile');
            $response = rest_do_request($request);
            
            if ($response->get_status() === 200) {
                $success_count++;
            } elseif ($response->get_status() === 429) {
                $rate_limited_count++;
            }
        }
        
        $this->assertGreaterThan(0, $rate_limited_count, 'Some requests should be rate limited');
        $this->assertLessThan(60, $success_count, 'Not all requests should succeed');
    }
}
```

## Automated Testing Pipeline

### 1. GitHub Actions Workflow
```yaml
name: WPMatch Quality Assurance Pipeline

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
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
      
      redis:
        image: redis:6-alpine
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    strategy:
      matrix:
        php-version: ['7.4', '8.0', '8.1', '8.2']
        wordpress-version: ['5.9', '6.0', '6.1', '6.2', '6.3', '6.4']

    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mysqli, zip, gd, redis
        coverage: xdebug
        
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
        
    - name: Install WordPress Test Suite
      run: |
        bash bin/install-wp-tests.sh wordpress_test root password 127.0.0.1 ${{ matrix.wordpress-version }}
        
    - name: Install PHP Dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
      
    - name: Install Node Dependencies
      run: npm ci
      
    - name: Build Assets
      run: npm run build
      
    - name: Setup Test Environment
      run: |
        cp tests/config/wp-tests-config.php /tmp/wordpress-tests-lib/wp-tests-config.php
        
    - name: Run Code Style Checks
      run: |
        vendor/bin/phpcs --standard=WordPress --extensions=php --ignore=*/vendor/*,*/node_modules/* .
        vendor/bin/eslint assets/js/
        
    - name: Run Security Scans
      run: |
        vendor/bin/psalm --show-info=true
        npm audit --audit-level moderate
        
    - name: Run Unit Tests
      run: |
        vendor/bin/phpunit --testsuite=unit --coverage-clover=coverage/unit-coverage.xml
        
    - name: Run Integration Tests
      run: |
        vendor/bin/phpunit --testsuite=integration --coverage-clover=coverage/integration-coverage.xml
        
    - name: Run Security Tests
      run: |
        vendor/bin/phpunit --testsuite=security --coverage-clover=coverage/security-coverage.xml
        
    - name: Run Performance Tests
      run: |
        vendor/bin/phpunit --testsuite=performance
        
    - name: Run API Tests
      run: |
        vendor/bin/phpunit --testsuite=api --coverage-clover=coverage/api-coverage.xml
        
    - name: Generate Coverage Report
      run: |
        vendor/bin/phpcov merge --clover coverage/combined-coverage.xml coverage/
        
    - name: Upload Coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: coverage/combined-coverage.xml
        fail_ci_if_error: true
        
    - name: Run Load Tests
      if: matrix.php-version == '8.2' && matrix.wordpress-version == '6.4'
      run: |
        npm run test:load
        
    - name: Archive Test Results
      if: failure()
      uses: actions/upload-artifact@v3
      with:
        name: test-results-${{ matrix.php-version }}-${{ matrix.wordpress-version }}
        path: |
          tests/logs/
          coverage/
```

### 2. Test Configuration Files

#### PHPUnit Configuration
```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    backupGlobals="false"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    processIsolation="false"
    stopOnFailure="false">
    
    <testsuites>
        <testsuite name="unit">
            <directory>tests/unit/</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/integration/</directory>
        </testsuite>
        <testsuite name="security">
            <directory>tests/security/</directory>
        </testsuite>
        <testsuite name="performance">
            <directory>tests/performance/</directory>
        </testsuite>
        <testsuite name="api">
            <directory>tests/api/</directory>
        </testsuite>
    </testsuites>
    
    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">includes/</directory>
            <directory suffix=".php">admin/</directory>
            <directory suffix=".php">public/</directory>
            <exclude>
                <directory>tests/</directory>
                <directory>vendor/</directory>
                <directory>node_modules/</directory>
            </exclude>
        </whitelist>
    </filter>
    
    <logging>
        <log type="coverage-html" target="coverage/html"/>
        <log type="coverage-clover" target="coverage/clover.xml"/>
        <log type="junit" target="coverage/junit.xml"/>
    </logging>
    
    <php>
        <const name="WP_TESTS_DOMAIN" value="example.org"/>
        <const name="WP_TESTS_EMAIL" value="admin@example.org"/>
        <const name="WP_TESTS_TITLE" value="Test Blog"/>
        <const name="WP_PHP_BINARY" value="/usr/bin/php"/>
    </php>
</phpunit>
```

## Quality Metrics and Reporting

### 1. Coverage Analysis
```php
/**
 * Generate detailed coverage reports
 */
class WPMatch_Coverage_Reporter {
    
    public static function generate_report() {
        $coverage_data = [
            'overall_coverage' => self::calculate_overall_coverage(),
            'file_coverage' => self::get_file_coverage(),
            'critical_path_coverage' => self::get_critical_path_coverage(),
            'security_coverage' => self::get_security_coverage(),
            'uncovered_lines' => self::get_uncovered_critical_lines()
        ];
        
        return $coverage_data;
    }
    
    private static function calculate_overall_coverage() {
        // Parse coverage XML and calculate percentages
        $coverage_file = 'coverage/combined-coverage.xml';
        if (!file_exists($coverage_file)) {
            return 0;
        }
        
        $xml = simplexml_load_file($coverage_file);
        $metrics = $xml->project->metrics;
        
        $covered_lines = (int) $metrics['coveredstatements'];
        $total_lines = (int) $metrics['statements'];
        
        return $total_lines > 0 ? ($covered_lines / $total_lines) * 100 : 0;
    }
}
```

### 2. Performance Benchmarks
```php
/**
 * Performance benchmark tracking
 */
class WPMatch_Performance_Tracker {
    
    private static $benchmarks = [];
    
    public static function start_benchmark($name) {
        self::$benchmarks[$name] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];
    }
    
    public static function end_benchmark($name) {
        if (!isset(self::$benchmarks[$name])) {
            return false;
        }
        
        $benchmark = self::$benchmarks[$name];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        return [
            'execution_time' => $end_time - $benchmark['start_time'],
            'memory_usage' => $end_memory - $benchmark['start_memory'],
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}
```

## Expected Testing Outcomes

### Coverage Targets Achievement
- **Unit Test Coverage**: 85%+
- **Integration Test Coverage**: 80%+
- **Security Test Coverage**: 100%
- **API Test Coverage**: 95%+
- **Overall Coverage**: 82%+

### Performance Benchmarks
- **Profile Search**: < 500ms (95th percentile)
- **Message Operations**: < 200ms average
- **API Response Times**: < 300ms average
- **Database Query Performance**: 90% under 100ms

### Quality Improvements
- **Bug Detection**: 95% of bugs caught before production
- **Security Vulnerability Prevention**: 100% of common vulnerabilities tested
- **Regression Prevention**: 98% effectiveness
- **Code Quality Score**: +15 points improvement

This comprehensive testing framework ensures WPMatch maintains high quality, security, and performance standards while facilitating rapid development and deployment cycles.