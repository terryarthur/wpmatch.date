<?php
/**
 * Default Dating Fields class for creating standard dating profile fields
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Default Dating Fields class
 * 
 * Creates standard dating profile fields using the new field types
 */
class WPMatch_Default_Dating_Fields {

    /**
     * Profile field manager instance
     *
     * @var WPMatch_Profile_Field_Manager
     */
    private $field_manager;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wpmatch_create_default_fields', array($this, 'create_default_fields'));
        add_action('init', array($this, 'init'), 20);
    }

    /**
     * Initialize
     */
    public function init() {
        if (class_exists('WPMatch_Profile_Field_Manager')) {
            $this->field_manager = new WPMatch_Profile_Field_Manager();
        }
    }

    /**
     * Create default dating profile fields
     */
    public function create_default_fields() {
        if (!$this->field_manager) {
            return;
        }

        $default_fields = $this->get_default_field_definitions();

        foreach ($default_fields as $field_data) {
            // Check if field already exists
            if (!$this->field_exists($field_data['field_name'])) {
                $result = $this->field_manager->create_field($field_data);
                
                if (is_wp_error($result)) {
                    error_log('WPMatch: Failed to create default field ' . $field_data['field_name'] . ': ' . $result->get_error_message());
                }
            }
        }
    }

    /**
     * Get default field definitions for dating profiles
     *
     * @return array Array of field definitions
     */
    private function get_default_field_definitions() {
        return array(
            // Basic Demographics
            array(
                'field_name' => 'gender',
                'field_label' => __('Gender', 'wpmatch'),
                'field_type' => 'gender',
                'field_description' => __('Your gender identity', 'wpmatch'),
                'field_group' => 'basic',
                'is_required' => true,
                'is_searchable' => true,
                'is_public' => true,
                'field_options' => array(
                    'choices' => array(
                        'male' => __('Male', 'wpmatch'),
                        'female' => __('Female', 'wpmatch'),
                        'non_binary' => __('Non-binary', 'wpmatch'),
                        'other' => __('Other', 'wpmatch'),
                        'prefer_not_to_say' => __('Prefer not to say', 'wpmatch')
                    ),
                    'allow_custom' => true
                )
            ),
            array(
                'field_name' => 'age_range_seeking',
                'field_label' => __('Age Range Seeking', 'wpmatch'),
                'field_type' => 'age_range',
                'field_description' => __('Age range you are looking for in a partner', 'wpmatch'),
                'field_group' => 'preferences',
                'is_required' => false,
                'is_searchable' => true,
                'is_public' => true,
                'field_options' => array(
                    'min_age' => 18,
                    'max_age' => 99,
                    'display_format' => 'range'
                )
            ),
            array(
                'field_name' => 'height',
                'field_label' => __('Height', 'wpmatch'),
                'field_type' => 'height',
                'field_description' => __('Your height', 'wpmatch'),
                'field_group' => 'basic',
                'is_required' => false,
                'is_searchable' => true,
                'is_public' => true,
                'field_options' => array(
                    'units' => 'both',
                    'metric_format' => 'cm',
                    'imperial_format' => 'feet_inches'
                )
            ),
            array(
                'field_name' => 'location',
                'field_label' => __('Location', 'wpmatch'),
                'field_type' => 'location',
                'field_description' => __('Your location for matching purposes', 'wpmatch'),
                'field_group' => 'basic',
                'is_required' => true,
                'is_searchable' => true,
                'is_public' => true,
                'field_options' => array(
                    'location_type' => 'city_state',
                    'show_distance' => true,
                    'privacy_levels' => array(
                        'exact' => __('Exact Location', 'wpmatch'),
                        'city' => __('City Only', 'wpmatch'),
                        'region' => __('Region/State Only', 'wpmatch'),
                        'country' => __('Country Only', 'wpmatch'),
                        'hidden' => __('Hidden', 'wpmatch')
                    ),
                    'default_privacy' => 'city',
                    'enable_map' => true
                )
            ),
            array(
                'field_name' => 'relationship_status',
                'field_label' => __('Relationship Status', 'wpmatch'),
                'field_type' => 'relationship_status',
                'field_description' => __('Your current relationship status', 'wpmatch'),
                'field_group' => 'relationship',
                'is_required' => true,
                'is_searchable' => true,
                'is_public' => true,
                'field_options' => array(
                    'choices' => array(
                        'single' => __('Single', 'wpmatch'),
                        'divorced' => __('Divorced', 'wpmatch'),
                        'widowed' => __('Widowed', 'wpmatch'),
                        'separated' => __('Separated', 'wpmatch'),
                        'in_relationship' => __('In a Relationship', 'wpmatch'),
                        'its_complicated' => __('It\'s Complicated', 'wpmatch')
                    )
                )
            ),
            array(
                'field_name' => 'looking_for',
                'field_label' => __('Looking For', 'wpmatch'),
                'field_type' => 'looking_for',
                'field_description' => __('What you are seeking in a relationship', 'wpmatch'),
                'field_group' => 'preferences',
                'is_required' => true,
                'is_searchable' => true,
                'is_public' => true,
                'field_options' => array(
                    'choices' => array(
                        'serious_relationship' => __('Serious Relationship', 'wpmatch'),
                        'casual_dating' => __('Casual Dating', 'wpmatch'),
                        'friendship' => __('Friendship', 'wpmatch'),
                        'activity_partner' => __('Activity Partner', 'wpmatch'),
                        'marriage' => __('Marriage', 'wpmatch'),
                        'fun' => __('Just for Fun', 'wpmatch')
                    ),
                    'allow_multiple' => true,
                    'max_selections' => 3
                )
            ),
            array(
                'field_name' => 'about_me',
                'field_label' => __('About Me', 'wpmatch'),
                'field_type' => 'textarea',
                'field_description' => __('Tell others about yourself', 'wpmatch'),
                'placeholder_text' => __('Share a bit about your personality, interests, and what makes you unique...', 'wpmatch'),
                'field_group' => 'about',
                'is_required' => false,
                'is_searchable' => true,
                'is_public' => true,
                'field_options' => array(
                    'rows' => 6,
                    'maxlength' => 1000
                ),
                'validation_rules' => array(
                    'max_length' => 1000
                )
            ),
            array(
                'field_name' => 'interests',
                'field_label' => __('Interests & Hobbies', 'wpmatch'),
                'field_type' => 'interests',
                'field_description' => __('Select your interests and hobbies', 'wpmatch'),
                'field_group' => 'about',
                'is_required' => false,
                'is_searchable' => true,
                'is_public' => true,
                'field_options' => array(
                    'choices' => array(
                        'sports' => __('Sports', 'wpmatch'),
                        'music' => __('Music', 'wpmatch'),
                        'movies' => __('Movies', 'wpmatch'),
                        'reading' => __('Reading', 'wpmatch'),
                        'travel' => __('Travel', 'wpmatch'),
                        'cooking' => __('Cooking', 'wpmatch'),
                        'art' => __('Art', 'wpmatch'),
                        'technology' => __('Technology', 'wpmatch'),
                        'fitness' => __('Fitness', 'wpmatch'),
                        'nature' => __('Nature', 'wpmatch'),
                        'gaming' => __('Gaming', 'wpmatch'),
                        'photography' => __('Photography', 'wpmatch')
                    ),
                    'allow_custom' => true,
                    'min_selections' => 1,
                    'max_selections' => 10,
                    'display_as' => 'tags'
                )
            )
        );
    }

    /**
     * Check if a field exists by name
     *
     * @param string $field_name Field name to check
     * @return bool True if exists, false otherwise
     */
    private function field_exists($field_name) {
        global $wpdb;
        
        if (!$this->field_manager || !$this->field_manager->database) {
            return false;
        }

        $table_name = $this->field_manager->database->get_table_name('profile_fields');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE field_name = %s",
            $field_name
        ));

        return $count > 0;
    }
}

// Initialize the default fields class
new WPMatch_Default_Dating_Fields();
