<?php
/**
 * Frontend Field Renderer for WPMatch
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Frontend Field Renderer class
 * 
 * Handles rendering of profile fields on the frontend including forms,
 * profile displays, and search filters.
 */
class WPMatch_Frontend_Field_Renderer {

    /**
     * Field manager instance
     *
     * @var WPMatch_Profile_Field_Manager
     */
    private $field_manager;

    /**
     * Field type registry instance
     *
     * @var WPMatch_Field_Type_Registry
     */
    private $type_registry;

    /**
     * Field validator instance
     *
     * @var WPMatch_Field_Validator
     */
    private $validator;

    /**
     * Render context (form, display, search)
     *
     * @var string
     */
    private $context = 'form';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'), 20);
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_shortcode('wpmatch_profile_form', array($this, 'render_profile_form_shortcode'));
        add_shortcode('wpmatch_profile_display', array($this, 'render_profile_display_shortcode'));
        add_shortcode('wpmatch_field', array($this, 'render_single_field_shortcode'));
        
        // AJAX handlers for frontend
        add_action('wp_ajax_wpmatch_frontend_save_profile', array($this, 'ajax_save_profile'));
        add_action('wp_ajax_nopriv_wpmatch_frontend_save_profile', array($this, 'ajax_save_profile'));
        add_action('wp_ajax_wpmatch_frontend_get_field_options', array($this, 'ajax_get_field_options'));
        add_action('wp_ajax_nopriv_wpmatch_frontend_get_field_options', array($this, 'ajax_get_field_options'));
        
        // Template hooks
        add_action('wpmatch_before_profile_form', array($this, 'render_form_notices'));
        add_action('wpmatch_after_profile_form', array($this, 'render_form_scripts'));
    }

    /**
     * Initialize dependencies
     */
    public function init() {
        // Load dependencies
        require_once WPMATCH_INCLUDES_PATH . 'class-profile-field-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-field-type-registry.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-field-validator.php';

        // Initialize managers
        $this->field_manager = new WPMatch_Profile_Field_Manager();
        $this->type_registry = new WPMatch_Field_Type_Registry();
        $this->validator = new WPMatch_Field_Validator();
        
        // Set validator dependencies
        $this->validator->set_type_registry($this->type_registry);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages that need field rendering
        if (!$this->should_enqueue_assets()) {
            return;
        }

        // Enqueue frontend field styles
        wp_enqueue_style(
            'wpmatch-frontend-fields',
            WPMATCH_PLUGIN_URL . 'assets/css/frontend-fields.css',
            array(),
            WPMATCH_VERSION
        );

        // Enqueue frontend field scripts
        wp_enqueue_script(
            'wpmatch-frontend-fields',
            WPMATCH_PLUGIN_URL . 'assets/js/frontend-fields.js',
            array('jquery', 'wp-util'),
            WPMATCH_VERSION,
            true
        );

        // Localize script
        wp_localize_script('wpmatch-frontend-fields', 'wpMatchFields', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmatch_frontend_nonce'),
            'strings' => array(
                'saving' => __('Saving...', 'wpmatch'),
                'saved' => __('Profile saved successfully!', 'wpmatch'),
                'error' => __('An error occurred. Please try again.', 'wpmatch'),
                'required' => __('This field is required.', 'wpmatch'),
                'invalid' => __('Please enter a valid value.', 'wpmatch'),
                'uploading' => __('Uploading...', 'wpmatch'),
                'upload_error' => __('Upload failed. Please try again.', 'wpmatch'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'wpmatch'),
                'loading' => __('Loading...', 'wpmatch')
            ),
            'validation' => array(
                'email_pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
                'url_pattern' => '^https?://.+$',
                'phone_pattern' => '^[+]?[(]?[\s\d\-\(\)]{10,}$'
            ),
            'upload' => array(
                'max_file_size' => wp_max_upload_size(),
                'allowed_types' => wp_get_mime_types()
            )
        ));
    }

    /**
     * Check if assets should be enqueued
     *
     * @return bool True if assets should be enqueued
     */
    private function should_enqueue_assets() {
        global $post;

        // Check for shortcodes
        if ($post && (
            has_shortcode($post->post_content, 'wpmatch_profile_form') ||
            has_shortcode($post->post_content, 'wpmatch_profile_display') ||
            has_shortcode($post->post_content, 'wpmatch_field')
        )) {
            return true;
        }

        // Check for specific pages/templates
        if (is_page(array('profile', 'edit-profile', 'search', 'members'))) {
            return true;
        }

        // Check for WPMatch query vars
        if (get_query_var('wp_dating_profile') || get_query_var('wp_dating_search')) {
            return true;
        }

        return false;
    }

    /**
     * Render profile form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered form HTML
     */
    public function render_profile_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'fields' => '', // Comma-separated field names
            'groups' => '', // Comma-separated group names
            'layout' => 'vertical', // vertical, horizontal, grid
            'show_labels' => 'true',
            'show_descriptions' => 'true',
            'ajax' => 'true',
            'redirect' => '',
            'class' => ''
        ), $atts);

        if (!$atts['user_id'] || !current_user_can('edit_user', $atts['user_id'])) {
            return '<p>' . __('You do not have permission to edit this profile.', 'wpmatch') . '</p>';
        }

        return $this->render_profile_form($atts);
    }

    /**
     * Render profile display shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered display HTML
     */
    public function render_profile_display_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'fields' => '', // Comma-separated field names
            'groups' => '', // Comma-separated group names
            'layout' => 'vertical', // vertical, horizontal, grid, card
            'show_labels' => 'true',
            'show_empty' => 'false',
            'class' => ''
        ), $atts);

        if (!$atts['user_id']) {
            return '<p>' . __('Invalid user.', 'wpmatch') . '</p>';
        }

        return $this->render_profile_display($atts);
    }

    /**
     * Render single field shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered field HTML
     */
    public function render_single_field_shortcode($atts) {
        $atts = shortcode_atts(array(
            'field' => '', // Field name
            'user_id' => get_current_user_id(),
            'context' => 'display', // form, display
            'show_label' => 'true',
            'show_description' => 'false',
            'class' => ''
        ), $atts);

        if (!$atts['field']) {
            return '<p>' . __('Field name is required.', 'wpmatch') . '</p>';
        }

        $field = $this->field_manager->get_field_by_name($atts['field']);
        if (!$field) {
            return '<p>' . __('Field not found.', 'wpmatch') . '</p>';
        }

        $this->context = $atts['context'];
        return $this->render_single_field($field, $atts['user_id'], $atts);
    }

    /**
     * Render complete profile form
     *
     * @param array $args Form arguments
     * @return string Rendered form HTML
     */
    public function render_profile_form($args) {
        $this->context = 'form';
        
        // Get fields to display
        $fields = $this->get_fields_for_form($args);
        
        if (empty($fields)) {
            return '<p>' . __('No profile fields available.', 'wpmatch') . '</p>';
        }

        // Get user field values
        $user_values = $this->get_user_field_values($args['user_id']);

        ob_start();
        ?>
        <div class="wpmatch-profile-form-container <?php echo esc_attr($args['class']); ?>">
            <?php do_action('wpmatch_before_profile_form', $args); ?>
            
            <form class="wpmatch-profile-form" 
                  data-user-id="<?php echo esc_attr($args['user_id']); ?>"
                  data-ajax="<?php echo esc_attr($args['ajax']); ?>"
                  data-redirect="<?php echo esc_attr($args['redirect']); ?>">
                
                <?php wp_nonce_field('wpmatch_save_profile', 'wpmatch_profile_nonce'); ?>
                
                <div class="form-fields layout-<?php echo esc_attr($args['layout']); ?>">
                    <?php
                    $grouped_fields = $this->group_fields_by_group($fields);
                    
                    foreach ($grouped_fields as $group_name => $group_fields) {
                        $this->render_field_group($group_name, $group_fields, $user_values, $args);
                    }
                    ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="wpmatch-btn wpmatch-btn-primary">
                        <?php _e('Save Profile', 'wpmatch'); ?>
                    </button>
                    
                    <?php if ($args['ajax'] === 'true'): ?>
                        <span class="form-loading" style="display: none;">
                            <?php _e('Saving...', 'wpmatch'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php do_action('wpmatch_after_profile_form', $args); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render profile display
     *
     * @param array $args Display arguments
     * @return string Rendered display HTML
     */
    public function render_profile_display($args) {
        $this->context = 'display';
        
        // Get fields to display
        $fields = $this->get_fields_for_display($args);
        
        if (empty($fields)) {
            return '<p>' . __('No profile information available.', 'wpmatch') . '</p>';
        }

        // Get user field values
        $user_values = $this->get_user_field_values($args['user_id']);

        ob_start();
        ?>
        <div class="wpmatch-profile-display-container <?php echo esc_attr($args['class']); ?>">
            <div class="profile-fields layout-<?php echo esc_attr($args['layout']); ?>">
                <?php
                $grouped_fields = $this->group_fields_by_group($fields);
                
                foreach ($grouped_fields as $group_name => $group_fields) {
                    $this->render_display_group($group_name, $group_fields, $user_values, $args);
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render field group for forms
     *
     * @param string $group_name   Group name
     * @param array  $fields       Group fields
     * @param array  $user_values  User field values
     * @param array  $args         Form arguments
     */
    private function render_field_group($group_name, $fields, $user_values, $args) {
        if (empty($fields)) {
            return;
        }

        $group_label = $this->get_group_label($group_name);
        ?>
        <div class="field-group field-group-<?php echo esc_attr($group_name); ?>">
            <?php if ($group_label): ?>
                <h3 class="group-title"><?php echo esc_html($group_label); ?></h3>
            <?php endif; ?>
            
            <div class="group-fields">
                <?php foreach ($fields as $field): ?>
                    <div class="field-wrapper field-type-<?php echo esc_attr($field->field_type); ?> field-width-<?php echo esc_attr($field->field_width); ?>">
                        <?php echo $this->render_form_field($field, $user_values, $args); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render display group
     *
     * @param string $group_name   Group name
     * @param array  $fields       Group fields
     * @param array  $user_values  User field values
     * @param array  $args         Display arguments
     */
    private function render_display_group($group_name, $fields, $user_values, $args) {
        $visible_fields = array();
        
        // Filter out empty fields if show_empty is false
        foreach ($fields as $field) {
            $value = $user_values[$field->field_name] ?? '';
            if ($args['show_empty'] === 'true' || !empty($value)) {
                $visible_fields[] = $field;
            }
        }

        if (empty($visible_fields)) {
            return;
        }

        $group_label = $this->get_group_label($group_name);
        ?>
        <div class="display-group display-group-<?php echo esc_attr($group_name); ?>">
            <?php if ($group_label): ?>
                <h3 class="group-title"><?php echo esc_html($group_label); ?></h3>
            <?php endif; ?>
            
            <div class="group-fields">
                <?php foreach ($visible_fields as $field): ?>
                    <div class="field-display field-type-<?php echo esc_attr($field->field_type); ?>">
                        <?php echo $this->render_display_field($field, $user_values, $args); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render form field
     *
     * @param object $field       Field configuration
     * @param array  $user_values User field values
     * @param array  $args        Form arguments
     * @return string Rendered field HTML
     */
    private function render_form_field($field, $user_values, $args) {
        $value = $user_values[$field->field_name] ?? $field->default_value ?? '';
        
        ob_start();
        ?>
        <div class="field-container" data-field-name="<?php echo esc_attr($field->field_name); ?>">
            <?php if ($args['show_labels'] === 'true'): ?>
                <label for="field_<?php echo esc_attr($field->field_name); ?>" class="field-label">
                    <?php echo esc_html($field->field_label); ?>
                    <?php if ($field->is_required): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            
            <?php if ($args['show_descriptions'] === 'true' && !empty($field->field_description)): ?>
                <div class="field-description">
                    <?php echo esc_html($field->field_description); ?>
                </div>
            <?php endif; ?>
            
            <div class="field-input">
                <?php echo $this->type_registry->render_field($field, $value, array(
                    'context' => 'form',
                    'id' => 'field_' . $field->field_name,
                    'name' => 'fields[' . $field->field_name . ']',
                    'class' => 'wpmatch-field',
                    'required' => $field->is_required,
                    'placeholder' => $field->placeholder_text
                )); ?>
            </div>
            
            <?php if (!empty($field->help_text)): ?>
                <div class="field-help">
                    <?php echo esc_html($field->help_text); ?>
                </div>
            <?php endif; ?>
            
            <div class="field-error" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render display field
     *
     * @param object $field       Field configuration
     * @param array  $user_values User field values
     * @param array  $args        Display arguments
     * @return string Rendered field HTML
     */
    private function render_display_field($field, $user_values, $args) {
        $value = $user_values[$field->field_name] ?? '';
        
        // Don't display empty non-public fields
        if (empty($value) && !$field->is_public) {
            return '';
        }

        ob_start();
        ?>
        <div class="field-display-item" data-field-name="<?php echo esc_attr($field->field_name); ?>">
            <?php if ($args['show_labels'] === 'true'): ?>
                <div class="field-label">
                    <?php echo esc_html($field->field_label); ?>
                </div>
            <?php endif; ?>
            
            <div class="field-value">
                <?php echo $this->type_registry->render_field($field, $value, array(
                    'context' => 'display',
                    'class' => 'wpmatch-field-display'
                )); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render single field
     *
     * @param object $field   Field configuration
     * @param int    $user_id User ID
     * @param array  $args    Rendering arguments
     * @return string Rendered field HTML
     */
    private function render_single_field($field, $user_id, $args) {
        $user_values = $this->get_user_field_values($user_id);
        
        if ($this->context === 'form') {
            return $this->render_form_field($field, $user_values, $args);
        } else {
            return $this->render_display_field($field, $user_values, $args);
        }
    }

    /**
     * Get fields for form rendering
     *
     * @param array $args Form arguments
     * @return array Fields array
     */
    private function get_fields_for_form($args) {
        $query_args = array(
            'status' => 'active',
            'is_editable' => true
        );

        // Filter by specific fields if provided
        if (!empty($args['fields'])) {
            $field_names = array_map('trim', explode(',', $args['fields']));
            $fields = array();
            
            foreach ($field_names as $field_name) {
                $field = $this->field_manager->get_field_by_name($field_name);
                if ($field && $field->status === 'active' && $field->is_editable) {
                    $fields[] = $field;
                }
            }
            
            return $fields;
        }

        // Filter by specific groups if provided
        if (!empty($args['groups'])) {
            $group_names = array_map('trim', explode(',', $args['groups']));
            $query_args['field_group'] = $group_names;
        }

        return $this->field_manager->get_fields($query_args);
    }

    /**
     * Get fields for display rendering
     *
     * @param array $args Display arguments
     * @return array Fields array
     */
    private function get_fields_for_display($args) {
        $query_args = array(
            'status' => 'active',
            'is_public' => true
        );

        // Filter by specific fields if provided
        if (!empty($args['fields'])) {
            $field_names = array_map('trim', explode(',', $args['fields']));
            $fields = array();
            
            foreach ($field_names as $field_name) {
                $field = $this->field_manager->get_field_by_name($field_name);
                if ($field && $field->status === 'active' && $field->is_public) {
                    $fields[] = $field;
                }
            }
            
            return $fields;
        }

        // Filter by specific groups if provided
        if (!empty($args['groups'])) {
            $group_names = array_map('trim', explode(',', $args['groups']));
            $query_args['field_group'] = $group_names;
        }

        return $this->field_manager->get_fields($query_args);
    }

    /**
     * Get user field values
     *
     * @param int $user_id User ID
     * @return array User field values
     */
    private function get_user_field_values($user_id) {
        // Use performance optimizer if available
        if (class_exists('WPMatch_Performance_Optimizer')) {
            return WPMatch_Performance_Optimizer::get_optimized_user_fields($user_id);
        }

        // Fallback implementation
        global $wpdb;
        
        if (!$this->field_manager || !$this->field_manager->database) {
            return array();
        }

        $values_table = $this->field_manager->database->get_table_name('profile_values');
        $fields_table = $this->field_manager->database->get_table_name('profile_fields');
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT pf.field_name, pv.field_value
            FROM {$values_table} pv
            INNER JOIN {$fields_table} pf ON pv.field_id = pf.id
            WHERE pv.user_id = %d AND pf.status = 'active'
        ", $user_id));
        
        $values = array();
        foreach ($results as $result) {
            $values[$result->field_name] = $result->field_value;
        }
        
        return $values;
    }

    /**
     * Group fields by field group
     *
     * @param array $fields Fields array
     * @return array Grouped fields
     */
    private function group_fields_by_group($fields) {
        $grouped = array();
        
        foreach ($fields as $field) {
            $group = $field->field_group ?? 'basic';
            if (!isset($grouped[$group])) {
                $grouped[$group] = array();
            }
            $grouped[$group][] = $field;
        }
        
        return $grouped;
    }

    /**
     * Get group label
     *
     * @param string $group_name Group name
     * @return string Group label
     */
    private function get_group_label($group_name) {
        $labels = array(
            'basic' => __('Basic Information', 'wpmatch'),
            'personal' => __('Personal Details', 'wpmatch'),
            'lifestyle' => __('Lifestyle', 'wpmatch'),
            'interests' => __('Interests', 'wpmatch'),
            'preferences' => __('Preferences', 'wpmatch'),
            'contact' => __('Contact Information', 'wpmatch'),
            'appearance' => __('Appearance', 'wpmatch'),
            'background' => __('Background', 'wpmatch')
        );
        
        // Check for custom group metadata
        $group_metadata = get_option('wpmatch_field_group_' . $group_name, array());
        if (!empty($group_metadata['label'])) {
            return $group_metadata['label'];
        }
        
        return $labels[$group_name] ?? ucfirst(str_replace('_', ' ', $group_name));
    }

    /**
     * Render form notices
     */
    public function render_form_notices() {
        ?>
        <div class="wpmatch-form-notices" style="display: none;">
            <div class="notice notice-success">
                <p class="success-message"></p>
            </div>
            <div class="notice notice-error">
                <p class="error-message"></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render form scripts
     */
    public function render_form_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize field validation and AJAX form submission
            if (typeof wpMatchFields !== 'undefined') {
                $('.wpmatch-profile-form').each(function() {
                    new WPMatchProfileForm(this);
                });
            }
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for saving profile
     */
    public function ajax_save_profile() {
        check_ajax_referer('wpmatch_frontend_nonce', 'nonce');
        
        $user_id = absint($_POST['user_id'] ?? 0);
        
        if (!$user_id || !current_user_can('edit_user', $user_id)) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        $fields_data = $_POST['fields'] ?? array();
        
        if (empty($fields_data)) {
            wp_send_json_error(__('No field data provided.', 'wpmatch'));
        }

        $errors = array();
        $saved_fields = array();

        foreach ($fields_data as $field_name => $field_value) {
            $field = $this->field_manager->get_field_by_name($field_name);
            
            if (!$field || $field->status !== 'active' || !$field->is_editable) {
                continue;
            }

            // Validate field value
            $validation_result = $this->validator->validate_user_field_value($field, $field_value);
            
            if (is_wp_error($validation_result)) {
                $errors[$field_name] = $validation_result->get_error_message();
                continue;
            }

            // Sanitize field value
            $sanitized_value = $this->validator->sanitize_user_field_value($field, $field_value);
            
            // Save field value
            $save_result = $this->save_user_field_value($user_id, $field->id, $sanitized_value);
            
            if (is_wp_error($save_result)) {
                $errors[$field_name] = $save_result->get_error_message();
            } else {
                $saved_fields[] = $field_name;
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => __('Some fields could not be saved.', 'wpmatch'),
                'field_errors' => $errors,
                'saved_fields' => $saved_fields
            ));
        }

        // Clear user field cache
        if (class_exists('WPMatch_Performance_Optimizer')) {
            WPMatch_Performance_Optimizer::clear_user_field_caches($user_id);
        }

        wp_send_json_success(array(
            'message' => __('Profile saved successfully!', 'wpmatch'),
            'saved_fields' => $saved_fields
        ));
    }

    /**
     * AJAX handler for getting field options
     */
    public function ajax_get_field_options() {
        check_ajax_referer('wpmatch_frontend_nonce', 'nonce');
        
        $field_name = sanitize_text_field($_POST['field_name'] ?? '');
        
        if (empty($field_name)) {
            wp_send_json_error(__('Field name is required.', 'wpmatch'));
        }

        $field = $this->field_manager->get_field_by_name($field_name);
        
        if (!$field || $field->status !== 'active') {
            wp_send_json_error(__('Field not found.', 'wpmatch'));
        }

        $options = $field->field_options ?? array();
        
        wp_send_json_success(array(
            'field_name' => $field_name,
            'field_type' => $field->field_type,
            'options' => $options
        ));
    }

    /**
     * Save user field value
     *
     * @param int    $user_id    User ID
     * @param int    $field_id   Field ID
     * @param mixed  $value      Field value
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function save_user_field_value($user_id, $field_id, $value) {
        global $wpdb;
        
        if (!$this->field_manager || !$this->field_manager->database) {
            return new WP_Error('database_error', __('Database connection error.', 'wpmatch'));
        }

        $table_name = $this->field_manager->database->get_table_name('profile_values');
        
        // Check if value already exists
        $existing_value = $wpdb->get_var($wpdb->prepare(
            "SELECT field_value FROM {$table_name} WHERE user_id = %d AND field_id = %d",
            $user_id, $field_id
        ));

        $data = array(
            'user_id' => $user_id,
            'field_id' => $field_id,
            'field_value' => $value,
            'updated_at' => current_time('mysql')
        );

        if ($existing_value !== null) {
            // Update existing value
            $result = $wpdb->update(
                $table_name,
                $data,
                array('user_id' => $user_id, 'field_id' => $field_id),
                array('%d', '%d', '%s', '%s'),
                array('%d', '%d')
            );
        } else {
            // Insert new value
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $table_name,
                $data,
                array('%d', '%d', '%s', '%s', '%s')
            );
        }

        if ($result === false) {
            return new WP_Error('save_error', __('Failed to save field value.', 'wpmatch'));
        }

        return true;
    }
}

// Initialize frontend field renderer
new WPMatch_Frontend_Field_Renderer();