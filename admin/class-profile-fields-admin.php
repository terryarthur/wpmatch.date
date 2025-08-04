<?php
/**
 * Profile Fields Admin class for WordPress admin interface
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Profile Fields Admin class
 * 
 * Handles the admin interface for profile fields management including
 * admin pages, AJAX handlers, and integration with WordPress admin.
 */
class WPMatch_Profile_Fields_Admin {

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
     * List table instance
     *
     * @var WPMatch_Profile_Fields_List_Table
     */
    private $list_table;

    /**
     * Constructor
     *
     * @param WPMatch_Profile_Field_Manager $field_manager Field manager instance
     * @param WPMatch_Field_Type_Registry $type_registry Field type registry instance  
     * @param WPMatch_Field_Validator $validator Field validator instance
     */
    public function __construct($field_manager = null, $type_registry = null, $validator = null) {
        $this->field_manager = $field_manager;
        $this->type_registry = $type_registry;
        $this->validator = $validator;
        
        add_action('init', array($this, 'init'), 20);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        
        // AJAX handlers
        add_action('wp_ajax_wpmatch_field_action', array($this, 'handle_ajax_field_action'));
        add_action('wp_ajax_wpmatch_create_field', array($this, 'ajax_create_field'));
        add_action('wp_ajax_wpmatch_update_field', array($this, 'ajax_update_field'));
        add_action('wp_ajax_wpmatch_delete_field', array($this, 'ajax_delete_field'));
        add_action('wp_ajax_wpmatch_get_field', array($this, 'ajax_get_field'));
        add_action('wp_ajax_wpmatch_get_fields', array($this, 'ajax_get_fields'));
        add_action('wp_ajax_wpmatch_reorder_fields', array($this, 'ajax_reorder_fields'));
        add_action('wp_ajax_wpmatch_validate_field', array($this, 'ajax_validate_field'));
        add_action('wp_ajax_wpmatch_get_field_types', array($this, 'ajax_get_field_types'));
        add_action('wp_ajax_wpmatch_duplicate_field', array($this, 'ajax_duplicate_field'));
        add_action('wp_ajax_wpmatch_create_default_fields', array($this, 'ajax_create_default_fields'));
        
        // Field groups
        add_action('wp_ajax_wpmatch_create_field_group', array($this, 'ajax_create_field_group'));
        add_action('wp_ajax_wpmatch_update_field_group', array($this, 'ajax_update_field_group'));
        add_action('wp_ajax_wpmatch_get_field_groups', array($this, 'ajax_get_field_groups'));
        add_action('wp_ajax_wpmatch_bulk_field_operations', array($this, 'ajax_bulk_field_operations'));
    }

    /**
     * Initialize dependencies
     */
    public function init() {
        // Load dependencies if not already provided
        if (!$this->field_manager) {
            require_once WPMATCH_INCLUDES_PATH . 'class-profile-field-manager.php';
            $this->field_manager = new WPMatch_Profile_Field_Manager();
        }
        
        if (!$this->type_registry) {
            require_once WPMATCH_INCLUDES_PATH . 'class-field-type-registry.php';
            $this->type_registry = new WPMatch_Field_Type_Registry();
        }
        
        if (!$this->validator) {
            require_once WPMATCH_INCLUDES_PATH . 'class-field-validator.php';
            $this->validator = new WPMatch_Field_Validator();
        }

        // Initialize list table
        require_once WPMATCH_ADMIN_PATH . 'class-profile-fields-list-table.php';
        $this->list_table = new WPMatch_Profile_Fields_List_Table($this->field_manager, $this->type_registry);
        $this->type_registry = new WPMatch_Field_Type_Registry();
        $this->validator = new WPMatch_Field_Validator();
        
        // Set validator dependencies
        $this->validator->set_type_registry($this->type_registry);
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Profile Fields submenu (replaces existing one)
        remove_submenu_page('wpmatch', 'wpmatch-profile-fields');
        
        add_submenu_page(
            'wpmatch',
            __('Profile Fields', 'wpmatch'),
            __('Profile Fields', 'wpmatch'),
            'manage_profile_fields',
            'wpmatch-profile-fields',
            array($this, 'admin_page_profile_fields')
        );

        // Field Groups submenu
        add_submenu_page(
            'wpmatch',
            __('Field Groups', 'wpmatch'),
            __('Field Groups', 'wpmatch'),
            'manage_profile_fields',
            'wpmatch-field-groups',
            array($this, 'admin_page_field_groups')
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix Current admin page
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Only load on profile fields pages
        if (!in_array($hook_suffix, array('wpmatch_page_wpmatch-profile-fields', 'wpmatch_page_wpmatch-field-groups'))) {
            return;
        }

        // Enqueue WordPress dependencies
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

        // Profile fields admin script
        wp_enqueue_script(
            'wpmatch-profile-fields-admin',
            WPMATCH_PLUGIN_URL . 'assets/js/admin-profile-fields.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-dialog', 'wp-util'),
            WPMATCH_VERSION,
            true
        );

        // Profile fields admin styles
        wp_enqueue_style(
            'wpmatch-profile-fields-admin',
            WPMATCH_PLUGIN_URL . 'assets/css/admin-profile-fields.css',
            array('wp-jquery-ui-dialog'),
            WPMATCH_VERSION
        );

        // Localize script
        wp_localize_script('wpmatch-profile-fields-admin', 'wpMatchFieldsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmatch_field_admin_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this field? This action cannot be undone.', 'wpmatch'),
                'confirmDeleteWithData' => __('This field has user data. Are you sure you want to delete it? Data will be preserved for 30 days.', 'wpmatch'),
                'processing' => __('Processing...', 'wpmatch'),
                'saved' => __('Saved successfully!', 'wpmatch'),
                'error' => __('An error occurred. Please try again.', 'wpmatch'),
                'fieldCreated' => __('Field created successfully!', 'wpmatch'),
                'fieldUpdated' => __('Field updated successfully!', 'wpmatch'),
                'fieldDeleted' => __('Field deleted successfully!', 'wpmatch'),
                'fieldDuplicated' => __('Field duplicated successfully!', 'wpmatch'),
                'validationFailed' => __('Validation failed. Please check your input.', 'wpmatch'),
                'requiredField' => __('This field is required.', 'wpmatch'),
                'invalidFieldName' => __('Field name must contain only lowercase letters, numbers, and underscores.', 'wpmatch'),
                'fieldNameExists' => __('A field with this name already exists.', 'wpmatch'),
                'addChoice' => __('Add Choice', 'wpmatch'),
                'removeChoice' => __('Remove', 'wpmatch'),
                'choiceValue' => __('Value', 'wpmatch'),
                'choiceLabel' => __('Label', 'wpmatch'),
                'noFieldsFound' => __('No fields found.', 'wpmatch'),
                'loadingFields' => __('Loading fields...', 'wpmatch'),
                'orderSaved' => __('Field order saved successfully!', 'wpmatch')
            ),
            'fieldTypes' => $this->type_registry->get_field_types_for_select(),
            'limits' => $this->validator->get_limits(),
            'reservedNames' => $this->validator->get_reserved_names()
        ));
    }

    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        // Handle bulk actions if needed
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $field_ids = isset($_POST['field_ids']) ? array_map('intval', $_POST['field_ids']) : array();

        if (empty($action) || empty($field_ids) || !current_user_can('manage_profile_fields')) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-fields')) {
            wp_die(__('Security check failed.', 'wpmatch'));
        }

        switch ($action) {
            case 'activate':
                $this->bulk_update_field_status($field_ids, 'active');
                break;
            case 'deactivate':
                $this->bulk_update_field_status($field_ids, 'inactive');
                break;
            case 'delete':
                $this->bulk_delete_fields($field_ids);
                break;
        }

        // Redirect to avoid resubmission
        wp_redirect(add_query_arg('bulk_action', $action, admin_url('admin.php?page=wpmatch-profile-fields')));
        exit;
    }

    /**
     * Profile Fields admin page
     */
    public function admin_page_profile_fields() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $field_id = isset($_GET['field_id']) ? absint($_GET['field_id']) : 0;

        switch ($action) {
            case 'add':
                $this->render_field_form();
                break;
            case 'edit':
                $this->render_field_form($field_id);
                break;
            case 'view':
                $this->render_field_view($field_id);
                break;
            default:
                $this->render_fields_list();
                break;
        }
    }

    /**
     * Field Groups admin page
     */
    public function admin_page_field_groups() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wpmatch-field-groups-admin">
                <p><?php _e('Organize your profile fields into logical groups for better user experience.', 'wpmatch'); ?></p>
                
                <!-- Field Groups management interface -->
                <div id="field-groups-container">
                    <div class="loading-spinner">
                        <span class="spinner is-active"></span>
                        <?php _e('Loading field groups...', 'wpmatch'); ?>
                    </div>
                </div>
                
                <!-- Add new group button -->
                <button type="button" class="button button-primary" id="add-field-group">
                    <?php _e('Add New Group', 'wpmatch'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render fields list
     */
    private function render_fields_list() {
        // Initialize list table
        if (!$this->list_table) {
            $this->list_table = new WPMatch_Profile_Fields_List_Table();
        }

        $this->list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            
            <a href="<?php echo admin_url('admin.php?page=wpmatch-profile-fields&action=add'); ?>" class="page-title-action">
                <?php _e('Add New Field', 'wpmatch'); ?>
            </a>
            
            <button type="button" class="page-title-action" id="create-default-fields" onclick="wpmatchCreateDefaultFields()">
                <?php _e('Create Default Dating Fields', 'wpmatch'); ?>
            </button>

            <hr class="wp-header-end">

            <?php $this->render_admin_notices(); ?>

            <div class="wpmatch-fields-admin">
                <!-- Filters and search -->
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <?php $this->list_table->bulk_actions(); ?>
                        
                        <select name="field_type_filter" id="field-type-filter">
                            <option value=""><?php _e('All Types', 'wpmatch'); ?></option>
                            <?php foreach ($this->type_registry->get_field_types_for_select() as $type => $label): ?>
                                <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="field_group_filter" id="field-group-filter">
                            <option value=""><?php _e('All Groups', 'wpmatch'); ?></option>
                            <?php foreach ($this->field_manager->get_field_groups() as $group): ?>
                                <option value="<?php echo esc_attr($group->field_group); ?>">
                                    <?php echo esc_html(ucfirst($group->field_group)); ?> (<?php echo $group->field_count; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="button" class="button" id="filter-fields">
                            <?php _e('Filter', 'wpmatch'); ?>
                        </button>
                    </div>
                    
                    <div class="alignright actions">
                        <button type="button" class="button" id="toggle-field-order">
                            <?php _e('Reorder Fields', 'wpmatch'); ?>
                        </button>
                    </div>
                </div>

                <!-- Fields table -->
                <form method="post" id="fields-form">
                    <?php
                    wp_nonce_field('bulk-fields');
                    $this->list_table->display();
                    ?>
                </form>
            </div>

            <!-- Field order interface (hidden by default) -->
            <div id="field-order-interface" style="display: none;">
                <h2><?php _e('Reorder Profile Fields', 'wpmatch'); ?></h2>
                <p><?php _e('Drag and drop fields to change their order. Fields are organized by groups.', 'wpmatch'); ?></p>
                
                <div id="field-order-groups">
                    <!-- Populated via AJAX -->
                </div>
                
                <div class="field-order-actions">
                    <button type="button" class="button button-primary" id="save-field-order">
                        <?php _e('Save Order', 'wpmatch'); ?>
                    </button>
                    <button type="button" class="button" id="cancel-field-order">
                        <?php _e('Cancel', 'wpmatch'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render field form (add/edit)
     *
     * @param int $field_id Optional field ID for editing
     */
    private function render_field_form($field_id = 0) {
        $field = null;
        $is_edit = false;

        if ($field_id) {
            $field = $this->field_manager->get_field($field_id);
            if (!$field) {
                wp_die(__('Field not found.', 'wpmatch'));
            }
            $is_edit = true;
        }

        ?>
        <div class="wrap">
            <h1>
                <?php echo $is_edit ? __('Edit Profile Field', 'wpmatch') : __('Add New Profile Field', 'wpmatch'); ?>
                <a href="<?php echo admin_url('admin.php?page=wpmatch-profile-fields'); ?>" class="page-title-action">
                    <?php _e('Back to Fields', 'wpmatch'); ?>
                </a>
            </h1>

            <hr class="wp-header-end">

            <?php $this->render_admin_notices(); ?>

            <div class="wpmatch-field-form">
                <form method="post" id="field-form" data-field-id="<?php echo $field_id; ?>">
                    <?php wp_nonce_field('wpmatch_field_form', '_wpnonce'); ?>
                    
                    <div class="field-form-container">
                        <div class="field-form-main">
                            <!-- Basic Information -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Basic Information', 'wpmatch'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="field_name"><?php _e('Field Name', 'wpmatch'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <input type="text" name="field_name" id="field_name" 
                                                       value="<?php echo $field ? esc_attr($field->field_name) : ''; ?>" 
                                                       class="regular-text" required 
                                                       <?php echo $is_edit ? 'readonly' : ''; ?> />
                                                <p class="description">
                                                    <?php _e('Unique identifier for this field. Can only contain lowercase letters, numbers, and underscores.', 'wpmatch'); ?>
                                                    <?php if ($is_edit): ?>
                                                        <br><strong><?php _e('Field name cannot be changed after creation.', 'wpmatch'); ?></strong>
                                                    <?php endif; ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="field_label"><?php _e('Field Label', 'wpmatch'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <input type="text" name="field_label" id="field_label" 
                                                       value="<?php echo $field ? esc_attr($field->field_label) : ''; ?>" 
                                                       class="regular-text" required />
                                                <p class="description">
                                                    <?php _e('Display name for this field as shown to users.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="field_type"><?php _e('Field Type', 'wpmatch'); ?> <span class="required">*</span></label>
                                            </th>
                                            <td>
                                                <select name="field_type" id="field_type" required>
                                                    <option value=""><?php _e('Select field type...', 'wpmatch'); ?></option>
                                                    <?php foreach ($this->type_registry->get_field_types() as $type => $config): ?>
                                                        <option value="<?php echo esc_attr($type); ?>" 
                                                                <?php selected($field ? $field->field_type : '', $type); ?>
                                                                data-supports="<?php echo esc_attr(wp_json_encode($config['supports'])); ?>">
                                                            <?php echo esc_html($config['label']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="description">
                                                    <?php _e('Type of field determines how users will input data.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="field_description"><?php _e('Description', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <textarea name="field_description" id="field_description" 
                                                          rows="3" class="large-text"><?php echo $field ? esc_textarea($field->field_description) : ''; ?></textarea>
                                                <p class="description">
                                                    <?php _e('Optional description explaining what this field is for.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Field Options -->
                            <div class="postbox" id="field-options-box">
                                <div class="postbox-header">
                                    <h2><?php _e('Field Options', 'wpmatch'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div id="field-options-content">
                                        <!-- Content populated by JavaScript based on field type -->
                                    </div>
                                </div>
                            </div>

                            <!-- Validation Rules -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Validation Rules', 'wpmatch'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php _e('Required Field', 'wpmatch'); ?></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="is_required" id="is_required" value="1" 
                                                           <?php checked($field ? $field->is_required : false); ?> />
                                                    <?php _e('Users must fill out this field', 'wpmatch'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        
                                        <tr class="validation-rule" data-types="text,textarea">
                                            <th scope="row">
                                                <label for="min_length"><?php _e('Minimum Length', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <input type="number" name="min_length" id="min_length" 
                                                       value="<?php echo $field ? esc_attr($field->min_length) : ''; ?>" 
                                                       class="small-text" min="0" />
                                                <p class="description">
                                                    <?php _e('Minimum number of characters required.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr class="validation-rule" data-types="text,textarea">
                                            <th scope="row">
                                                <label for="max_length"><?php _e('Maximum Length', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <input type="number" name="max_length" id="max_length" 
                                                       value="<?php echo $field ? esc_attr($field->max_length) : ''; ?>" 
                                                       class="small-text" min="1" />
                                                <p class="description">
                                                    <?php _e('Maximum number of characters allowed.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr class="validation-rule" data-types="number,range">
                                            <th scope="row">
                                                <label for="min_value"><?php _e('Minimum Value', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <input type="number" name="min_value" id="min_value" 
                                                       value="<?php echo $field ? esc_attr($field->min_value) : ''; ?>" 
                                                       class="small-text" step="any" />
                                                <p class="description">
                                                    <?php _e('Minimum numeric value allowed.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr class="validation-rule" data-types="number,range">
                                            <th scope="row">
                                                <label for="max_value"><?php _e('Maximum Value', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <input type="number" name="max_value" id="max_value" 
                                                       value="<?php echo $field ? esc_attr($field->max_value) : ''; ?>" 
                                                       class="small-text" step="any" />
                                                <p class="description">
                                                    <?php _e('Maximum numeric value allowed.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr class="validation-rule" data-types="text">
                                            <th scope="row">
                                                <label for="regex_pattern"><?php _e('Custom Pattern', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" name="regex_pattern" id="regex_pattern" 
                                                       value="<?php echo $field ? esc_attr($field->regex_pattern) : ''; ?>" 
                                                       class="regular-text" />
                                                <p class="description">
                                                    <?php _e('Regular expression pattern for custom validation (advanced users only).', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="field-form-sidebar">
                            <!-- Display Settings -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Display Settings', 'wpmatch'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="field_group"><?php _e('Field Group', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <select name="field_group" id="field_group">
                                                    <option value="basic" <?php selected($field ? $field->field_group : 'basic', 'basic'); ?>>
                                                        <?php _e('Basic Information', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="personal" <?php selected($field ? $field->field_group : '', 'personal'); ?>>
                                                        <?php _e('Personal Details', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="lifestyle" <?php selected($field ? $field->field_group : '', 'lifestyle'); ?>>
                                                        <?php _e('Lifestyle', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="interests" <?php selected($field ? $field->field_group : '', 'interests'); ?>>
                                                        <?php _e('Interests', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="preferences" <?php selected($field ? $field->field_group : '', 'preferences'); ?>>
                                                        <?php _e('Preferences', 'wpmatch'); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="field_width"><?php _e('Field Width', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <select name="field_width" id="field_width">
                                                    <option value="full" <?php selected($field ? $field->field_width : 'full', 'full'); ?>>
                                                        <?php _e('Full Width', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="half" <?php selected($field ? $field->field_width : '', 'half'); ?>>
                                                        <?php _e('Half Width', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="third" <?php selected($field ? $field->field_width : '', 'third'); ?>>
                                                        <?php _e('One Third', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="quarter" <?php selected($field ? $field->field_width : '', 'quarter'); ?>>
                                                        <?php _e('One Quarter', 'wpmatch'); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="placeholder_text"><?php _e('Placeholder Text', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" name="placeholder_text" id="placeholder_text" 
                                                       value="<?php echo $field ? esc_attr($field->placeholder_text) : ''; ?>" 
                                                       class="regular-text" />
                                                <p class="description">
                                                    <?php _e('Hint text shown in empty fields.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="help_text"><?php _e('Help Text', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <textarea name="help_text" id="help_text" 
                                                          rows="2" class="large-text"><?php echo $field ? esc_textarea($field->help_text) : ''; ?></textarea>
                                                <p class="description">
                                                    <?php _e('Additional guidance shown below the field.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row">
                                                <label for="default_value"><?php _e('Default Value', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" name="default_value" id="default_value" 
                                                       value="<?php echo $field ? esc_attr($field->default_value) : ''; ?>" 
                                                       class="regular-text" />
                                                <p class="description">
                                                    <?php _e('Pre-filled value for new users.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Privacy Settings -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Privacy & Search', 'wpmatch'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php _e('Visibility', 'wpmatch'); ?></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="is_public" id="is_public" value="1" 
                                                           <?php checked($field ? $field->is_public : true); ?> />
                                                    <?php _e('Show in public profiles', 'wpmatch'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php _e('Search', 'wpmatch'); ?></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="is_searchable" id="is_searchable" value="1" 
                                                           <?php checked($field ? $field->is_searchable : false); ?> />
                                                    <?php _e('Include in search filters', 'wpmatch'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        
                                        <tr>
                                            <th scope="row"><?php _e('Editing', 'wpmatch'); ?></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="is_editable" id="is_editable" value="1" 
                                                           <?php checked($field ? $field->is_editable : true); ?> />
                                                    <?php _e('Users can edit this field', 'wpmatch'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Field Status -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Status', 'wpmatch'); ?></h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="status"><?php _e('Field Status', 'wpmatch'); ?></label>
                                            </th>
                                            <td>
                                                <select name="status" id="status">
                                                    <option value="active" <?php selected($field ? $field->status : 'active', 'active'); ?>>
                                                        <?php _e('Active', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="inactive" <?php selected($field ? $field->status : '', 'inactive'); ?>>
                                                        <?php _e('Inactive', 'wpmatch'); ?>
                                                    </option>
                                                    <option value="draft" <?php selected($field ? $field->status : '', 'draft'); ?>>
                                                        <?php _e('Draft', 'wpmatch'); ?>
                                                    </option>
                                                </select>
                                                <p class="description">
                                                    <?php _e('Only active fields are shown to users.', 'wpmatch'); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2><?php _e('Actions', 'wpmatch'); ?></h2>
                                </div>
                                <div class="inside">
                                    <div class="submitbox">
                                        <div class="misc-pub-section">
                                            <input type="submit" name="save_field" id="save-field" 
                                                   class="button button-primary button-large" 
                                                   value="<?php echo $is_edit ? __('Update Field', 'wpmatch') : __('Create Field', 'wpmatch'); ?>" />
                                        </div>
                                        
                                        <?php if ($is_edit): ?>
                                        <div class="misc-pub-section">
                                            <button type="button" class="button button-secondary" id="duplicate-field">
                                                <?php _e('Duplicate Field', 'wpmatch'); ?>
                                            </button>
                                        </div>
                                        
                                        <div class="misc-pub-section">
                                            <button type="button" class="button button-link-delete" id="delete-field" 
                                                    data-field-id="<?php echo $field_id; ?>">
                                                <?php _e('Delete Field', 'wpmatch'); ?>
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render field view
     *
     * @param int $field_id Field ID
     */
    private function render_field_view($field_id) {
        $field = $this->field_manager->get_field($field_id);
        if (!$field) {
            wp_die(__('Field not found.', 'wpmatch'));
        }

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html($field->field_label); ?>
                <a href="<?php echo admin_url('admin.php?page=wpmatch-profile-fields&action=edit&field_id=' . $field_id); ?>" class="page-title-action">
                    <?php _e('Edit', 'wpmatch'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wpmatch-profile-fields'); ?>" class="page-title-action">
                    <?php _e('Back to Fields', 'wpmatch'); ?>
                </a>
            </h1>

            <hr class="wp-header-end">

            <div class="wpmatch-field-view">
                <!-- Field details -->
                <div class="field-view-details">
                    <!-- Implementation of field view interface -->
                    <p><?php _e('Field view interface to be implemented.', 'wpmatch'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render admin notices
     */
    private function render_admin_notices() {
        // Handle various admin notices
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $notice_type = isset($_GET['notice_type']) ? sanitize_text_field($_GET['notice_type']) : 'success';
            
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice_type),
                esc_html($message)
            );
        }
    }

    /**
     * Bulk update field status
     *
     * @param array  $field_ids Field IDs
     * @param string $status    New status
     */
    private function bulk_update_field_status($field_ids, $status) {
        $updated = 0;
        foreach ($field_ids as $field_id) {
            $result = $this->field_manager->update_field($field_id, array('status' => $status));
            if (!is_wp_error($result)) {
                $updated++;
            }
        }

        if ($updated > 0) {
            $message = sprintf(_n('%d field status updated.', '%d field statuses updated.', $updated, 'wpmatch'), $updated);
            wp_redirect(add_query_arg(array('message' => $message, 'notice_type' => 'success'), admin_url('admin.php?page=wpmatch-profile-fields')));
            exit;
        }
    }

    /**
     * Bulk delete fields
     *
     * @param array $field_ids Field IDs
     */
    private function bulk_delete_fields($field_ids) {
        $deleted = 0;
        foreach ($field_ids as $field_id) {
            $result = $this->field_manager->delete_field($field_id);
            if (!is_wp_error($result)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $message = sprintf(_n('%d field deleted.', '%d fields deleted.', $deleted, 'wpmatch'), $deleted);
            wp_redirect(add_query_arg(array('message' => $message, 'notice_type' => 'success'), admin_url('admin.php?page=wpmatch-profile-fields')));
            exit;
        }
    }

    // AJAX Handlers

    /**
     * Main AJAX handler for field actions
     */
    public function handle_ajax_field_action() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        $field_action = sanitize_text_field($_POST['field_action'] ?? '');
        
        switch ($field_action) {
            case 'delete_field':
                $this->ajax_delete_field_handler();
                break;
                
            case 'duplicate_field':
                $this->ajax_duplicate_field_handler();
                break;
                
            case 'toggle_status':
                $this->ajax_toggle_status_handler();
                break;
                
            case 'get_field_preview':
                $this->ajax_get_field_preview_handler();
                break;
                
            case 'update_order':
                $this->ajax_update_order_handler();
                break;
                
            default:
                wp_send_json_error(__('Invalid action.', 'wpmatch'));
        }
    }

    /**
     * AJAX handler for deleting a field
     */
    private function ajax_delete_field_handler() {
        $field_id = absint($_POST['field_id'] ?? 0);
        
        if (!$field_id) {
            wp_send_json_error(__('Invalid field ID.', 'wpmatch'));
        }

        $field = $this->field_manager->get_field($field_id);
        if (!$field) {
            wp_send_json_error(__('Field not found.', 'wpmatch'));
        }

        if ($field->is_system) {
            wp_send_json_error(__('System fields cannot be deleted.', 'wpmatch'));
        }

        $result = $this->field_manager->delete_field($field_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Field deleted successfully.', 'wpmatch'));
    }

    /**
     * AJAX handler for duplicating a field
     */
    private function ajax_duplicate_field_handler() {
        $field_id = absint($_POST['field_id'] ?? 0);
        
        if (!$field_id) {
            wp_send_json_error(__('Invalid field ID.', 'wpmatch'));
        }

        $field = $this->field_manager->get_field($field_id);
        if (!$field) {
            wp_send_json_error(__('Field not found.', 'wpmatch'));
        }

        $result = $this->field_manager->duplicate_field($field_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Field duplicated successfully.', 'wpmatch'));
    }

    /**
     * AJAX handler for toggling field status
     */
    private function ajax_toggle_status_handler() {
        $field_id = absint($_POST['field_id'] ?? 0);
        $current_status = sanitize_text_field($_POST['current_status'] ?? 'active');
        
        if (!$field_id) {
            wp_send_json_error(__('Invalid field ID.', 'wpmatch'));
        }

        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        
        $result = $this->field_manager->update_field_status($field_id, $new_status);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Field status changed to %s.', 'wpmatch'), $new_status),
            'new_status' => $new_status
        ));
    }

    /**
     * AJAX handler for getting field preview
     */
    private function ajax_get_field_preview_handler() {
        $field_id = absint($_POST['field_id'] ?? 0);
        
        if (!$field_id) {
            wp_send_json_error(__('Invalid field ID.', 'wpmatch'));
        }

        $field = $this->field_manager->get_field($field_id);
        if (!$field) {
            wp_send_json_error(__('Field not found.', 'wpmatch'));
        }

        $html = $this->type_registry->render_field($field, '', array('class' => 'wpmatch-field-preview'));
        
        wp_send_json_success(array(
            'html' => $html,
            'field' => array(
                'field_label' => $field->field_label,
                'field_type' => $field->field_type
            )
        ));
    }

    /**
     * AJAX handler for updating field order
     */
    private function ajax_update_order_handler() {
        $field_order = $_POST['field_order'] ?? array();
        
        if (!is_array($field_order) || empty($field_order)) {
            wp_send_json_error(__('Invalid field order data.', 'wpmatch'));
        }

        $updated = 0;
        foreach ($field_order as $field_id => $order) {
            $field_id = absint($field_id);
            $order = absint($order);
            
            if ($field_id && $order) {
                $result = $this->field_manager->update_field_order($field_id, $order);
                if (!is_wp_error($result)) {
                    $updated++;
                }
            }
        }

        if ($updated > 0) {
            wp_send_json_success(sprintf(__('%d field(s) reordered.', 'wpmatch'), $updated));
        } else {
            wp_send_json_error(__('Failed to update field order.', 'wpmatch'));
        }
    }

    /**
     * AJAX handler for creating fields
     */
    public function ajax_create_field() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('create_field', 10, 60); // 10 requests per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get and sanitize field data
        $field_data = array();
        $required_fields = array('field_name', 'field_label', 'field_type');
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(sprintf(__('The %s field is required.', 'wpmatch'), str_replace('_', ' ', $field)));
            }
            $field_data[$field] = sanitize_text_field($_POST[$field]);
        }

        // Optional fields
        $optional_fields = array(
            'field_description', 'placeholder_text', 'help_text', 'field_group',
            'field_width', 'default_value', 'field_class', 'status', 'regex_pattern'
        );
        
        foreach ($optional_fields as $field) {
            if (isset($_POST[$field])) {
                $field_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        // Boolean fields
        $boolean_fields = array('is_required', 'is_searchable', 'is_public', 'is_editable');
        foreach ($boolean_fields as $field) {
            $field_data[$field] = !empty($_POST[$field]);
        }

        // Numeric fields
        $numeric_fields = array('min_value', 'max_value', 'min_length', 'max_length', 'field_order');
        foreach ($numeric_fields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $field_data[$field] = is_numeric($_POST[$field]) ? $_POST[$field] : null;
            }
        }

        // JSON fields
        if (isset($_POST['field_options']) && !empty($_POST['field_options'])) {
            $field_options = json_decode(stripslashes($_POST['field_options']), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $field_data['field_options'] = $field_options;
            }
        }

        if (isset($_POST['validation_rules']) && !empty($_POST['validation_rules'])) {
            $validation_rules = json_decode(stripslashes($_POST['validation_rules']), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $field_data['validation_rules'] = $validation_rules;
            }
        }

        if (isset($_POST['display_options']) && !empty($_POST['display_options'])) {
            $display_options = json_decode(stripslashes($_POST['display_options']), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $field_data['display_options'] = $display_options;
            }
        }

        if (isset($_POST['conditional_logic']) && !empty($_POST['conditional_logic'])) {
            $conditional_logic = json_decode(stripslashes($_POST['conditional_logic']), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $field_data['conditional_logic'] = $conditional_logic;
            }
        }

        // Create the field
        $result = $this->field_manager->create_field($field_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Get the created field
        $field = $this->field_manager->get_field($result);
        
        wp_send_json_success(array(
            'message' => __('Field created successfully!', 'wpmatch'),
            'field_id' => $result,
            'field' => $field,
            'redirect_url' => admin_url('admin.php?page=wpmatch-profile-fields&action=edit&field_id=' . $result)
        ));
    }

    /**
     * AJAX handler for updating fields
     */
    public function ajax_update_field() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('update_field', 20, 60); // 20 requests per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get field ID
        $field_id = absint($_POST['field_id'] ?? 0);
        if (!$field_id) {
            wp_send_json_error(__('Invalid field ID.', 'wpmatch'));
        }

        // Check if field exists
        $existing_field = $this->field_manager->get_field($field_id);
        if (!$existing_field) {
            wp_send_json_error(__('Field not found.', 'wpmatch'));
        }

        // Get and sanitize field data
        $field_data = array();
        
        // Basic fields
        $text_fields = array(
            'field_label', 'field_description', 'placeholder_text', 'help_text',
            'field_group', 'field_width', 'default_value', 'field_class', 'status', 'regex_pattern'
        );
        
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $field_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        // Boolean fields
        $boolean_fields = array('is_required', 'is_searchable', 'is_public', 'is_editable');
        foreach ($boolean_fields as $field) {
            if (isset($_POST[$field])) {
                $field_data[$field] = !empty($_POST[$field]);
            }
        }

        // Numeric fields
        $numeric_fields = array('min_value', 'max_value', 'min_length', 'max_length', 'field_order');
        foreach ($numeric_fields as $field) {
            if (isset($_POST[$field])) {
                $field_data[$field] = ($_POST[$field] !== '') ? $_POST[$field] : null;
            }
        }

        // JSON fields
        if (isset($_POST['field_options'])) {
            if (empty($_POST['field_options'])) {
                $field_data['field_options'] = null;
            } else {
                $field_options = json_decode(stripslashes($_POST['field_options']), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $field_data['field_options'] = $field_options;
                } else {
                    wp_send_json_error(__('Invalid field options format.', 'wpmatch'));
                }
            }
        }

        if (isset($_POST['validation_rules'])) {
            if (empty($_POST['validation_rules'])) {
                $field_data['validation_rules'] = null;
            } else {
                $validation_rules = json_decode(stripslashes($_POST['validation_rules']), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $field_data['validation_rules'] = $validation_rules;
                } else {
                    wp_send_json_error(__('Invalid validation rules format.', 'wpmatch'));
                }
            }
        }

        if (isset($_POST['display_options'])) {
            if (empty($_POST['display_options'])) {
                $field_data['display_options'] = null;
            } else {
                $display_options = json_decode(stripslashes($_POST['display_options']), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $field_data['display_options'] = $display_options;
                } else {
                    wp_send_json_error(__('Invalid display options format.', 'wpmatch'));
                }
            }
        }

        if (isset($_POST['conditional_logic'])) {
            if (empty($_POST['conditional_logic'])) {
                $field_data['conditional_logic'] = null;
            } else {
                $conditional_logic = json_decode(stripslashes($_POST['conditional_logic']), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $field_data['conditional_logic'] = $conditional_logic;
                } else {
                    wp_send_json_error(__('Invalid conditional logic format.', 'wpmatch'));
                }
            }
        }

        // Update the field
        $result = $this->field_manager->update_field($field_id, $field_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Get updated field
        $updated_field = $this->field_manager->get_field($field_id);
        
        wp_send_json_success(array(
            'message' => __('Field updated successfully!', 'wpmatch'),
            'field_id' => $field_id,
            'field' => $updated_field
        ));
    }

    /**
     * AJAX handler for deleting fields
     */
    public function ajax_delete_field() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('delete_field', 5, 60); // 5 deletions per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get field ID
        $field_id = absint($_POST['field_id'] ?? 0);
        if (!$field_id) {
            wp_send_json_error(__('Invalid field ID.', 'wpmatch'));
        }

        // Check if field exists
        $field = $this->field_manager->get_field($field_id);
        if (!$field) {
            wp_send_json_error(__('Field not found.', 'wpmatch'));
        }

        // Check if it's a system field
        if (!empty($field->is_system)) {
            wp_send_json_error(__('System fields cannot be deleted.', 'wpmatch'));
        }

        // Check for force delete flag
        $force_delete = !empty($_POST['force_delete']);
        
        // Delete the field
        $result = $this->field_manager->delete_field($field_id, $force_delete);
        
        if (is_wp_error($result)) {
            // If field has data and is being deprecated, this is expected
            if ($result->get_error_code() === 'field_has_data') {
                wp_send_json_success(array(
                    'message' => $result->get_error_message(),
                    'field_deprecated' => true,
                    'field_id' => $field_id
                ));
            } else {
                wp_send_json_error($result->get_error_message());
            }
        }

        wp_send_json_success(array(
            'message' => __('Field deleted successfully!', 'wpmatch'),
            'field_id' => $field_id,
            'redirect_url' => admin_url('admin.php?page=wpmatch-profile-fields')
        ));
    }

    /**
     * AJAX handler for getting field data
     */
    public function ajax_get_field() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check (generous limit for reads)
        $rate_limit_result = $this->check_rate_limit('get_field', 100, 60); // 100 requests per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get field ID
        $field_id = absint($_POST['field_id'] ?? 0);
        if (!$field_id) {
            wp_send_json_error(__('Invalid field ID.', 'wpmatch'));
        }

        // Get the field
        $field = $this->field_manager->get_field($field_id);
        if (!$field) {
            wp_send_json_error(__('Field not found.', 'wpmatch'));
        }

        // Include additional metadata
        $field_data = array(
            'field' => $field,
            'usage_count' => $this->field_manager->get_field_usage_count($field_id),
            'field_type_config' => $this->type_registry->get_field_type($field->field_type),
            'edit_url' => admin_url('admin.php?page=wpmatch-profile-fields&action=edit&field_id=' . $field_id),
            'preview_html' => $this->generate_field_preview($field)
        );

        wp_send_json_success($field_data);
    }

    /**
     * AJAX handler for getting fields list
     */
    public function ajax_get_fields() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('get_fields', 50, 60); // 50 requests per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get query parameters
        $args = array();
        
        if (isset($_POST['status']) && !empty($_POST['status'])) {
            $args['status'] = sanitize_text_field($_POST['status']);
        }
        
        if (isset($_POST['field_type']) && !empty($_POST['field_type'])) {
            $args['field_type'] = sanitize_text_field($_POST['field_type']);
        }
        
        if (isset($_POST['field_group']) && !empty($_POST['field_group'])) {
            $args['field_group'] = sanitize_text_field($_POST['field_group']);
        }
        
        if (isset($_POST['search']) && !empty($_POST['search'])) {
            $args['search'] = sanitize_text_field($_POST['search']);
        }

        if (isset($_POST['orderby']) && !empty($_POST['orderby'])) {
            $args['orderby'] = sanitize_text_field($_POST['orderby']);
        }

        if (isset($_POST['order']) && !empty($_POST['order'])) {
            $args['order'] = sanitize_text_field($_POST['order']);
        }

        // Pagination
        if (isset($_POST['limit']) && is_numeric($_POST['limit'])) {
            $args['limit'] = min(absint($_POST['limit']), 100); // Max 100 fields per request
        }
        
        if (isset($_POST['offset']) && is_numeric($_POST['offset'])) {
            $args['offset'] = absint($_POST['offset']);
        }

        // Get fields
        $fields = $this->field_manager->get_fields($args);
        $total_count = $this->field_manager->get_fields_count($args);
        
        // Add usage counts for each field
        $fields_with_meta = array();
        foreach ($fields as $field) {
            $field_data = (array) $field;
            $field_data['usage_count'] = $this->field_manager->get_field_usage_count($field->id);
            $field_data['edit_url'] = admin_url('admin.php?page=wpmatch-profile-fields&action=edit&field_id=' . $field->id);
            $fields_with_meta[] = $field_data;
        }

        wp_send_json_success(array(
            'fields' => $fields_with_meta,
            'total_count' => $total_count,
            'query_args' => $args
        ));
    }

    /**
     * AJAX handler for reordering fields
     */
    public function ajax_reorder_fields() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('reorder_fields', 20, 60); // 20 reorder operations per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get field order data
        $field_orders = $_POST['field_orders'] ?? array();
        
        if (!is_array($field_orders) || empty($field_orders)) {
            wp_send_json_error(__('Invalid field order data.', 'wpmatch'));
        }

        // Validate and sanitize field order data
        $sanitized_orders = array();
        foreach ($field_orders as $field_id => $order_data) {
            $field_id = absint($field_id);
            if (!$field_id) {
                continue;
            }

            // Validate field exists
            $field = $this->field_manager->get_field($field_id);
            if (!$field) {
                wp_send_json_error(sprintf(__('Field ID %d not found.', 'wpmatch'), $field_id));
            }

            $sanitized_orders[$field_id] = array(
                'order' => absint($order_data['order'] ?? 0),
                'group' => sanitize_text_field($order_data['group'] ?? $field->field_group)
            );
        }

        // Reorder fields
        $result = $this->field_manager->reorder_fields($sanitized_orders);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => __('Fields reordered successfully!', 'wpmatch'),
            'updated_count' => count($sanitized_orders)
        ));
    }

    /**
     * AJAX handler for validating field data
     */
    public function ajax_validate_field() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('validate_field', 100, 60); // 100 validations per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get field data from POST
        $field_data = array();
        $validation_type = sanitize_text_field($_POST['validation_type'] ?? 'full');
        $field_id = absint($_POST['field_id'] ?? 0);

        // Basic field data
        $all_fields = array(
            'field_name', 'field_label', 'field_type', 'field_description',
            'placeholder_text', 'help_text', 'field_group', 'field_width',
            'default_value', 'field_class', 'status', 'regex_pattern',
            'min_value', 'max_value', 'min_length', 'max_length', 'field_order'
        );
        
        foreach ($all_fields as $field) {
            if (isset($_POST[$field])) {
                $field_data[$field] = $_POST[$field];
            }
        }

        // Boolean fields
        $boolean_fields = array('is_required', 'is_searchable', 'is_public', 'is_editable');
        foreach ($boolean_fields as $field) {
            if (isset($_POST[$field])) {
                $field_data[$field] = !empty($_POST[$field]);
            }
        }

        // JSON fields
        $json_fields = array('field_options', 'validation_rules', 'display_options', 'conditional_logic');
        foreach ($json_fields as $field) {
            if (isset($_POST[$field]) && !empty($_POST[$field])) {
                $decoded = json_decode(stripslashes($_POST[$field]), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $field_data[$field] = $decoded;
                } else {
                    wp_send_json_error(sprintf(__('Invalid JSON format for %s.', 'wpmatch'), $field));
                }
            }
        }

        // Perform validation based on type
        switch ($validation_type) {
            case 'field_name':
                $result = $this->validator->validate_field_name($field_data['field_name'] ?? '', $field_data);
                // Also check for uniqueness
                if ($result === true && !empty($field_data['field_name'])) {
                    if ($this->field_manager->field_name_exists($field_data['field_name'], $field_id)) {
                        $result = new WP_Error('duplicate_name', __('A field with this name already exists.', 'wpmatch'));
                    }
                }
                break;
                
            case 'field_options':
                $result = $this->validator->validate_field_options($field_data['field_options'] ?? array(), $field_data);
                break;
                
            case 'validation_rules':
                $result = $this->validator->validate_validation_rules($field_data['validation_rules'] ?? array(), $field_data);
                break;
                
            case 'full':
            default:
                $result = $this->validator->validate_field_data($field_data, $field_id);
                break;
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'errors' => $result->get_error_data(),
                'validation_type' => $validation_type
            ));
        }

        wp_send_json_success(array(
            'message' => __('Validation passed.', 'wpmatch'),
            'validation_type' => $validation_type,
            'field_data' => $field_data
        ));
    }

    /**
     * AJAX handler for getting field types
     */
    public function ajax_get_field_types() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check (generous for metadata)
        $rate_limit_result = $this->check_rate_limit('get_field_types', 100, 60);
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get field types with full configuration
        $field_types = $this->type_registry->get_field_types();
        $field_types_for_select = $this->type_registry->get_field_types_for_select();
        
        // Get specific field type if requested
        $field_type = sanitize_text_field($_POST['field_type'] ?? '');
        if (!empty($field_type)) {
            $type_config = $this->type_registry->get_field_type($field_type);
            if (!$type_config) {
                wp_send_json_error(__('Field type not found.', 'wpmatch'));
            }
            
            wp_send_json_success(array(
                'field_type' => $field_type,
                'config' => $type_config
            ));
        } else {
            wp_send_json_success(array(
                'field_types' => $field_types,
                'field_types_for_select' => $field_types_for_select,
                'total_types' => count($field_types)
            ));
        }
    }

    /**
     * AJAX handler for duplicating fields
     */
    public function ajax_duplicate_field() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('duplicate_field', 10, 60); // 10 duplications per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get field ID
        $field_id = absint($_POST['field_id'] ?? 0);
        if (!$field_id) {
            wp_send_json_error(__('Invalid field ID.', 'wpmatch'));
        }

        // Check if field exists
        $original_field = $this->field_manager->get_field($field_id);
        if (!$original_field) {
            wp_send_json_error(__('Field not found.', 'wpmatch'));
        }

        // Duplicate the field
        $result = $this->field_manager->duplicate_field($field_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Get the duplicated field
        $duplicated_field = $this->field_manager->get_field($result);
        
        wp_send_json_success(array(
            'message' => __('Field duplicated successfully!', 'wpmatch'),
            'original_field_id' => $field_id,
            'new_field_id' => $result,
            'new_field' => $duplicated_field,
            'edit_url' => admin_url('admin.php?page=wpmatch-profile-fields&action=edit&field_id=' . $result)
        ));
    }

    /**
     * AJAX handler for creating field groups
     */
    public function ajax_create_field_group() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('create_field_group', 10, 60);
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get group data
        $group_name = sanitize_text_field($_POST['group_name'] ?? '');
        $group_label = sanitize_text_field($_POST['group_label'] ?? '');
        $group_description = sanitize_textarea_field($_POST['group_description'] ?? '');
        
        if (empty($group_name) || empty($group_label)) {
            wp_send_json_error(__('Group name and label are required.', 'wpmatch'));
        }

        // Validate group name format
        if (!preg_match('/^[a-z][a-z0-9_]*[a-z0-9]$/', $group_name)) {
            wp_send_json_error(__('Group name must contain only lowercase letters, numbers, and underscores.', 'wpmatch'));
        }

        // Check if group already exists
        $existing_groups = $this->field_manager->get_field_groups();
        foreach ($existing_groups as $group) {
            if ($group->field_group === $group_name) {
                wp_send_json_error(__('A group with this name already exists.', 'wpmatch'));
            }
        }

        // Store group metadata
        $group_metadata = array(
            'label' => $group_label,
            'description' => $group_description,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        update_option('wpmatch_field_group_' . $group_name, $group_metadata);

        wp_send_json_success(array(
            'message' => __('Field group created successfully!', 'wpmatch'),
            'group_name' => $group_name,
            'group_data' => $group_metadata
        ));
    }

    /**
     * AJAX handler for updating field groups
     */
    public function ajax_update_field_group() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('update_field_group', 20, 60);
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get group data
        $group_name = sanitize_text_field($_POST['group_name'] ?? '');
        $group_label = sanitize_text_field($_POST['group_label'] ?? '');
        $group_description = sanitize_textarea_field($_POST['group_description'] ?? '');
        
        if (empty($group_name) || empty($group_label)) {
            wp_send_json_error(__('Group name and label are required.', 'wpmatch'));
        }

        // Get existing group metadata
        $existing_metadata = get_option('wpmatch_field_group_' . $group_name, array());
        if (empty($existing_metadata)) {
            wp_send_json_error(__('Field group not found.', 'wpmatch'));
        }

        // Update group metadata
        $group_metadata = array_merge($existing_metadata, array(
            'label' => $group_label,
            'description' => $group_description,
            'updated_by' => get_current_user_id(),
            'updated_at' => current_time('mysql')
        ));
        
        update_option('wpmatch_field_group_' . $group_name, $group_metadata);

        wp_send_json_success(array(
            'message' => __('Field group updated successfully!', 'wpmatch'),
            'group_name' => $group_name,
            'group_data' => $group_metadata
        ));
    }

    /**
     * AJAX handler for getting field groups
     */
    public function ajax_get_field_groups() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('get_field_groups', 50, 60);
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        // Get field groups with metadata
        $groups = $this->field_manager->get_field_groups();
        $groups_with_metadata = array();
        
        foreach ($groups as $group) {
            $metadata = get_option('wpmatch_field_group_' . $group->field_group, array());
            $group_data = array(
                'name' => $group->field_group,
                'field_count' => $group->field_count,
                'max_order' => $group->max_order,
                'label' => $metadata['label'] ?? ucfirst(str_replace('_', ' ', $group->field_group)),
                'description' => $metadata['description'] ?? '',
                'created_by' => $metadata['created_by'] ?? null,
                'created_at' => $metadata['created_at'] ?? null,
                'updated_by' => $metadata['updated_by'] ?? null,
                'updated_at' => $metadata['updated_at'] ?? null
            );
            
            $groups_with_metadata[] = $group_data;
        }

        wp_send_json_success(array(
            'groups' => $groups_with_metadata,
            'total_groups' => count($groups_with_metadata)
        ));
    }

    /**
     * Check rate limit for AJAX operations
     *
     * @param string $action    Action being performed
     * @param int    $limit     Request limit
     * @param int    $timeframe Timeframe in seconds
     * @return bool|WP_Error True if within limit, WP_Error if exceeded
     */
    private function check_rate_limit($action, $limit = 30, $timeframe = 60) {
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $cache_key = "wpmatch_rate_limit_{$action}_{$user_id}_{$ip_address}";
        
        $requests = get_transient($cache_key);
        if ($requests === false) {
            $requests = array();
        }
        
        // Clean old requests
        $current_time = time();
        $requests = array_filter($requests, function($timestamp) use ($current_time, $timeframe) {
            return ($current_time - $timestamp) < $timeframe;
        });
        
        // Check if limit exceeded
        if (count($requests) >= $limit) {
            return new WP_Error('rate_limit_exceeded', 
                sprintf(__('Rate limit exceeded. Maximum %d requests per %d seconds allowed.', 'wpmatch'), $limit, $timeframe)
            );
        }
        
        // Add current request
        $requests[] = $current_time;
        set_transient($cache_key, $requests, $timeframe);
        
        return true;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Generate field preview HTML
     *
     * @param object $field Field object
     * @return string Preview HTML
     */
    private function generate_field_preview($field) {
        if (!$this->type_registry) {
            return '<p>' . __('Preview not available.', 'wpmatch') . '</p>';
        }

        try {
            $preview_html = $this->type_registry->render_field($field, '', array(
                'class' => 'wpmatch-field-preview',
                'readonly' => true
            ));
            
            return '<div class="field-preview-wrapper">' . $preview_html . '</div>';
        } catch (Exception $e) {
            return '<p>' . __('Preview error: ', 'wpmatch') . esc_html($e->getMessage()) . '</p>';
        }
    }

    /**
     * Bulk operations for fields
     *
     * @param array  $field_ids Field IDs
     * @param string $operation Operation to perform
     * @param array  $options   Additional options
     * @return array Results array
     */
    public function bulk_field_operations($field_ids, $operation, $options = array()) {
        if (!current_user_can('manage_profile_fields')) {
            return array('error' => __('Permission denied.', 'wpmatch'));
        }

        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array(),
            'messages' => array()
        );

        foreach ($field_ids as $field_id) {
            $field_id = absint($field_id);
            if (!$field_id) {
                continue;
            }

            switch ($operation) {
                case 'delete':
                    $result = $this->field_manager->delete_field($field_id, !empty($options['force_delete']));
                    break;
                    
                case 'activate':
                    $result = $this->field_manager->update_field_status($field_id, 'active');
                    break;
                    
                case 'deactivate':
                    $result = $this->field_manager->update_field_status($field_id, 'inactive');
                    break;
                    
                case 'archive':
                    $result = $this->field_manager->update_field_status($field_id, 'archived');
                    break;
                    
                default:
                    $result = new WP_Error('invalid_operation', __('Invalid operation.', 'wpmatch'));
            }

            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Field ID %d: %s', 'wpmatch'), $field_id, $result->get_error_message());
            } else {
                $results['success']++;
            }
        }

        if ($results['success'] > 0) {
            $results['messages'][] = sprintf(_n('%d field processed successfully.', '%d fields processed successfully.', $results['success'], 'wpmatch'), $results['success']);
        }

        if ($results['failed'] > 0) {
            $results['messages'][] = sprintf(_n('%d field failed to process.', '%d fields failed to process.', $results['failed'], 'wpmatch'), $results['failed']);
        }

        return $results;
    }

    /**
     * Enhanced AJAX handler for bulk operations
     */
    public function ajax_bulk_field_operations() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('bulk_operations', 5, 60); // 5 bulk operations per minute
        if (is_wp_error($rate_limit_result)) {
            wp_send_json_error($rate_limit_result->get_error_message());
        }

        $field_ids = $_POST['field_ids'] ?? array();
        $operation = sanitize_text_field($_POST['operation'] ?? '');
        $options = $_POST['options'] ?? array();

        if (empty($field_ids) || !is_array($field_ids)) {
            wp_send_json_error(__('No fields selected.', 'wpmatch'));
        }

        if (empty($operation)) {
            wp_send_json_error(__('No operation specified.', 'wpmatch'));
        }

        // Limit bulk operations to prevent timeouts
        if (count($field_ids) > 50) {
            wp_send_json_error(__('Too many fields selected. Maximum 50 fields allowed per bulk operation.', 'wpmatch'));
        }

        $results = $this->bulk_field_operations($field_ids, $operation, $options);

        if (!empty($results['error'])) {
            wp_send_json_error($results['error']);
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX handler for creating default dating fields
     */
    public function ajax_create_default_fields() {
        check_ajax_referer('wpmatch_field_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        try {
            // Trigger the creation of default fields
            do_action('wpmatch_create_default_fields');
            
            wp_send_json_success(array(
                'message' => __('Default dating fields have been created successfully!', 'wpmatch'),
                'redirect' => admin_url('admin.php?page=wpmatch-profile-fields')
            ));
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to create default fields: ', 'wpmatch') . $e->getMessage());
        }
    }

    /**
     * Singleton instance
     *
     * @var WPMatch_Profile_Fields_Admin
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return WPMatch_Profile_Fields_Admin
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
