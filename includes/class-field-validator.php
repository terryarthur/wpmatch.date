<?php
/**
 * Field Validator class for comprehensive validation
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Field Validator class
 * 
 * Provides comprehensive validation for profile fields including
 * field configuration validation, user input validation, and security checks.
 */
class WPMatch_Field_Validator {

    /**
     * Reserved field names that cannot be used
     *
     * @var array
     */
    private $reserved_names = array(
        'id', 'user_id', 'username', 'password', 'email', 'login', 'user_login',
        'user_pass', 'user_email', 'user_url', 'user_nicename', 'display_name',
        'nickname', 'first_name', 'last_name', 'description', 'rich_editing',
        'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl',
        'show_admin_bar_front', 'locale', 'wp_capabilities', 'wp_user_level',
        'dismissed_wp_pointers', 'show_welcome_panel', 'created_at', 'updated_at',
        'action', 'nonce', '_wpnonce', '_wp_http_referer', 'submit'
    );

    /**
     * Allowed field types
     *
     * @var array
     */
    private $allowed_field_types = array(
        'text', 'textarea', 'select', 'multiselect', 'checkbox', 'radio',
        'number', 'date', 'time', 'datetime', 'url', 'email', 'tel',
        'file', 'image', 'range', 'color', 'rating'
    );

    /**
     * Maximum field limits
     *
     * @var array
     */
    private $limits = array(
        'field_name_length' => 100,
        'field_label_length' => 255,
        'field_description_length' => 1000,
        'help_text_length' => 500,
        'placeholder_text_length' => 255,
        'regex_pattern_length' => 500,
        'default_value_length' => 1000,
        'field_class_length' => 255,
        'max_options_count' => 100,
        'max_field_order' => 999,
        'max_min_length' => 10000,
        'max_max_length' => 10000
    );

    /**
     * Field type registry instance
     *
     * @var WPMatch_Field_Type_Registry
     */
    private $type_registry;

    /**
     * Constructor
     */
    public function __construct() {
        // Will be set when dependencies are loaded
        $this->type_registry = null;
    }

    /**
     * Set field type registry
     *
     * @param WPMatch_Field_Type_Registry $registry
     */
    public function set_type_registry($registry) {
        $this->type_registry = $registry;
    }

    /**
     * Validate complete field data for creation/update
     *
     * @param array $field_data Field configuration data
     * @param int   $field_id   Optional field ID for updates
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_field_data($field_data, $field_id = null) {
        $errors = array();

        // Validate required fields
        $required_fields = array('field_name', 'field_label', 'field_type');
        foreach ($required_fields as $required_field) {
            if (empty($field_data[$required_field])) {
                $errors[] = sprintf(__('The %s field is required.', 'wpmatch'), str_replace('_', ' ', $required_field));
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', __('Validation failed.', 'wpmatch'), $errors);
        }

        // Validate individual fields
        $field_validations = array(
            'field_name' => 'validate_field_name',
            'field_label' => 'validate_field_label',
            'field_type' => 'validate_field_type',
            'field_description' => 'validate_field_description',
            'placeholder_text' => 'validate_placeholder_text',
            'help_text' => 'validate_help_text',
            'field_options' => 'validate_field_options',
            'validation_rules' => 'validate_validation_rules',
            'display_options' => 'validate_display_options',
            'conditional_logic' => 'validate_conditional_logic',
            'min_value' => 'validate_min_value',
            'max_value' => 'validate_max_value',
            'min_length' => 'validate_min_length',
            'max_length' => 'validate_max_length',
            'regex_pattern' => 'validate_regex_pattern',
            'default_value' => 'validate_default_value',
            'field_width' => 'validate_field_width',
            'field_class' => 'validate_field_class',
            'field_group' => 'validate_field_group',
            'field_order' => 'validate_field_order',
            'status' => 'validate_status'
        );

        foreach ($field_validations as $field => $method) {
            if (isset($field_data[$field])) {
                $result = $this->$method($field_data[$field], $field_data);
                if (is_wp_error($result)) {
                    $errors = array_merge($errors, $result->get_error_data());
                }
            }
        }

        // Type-specific validation
        if (isset($field_data['field_type'])) {
            $type_validation = $this->validate_field_type_specific($field_data);
            if (is_wp_error($type_validation)) {
                $errors = array_merge($errors, $type_validation->get_error_data());
            }
        }

        // Cross-field validation
        $cross_validation = $this->validate_cross_field_rules($field_data);
        if (is_wp_error($cross_validation)) {
            $errors = array_merge($errors, $cross_validation->get_error_data());
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', __('Validation failed.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate field name
     *
     * @param string $field_name Field name
     * @param array  $field_data Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_name($field_name, $field_data = array()) {
        $errors = array();

        // Check length
        if (strlen($field_name) > $this->limits['field_name_length']) {
            $errors[] = sprintf(__('Field name must be %d characters or less.', 'wpmatch'), $this->limits['field_name_length']);
        }

        // Check format (lowercase letters, numbers, underscores only)
        if (!preg_match('/^[a-z][a-z0-9_]*[a-z0-9]$/', $field_name)) {
            $errors[] = __('Field name must start with a letter, contain only lowercase letters, numbers, and underscores, and end with a letter or number.', 'wpmatch');
        }

        // Check reserved names
        if (in_array(strtolower($field_name), $this->reserved_names)) {
            $errors[] = sprintf(__('"%s" is a reserved field name and cannot be used.', 'wpmatch'), $field_name);
        }

        // Check WordPress meta key restrictions
        if (substr($field_name, 0, 3) === 'wp_' || substr($field_name, 0, 1) === '_') {
            $errors[] = __('Field names cannot start with "wp_" or underscore as these are reserved for WordPress.', 'wpmatch');
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_field_name', __('Invalid field name.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate field label
     *
     * @param string $field_label Field label
     * @param array  $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_label($field_label, $field_data = array()) {
        $errors = array();

        if (empty(trim($field_label))) {
            $errors[] = __('Field label cannot be empty.', 'wpmatch');
        }

        if (strlen($field_label) > $this->limits['field_label_length']) {
            $errors[] = sprintf(__('Field label must be %d characters or less.', 'wpmatch'), $this->limits['field_label_length']);
        }

        // Check for HTML tags (not allowed in labels)
        if ($field_label !== strip_tags($field_label)) {
            $errors[] = __('Field label cannot contain HTML tags.', 'wpmatch');
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_field_label', __('Invalid field label.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate field type
     *
     * @param string $field_type Field type
     * @param array  $field_data Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_type($field_type, $field_data = array()) {
        if (!in_array($field_type, $this->allowed_field_types)) {
            return new WP_Error('invalid_field_type', 
                sprintf(__('"%s" is not a valid field type.', 'wpmatch'), $field_type)
            );
        }

        // If type registry is available, check if type is registered
        if ($this->type_registry && !$this->type_registry->field_type_exists($field_type)) {
            return new WP_Error('unregistered_field_type', 
                sprintf(__('Field type "%s" is not registered.', 'wpmatch'), $field_type)
            );
        }

        return true;
    }

    /**
     * Validate field description
     *
     * @param string $field_description Field description
     * @param array  $field_data        Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_description($field_description, $field_data = array()) {
        if (strlen($field_description) > $this->limits['field_description_length']) {
            return new WP_Error('invalid_field_description', 
                sprintf(__('Field description must be %d characters or less.', 'wpmatch'), $this->limits['field_description_length'])
            );
        }

        return true;
    }

    /**
     * Validate placeholder text
     *
     * @param string $placeholder_text Placeholder text
     * @param array  $field_data       Complete field data
     * @return bool|WP_Error
     */
    public function validate_placeholder_text($placeholder_text, $field_data = array()) {
        if (strlen($placeholder_text) > $this->limits['placeholder_text_length']) {
            return new WP_Error('invalid_placeholder_text', 
                sprintf(__('Placeholder text must be %d characters or less.', 'wpmatch'), $this->limits['placeholder_text_length'])
            );
        }

        return true;
    }

    /**
     * Validate help text
     *
     * @param string $help_text  Help text
     * @param array  $field_data Complete field data
     * @return bool|WP_Error
     */
    public function validate_help_text($help_text, $field_data = array()) {
        if (strlen($help_text) > $this->limits['help_text_length']) {
            return new WP_Error('invalid_help_text', 
                sprintf(__('Help text must be %d characters or less.', 'wpmatch'), $this->limits['help_text_length'])
            );
        }

        return true;
    }

    /**
     * Validate field options
     *
     * @param mixed $field_options Field options
     * @param array $field_data    Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_options($field_options, $field_data = array()) {
        if (!is_array($field_options)) {
            return new WP_Error('invalid_field_options', __('Field options must be an array.', 'wpmatch'));
        }

        $field_type = $field_data['field_type'] ?? '';
        $errors = array();

        // Validate based on field type
        switch ($field_type) {
            case 'select':
            case 'multiselect':
            case 'radio':
                if (isset($field_options['choices'])) {
                    $choices_validation = $this->validate_field_choices($field_options['choices']);
                    if (is_wp_error($choices_validation)) {
                        $errors = array_merge($errors, $choices_validation->get_error_data());
                    }
                }
                break;

            case 'number':
            case 'range':
                if (isset($field_options['step']) && !is_numeric($field_options['step'])) {
                    $errors[] = __('Step value must be numeric.', 'wpmatch');
                }
                if (isset($field_options['decimal_places']) && (!is_numeric($field_options['decimal_places']) || $field_options['decimal_places'] < 0)) {
                    $errors[] = __('Decimal places must be a non-negative number.', 'wpmatch');
                }
                break;
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_field_options', __('Invalid field options.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate validation rules
     *
     * @param mixed $validation_rules Validation rules
     * @param array $field_data       Complete field data
     * @return bool|WP_Error
     */
    public function validate_validation_rules($validation_rules, $field_data = array()) {
        if (!is_array($validation_rules)) {
            return new WP_Error('invalid_validation_rules', __('Validation rules must be an array.', 'wpmatch'));
        }

        $errors = array();

        // Validate individual rules
        foreach ($validation_rules as $rule => $value) {
            switch ($rule) {
                case 'required':
                    if (!is_bool($value)) {
                        $errors[] = __('Required rule must be boolean.', 'wpmatch');
                    }
                    break;

                case 'min_length':
                case 'max_length':
                    if (!is_numeric($value) || $value < 0) {
                        $errors[] = sprintf(__('%s must be a non-negative number.', 'wpmatch'), $rule);
                    }
                    break;

                case 'min_value':
                case 'max_value':
                    if (!is_numeric($value)) {
                        $errors[] = sprintf(__('%s must be numeric.', 'wpmatch'), $rule);
                    }
                    break;

                case 'regex_pattern':
                    if (!$this->is_valid_regex($value)) {
                        $errors[] = __('Invalid regular expression pattern.', 'wpmatch');
                    }
                    break;
            }
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_validation_rules', __('Invalid validation rules.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate display options
     *
     * @param mixed $display_options Display options
     * @param array $field_data      Complete field data
     * @return bool|WP_Error
     */
    public function validate_display_options($display_options, $field_data = array()) {
        if (!is_array($display_options)) {
            return new WP_Error('invalid_display_options', __('Display options must be an array.', 'wpmatch'));
        }

        // Additional validation can be added here based on specific display options

        return true;
    }

    /**
     * Validate conditional logic
     *
     * @param mixed $conditional_logic Conditional logic
     * @param array $field_data        Complete field data
     * @return bool|WP_Error
     */
    public function validate_conditional_logic($conditional_logic, $field_data = array()) {
        if (!is_array($conditional_logic)) {
            return new WP_Error('invalid_conditional_logic', __('Conditional logic must be an array.', 'wpmatch'));
        }

        $errors = array();

        // Validate show_if conditions
        if (isset($conditional_logic['show_if'])) {
            $show_if_validation = $this->validate_conditional_rule($conditional_logic['show_if']);
            if (is_wp_error($show_if_validation)) {
                $errors = array_merge($errors, $show_if_validation->get_error_data());
            }
        }

        // Validate hide_if conditions
        if (isset($conditional_logic['hide_if'])) {
            $hide_if_validation = $this->validate_conditional_rule($conditional_logic['hide_if']);
            if (is_wp_error($hide_if_validation)) {
                $errors = array_merge($errors, $hide_if_validation->get_error_data());
            }
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_conditional_logic', __('Invalid conditional logic.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate min value
     *
     * @param mixed $min_value   Min value
     * @param array $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_min_value($min_value, $field_data = array()) {
        if ($min_value !== null && !is_numeric($min_value)) {
            return new WP_Error('invalid_min_value', __('Minimum value must be numeric.', 'wpmatch'));
        }

        return true;
    }

    /**
     * Validate max value
     *
     * @param mixed $max_value   Max value
     * @param array $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_max_value($max_value, $field_data = array()) {
        if ($max_value !== null && !is_numeric($max_value)) {
            return new WP_Error('invalid_max_value', __('Maximum value must be numeric.', 'wpmatch'));
        }

        return true;
    }

    /**
     * Validate min length
     *
     * @param mixed $min_length  Min length
     * @param array $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_min_length($min_length, $field_data = array()) {
        if ($min_length !== null && (!is_numeric($min_length) || $min_length < 0 || $min_length > $this->limits['max_min_length'])) {
            return new WP_Error('invalid_min_length', 
                sprintf(__('Minimum length must be between 0 and %d.', 'wpmatch'), $this->limits['max_min_length'])
            );
        }

        return true;
    }

    /**
     * Validate max length
     *
     * @param mixed $max_length  Max length
     * @param array $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_max_length($max_length, $field_data = array()) {
        if ($max_length !== null && (!is_numeric($max_length) || $max_length < 1 || $max_length > $this->limits['max_max_length'])) {
            return new WP_Error('invalid_max_length', 
                sprintf(__('Maximum length must be between 1 and %d.', 'wpmatch'), $this->limits['max_max_length'])
            );
        }

        return true;
    }

    /**
     * Validate regex pattern
     *
     * @param string $regex_pattern Regex pattern
     * @param array  $field_data    Complete field data
     * @return bool|WP_Error
     */
    public function validate_regex_pattern($regex_pattern, $field_data = array()) {
        if (strlen($regex_pattern) > $this->limits['regex_pattern_length']) {
            return new WP_Error('invalid_regex_pattern', 
                sprintf(__('Regex pattern must be %d characters or less.', 'wpmatch'), $this->limits['regex_pattern_length'])
            );
        }

        if (!$this->is_valid_regex($regex_pattern)) {
            return new WP_Error('invalid_regex_pattern', __('Invalid regular expression pattern.', 'wpmatch'));
        }

        return true;
    }

    /**
     * Validate default value
     *
     * @param mixed $default_value Default value
     * @param array $field_data    Complete field data
     * @return bool|WP_Error
     */
    public function validate_default_value($default_value, $field_data = array()) {
        if (is_string($default_value) && strlen($default_value) > $this->limits['default_value_length']) {
            return new WP_Error('invalid_default_value', 
                sprintf(__('Default value must be %d characters or less.', 'wpmatch'), $this->limits['default_value_length'])
            );
        }

        // Validate default value against field type if type registry is available
        if ($this->type_registry && isset($field_data['field_type'])) {
            // Create a mock field object for validation
            $mock_field = (object) array_merge($field_data, array(
                'is_required' => false // Don't require default values
            ));

            $validation = $this->type_registry->validate_field_value($mock_field, $default_value);
            if (is_wp_error($validation)) {
                return new WP_Error('invalid_default_value', 
                    sprintf(__('Default value is not valid for this field type: %s', 'wpmatch'), $validation->get_error_message())
                );
            }
        }

        return true;
    }

    /**
     * Validate field width
     *
     * @param string $field_width Field width
     * @param array  $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_width($field_width, $field_data = array()) {
        $allowed_widths = array('full', 'half', 'third', 'quarter', 'auto');
        
        if (!in_array($field_width, $allowed_widths)) {
            return new WP_Error('invalid_field_width', 
                sprintf(__('Field width must be one of: %s', 'wpmatch'), implode(', ', $allowed_widths))
            );
        }

        return true;
    }

    /**
     * Validate field class
     *
     * @param string $field_class Field CSS class
     * @param array  $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_class($field_class, $field_data = array()) {
        if (strlen($field_class) > $this->limits['field_class_length']) {
            return new WP_Error('invalid_field_class', 
                sprintf(__('Field class must be %d characters or less.', 'wpmatch'), $this->limits['field_class_length'])
            );
        }

        // Validate CSS class name format
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-\s]*$/', $field_class)) {
            return new WP_Error('invalid_field_class', __('Field class contains invalid characters.', 'wpmatch'));
        }

        return true;
    }

    /**
     * Validate field group
     *
     * @param string $field_group Field group
     * @param array  $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_group($field_group, $field_data = array()) {
        if (!preg_match('/^[a-z][a-z0-9_]*[a-z0-9]$/', $field_group)) {
            return new WP_Error('invalid_field_group', 
                __('Field group must contain only lowercase letters, numbers, and underscores.', 'wpmatch')
            );
        }

        return true;
    }

    /**
     * Validate field order
     *
     * @param int   $field_order Field order
     * @param array $field_data  Complete field data
     * @return bool|WP_Error
     */
    public function validate_field_order($field_order, $field_data = array()) {
        if (!is_numeric($field_order) || $field_order < 0 || $field_order > $this->limits['max_field_order']) {
            return new WP_Error('invalid_field_order', 
                sprintf(__('Field order must be between 0 and %d.', 'wpmatch'), $this->limits['max_field_order'])
            );
        }

        return true;
    }

    /**
     * Validate status
     *
     * @param string $status     Field status
     * @param array  $field_data Complete field data
     * @return bool|WP_Error
     */
    public function validate_status($status, $field_data = array()) {
        $allowed_statuses = array('active', 'inactive', 'draft', 'deprecated', 'archived');
        
        if (!in_array($status, $allowed_statuses)) {
            return new WP_Error('invalid_status', 
                sprintf(__('Status must be one of: %s', 'wpmatch'), implode(', ', $allowed_statuses))
            );
        }

        return true;
    }

    /**
     * Validate field type specific requirements
     *
     * @param array $field_data Complete field data
     * @return bool|WP_Error
     */
    private function validate_field_type_specific($field_data) {
        $field_type = $field_data['field_type'];
        $errors = array();

        switch ($field_type) {
            case 'select':
            case 'multiselect':
            case 'radio':
                // These types require choices
                $field_options = $field_data['field_options'] ?? array();
                if (empty($field_options['choices']) || !is_array($field_options['choices'])) {
                    $errors[] = sprintf(__('%s fields must have at least one choice option.', 'wpmatch'), ucfirst($field_type));
                }
                break;

            case 'number':
            case 'range':
                // Validate min/max value relationship
                $min_value = $field_data['min_value'] ?? null;
                $max_value = $field_data['max_value'] ?? null;
                
                if ($min_value !== null && $max_value !== null && $min_value >= $max_value) {
                    $errors[] = __('Minimum value must be less than maximum value.', 'wpmatch');
                }
                break;

            case 'text':
            case 'textarea':
                // Validate min/max length relationship
                $min_length = $field_data['min_length'] ?? null;
                $max_length = $field_data['max_length'] ?? null;
                
                if ($min_length !== null && $max_length !== null && $min_length >= $max_length) {
                    $errors[] = __('Minimum length must be less than maximum length.', 'wpmatch');
                }
                break;
        }

        if (!empty($errors)) {
            return new WP_Error('field_type_validation_failed', __('Field type validation failed.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate cross-field rules
     *
     * @param array $field_data Complete field data
     * @return bool|WP_Error
     */
    private function validate_cross_field_rules($field_data) {
        $errors = array();

        // If field is required, it should generally be public (unless specifically configured otherwise)
        if (!empty($field_data['is_required']) && isset($field_data['is_public']) && !$field_data['is_public']) {
            // This is a warning rather than an error, but could be configurable
        }

        // Searchable fields should generally be public
        if (!empty($field_data['is_searchable']) && isset($field_data['is_public']) && !$field_data['is_public']) {
            $errors[] = __('Searchable fields should generally be public.', 'wpmatch');
        }

        if (!empty($errors)) {
            return new WP_Error('cross_field_validation_failed', __('Cross-field validation failed.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate field choices
     *
     * @param array $choices Field choices
     * @return bool|WP_Error
     */
    private function validate_field_choices($choices) {
        $errors = array();

        if (!is_array($choices)) {
            $errors[] = __('Choices must be an array.', 'wpmatch');
            return new WP_Error('invalid_choices', __('Invalid choices.', 'wpmatch'), $errors);
        }

        if (count($choices) > $this->limits['max_options_count']) {
            $errors[] = sprintf(__('Maximum %d choices allowed.', 'wpmatch'), $this->limits['max_options_count']);
        }

        if (empty($choices)) {
            $errors[] = __('At least one choice is required.', 'wpmatch');
        }

        // Validate individual choices
        foreach ($choices as $key => $value) {
            if (is_string($value) && strlen($value) > 255) {
                $errors[] = __('Choice labels must be 255 characters or less.', 'wpmatch');
            }
            
            if (empty(trim($value))) {
                $errors[] = __('Choice labels cannot be empty.', 'wpmatch');
            }
        }

        // Check for duplicate values
        $values = array_values($choices);
        if (count($values) !== count(array_unique($values))) {
            $errors[] = __('Duplicate choice values are not allowed.', 'wpmatch');
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_choices', __('Invalid choices.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Validate conditional rule
     *
     * @param array $rule Conditional rule
     * @return bool|WP_Error
     */
    private function validate_conditional_rule($rule) {
        $errors = array();

        if (!is_array($rule)) {
            $errors[] = __('Conditional rule must be an array.', 'wpmatch');
            return new WP_Error('invalid_conditional_rule', __('Invalid conditional rule.', 'wpmatch'), $errors);
        }

        $required_keys = array('field', 'operator', 'value');
        foreach ($required_keys as $key) {
            if (!isset($rule[$key])) {
                $errors[] = sprintf(__('Conditional rule missing required key: %s', 'wpmatch'), $key);
            }
        }

        if (isset($rule['operator'])) {
            $allowed_operators = array('equals', 'not_equals', 'contains', 'not_contains', 'greater_than', 'less_than', 'empty', 'not_empty');
            if (!in_array($rule['operator'], $allowed_operators)) {
                $errors[] = sprintf(__('Invalid conditional operator: %s', 'wpmatch'), $rule['operator']);
            }
        }

        if (!empty($errors)) {
            return new WP_Error('invalid_conditional_rule', __('Invalid conditional rule.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Check if regex pattern is valid
     *
     * @param string $pattern Regex pattern
     * @return bool True if valid, false otherwise
     */
    private function is_valid_regex($pattern) {
        if (empty($pattern)) {
            return true; // Empty pattern is valid (no validation)
        }

        // Test the regex pattern
        $test_result = @preg_match($pattern, 'test');
        return $test_result !== false;
    }

    /**
     * Validate user field value
     *
     * @param object $field Field configuration
     * @param mixed  $value Field value
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_user_field_value($field, $value) {
        // Use type registry if available
        if ($this->type_registry) {
            return $this->type_registry->validate_field_value($field, $value);
        }

        // Basic fallback validation
        if ($field->is_required && empty($value)) {
            return new WP_Error('required_field', 
                sprintf(__('The %s field is required.', 'wpmatch'), $field->field_label)
            );
        }

        return true;
    }

    /**
     * Sanitize user field value
     *
     * @param object $field Field configuration
     * @param mixed  $value Field value
     * @return mixed Sanitized value
     */
    public function sanitize_user_field_value($field, $value) {
        // Use type registry if available
        if ($this->type_registry) {
            return $this->type_registry->sanitize_field_value($field, $value);
        }

        // Basic fallback sanitization
        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        return $value;
    }

    /**
     * Get validation limits
     *
     * @return array Validation limits
     */
    public function get_limits() {
        return $this->limits;
    }

    /**
     * Get reserved field names
     *
     * @return array Reserved field names
     */
    public function get_reserved_names() {
        return $this->reserved_names;
    }

    /**
     * Get allowed field types
     *
     * @return array Allowed field types
     */
    public function get_allowed_field_types() {
        return $this->allowed_field_types;
    }
}