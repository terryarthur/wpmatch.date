<?php
/**
 * Basic Integration Test for WPMatch Profile Fields
 * 
 * This script tests the basic functionality of the profile fields system
 * 
 * @package WPMatch
 * @since 1.0.0
 */

// This would be run in WordPress admin or via WP-CLI
// For demonstration purposes only

if (!defined('ABSPATH')) {
    // For testing outside WordPress, we'd need to bootstrap WP
    // require_once('../../../wp-config.php');
    echo "This script should be run within WordPress environment\n";
    exit;
}

/**
 * Test Profile Fields Integration
 */
function test_wpmatch_profile_fields_integration() {
    echo "<h2>WPMatch Profile Fields Integration Test</h2>\n";
    
    $errors = array();
    $success = array();
    
    // Test 1: Check if database tables exist
    echo "<h3>1. Database Tables Test</h3>\n";
    try {
        $database = new WPMatch_Database();
        $tables = $database->get_all_tables();
        
        foreach ($tables as $table_key => $table_name) {
            global $wpdb;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            
            if ($table_exists) {
                $success[] = "Table {$table_name} exists";
            } else {
                $errors[] = "Table {$table_name} missing";
            }
        }
    } catch (Exception $e) {
        $errors[] = "Database test failed: " . $e->getMessage();
    }
    
    // Test 2: Check if classes can be instantiated
    echo "<h3>2. Class Instantiation Test</h3>\n";
    try {
        $field_manager = new WPMatch_Profile_Field_Manager();
        $success[] = "WPMatch_Profile_Field_Manager instantiated";
        
        $type_registry = new WPMatch_Field_Type_Registry();
        $success[] = "WPMatch_Field_Type_Registry instantiated";
        
        $validator = new WPMatch_Field_Validator();
        $success[] = "WPMatch_Field_Validator instantiated";
        
        $field_groups_manager = new WPMatch_Field_Groups_Manager();
        $success[] = "WPMatch_Field_Groups_Manager instantiated";
        
    } catch (Exception $e) {
        $errors[] = "Class instantiation failed: " . $e->getMessage();
    }
    
    // Test 3: Check if default field types are registered
    echo "<h3>3. Field Types Registration Test</h3>\n";
    try {
        $type_registry = new WPMatch_Field_Type_Registry();
        $field_types = $type_registry->get_field_types();
        
        $expected_types = array('text', 'textarea', 'select', 'checkbox', 'number', 'date', 'email');
        
        foreach ($expected_types as $type) {
            if (isset($field_types[$type])) {
                $success[] = "Field type '{$type}' registered";
            } else {
                $errors[] = "Field type '{$type}' not registered";
            }
        }
    } catch (Exception $e) {
        $errors[] = "Field types test failed: " . $e->getMessage();
    }
    
    // Test 4: Test field validation
    echo "<h3>4. Field Validation Test</h3>\n";
    try {
        $validator = new WPMatch_Field_Validator();
        
        // Test valid field data
        $valid_field = array(
            'field_name' => 'test_field',
            'field_label' => 'Test Field',
            'field_type' => 'text',
            'field_group' => 'basic',
            'status' => 'active'
        );
        
        $validation_result = $validator->validate_field_data($valid_field);
        if ($validation_result === true) {
            $success[] = "Valid field data passed validation";
        } else {
            $errors[] = "Valid field data failed validation: " . $validation_result->get_error_message();
        }
        
        // Test invalid field data
        $invalid_field = array(
            'field_name' => 'Invalid Field Name!', // Invalid characters
            'field_label' => '',                   // Empty label
            'field_type' => 'invalid_type',        // Invalid type
        );
        
        $validation_result = $validator->validate_field_data($invalid_field);
        if (is_wp_error($validation_result)) {
            $success[] = "Invalid field data correctly rejected";
        } else {
            $errors[] = "Invalid field data incorrectly accepted";
        }
        
    } catch (Exception $e) {
        $errors[] = "Field validation test failed: " . $e->getMessage();
    }
    
    // Test 5: Test CRUD operations (if user has permissions)
    echo "<h3>5. CRUD Operations Test</h3>\n";
    if (current_user_can('manage_profile_fields')) {
        try {
            $field_manager = new WPMatch_Profile_Field_Manager();
            
            // Test field creation
            $test_field_data = array(
                'field_name' => 'test_integration_field',
                'field_label' => 'Test Integration Field',
                'field_type' => 'text',
                'field_description' => 'This is a test field for integration testing',
                'field_group' => 'basic',
                'is_required' => false,
                'is_public' => true,
                'status' => 'active'
            );
            
            $field_id = $field_manager->create_field($test_field_data);
            if (!is_wp_error($field_id)) {
                $success[] = "Test field created with ID: {$field_id}";
                
                // Test field retrieval
                $retrieved_field = $field_manager->get_field($field_id);
                if ($retrieved_field && $retrieved_field->field_name === 'test_integration_field') {
                    $success[] = "Test field retrieved successfully";
                    
                    // Test field update
                    $update_result = $field_manager->update_field($field_id, array(
                        'field_label' => 'Updated Test Field'
                    ));
                    
                    if (!is_wp_error($update_result)) {
                        $success[] = "Test field updated successfully";
                    } else {
                        $errors[] = "Field update failed: " . $update_result->get_error_message();
                    }
                    
                    // Clean up - delete test field
                    $delete_result = $field_manager->delete_field($field_id);
                    if (!is_wp_error($delete_result)) {
                        $success[] = "Test field deleted successfully";
                    } else {
                        $errors[] = "Field deletion failed: " . $delete_result->get_error_message();
                    }
                    
                } else {
                    $errors[] = "Failed to retrieve test field";
                }
            } else {
                $errors[] = "Field creation failed: " . $field_id->get_error_message();
            }
            
        } catch (Exception $e) {
            $errors[] = "CRUD operations test failed: " . $e->getMessage();
        }
    } else {
        $errors[] = "Cannot test CRUD operations - insufficient permissions";
    }
    
    // Test 6: Check admin menu integration
    echo "<h3>6. Admin Menu Integration Test</h3>\n";
    if (is_admin()) {
        global $_wp_submenu_nopriv;
        $menu_exists = false;
        
        // Check if our admin page exists
        if (class_exists('WPMatch_Profile_Fields_Admin')) {
            $success[] = "Profile Fields Admin class exists";
            
            // Check if menu callback is registered
            global $admin_page_hooks;
            if (isset($admin_page_hooks['wpmatch'])) {
                $success[] = "WPMatch admin menu registered";
            } else {
                $errors[] = "WPMatch admin menu not registered";
            }
        } else {
            $errors[] = "Profile Fields Admin class not found";
        }
    } else {
        $success[] = "Admin menu test skipped (not in admin context)";
    }
    
    // Display results
    echo "<h3>Test Results</h3>\n";
    
    if (!empty($success)) {
        echo "<div style='color: green;'><h4>✓ Successful Tests:</h4><ul>\n";
        foreach ($success as $message) {
            echo "<li>{$message}</li>\n";
        }
        echo "</ul></div>\n";
    }
    
    if (!empty($errors)) {
        echo "<div style='color: red;'><h4>✗ Failed Tests:</h4><ul>\n";
        foreach ($errors as $message) {
            echo "<li>{$message}</li>\n";
        }
        echo "</ul></div>\n";
    }
    
    $total_tests = count($success) + count($errors);
    $success_rate = $total_tests > 0 ? round((count($success) / $total_tests) * 100, 2) : 0;
    
    echo "<div style='margin-top: 20px; padding: 10px; border: 1px solid #ccc;'>\n";
    echo "<strong>Summary:</strong><br>\n";
    echo "Total Tests: {$total_tests}<br>\n";
    echo "Successful: " . count($success) . "<br>\n";
    echo "Failed: " . count($errors) . "<br>\n";
    echo "Success Rate: {$success_rate}%<br>\n";
    echo "</div>\n";
    
    return array(
        'success' => $success,
        'errors' => $errors,
        'success_rate' => $success_rate
    );
}

// Run the test if called directly
if (defined('WP_CLI') && WP_CLI) {
    // WP-CLI command
    WP_CLI::add_command('wpmatch test-integration', 'test_wpmatch_profile_fields_integration');
} elseif (isset($_GET['wpmatch_test']) && current_user_can('manage_options')) {
    // Web interface (for admin users only)
    test_wpmatch_profile_fields_integration();
}