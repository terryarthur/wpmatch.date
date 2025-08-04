<?php
/**
 * Test WPMatch dating field types
 */

if (!defined('ABSPATH')) {
    exit;
}

// Test function to verify field types
function test_wpmatch_field_types() {
    if (class_exists('WPMatch_Field_Type_Registry')) {
        $registry = new WPMatch_Field_Type_Registry();
        $field_types = $registry->get_field_types();
        
        $dating_types = array('age_range', 'height', 'weight', 'relationship_status', 
                             'looking_for', 'gender', 'interests', 'location', 
                             'education', 'profession', 'zodiac', 'lifestyle');
        
        $count = 0;
        foreach ($dating_types as $type) {
            if (isset($field_types[$type])) {
                $count++;
            }
        }
        
        return array(
            'total_types' => count($field_types),
            'dating_types' => $count,
            'success' => $count >= 12
        );
    }
    
    return array('error' => 'Registry not found');
}
?>
