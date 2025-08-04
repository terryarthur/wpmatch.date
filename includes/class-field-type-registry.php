<?php
/**
 * Field Type Registry class for managing different field types
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Field Type Registry class
 * 
 * Manages registration and configuration of different field types
 * with their validation rules, display options, and default settings.
 */
class WPMatch_Field_Type_Registry {

    /**
     * Registered field types
     *
     * @var array
     */
    private $field_types = array();

    /**
     * Constructor
     */
    public function __construct() {
        // Delay registration until text domain is loaded
        add_action('init', array($this, 'register_default_field_types'), 20);
    }

    /**
     * Register default field types
     */
    public function register_default_field_types() {
        // Only register once
        if (!empty($this->field_types)) {
            return;
        }
        
        // Text field
        $this->register_field_type('text', array(
            'label' => __('Text Input', 'wpmatch'),
            'description' => __('Single line text input field', 'wpmatch'),
            'icon' => 'dashicons-edit',
            'supports' => array('placeholder', 'help_text', 'default_value', 'min_length', 'max_length', 'regex_pattern'),
            'validation_options' => array('required', 'min_length', 'max_length', 'regex_pattern'),
            'default_options' => array(
                'input_type' => 'text',
                'maxlength' => 255
            ),
            'render_callback' => array($this, 'render_text_field'),
            'validate_callback' => array($this, 'validate_text_field'),
            'sanitize_callback' => array($this, 'sanitize_text_field')
        ));

        // Textarea field
        $this->register_field_type('textarea', array(
            'label' => __('Textarea', 'wpmatch'),
            'description' => __('Multi-line text input field', 'wpmatch'),
            'icon' => 'dashicons-text',
            'supports' => array('placeholder', 'help_text', 'default_value', 'min_length', 'max_length'),
            'validation_options' => array('required', 'min_length', 'max_length'),
            'default_options' => array(
                'rows' => 4,
                'cols' => 50,
                'maxlength' => 1000
            ),
            'render_callback' => array($this, 'render_textarea_field'),
            'validate_callback' => array($this, 'validate_textarea_field'),
            'sanitize_callback' => array($this, 'sanitize_textarea_field')
        ));

        // Select field
        $this->register_field_type('select', array(
            'label' => __('Select Dropdown', 'wpmatch'),
            'description' => __('Single selection dropdown field', 'wpmatch'),
            'icon' => 'dashicons-list-view',
            'supports' => array('help_text', 'default_value', 'options'),
            'validation_options' => array('required'),
            'default_options' => array(
                'choices' => array(),
                'allow_other' => false,
                'placeholder' => __('Please select...', 'wpmatch')
            ),
            'render_callback' => array($this, 'render_select_field'),
            'validate_callback' => array($this, 'validate_select_field'),
            'sanitize_callback' => array($this, 'sanitize_select_field')
        ));

        // Multi-select field
        $this->register_field_type('multiselect', array(
            'label' => __('Multi-Select', 'wpmatch'),
            'description' => __('Multiple selection dropdown field', 'wpmatch'),
            'icon' => 'dashicons-list-view',
            'supports' => array('help_text', 'default_value', 'options'),
            'validation_options' => array('required', 'min_selections', 'max_selections'),
            'default_options' => array(
                'choices' => array(),
                'min_selections' => 0,
                'max_selections' => 0, // 0 = unlimited
                'display_as' => 'select' // select, checkboxes
            ),
            'render_callback' => array($this, 'render_multiselect_field'),
            'validate_callback' => array($this, 'validate_multiselect_field'),
            'sanitize_callback' => array($this, 'sanitize_multiselect_field')
        ));

        // Checkbox field
        $this->register_field_type('checkbox', array(
            'label' => __('Checkbox', 'wpmatch'),
            'description' => __('Single checkbox for yes/no questions', 'wpmatch'),
            'icon' => 'dashicons-yes',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'checked_value' => '1',
                'unchecked_value' => '0'
            ),
            'render_callback' => array($this, 'render_checkbox_field'),
            'validate_callback' => array($this, 'validate_checkbox_field'),
            'sanitize_callback' => array($this, 'sanitize_checkbox_field')
        ));

        // Radio field
        $this->register_field_type('radio', array(
            'label' => __('Radio Buttons', 'wpmatch'),
            'description' => __('Single selection from radio button options', 'wpmatch'),
            'icon' => 'dashicons-marker',
            'supports' => array('help_text', 'default_value', 'options'),
            'validation_options' => array('required'),
            'default_options' => array(
                'choices' => array(),
                'layout' => 'vertical' // vertical, horizontal
            ),
            'render_callback' => array($this, 'render_radio_field'),
            'validate_callback' => array($this, 'validate_radio_field'),
            'sanitize_callback' => array($this, 'sanitize_radio_field')
        ));

        // Number field
        $this->register_field_type('number', array(
            'label' => __('Number', 'wpmatch'),
            'description' => __('Numeric input field', 'wpmatch'),
            'icon' => 'dashicons-calculator',
            'supports' => array('placeholder', 'help_text', 'default_value', 'min_value', 'max_value'),
            'validation_options' => array('required', 'min_value', 'max_value'),
            'default_options' => array(
                'step' => 1,
                'decimal_places' => 0
            ),
            'render_callback' => array($this, 'render_number_field'),
            'validate_callback' => array($this, 'validate_number_field'),
            'sanitize_callback' => array($this, 'sanitize_number_field')
        ));

        // Date field
        $this->register_field_type('date', array(
            'label' => __('Date', 'wpmatch'),
            'description' => __('Date picker field', 'wpmatch'),
            'icon' => 'dashicons-calendar-alt',
            'supports' => array('help_text', 'default_value', 'min_value', 'max_value'),
            'validation_options' => array('required', 'min_date', 'max_date'),
            'default_options' => array(
                'date_format' => 'Y-m-d',
                'display_format' => get_option('date_format'),
                'enable_time' => false
            ),
            'render_callback' => array($this, 'render_date_field'),
            'validate_callback' => array($this, 'validate_date_field'),
            'sanitize_callback' => array($this, 'sanitize_date_field')
        ));

        // Email field
        $this->register_field_type('email', array(
            'label' => __('Email', 'wpmatch'),
            'description' => __('Email address input field', 'wpmatch'),
            'icon' => 'dashicons-email',
            'supports' => array('placeholder', 'help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'verify_email' => false
            ),
            'render_callback' => array($this, 'render_email_field'),
            'validate_callback' => array($this, 'validate_email_field'),
            'sanitize_callback' => array($this, 'sanitize_email_field')
        ));

        // URL field
        $this->register_field_type('url', array(
            'label' => __('URL', 'wpmatch'),
            'description' => __('Website URL input field', 'wpmatch'),
            'icon' => 'dashicons-admin-links',
            'supports' => array('placeholder', 'help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'allowed_protocols' => array('http', 'https')
            ),
            'render_callback' => array($this, 'render_url_field'),
            'validate_callback' => array($this, 'validate_url_field'),
            'sanitize_callback' => array($this, 'sanitize_url_field')
        ));

        // Range/Slider field
        $this->register_field_type('range', array(
            'label' => __('Range Slider', 'wpmatch'),
            'description' => __('Range slider for numeric values', 'wpmatch'),
            'icon' => 'dashicons-leftright',
            'supports' => array('help_text', 'default_value', 'min_value', 'max_value'),
            'validation_options' => array('required', 'min_value', 'max_value'),
            'default_options' => array(
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'show_value' => true
            ),
            'render_callback' => array($this, 'render_range_field'),
            'validate_callback' => array($this, 'validate_range_field'),
            'sanitize_callback' => array($this, 'sanitize_range_field')
        ));

        // Age Range field (specific for dating)
        $this->register_field_type('age_range', array(
            'label' => __('Age Range', 'wpmatch'),
            'description' => __('Age range selector for dating preferences', 'wpmatch'),
            'icon' => 'dashicons-clock',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'min_age' => 18,
                'max_age' => 99,
                'step' => 1,
                'display_format' => 'range' // range, separate
            ),
            'render_callback' => array($this, 'render_age_range_field'),
            'validate_callback' => array($this, 'validate_age_range_field'),
            'sanitize_callback' => array($this, 'sanitize_age_range_field')
        ));

        // Height field (metric/imperial)
        $this->register_field_type('height', array(
            'label' => __('Height', 'wpmatch'),
            'description' => __('Height input with metric/imperial units', 'wpmatch'),
            'icon' => 'dashicons-editor-expand',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'units' => 'metric', // metric, imperial, both
                'metric_format' => 'cm', // cm, m
                'imperial_format' => 'feet_inches', // feet_inches, inches
                'min_height_cm' => 120,
                'max_height_cm' => 250
            ),
            'render_callback' => array($this, 'render_height_field'),
            'validate_callback' => array($this, 'validate_height_field'),
            'sanitize_callback' => array($this, 'sanitize_height_field')
        ));

        // Weight field (metric/imperial)
        $this->register_field_type('weight', array(
            'label' => __('Weight', 'wpmatch'),
            'description' => __('Weight input with metric/imperial units', 'wpmatch'),
            'icon' => 'dashicons-universal-access',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'units' => 'metric', // metric, imperial, both
                'metric_unit' => 'kg',
                'imperial_unit' => 'lbs',
                'min_weight_kg' => 30,
                'max_weight_kg' => 300
            ),
            'render_callback' => array($this, 'render_weight_field'),
            'validate_callback' => array($this, 'validate_weight_field'),
            'sanitize_callback' => array($this, 'sanitize_weight_field')
        ));

        // Relationship Status field
        $this->register_field_type('relationship_status', array(
            'label' => __('Relationship Status', 'wpmatch'),
            'description' => __('Current relationship status selector', 'wpmatch'),
            'icon' => 'dashicons-heart',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'choices' => array(
                    'single' => __('Single', 'wpmatch'),
                    'divorced' => __('Divorced', 'wpmatch'),
                    'widowed' => __('Widowed', 'wpmatch'),
                    'separated' => __('Separated', 'wpmatch'),
                    'in_relationship' => __('In a Relationship', 'wpmatch'),
                    'its_complicated' => __('It\'s Complicated', 'wpmatch')
                ),
                'allow_custom' => false
            ),
            'render_callback' => array($this, 'render_relationship_status_field'),
            'validate_callback' => array($this, 'validate_relationship_status_field'),
            'sanitize_callback' => array($this, 'sanitize_relationship_status_field')
        ));

        // Looking For field
        $this->register_field_type('looking_for', array(
            'label' => __('Looking For', 'wpmatch'),
            'description' => __('What the user is seeking in a relationship', 'wpmatch'),
            'icon' => 'dashicons-search',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
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
            ),
            'render_callback' => array($this, 'render_looking_for_field'),
            'validate_callback' => array($this, 'validate_looking_for_field'),
            'sanitize_callback' => array($this, 'sanitize_looking_for_field')
        ));

        // Gender field
        $this->register_field_type('gender', array(
            'label' => __('Gender', 'wpmatch'),
            'description' => __('Gender identity selector', 'wpmatch'),
            'icon' => 'dashicons-admin-users',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'choices' => array(
                    'male' => __('Male', 'wpmatch'),
                    'female' => __('Female', 'wpmatch'),
                    'non_binary' => __('Non-binary', 'wpmatch'),
                    'other' => __('Other', 'wpmatch'),
                    'prefer_not_to_say' => __('Prefer not to say', 'wpmatch')
                ),
                'allow_custom' => true,
                'include_pronouns' => false
            ),
            'render_callback' => array($this, 'render_gender_field'),
            'validate_callback' => array($this, 'validate_gender_field'),
            'sanitize_callback' => array($this, 'sanitize_gender_field')
        ));

        // Interests/Hobbies field
        $this->register_field_type('interests', array(
            'label' => __('Interests & Hobbies', 'wpmatch'),
            'description' => __('Multiple selection of interests and hobbies', 'wpmatch'),
            'icon' => 'dashicons-star-filled',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required', 'min_selections', 'max_selections'),
            'default_options' => array(
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
                'display_as' => 'tags' // tags, checkboxes, pills
            ),
            'render_callback' => array($this, 'render_interests_field'),
            'validate_callback' => array($this, 'validate_interests_field'),
            'sanitize_callback' => array($this, 'sanitize_interests_field')
        ));

        // Location field
        $this->register_field_type('location', array(
            'label' => __('Location', 'wpmatch'),
            'description' => __('Geographic location with privacy controls', 'wpmatch'),
            'icon' => 'dashicons-location',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'location_type' => 'city_state', // city_state, coordinates, postal_code
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
            ),
            'render_callback' => array($this, 'render_location_field'),
            'validate_callback' => array($this, 'validate_location_field'),
            'sanitize_callback' => array($this, 'sanitize_location_field')
        ));

        // Education Level field
        $this->register_field_type('education', array(
            'label' => __('Education Level', 'wpmatch'),
            'description' => __('Educational background selector', 'wpmatch'),
            'icon' => 'dashicons-welcome-learn-more',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'choices' => array(
                    'high_school' => __('High School', 'wpmatch'),
                    'some_college' => __('Some College', 'wpmatch'),
                    'bachelors' => __('Bachelor\'s Degree', 'wpmatch'),
                    'masters' => __('Master\'s Degree', 'wpmatch'),
                    'doctorate' => __('Doctorate', 'wpmatch'),
                    'trade_school' => __('Trade School', 'wpmatch'),
                    'other' => __('Other', 'wpmatch')
                ),
                'include_field_of_study' => false
            ),
            'render_callback' => array($this, 'render_education_field'),
            'validate_callback' => array($this, 'validate_education_field'),
            'sanitize_callback' => array($this, 'sanitize_education_field')
        ));

        // Profession/Occupation field
        $this->register_field_type('profession', array(
            'label' => __('Profession/Occupation', 'wpmatch'),
            'description' => __('Job title and industry selector', 'wpmatch'),
            'icon' => 'dashicons-businessman',
            'supports' => array('help_text', 'default_value', 'placeholder'),
            'validation_options' => array('required'),
            'default_options' => array(
                'include_industry' => true,
                'include_company' => false,
                'privacy_options' => array(
                    'public' => __('Public', 'wpmatch'),
                    'members_only' => __('Members Only', 'wpmatch'),
                    'hidden' => __('Hidden', 'wpmatch')
                ),
                'default_privacy' => 'public'
            ),
            'render_callback' => array($this, 'render_profession_field'),
            'validate_callback' => array($this, 'validate_profession_field'),
            'sanitize_callback' => array($this, 'sanitize_profession_field')
        ));

        // Zodiac Sign field
        $this->register_field_type('zodiac', array(
            'label' => __('Zodiac Sign', 'wpmatch'),
            'description' => __('Astrological sign selector', 'wpmatch'),
            'icon' => 'dashicons-star-half',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'choices' => array(
                    'aries' => __('Aries', 'wpmatch'),
                    'taurus' => __('Taurus', 'wpmatch'),
                    'gemini' => __('Gemini', 'wpmatch'),
                    'cancer' => __('Cancer', 'wpmatch'),
                    'leo' => __('Leo', 'wpmatch'),
                    'virgo' => __('Virgo', 'wpmatch'),
                    'libra' => __('Libra', 'wpmatch'),
                    'scorpio' => __('Scorpio', 'wpmatch'),
                    'sagittarius' => __('Sagittarius', 'wpmatch'),
                    'capricorn' => __('Capricorn', 'wpmatch'),
                    'aquarius' => __('Aquarius', 'wpmatch'),
                    'pisces' => __('Pisces', 'wpmatch')
                ),
                'show_compatibility' => true,
                'auto_calculate' => false // Calculate from birthdate if available
            ),
            'render_callback' => array($this, 'render_zodiac_field'),
            'validate_callback' => array($this, 'validate_zodiac_field'),
            'sanitize_callback' => array($this, 'sanitize_zodiac_field')
        ));

        // Lifestyle field
        $this->register_field_type('lifestyle', array(
            'label' => __('Lifestyle', 'wpmatch'),
            'description' => __('Lifestyle preferences and habits', 'wpmatch'),
            'icon' => 'dashicons-palmtree',
            'supports' => array('help_text', 'default_value'),
            'validation_options' => array('required'),
            'default_options' => array(
                'categories' => array(
                    'smoking' => array(
                        'label' => __('Smoking', 'wpmatch'),
                        'choices' => array(
                            'never' => __('Never', 'wpmatch'),
                            'socially' => __('Socially', 'wpmatch'),
                            'regularly' => __('Regularly', 'wpmatch'),
                            'trying_to_quit' => __('Trying to Quit', 'wpmatch')
                        )
                    ),
                    'drinking' => array(
                        'label' => __('Drinking', 'wpmatch'),
                        'choices' => array(
                            'never' => __('Never', 'wpmatch'),
                            'socially' => __('Socially', 'wpmatch'),
                            'regularly' => __('Regularly', 'wpmatch'),
                            'occasionally' => __('Occasionally', 'wpmatch')
                        )
                    ),
                    'exercise' => array(
                        'label' => __('Exercise', 'wpmatch'),
                        'choices' => array(
                            'never' => __('Never', 'wpmatch'),
                            'sometimes' => __('Sometimes', 'wpmatch'),
                            'regularly' => __('Regularly', 'wpmatch'),
                            'daily' => __('Daily', 'wpmatch')
                        )
                    )
                ),
                'allow_multiple_categories' => true
            ),
            'render_callback' => array($this, 'render_lifestyle_field'),
            'validate_callback' => array($this, 'validate_lifestyle_field'),
            'sanitize_callback' => array($this, 'sanitize_lifestyle_field')
        ));
        
        /**
         * Fires after default field types are registered
         * 
         * @param WPMatch_Field_Type_Registry $registry The registry instance
         */
        do_action('wpmatch_field_types_registered', $this);
    }

    /**
     * Register a field type
     *
     * @param string $type_name Field type name
     * @param array  $args      Field type configuration
     * @return bool True on success, false on failure
     */
    public function register_field_type($type_name, $args) {
        if (empty($type_name) || isset($this->field_types[$type_name])) {
            return false;
        }

        $defaults = array(
            'label' => '',
            'description' => '',
            'icon' => 'dashicons-admin-generic',
            'supports' => array(),
            'validation_options' => array(),
            'default_options' => array(),
            'render_callback' => null,
            'validate_callback' => null,
            'sanitize_callback' => null
        );

        $this->field_types[$type_name] = wp_parse_args($args, $defaults);

        /**
         * Fires after a field type is registered
         *
         * @param string $type_name Field type name
         * @param array  $args      Field type configuration
         */
        do_action('wpmatch_field_type_registered', $type_name, $args);

        return true;
    }

    /**
     * Unregister a field type
     *
     * @param string $type_name Field type name
     * @return bool True on success, false on failure
     */
    public function unregister_field_type($type_name) {
        if (!isset($this->field_types[$type_name])) {
            return false;
        }

        unset($this->field_types[$type_name]);

        /**
         * Fires after a field type is unregistered
         *
         * @param string $type_name Field type name
         */
        do_action('wpmatch_field_type_unregistered', $type_name);

        return true;
    }

    /**
     * Get all registered field types
     *
     * @return array Registered field types
     */
    public function get_field_types() {
        return $this->field_types;
    }

    /**
     * Get a specific field type
     *
     * @param string $type_name Field type name
     * @return array|null Field type configuration or null if not found
     */
    public function get_field_type($type_name) {
        return isset($this->field_types[$type_name]) ? $this->field_types[$type_name] : null;
    }

    /**
     * Check if a field type exists
     *
     * @param string $type_name Field type name
     * @return bool True if exists, false otherwise
     */
    public function field_type_exists($type_name) {
        return isset($this->field_types[$type_name]);
    }

    /**
     * Get field types for select dropdown
     *
     * @return array Array of type_name => label pairs
     */
    public function get_field_types_for_select() {
        $options = array();
        foreach ($this->field_types as $type_name => $config) {
            $options[$type_name] = $config['label'];
        }
        return $options;
    }

    /**
     * Render field HTML based on type
     *
     * @param object $field Field configuration
     * @param mixed  $value Current field value
     * @param array  $args  Additional render arguments
     * @return string Field HTML
     */
    public function render_field($field, $value = '', $args = array()) {
        $field_type = $this->get_field_type($field->field_type);
        
        if (!$field_type || !is_callable($field_type['render_callback'])) {
            return $this->render_fallback_field($field, $value, $args);
        }

        return call_user_func($field_type['render_callback'], $field, $value, $args);
    }

    /**
     * Validate field value based on type
     *
     * @param object $field Field configuration
     * @param mixed  $value Field value to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_field_value($field, $value) {
        $field_type = $this->get_field_type($field->field_type);
        
        if (!$field_type || !is_callable($field_type['validate_callback'])) {
            return $this->validate_fallback_field($field, $value);
        }

        return call_user_func($field_type['validate_callback'], $field, $value);
    }

    /**
     * Sanitize field value based on type
     *
     * @param object $field Field configuration
     * @param mixed  $value Field value to sanitize
     * @return mixed Sanitized value
     */
    public function sanitize_field_value($field, $value) {
        $field_type = $this->get_field_type($field->field_type);
        
        if (!$field_type || !is_callable($field_type['sanitize_callback'])) {
            return $this->sanitize_fallback_field($field, $value);
        }

        return call_user_func($field_type['sanitize_callback'], $field, $value);
    }

    // Field Type Render Methods

    /**
     * Render text field
     */
    public function render_text_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $defaults = array(
            'class' => 'wpmatch-field-text',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $attributes = array(
            'type' => $field_options['input_type'] ?? 'text',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => esc_attr($value),
            'class' => $args['class'],
            'placeholder' => esc_attr($field->placeholder_text ?? ''),
        );

        if (isset($field_options['maxlength'])) {
            $attributes['maxlength'] = $field_options['maxlength'];
        }

        if ($field->is_required) {
            $attributes['required'] = 'required';
        }

        $attr_string = '';
        foreach ($attributes as $key => $val) {
            if ($val !== '') {
                $attr_string .= sprintf(' %s="%s"', $key, esc_attr($val));
            }
        }

        return sprintf('<input%s />', $attr_string);
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $defaults = array(
            'class' => 'wpmatch-field-textarea',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $attributes = array(
            'id' => $args['id'],
            'name' => $args['name'],
            'class' => $args['class'],
            'placeholder' => esc_attr($field->placeholder_text ?? ''),
            'rows' => $field_options['rows'] ?? 4,
            'cols' => $field_options['cols'] ?? 50,
        );

        if (isset($field_options['maxlength'])) {
            $attributes['maxlength'] = $field_options['maxlength'];
        }

        if ($field->is_required) {
            $attributes['required'] = 'required';
        }

        $attr_string = '';
        foreach ($attributes as $key => $val) {
            if ($val !== '') {
                $attr_string .= sprintf(' %s="%s"', $key, esc_attr($val));
            }
        }

        return sprintf('<textarea%s>%s</textarea>', $attr_string, esc_textarea($value));
    }

    /**
     * Render select field
     */
    public function render_select_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $choices = $field_options['choices'] ?? array();
        
        $defaults = array(
            'class' => 'wpmatch-field-select',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $html = sprintf('<select id="%s" name="%s" class="%s"%s>',
            esc_attr($args['id']),
            esc_attr($args['name']),
            esc_attr($args['class']),
            $field->is_required ? ' required' : ''
        );

        // Add placeholder option
        $placeholder = $field_options['placeholder'] ?? __('Please select...', 'wpmatch');
        if ($placeholder) {
            $html .= sprintf('<option value="">%s</option>', esc_html($placeholder));
        }

        // Add choices
        if (is_array($choices)) {
            foreach ($choices as $choice_value => $choice_label) {
                // Handle both indexed and associative arrays
                if (is_numeric($choice_value)) {
                    $choice_value = $choice_label;
                }
                
                $selected = ($value == $choice_value) ? ' selected' : '';
                $html .= sprintf('<option value="%s"%s>%s</option>',
                    esc_attr($choice_value),
                    $selected,
                    esc_html($choice_label)
                );
            }
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $checked_value = $field_options['checked_value'] ?? '1';
        
        $defaults = array(
            'class' => 'wpmatch-field-checkbox',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $checked = ($value == $checked_value) ? ' checked' : '';
        
        return sprintf('<input type="checkbox" id="%s" name="%s" value="%s" class="%s"%s%s />',
            esc_attr($args['id']),
            esc_attr($args['name']),
            esc_attr($checked_value),
            esc_attr($args['class']),
            $checked,
            $field->is_required ? ' required' : ''
        );
    }

    /**
     * Render number field
     */
    public function render_number_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $defaults = array(
            'class' => 'wpmatch-field-number',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $attributes = array(
            'type' => 'number',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => esc_attr($value),
            'class' => $args['class'],
            'placeholder' => esc_attr($field->placeholder_text ?? ''),
            'step' => $field_options['step'] ?? 1
        );

        if ($field->min_value !== null) {
            $attributes['min'] = $field->min_value;
        }

        if ($field->max_value !== null) {
            $attributes['max'] = $field->max_value;
        }

        if ($field->is_required) {
            $attributes['required'] = 'required';
        }

        $attr_string = '';
        foreach ($attributes as $key => $val) {
            if ($val !== '') {
                $attr_string .= sprintf(' %s="%s"', $key, esc_attr($val));
            }
        }

        return sprintf('<input%s />', $attr_string);
    }

    /**
     * Render date field
     */
    public function render_date_field($field, $value = '', $args = array()) {
        $defaults = array(
            'class' => 'wpmatch-field-date',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $attributes = array(
            'type' => 'date',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => esc_attr($value),
            'class' => $args['class']
        );

        if ($field->min_value !== null) {
            $attributes['min'] = $field->min_value;
        }

        if ($field->max_value !== null) {
            $attributes['max'] = $field->max_value;
        }

        if ($field->is_required) {
            $attributes['required'] = 'required';
        }

        $attr_string = '';
        foreach ($attributes as $key => $val) {
            if ($val !== '') {
                $attr_string .= sprintf(' %s="%s"', $key, esc_attr($val));
            }
        }

        return sprintf('<input%s />', $attr_string);
    }

    /**
     * Render email field
     */
    public function render_email_field($field, $value = '', $args = array()) {
        $defaults = array(
            'class' => 'wpmatch-field-email',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $attributes = array(
            'type' => 'email',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => esc_attr($value),
            'class' => $args['class'],
            'placeholder' => esc_attr($field->placeholder_text ?? '')
        );

        if ($field->is_required) {
            $attributes['required'] = 'required';
        }

        $attr_string = '';
        foreach ($attributes as $key => $val) {
            if ($val !== '') {
                $attr_string .= sprintf(' %s="%s"', $key, esc_attr($val));
            }
        }

        return sprintf('<input%s />', $attr_string);
    }

    /**
     * Render URL field
     */
    public function render_url_field($field, $value = '', $args = array()) {
        $defaults = array(
            'class' => 'wpmatch-field-url',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $attributes = array(
            'type' => 'url',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => esc_attr($value),
            'class' => $args['class'],
            'placeholder' => esc_attr($field->placeholder_text ?? '')
        );

        if ($field->is_required) {
            $attributes['required'] = 'required';
        }

        $attr_string = '';
        foreach ($attributes as $key => $val) {
            if ($val !== '') {
                $attr_string .= sprintf(' %s="%s"', $key, esc_attr($val));
            }
        }

        return sprintf('<input%s />', $attr_string);
    }

    /**
     * Render radio field
     */
    public function render_radio_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $choices = $field_options['choices'] ?? array();
        $layout = $field_options['layout'] ?? 'vertical';
        
        $defaults = array(
            'class' => 'wpmatch-field-radio',
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $html = sprintf('<div class="%s %s-layout">',
            esc_attr($args['class']),
            esc_attr($layout)
        );

        if (is_array($choices)) {
            foreach ($choices as $choice_value => $choice_label) {
                // Handle both indexed and associative arrays
                if (is_numeric($choice_value)) {
                    $choice_value = $choice_label;
                }
                
                $checked = ($value == $choice_value) ? ' checked' : '';
                $option_id = $args['name'] . '_' . sanitize_title($choice_value);
                
                $html .= sprintf('<label for="%s"><input type="radio" id="%s" name="%s" value="%s"%s%s /> %s</label>',
                    esc_attr($option_id),
                    esc_attr($option_id),
                    esc_attr($args['name']),
                    esc_attr($choice_value),
                    $checked,
                    $field->is_required ? ' required' : '',
                    esc_html($choice_label)
                );
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render multiselect field
     */
    public function render_multiselect_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $choices = $field_options['choices'] ?? array();
        $display_as = $field_options['display_as'] ?? 'select';
        $values = is_array($value) ? $value : ($value ? explode(',', $value) : array());
        
        $defaults = array(
            'class' => 'wpmatch-field-multiselect',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name . '[]'
        );
        $args = wp_parse_args($args, $defaults);

        if ($display_as === 'checkboxes') {
            return $this->render_checkbox_group($field, $choices, $values, $args);
        } else {
            return $this->render_multiple_select($field, $choices, $values, $args);
        }
    }

    /**
     * Render range field
     */
    public function render_range_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $defaults = array(
            'class' => 'wpmatch-field-range',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $min = $field->min_value ?? $field_options['min'] ?? 0;
        $max = $field->max_value ?? $field_options['max'] ?? 100;
        $step = $field_options['step'] ?? 1;
        $show_value = $field_options['show_value'] ?? true;

        $html = sprintf('<div class="%s-wrapper">', esc_attr($args['class']));
        
        $html .= sprintf('<input type="range" id="%s" name="%s" value="%s" class="%s" min="%s" max="%s" step="%s"%s />',
            esc_attr($args['id']),
            esc_attr($args['name']),
            esc_attr($value),
            esc_attr($args['class']),
            esc_attr($min),
            esc_attr($max),
            esc_attr($step),
            $field->is_required ? ' required' : ''
        );

        if ($show_value) {
            $html .= sprintf('<span class="range-value" data-for="%s">%s</span>',
                esc_attr($args['id']),
                esc_html($value)
            );
        }

        $html .= '</div>';

        return $html;
    }

    // Field Type Validation Methods

    /**
     * Validate text field
     */
    public function validate_text_field($field, $value) {
        return $this->validate_text_length($field, $value);
    }

    /**
     * Validate textarea field
     */
    public function validate_textarea_field($field, $value) {
        return $this->validate_text_length($field, $value);
    }

    /**
     * Validate select field
     */
    public function validate_select_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label));
        }

        // Validate against available choices
        $field_options = $field->field_options ?: array();
        $choices = $field_options['choices'] ?? array();
        
        if (!empty($value) && is_array($choices) && !in_array($value, array_keys($choices)) && !in_array($value, array_values($choices))) {
            return new WP_Error('invalid_choice', sprintf(__('Invalid choice for %s field.', 'wpmatch'), $field->field_label));
        }

        return true;
    }

    /**
     * Validate checkbox field
     */
    public function validate_checkbox_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label));
        }

        return true;
    }

    /**
     * Validate number field
     */
    public function validate_number_field($field, $value) {
        if ($field->is_required && $value === '') {
            return new WP_Error('required_field', sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label));
        }

        if ($value !== '' && !is_numeric($value)) {
            return new WP_Error('invalid_number', sprintf(__('The %s field must be a valid number.', 'wpmatch'), $field->field_label));
        }

        if ($value !== '') {
            $num_value = floatval($value);
            
            if ($field->min_value !== null && $num_value < $field->min_value) {
                return new WP_Error('number_too_small', sprintf(__('The %s field must be at least %s.', 'wpmatch'), $field->field_label, $field->min_value));
            }

            if ($field->max_value !== null && $num_value > $field->max_value) {
                return new WP_Error('number_too_large', sprintf(__('The %s field must be no more than %s.', 'wpmatch'), $field->field_label, $field->max_value));
            }
        }

        return true;
    }

    /**
     * Validate email field
     */
    public function validate_email_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label));
        }

        if (!empty($value) && !is_email($value)) {
            return new WP_Error('invalid_email', sprintf(__('The %s field must be a valid email address.', 'wpmatch'), $field->field_label));
        }

        return true;
    }

    /**
     * Validate URL field
     */
    public function validate_url_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label));
        }

        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', sprintf(__('The %s field must be a valid URL.', 'wpmatch'), $field->field_label));
        }

        return true;
    }

    /**
     * Validate date field
     */
    public function validate_date_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label));
        }

        if (!empty($value) && !$this->is_valid_date($value)) {
            return new WP_Error('invalid_date', sprintf(__('The %s field must be a valid date.', 'wpmatch'), $field->field_label));
        }

        // Validate date range
        if (!empty($value)) {
            $date = strtotime($value);
            
            if ($field->min_value && $date < strtotime($field->min_value)) {
                return new WP_Error('date_too_early', sprintf(__('The %s field must be no earlier than %s.', 'wpmatch'), $field->field_label, $field->min_value));
            }

            if ($field->max_value && $date > strtotime($field->max_value)) {
                return new WP_Error('date_too_late', sprintf(__('The %s field must be no later than %s.', 'wpmatch'), $field->field_label, $field->max_value));
            }
        }

        return true;
    }

    /**
     * Validate radio field
     */
    public function validate_radio_field($field, $value) {
        return $this->validate_select_field($field, $value);
    }

    /**
     * Validate multiselect field
     */
    public function validate_multiselect_field($field, $value) {
        $values = is_array($value) ? $value : ($value ? explode(',', $value) : array());
        
        if ($field->is_required && empty($values)) {
            return new WP_Error('required_field', sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label));
        }

        // Validate against available choices
        $field_options = $field->field_options ?: array();
        $choices = $field_options['choices'] ?? array();
        
        if (!empty($values) && is_array($choices)) {
            $valid_choices = array_merge(array_keys($choices), array_values($choices));
            foreach ($values as $val) {
                if (!in_array($val, $valid_choices)) {
                    return new WP_Error('invalid_choice', sprintf(__('Invalid choice for %s field.', 'wpmatch'), $field->field_label));
                }
            }
        }

        return true;
    }

    /**
     * Validate range field
     */
    public function validate_range_field($field, $value) {
        return $this->validate_number_field($field, $value);
    }

    // Field Type Sanitization Methods

    /**
     * Sanitize text field
     */
    public function sanitize_text_field($field, $value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize textarea field
     */
    public function sanitize_textarea_field($field, $value) {
        return sanitize_textarea_field($value);
    }

    /**
     * Sanitize select field
     */
    public function sanitize_select_field($field, $value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize checkbox field
     */
    public function sanitize_checkbox_field($field, $value) {
        $field_options = $field->field_options ?: array();
        $checked_value = $field_options['checked_value'] ?? '1';
        $unchecked_value = $field_options['unchecked_value'] ?? '0';
        
        return $value ? $checked_value : $unchecked_value;
    }

    /**
     * Sanitize number field
     */
    public function sanitize_number_field($field, $value) {
        return is_numeric($value) ? $value : '';
    }

    /**
     * Sanitize email field
     */
    public function sanitize_email_field($field, $value) {
        return sanitize_email($value);
    }

    /**
     * Sanitize URL field
     */
    public function sanitize_url_field($field, $value) {
        return esc_url_raw($value);
    }

    /**
     * Sanitize date field
     */
    public function sanitize_date_field($field, $value) {
        if (!$this->is_valid_date($value)) {
            return '';
        }
        return sanitize_text_field($value);
    }

    /**
     * Sanitize radio field
     */
    public function sanitize_radio_field($field, $value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize multiselect field
     */
    public function sanitize_multiselect_field($field, $value) {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return array();
    }

    /**
     * Sanitize range field
     */
    public function sanitize_range_field($field, $value) {
        return is_numeric($value) ? $value : '';
    }

    // Helper methods

    /**
     * Validate text length
     */
    private function validate_text_length($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label));
        }

        if (!empty($value)) {
            $length = strlen($value);
            
            if ($field->min_length && $length < $field->min_length) {
                return new WP_Error('text_too_short', sprintf(__('The %s field must be at least %d characters.', 'wpmatch'), $field->field_label, $field->min_length));
            }

            if ($field->max_length && $length > $field->max_length) {
                return new WP_Error('text_too_long', sprintf(__('The %s field must be no more than %d characters.', 'wpmatch'), $field->field_label, $field->max_length));
            }

            // Regex pattern validation
            if ($field->regex_pattern && !preg_match($field->regex_pattern, $value)) {
                return new WP_Error('invalid_format', sprintf(__('The %s field format is invalid.', 'wpmatch'), $field->field_label));
            }
        }

        return true;
    }

    /**
     * Check if date is valid
     */
    private function is_valid_date($date) {
        if (empty($date)) {
            return false;
        }
        
        $timestamp = strtotime($date);
        return $timestamp !== false && date('Y-m-d', $timestamp) === $date;
    }

    /**
     * Render fallback field for unknown types
     */
    private function render_fallback_field($field, $value, $args) {
        return $this->render_text_field($field, $value, $args);
    }

    /**
     * Validate fallback field for unknown types
     */
    private function validate_fallback_field($field, $value) {
        return $this->validate_text_field($field, $value);
    }

    /**
     * Sanitize fallback field for unknown types
     */
    private function sanitize_fallback_field($field, $value) {
        return sanitize_text_field($value);
    }

    /**
     * Render checkbox group for multiselect
     */
    private function render_checkbox_group($field, $choices, $values, $args) {
        $html = sprintf('<div class="%s checkbox-group">', esc_attr($args['class']));

        if (is_array($choices)) {
            foreach ($choices as $choice_value => $choice_label) {
                if (is_numeric($choice_value)) {
                    $choice_value = $choice_label;
                }
                
                $checked = in_array($choice_value, $values) ? ' checked' : '';
                $option_id = $field->field_name . '_' . sanitize_title($choice_value);
                
                $html .= sprintf('<label for="%s"><input type="checkbox" id="%s" name="%s" value="%s"%s /> %s</label>',
                    esc_attr($option_id),
                    esc_attr($option_id),
                    esc_attr($args['name']),
                    esc_attr($choice_value),
                    $checked,
                    esc_html($choice_label)
                );
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render multiple select element
     */
    private function render_multiple_select($field, $choices, $values, $args) {
        $html = sprintf('<select id="%s" name="%s" class="%s" multiple%s>',
            esc_attr($args['id']),
            esc_attr($args['name']),
            esc_attr($args['class']),
            $field->is_required ? ' required' : ''
        );

        if (is_array($choices)) {
            foreach ($choices as $choice_value => $choice_label) {
                if (is_numeric($choice_value)) {
                    $choice_value = $choice_label;
                }
                
                $selected = in_array($choice_value, $values) ? ' selected' : '';
                $html .= sprintf('<option value="%s"%s>%s</option>',
                    esc_attr($choice_value),
                    $selected,
                    esc_html($choice_label)
                );
            }
        }

        $html .= '</select>';
        return $html;
    }

    // Dating-specific field render methods

    /**
     * Render age range field
     */
    public function render_age_range_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $min_age = $field_options['min_age'] ?? 18;
        $max_age = $field_options['max_age'] ?? 99;
        $display_format = $field_options['display_format'] ?? 'range';
        
        $defaults = array(
            'class' => 'wpmatch-field-age-range',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        // Parse existing value
        $current_values = array('min' => $min_age, 'max' => $max_age);
        if (!empty($value) && is_string($value)) {
            $parsed = json_decode($value, true);
            if ($parsed && isset($parsed['min'], $parsed['max'])) {
                $current_values = $parsed;
            }
        } elseif (is_array($value)) {
            $current_values = wp_parse_args($value, $current_values);
        }

        $html = sprintf('<div class="%s" id="%s">', esc_attr($args['class']), esc_attr($args['id']));
        
        if ($display_format === 'range') {
            $html .= '<div class="age-range-container">';
            $html .= sprintf('<label>%s:</label>', __('Age Range', 'wpmatch'));
            $html .= '<div class="range-inputs">';
            $html .= sprintf('<input type="number" name="%s[min]" value="%d" min="%d" max="%d" placeholder="%s" />', 
                esc_attr($args['name']), $current_values['min'], $min_age, $max_age, __('Min', 'wpmatch'));
            $html .= '<span class="range-separator">-</span>';
            $html .= sprintf('<input type="number" name="%s[max]" value="%d" min="%d" max="%d" placeholder="%s" />', 
                esc_attr($args['name']), $current_values['max'], $min_age, $max_age, __('Max', 'wpmatch'));
            $html .= '</div></div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render height field
     */
    public function render_height_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $units = $field_options['units'] ?? 'metric';
        
        $defaults = array(
            'class' => 'wpmatch-field-height',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $html = sprintf('<div class="%s" id="%s">', esc_attr($args['class']), esc_attr($args['id']));

        if ($units === 'metric' || $units === 'both') {
            $html .= '<div class="height-metric">';
            $html .= sprintf('<input type="number" name="%s[cm]" value="%s" min="120" max="250" placeholder="%s" />',
                esc_attr($args['name']), esc_attr($value), __('Height (cm)', 'wpmatch'));
            $html .= sprintf('<span class="unit-label">%s</span>', __('cm', 'wpmatch'));
            $html .= '</div>';
        }

        if ($units === 'imperial' || $units === 'both') {
            $html .= '<div class="height-imperial">';
            $html .= sprintf('<input type="number" name="%s[feet]" value="" min="3" max="8" placeholder="%s" />',
                esc_attr($args['name']), __('Feet', 'wpmatch'));
            $html .= sprintf('<input type="number" name="%s[inches]" value="" min="0" max="11" placeholder="%s" />',
                esc_attr($args['name']), __('Inches', 'wpmatch'));
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render weight field
     */
    public function render_weight_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $units = $field_options['units'] ?? 'metric';
        
        $defaults = array(
            'class' => 'wpmatch-field-weight',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $html = sprintf('<div class="%s" id="%s">', esc_attr($args['class']), esc_attr($args['id']));

        if ($units === 'metric' || $units === 'both') {
            $html .= '<div class="weight-metric">';
            $html .= sprintf('<input type="number" name="%s[kg]" value="%s" min="30" max="300" placeholder="%s" />',
                esc_attr($args['name']), esc_attr($value), __('Weight (kg)', 'wpmatch'));
            $html .= sprintf('<span class="unit-label">%s</span>', __('kg', 'wpmatch'));
            $html .= '</div>';
        }

        if ($units === 'imperial' || $units === 'both') {
            $html .= '<div class="weight-imperial">';
            $html .= sprintf('<input type="number" name="%s[lbs]" value="" min="65" max="660" placeholder="%s" />',
                esc_attr($args['name']), __('Weight (lbs)', 'wpmatch'));
            $html .= sprintf('<span class="unit-label">%s</span>', __('lbs', 'wpmatch'));
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render relationship status field
     */
    public function render_relationship_status_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $choices = $field_options['choices'] ?? array();
        
        return $this->render_select_field($field, $value, $args);
    }

    /**
     * Render looking for field
     */
    public function render_looking_for_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $choices = $field_options['choices'] ?? array();
        $allow_multiple = $field_options['allow_multiple'] ?? true;
        
        $defaults = array(
            'class' => 'wpmatch-field-looking-for',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name . ($allow_multiple ? '[]' : '')
        );
        $args = wp_parse_args($args, $defaults);

        if ($allow_multiple) {
            $values = is_array($value) ? $value : ($value ? array($value) : array());
            return $this->render_checkbox_group($field, $choices, $values, $args);
        } else {
            return $this->render_select_field($field, $value, $args);
        }
    }

    /**
     * Render gender field
     */
    public function render_gender_field($field, $value = '', $args = array()) {
        return $this->render_select_field($field, $value, $args);
    }

    /**
     * Render interests field
     */
    public function render_interests_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $choices = $field_options['choices'] ?? array();
        $allow_custom = $field_options['allow_custom'] ?? true;
        $display_as = $field_options['display_as'] ?? 'tags';
        
        $defaults = array(
            'class' => 'wpmatch-field-interests',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name . '[]'
        );
        $args = wp_parse_args($args, $defaults);

        $values = is_array($value) ? $value : ($value ? array($value) : array());

        $html = sprintf('<div class="%s" id="%s">', esc_attr($args['class']), esc_attr($args['id']));

        if ($display_as === 'tags') {
            $html .= '<div class="interests-tags">';
            foreach ($choices as $choice_value => $choice_label) {
                if (is_numeric($choice_value)) {
                    $choice_value = $choice_label;
                }
                $checked = in_array($choice_value, $values) ? ' checked' : '';
                $html .= sprintf('<label class="interest-tag"><input type="checkbox" name="%s" value="%s"%s /> %s</label>',
                    esc_attr($args['name']), esc_attr($choice_value), $checked, esc_html($choice_label));
            }
            $html .= '</div>';
        } else {
            $html .= $this->render_checkbox_group($field, $choices, $values, $args);
        }

        if ($allow_custom) {
            $html .= sprintf('<div class="custom-interest"><input type="text" placeholder="%s" /></div>', 
                __('Add custom interest...', 'wpmatch'));
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render location field
     */
    public function render_location_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $location_type = $field_options['location_type'] ?? 'city_state';
        $enable_map = $field_options['enable_map'] ?? true;
        
        $defaults = array(
            'class' => 'wpmatch-field-location',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $html = sprintf('<div class="%s" id="%s">', esc_attr($args['class']), esc_attr($args['id']));

        switch ($location_type) {
            case 'city_state':
                $html .= sprintf('<input type="text" name="%s[city]" placeholder="%s" />',
                    esc_attr($args['name']), __('City', 'wpmatch'));
                $html .= sprintf('<input type="text" name="%s[state]" placeholder="%s" />',
                    esc_attr($args['name']), __('State/Province', 'wpmatch'));
                $html .= sprintf('<input type="text" name="%s[country]" placeholder="%s" />',
                    esc_attr($args['name']), __('Country', 'wpmatch'));
                break;
            case 'postal_code':
                $html .= sprintf('<input type="text" name="%s" placeholder="%s" value="%s" />',
                    esc_attr($args['name']), __('Postal/ZIP Code', 'wpmatch'), esc_attr($value));
                break;
        }

        // Privacy settings
        $privacy_levels = $field_options['privacy_levels'] ?? array();
        if (!empty($privacy_levels)) {
            $html .= '<div class="location-privacy">';
            $html .= sprintf('<label>%s:</label>', __('Privacy Level', 'wpmatch'));
            $html .= sprintf('<select name="%s[privacy]">', esc_attr($args['name']));
            foreach ($privacy_levels as $level => $label) {
                $html .= sprintf('<option value="%s">%s</option>', esc_attr($level), esc_html($label));
            }
            $html .= '</select></div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render education field
     */
    public function render_education_field($field, $value = '', $args = array()) {
        return $this->render_select_field($field, $value, $args);
    }

    /**
     * Render profession field
     */
    public function render_profession_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $include_industry = $field_options['include_industry'] ?? true;
        $include_company = $field_options['include_company'] ?? false;
        
        $defaults = array(
            'class' => 'wpmatch-field-profession',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $html = sprintf('<div class="%s" id="%s">', esc_attr($args['class']), esc_attr($args['id']));
        
        $html .= sprintf('<input type="text" name="%s[title]" placeholder="%s" value="%s" />',
            esc_attr($args['name']), __('Job Title', 'wpmatch'), esc_attr($value));

        if ($include_industry) {
            $html .= sprintf('<input type="text" name="%s[industry]" placeholder="%s" />',
                esc_attr($args['name']), __('Industry', 'wpmatch'));
        }

        if ($include_company) {
            $html .= sprintf('<input type="text" name="%s[company]" placeholder="%s" />',
                esc_attr($args['name']), __('Company', 'wpmatch'));
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render zodiac field
     */
    public function render_zodiac_field($field, $value = '', $args = array()) {
        return $this->render_select_field($field, $value, $args);
    }

    /**
     * Render lifestyle field
     */
    public function render_lifestyle_field($field, $value = '', $args = array()) {
        $field_options = $field->field_options ?: array();
        $categories = $field_options['categories'] ?? array();
        
        $defaults = array(
            'class' => 'wpmatch-field-lifestyle',
            'id' => 'field_' . $field->field_name,
            'name' => $field->field_name
        );
        $args = wp_parse_args($args, $defaults);

        $html = sprintf('<div class="%s" id="%s">', esc_attr($args['class']), esc_attr($args['id']));

        foreach ($categories as $category_key => $category_data) {
            $html .= sprintf('<div class="lifestyle-category"><h4>%s</h4>', esc_html($category_data['label']));
            $html .= sprintf('<select name="%s[%s]">', esc_attr($args['name']), esc_attr($category_key));
            $html .= sprintf('<option value="">%s</option>', __('Select...', 'wpmatch'));
            
            if (isset($category_data['choices'])) {
                foreach ($category_data['choices'] as $choice_value => $choice_label) {
                    $selected = (isset($value[$category_key]) && $value[$category_key] === $choice_value) ? ' selected' : '';
                    $html .= sprintf('<option value="%s"%s>%s</option>',
                        esc_attr($choice_value), $selected, esc_html($choice_label));
                }
            }
            
            $html .= '</select></div>';
        }

        $html .= '</div>';
        return $html;
    }

    // Validation methods for new field types

    /**
     * Validate age range field
     */
    public function validate_age_range_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', __('Age range is required.', 'wpmatch'));
        }

        if (!empty($value)) {
            $data = is_string($value) ? json_decode($value, true) : $value;
            if (!is_array($data) || !isset($data['min'], $data['max'])) {
                return new WP_Error('invalid_format', __('Invalid age range format.', 'wpmatch'));
            }

            if ($data['min'] < 18 || $data['max'] > 99 || $data['min'] > $data['max']) {
                return new WP_Error('invalid_range', __('Invalid age range values.', 'wpmatch'));
            }
        }

        return true;
    }

    /**
     * Validate height field
     */
    public function validate_height_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', __('Height is required.', 'wpmatch'));
        }

        // Add height validation logic here
        return true;
    }

    /**
     * Validate weight field
     */
    public function validate_weight_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', __('Weight is required.', 'wpmatch'));
        }

        // Add weight validation logic here
        return true;
    }

    /**
     * Validate relationship status field
     */
    public function validate_relationship_status_field($field, $value) {
        return $this->validate_select_field($field, $value);
    }

    /**
     * Validate looking for field
     */
    public function validate_looking_for_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', __('Please specify what you are looking for.', 'wpmatch'));
        }

        return true;
    }

    /**
     * Validate gender field
     */
    public function validate_gender_field($field, $value) {
        return $this->validate_select_field($field, $value);
    }

    /**
     * Validate interests field
     */
    public function validate_interests_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', __('Please select at least one interest.', 'wpmatch'));
        }

        return true;
    }

    /**
     * Validate location field
     */
    public function validate_location_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', __('Location is required.', 'wpmatch'));
        }

        return true;
    }

    /**
     * Validate education field
     */
    public function validate_education_field($field, $value) {
        return $this->validate_select_field($field, $value);
    }

    /**
     * Validate profession field
     */
    public function validate_profession_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', __('Profession is required.', 'wpmatch'));
        }

        return true;
    }

    /**
     * Validate zodiac field
     */
    public function validate_zodiac_field($field, $value) {
        return $this->validate_select_field($field, $value);
    }

    /**
     * Validate lifestyle field
     */
    public function validate_lifestyle_field($field, $value) {
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', __('Lifestyle information is required.', 'wpmatch'));
        }

        return true;
    }

    // Sanitization methods for new field types

    /**
     * Sanitize age range field
     */
    public function sanitize_age_range_field($field, $value) {
        if (is_string($value)) {
            $data = json_decode($value, true);
        } else {
            $data = $value;
        }

        if (is_array($data)) {
            return array(
                'min' => absint($data['min'] ?? 18),
                'max' => absint($data['max'] ?? 99)
            );
        }

        return array('min' => 18, 'max' => 99);
    }

    /**
     * Sanitize height field
     */
    public function sanitize_height_field($field, $value) {
        if (is_array($value)) {
            $sanitized = array();
            foreach ($value as $key => $val) {
                $sanitized[sanitize_key($key)] = absint($val);
            }
            return $sanitized;
        }
        return absint($value);
    }

    /**
     * Sanitize weight field
     */
    public function sanitize_weight_field($field, $value) {
        if (is_array($value)) {
            $sanitized = array();
            foreach ($value as $key => $val) {
                $sanitized[sanitize_key($key)] = absint($val);
            }
            return $sanitized;
        }
        return absint($value);
    }

    /**
     * Sanitize relationship status field
     */
    public function sanitize_relationship_status_field($field, $value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize looking for field
     */
    public function sanitize_looking_for_field($field, $value) {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return sanitize_text_field($value);
    }

    /**
     * Sanitize gender field
     */
    public function sanitize_gender_field($field, $value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize interests field
     */
    public function sanitize_interests_field($field, $value) {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return sanitize_text_field($value);
    }

    /**
     * Sanitize location field
     */
    public function sanitize_location_field($field, $value) {
        if (is_array($value)) {
            $sanitized = array();
            foreach ($value as $key => $val) {
                $sanitized[sanitize_key($key)] = sanitize_text_field($val);
            }
            return $sanitized;
        }
        return sanitize_text_field($value);
    }

    /**
     * Sanitize education field
     */
    public function sanitize_education_field($field, $value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize profession field
     */
    public function sanitize_profession_field($field, $value) {
        if (is_array($value)) {
            $sanitized = array();
            foreach ($value as $key => $val) {
                $sanitized[sanitize_key($key)] = sanitize_text_field($val);
            }
            return $sanitized;
        }
        return sanitize_text_field($value);
    }

    /**
     * Sanitize zodiac field
     */
    public function sanitize_zodiac_field($field, $value) {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize lifestyle field
     */
    public function sanitize_lifestyle_field($field, $value) {
        if (is_array($value)) {
            $sanitized = array();
            foreach ($value as $key => $val) {
                $sanitized[sanitize_key($key)] = sanitize_text_field($val);
            }
            return $sanitized;
        }
        return sanitize_text_field($value);
    }
}