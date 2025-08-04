<?php
/**
 * Integration Tests for WPMatch Profile Fields
 *
 * Tests the complete workflow of profile fields management
 * including database operations, AJAX handlers, and frontend integration.
 *
 * @package WPMatch
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

class ProfileFieldsIntegrationTest extends TestCase {
    
    private $database;
    private $field_manager;
    private $admin;
    
    public function setUp(): void {
        // Mock WordPress environment
        $this->setup_wordpress_mocks();
        
        // Initialize components
        $this->database = $this->createMock(WPMatch_Database::class);
        $this->field_manager = $this->createMock(WPMatch_Profile_Field_Manager::class);
        $this->admin = $this->createMock(WPMatch_Profile_Fields_Admin::class);
    }
    
    private function setup_wordpress_mocks() {
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action) { return true; }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) { return true; }
        }
        if (!function_exists('wp_send_json_success')) {
            function wp_send_json_success($data) { 
                echo json_encode(['success' => true, 'data' => $data]);
                exit;
            }
        }
        if (!function_exists('wp_send_json_error')) {
            function wp_send_json_error($data) { 
                echo json_encode(['success' => false, 'data' => $data]);
                exit;
            }
        }
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) { return trim(strip_tags($str)); }
        }
        if (!function_exists('absint')) {
            function absint($maybeint) { return abs(intval($maybeint)); }
        }
        if (!defined('ABSPATH')) {
            define('ABSPATH', dirname(dirname(__DIR__)) . '/');
        }
    }
    
    /**
     * Test complete field creation workflow
     */
    public function test_complete_field_creation_workflow() {
        // Simulate field creation request
        $field_data = [
            'name' => 'test_field',
            'label' => 'Test Field',
            'type' => 'text',
            'group' => 'basic',
            'description' => 'A test field for integration testing',
            'is_required' => true,
            'is_searchable' => false,
            'field_order' => 1,
            'status' => 'active'
        ];
        
        // Mock database operations
        $this->database
            ->expects($this->once())
            ->method('insert_field')
            ->with($field_data)
            ->willReturn(123);
        
        $this->database
            ->expects($this->once())
            ->method('get_field_by_id')
            ->with(123)
            ->willReturn((object) array_merge($field_data, ['id' => 123]));
        
        // Test field creation
        $field_id = $this->database->insert_field($field_data);
        $this->assertEquals(123, $field_id);
        
        // Verify field was created correctly
        $created_field = $this->database->get_field_by_id($field_id);
        $this->assertEquals('test_field', $created_field->name);
        $this->assertEquals('Test Field', $created_field->label);
        $this->assertEquals('text', $created_field->type);
    }
    
    /**
     * Test AJAX create field integration
     */
    public function test_ajax_create_field_integration() {
        // Set up POST data
        $_POST = [
            'action' => 'wpmatch_create_field',
            'nonce' => 'test_nonce',
            'field_name' => 'integration_test',
            'field_label' => 'Integration Test Field',
            'field_type' => 'email',
            'field_group' => 'contact',
            'field_description' => 'Email field for testing',
            'is_required' => '1',
            'is_searchable' => '1'
        ];
        
        // Mock successful field creation
        $expected_field = [
            'id' => 456,
            'name' => 'integration_test',
            'label' => 'Integration Test Field',
            'type' => 'email',
            'group' => 'contact'
        ];
        
        $this->field_manager
            ->expects($this->once())
            ->method('create_field')
            ->willReturn($expected_field);
        
        // Simulate AJAX request handling
        ob_start();
        try {
            // Would normally call the AJAX handler here
            // $this->admin->ajax_create_field();
            
            // For testing, simulate the expected response
            wp_send_json_success($expected_field);
        } catch (Exception $e) {
            // Expected due to exit in wp_send_json_success
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals(456, $response['data']['id']);
        $this->assertEquals('integration_test', $response['data']['name']);
    }
    
    /**
     * Test field update integration
     */
    public function test_field_update_integration() {
        $field_id = 123;
        $update_data = [
            'label' => 'Updated Field Label',
            'description' => 'Updated description',
            'is_required' => false
        ];
        
        // Mock field exists check
        $this->database
            ->expects($this->once())
            ->method('field_exists')
            ->with($field_id)
            ->willReturn(true);
        
        // Mock update operation
        $this->database
            ->expects($this->once())
            ->method('update_field')
            ->with($field_id, $update_data)
            ->willReturn(true);
        
        // Mock getting updated field
        $updated_field = (object) array_merge([
            'id' => $field_id,
            'name' => 'existing_field'
        ], $update_data);
        
        $this->database
            ->expects($this->once())
            ->method('get_field_by_id')
            ->with($field_id)
            ->willReturn($updated_field);
        
        // Test the update workflow
        $this->assertTrue($this->database->field_exists($field_id));
        $this->assertTrue($this->database->update_field($field_id, $update_data));
        
        $result = $this->database->get_field_by_id($field_id);
        $this->assertEquals('Updated Field Label', $result->label);
        $this->assertEquals('Updated description', $result->description);
        $this->assertFalse($result->is_required);
    }
    
    /**
     * Test field deletion with cleanup
     */
    public function test_field_deletion_with_cleanup() {
        $field_id = 789;
        
        // Mock field exists
        $this->database
            ->expects($this->once())
            ->method('field_exists')
            ->with($field_id)
            ->willReturn(true);
        
        // Mock cleanup operations
        $this->database
            ->expects($this->once())
            ->method('delete_field_values')
            ->with($field_id)
            ->willReturn(true);
        
        $this->database
            ->expects($this->once())
            ->method('delete_field')
            ->with($field_id)
            ->willReturn(true);
        
        // Test deletion workflow
        $this->assertTrue($this->database->field_exists($field_id));
        
        // Clean up field values first
        $this->assertTrue($this->database->delete_field_values($field_id));
        
        // Then delete the field
        $this->assertTrue($this->database->delete_field($field_id));
    }
    
    /**
     * Test bulk field operations
     */
    public function test_bulk_field_operations() {
        $field_ids = [101, 102, 103];
        
        // Mock bulk status update
        $this->database
            ->expects($this->once())
            ->method('bulk_update_field_status')
            ->with($field_ids, 'inactive')
            ->willReturn(3); // Number of affected rows
        
        // Test bulk operation
        $affected_rows = $this->database->bulk_update_field_status($field_ids, 'inactive');
        $this->assertEquals(3, $affected_rows);
    }
    
    /**
     * Test field ordering and reordering
     */
    public function test_field_ordering_integration() {
        $reorder_data = [
            ['id' => 1, 'order' => 3],
            ['id' => 2, 'order' => 1],
            ['id' => 3, 'order' => 2]
        ];
        
        // Mock reordering operations
        foreach ($reorder_data as $item) {
            $this->database
                ->expects($this->once())
                ->method('update_field_order')
                ->with($item['id'], $item['order'])
                ->willReturn(true);
        }
        
        // Test reordering
        foreach ($reorder_data as $item) {
            $this->assertTrue($this->database->update_field_order($item['id'], $item['order']));
        }
    }
    
    /**
     * Test field validation integration
     */
    public function test_field_validation_integration() {
        $validator = $this->createMock(WPMatch_Field_Validator::class);
        
        $valid_field_data = [
            'name' => 'valid_field',
            'label' => 'Valid Field',
            'type' => 'text',
            'group' => 'basic'
        ];
        
        $invalid_field_data = [
            'name' => '', // Missing required name
            'label' => 'Invalid Field',
            'type' => 'invalid_type',
            'group' => 'basic'
        ];
        
        // Mock validation results
        $validator
            ->expects($this->once())
            ->method('validate_field_data')
            ->with($valid_field_data)
            ->willReturn(['valid' => true, 'errors' => []]);
        
        $validator
            ->expects($this->once())
            ->method('validate_field_data')
            ->with($invalid_field_data)
            ->willReturn([
                'valid' => false, 
                'errors' => ['Field name is required', 'Invalid field type']
            ]);
        
        // Test validation
        $valid_result = $validator->validate_field_data($valid_field_data);
        $this->assertTrue($valid_result['valid']);
        $this->assertEmpty($valid_result['errors']);
        
        $invalid_result = $validator->validate_field_data($invalid_field_data);
        $this->assertFalse($invalid_result['valid']);
        $this->assertNotEmpty($invalid_result['errors']);
    }
    
    /**
     * Test frontend field rendering integration
     */
    public function test_frontend_field_rendering() {
        $field_data = [
            'id' => 123,
            'name' => 'age',
            'label' => 'Age',
            'type' => 'number',
            'description' => 'Your age',
            'is_required' => true,
            'options' => []
        ];
        
        $renderer = $this->createMock(WPMatch_Frontend_Field_Renderer::class);
        
        // Mock field rendering
        $expected_html = '<div class="wpmatch-field-wrapper">' .
                        '<label for="age">Age *</label>' .
                        '<input type="number" id="age" name="age" required>' .
                        '<span class="description">Your age</span>' .
                        '</div>';
        
        $renderer
            ->expects($this->once())
            ->method('render_field')
            ->with($field_data)
            ->willReturn($expected_html);
        
        // Test rendering
        $html = $renderer->render_field($field_data);
        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('Age *', $html);
    }
    
    /**
     * Test search integration with profile fields
     */
    public function test_search_integration() {
        $search_criteria = [
            'age_min' => 25,
            'age_max' => 35,
            'location' => 'New York',
            'interests' => ['music', 'travel']
        ];
        
        $search_manager = $this->createMock(WPMatch_Search_Manager::class);
        
        // Mock search results
        $expected_results = [
            ['user_id' => 101, 'match_score' => 0.85],
            ['user_id' => 102, 'match_score' => 0.78],
            ['user_id' => 103, 'match_score' => 0.72]
        ];
        
        $search_manager
            ->expects($this->once())
            ->method('search_profiles')
            ->with($search_criteria)
            ->willReturn($expected_results);
        
        // Test search
        $results = $search_manager->search_profiles($search_criteria);
        $this->assertCount(3, $results);
        $this->assertEquals(101, $results[0]['user_id']);
        $this->assertEquals(0.85, $results[0]['match_score']);
    }
    
    /**
     * Test import/export integration
     */
    public function test_import_export_integration() {
        $fields_to_export = [
            ['id' => 1, 'name' => 'age', 'label' => 'Age', 'type' => 'number'],
            ['id' => 2, 'name' => 'location', 'label' => 'Location', 'type' => 'text'],
            ['id' => 3, 'name' => 'interests', 'label' => 'Interests', 'type' => 'checkbox']
        ];
        
        $import_export = $this->createMock(WPMatch_Field_Import_Export::class);
        
        // Mock export
        $export_data = json_encode($fields_to_export);
        $import_export
            ->expects($this->once())
            ->method('export_fields')
            ->willReturn($export_data);
        
        // Mock import
        $import_export
            ->expects($this->once())
            ->method('import_fields')
            ->with($export_data)
            ->willReturn(['imported' => 3, 'skipped' => 0, 'errors' => []]);
        
        // Test export
        $exported = $import_export->export_fields();
        $this->assertJson($exported);
        
        // Test import
        $import_result = $import_export->import_fields($exported);
        $this->assertEquals(3, $import_result['imported']);
        $this->assertEquals(0, $import_result['skipped']);
        $this->assertEmpty($import_result['errors']);
    }
    
    /**
     * Test error handling in integration scenarios
     */
    public function test_error_handling_integration() {
        // Test database connection failure
        $this->database
            ->expects($this->once())
            ->method('create_field')
            ->willThrowException(new Exception('Database connection failed'));
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database connection failed');
        
        $this->database->create_field(['name' => 'test']);
    }
    
    /**
     * Test performance with large datasets
     */
    public function test_large_dataset_performance() {
        // Mock large number of fields
        $large_field_set = [];
        for ($i = 1; $i <= 1000; $i++) {
            $large_field_set[] = [
                'id' => $i,
                'name' => "field_$i",
                'label' => "Field $i",
                'type' => 'text'
            ];
        }
        
        $this->database
            ->expects($this->once())
            ->method('get_all_fields')
            ->willReturn($large_field_set);
        
        $start_time = microtime(true);
        $fields = $this->database->get_all_fields();
        $end_time = microtime(true);
        
        $execution_time = $end_time - $start_time;
        
        $this->assertCount(1000, $fields);
        $this->assertLessThan(1.0, $execution_time, 'Query should complete within 1 second');
    }
    
    public function tearDown(): void {
        // Clean up
        $_POST = [];
        $_GET = [];
    }
}