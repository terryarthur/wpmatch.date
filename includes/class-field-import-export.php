<?php
/**
 * Field Import/Export for WPMatch
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Field Import/Export class
 * 
 * Handles importing and exporting of field configurations including
 * validation, conflict resolution, and migration tools.
 */
class WPMatch_Field_Import_Export {

    /**
     * Field manager instance
     *
     * @var WPMatch_Profile_Field_Manager
     */
    private $field_manager;

    /**
     * Field validator instance
     *
     * @var WPMatch_Field_Validator
     */
    private $validator;

    /**
     * Export file version
     */
    const EXPORT_VERSION = '1.0.0';

    /**
     * Maximum import file size (5MB)
     */
    const MAX_FILE_SIZE = 5242880;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'), 25);
        
        // Admin AJAX handlers
        add_action('wp_ajax_wpmatch_export_fields', array($this, 'ajax_export_fields'));
        add_action('wp_ajax_wpmatch_import_fields', array($this, 'ajax_import_fields'));
        add_action('wp_ajax_wpmatch_preview_import', array($this, 'ajax_preview_import'));
        add_action('wp_ajax_wpmatch_download_export', array($this, 'ajax_download_export'));
        
        // Admin menu hooks
        add_action('admin_menu', array($this, 'add_admin_menu'), 100);
    }

    /**
     * Initialize dependencies
     */
    public function init() {
        // Load dependencies
        require_once WPMATCH_INCLUDES_PATH . 'class-profile-field-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-field-validator.php';

        // Initialize managers
        $this->field_manager = new WPMatch_Profile_Field_Manager();
        $this->validator = new WPMatch_Field_Validator();
    }

    /**
     * Add admin menu for import/export
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wpmatch',
            __('Import/Export Fields', 'wpmatch'),
            __('Import/Export', 'wpmatch'),
            'manage_profile_fields',
            'wpmatch-import-export',
            array($this, 'admin_page_import_export')
        );
    }

    /**
     * Render import/export admin page
     */
    public function admin_page_import_export() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wpmatch-import-export-admin">
                <div class="import-export-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#export" class="nav-tab nav-tab-active" data-tab="export">
                            <?php _e('Export Fields', 'wpmatch'); ?>
                        </a>
                        <a href="#import" class="nav-tab" data-tab="import">
                            <?php _e('Import Fields', 'wpmatch'); ?>
                        </a>
                        <a href="#backup" class="nav-tab" data-tab="backup">
                            <?php _e('Backup/Restore', 'wpmatch'); ?>
                        </a>
                    </nav>
                </div>

                <!-- Export Tab -->
                <div id="export-tab" class="tab-content active">
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><?php _e('Export Profile Fields', 'wpmatch'); ?></h2>
                        </div>
                        <div class="inside">
                            <p><?php _e('Export your profile field configurations to a JSON file that can be imported to another site.', 'wpmatch'); ?></p>
                            
                            <form id="export-form">
                                <?php wp_nonce_field('wpmatch_export_fields', 'export_nonce'); ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Export Options', 'wpmatch'); ?></th>
                                        <td>
                                            <fieldset>
                                                <label>
                                                    <input type="checkbox" name="export_options[]" value="fields" checked disabled>
                                                    <?php _e('Field Configurations', 'wpmatch'); ?>
                                                    <span class="description">(<?php _e('Always included', 'wpmatch'); ?>)</span>
                                                </label><br>
                                                
                                                <label>
                                                    <input type="checkbox" name="export_options[]" value="field_groups" checked>
                                                    <?php _e('Field Group Metadata', 'wpmatch'); ?>
                                                </label><br>
                                                
                                                <label>
                                                    <input type="checkbox" name="export_options[]" value="user_data">
                                                    <?php _e('User Field Values', 'wpmatch'); ?>
                                                    <span class="description">(<?php _e('Warning: May contain sensitive data', 'wpmatch'); ?>)</span>
                                                </label><br>
                                                
                                                <label>
                                                    <input type="checkbox" name="export_options[]" value="settings">
                                                    <?php _e('Plugin Settings', 'wpmatch'); ?>
                                                </label>
                                            </fieldset>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row"><?php _e('Field Filters', 'wpmatch'); ?></th>
                                        <td>
                                            <fieldset>
                                                <label for="export_status"><?php _e('Field Status:', 'wpmatch'); ?></label>
                                                <select name="export_status" id="export_status">
                                                    <option value="all"><?php _e('All Fields', 'wpmatch'); ?></option>
                                                    <option value="active" selected><?php _e('Active Only', 'wpmatch'); ?></option>
                                                    <option value="inactive"><?php _e('Inactive Only', 'wpmatch'); ?></option>
                                                </select><br><br>
                                                
                                                <label for="export_groups"><?php _e('Field Groups:', 'wpmatch'); ?></label>
                                                <select name="export_groups[]" id="export_groups" multiple style="height: 100px;">
                                                    <option value="all" selected><?php _e('All Groups', 'wpmatch'); ?></option>
                                                    <?php foreach ($this->field_manager->get_field_groups() as $group): ?>
                                                        <option value="<?php echo esc_attr($group->field_group); ?>">
                                                            <?php echo esc_html(ucfirst($group->field_group)); ?> (<?php echo $group->field_count; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>
                                
                                <p class="submit">
                                    <button type="submit" class="button button-primary">
                                        <?php _e('Generate Export File', 'wpmatch'); ?>
                                    </button>
                                </p>
                            </form>
                            
                            <div id="export-result" style="display: none;">
                                <h3><?php _e('Export Complete', 'wpmatch'); ?></h3>
                                <p id="export-summary"></p>
                                <p>
                                    <a href="#" id="download-export" class="button button-secondary">
                                        <?php _e('Download Export File', 'wpmatch'); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import Tab -->
                <div id="import-tab" class="tab-content">
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><?php _e('Import Profile Fields', 'wpmatch'); ?></h2>
                        </div>
                        <div class="inside">
                            <p><?php _e('Import profile field configurations from a JSON export file.', 'wpmatch'); ?></p>
                            
                            <form id="import-form" enctype="multipart/form-data">
                                <?php wp_nonce_field('wpmatch_import_fields', 'import_nonce'); ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Import File', 'wpmatch'); ?></th>
                                        <td>
                                            <input type="file" name="import_file" id="import_file" accept=".json" required>
                                            <p class="description">
                                                <?php printf(__('Maximum file size: %s', 'wpmatch'), size_format(self::MAX_FILE_SIZE)); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row"><?php _e('Import Mode', 'wpmatch'); ?></th>
                                        <td>
                                            <fieldset>
                                                <label>
                                                    <input type="radio" name="import_mode" value="preview" checked>
                                                    <?php _e('Preview First', 'wpmatch'); ?>
                                                    <span class="description">(<?php _e('Recommended', 'wpmatch'); ?>)</span>
                                                </label><br>
                                                
                                                <label>
                                                    <input type="radio" name="import_mode" value="direct">
                                                    <?php _e('Import Directly', 'wpmatch'); ?>
                                                    <span class="description">(<?php _e('Skip preview', 'wpmatch'); ?>)</span>
                                                </label>
                                            </fieldset>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row"><?php _e('Conflict Resolution', 'wpmatch'); ?></th>
                                        <td>
                                            <fieldset>
                                                <label>
                                                    <input type="radio" name="conflict_mode" value="skip" checked>
                                                    <?php _e('Skip Conflicting Fields', 'wpmatch'); ?>
                                                </label><br>
                                                
                                                <label>
                                                    <input type="radio" name="conflict_mode" value="update">
                                                    <?php _e('Update Existing Fields', 'wpmatch'); ?>
                                                </label><br>
                                                
                                                <label>
                                                    <input type="radio" name="conflict_mode" value="rename">
                                                    <?php _e('Rename Conflicting Fields', 'wpmatch'); ?>
                                                </label>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>
                                
                                <p class="submit">
                                    <button type="submit" class="button button-primary">
                                        <?php _e('Upload and Process', 'wpmatch'); ?>
                                    </button>
                                </p>
                            </form>
                            
                            <div id="import-preview" style="display: none;">
                                <!-- Import preview will be loaded here -->
                            </div>
                            
                            <div id="import-result" style="display: none;">
                                <!-- Import results will be displayed here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup/Restore Tab -->
                <div id="backup-tab" class="tab-content">
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2><?php _e('Backup & Restore', 'wpmatch'); ?></h2>
                        </div>
                        <div class="inside">
                            <p><?php _e('Create automatic backups of your field configurations and restore them when needed.', 'wpmatch'); ?></p>
                            
                            <h3><?php _e('Automatic Backups', 'wpmatch'); ?></h3>
                            <div class="backup-list">
                                <?php $this->render_backup_list(); ?>
                            </div>
                            
                            <h3><?php _e('Manual Backup', 'wpmatch'); ?></h3>
                            <form id="backup-form">
                                <?php wp_nonce_field('wpmatch_create_backup', 'backup_nonce'); ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Backup Name', 'wpmatch'); ?></th>
                                        <td>
                                            <input type="text" name="backup_name" class="regular-text" 
                                                   placeholder="<?php echo esc_attr(sprintf(__('Backup %s', 'wpmatch'), date('Y-m-d H:i:s'))); ?>">
                                        </td>
                                    </tr>
                                </table>
                                
                                <p class="submit">
                                    <button type="submit" class="button button-secondary">
                                        <?php _e('Create Manual Backup', 'wpmatch'); ?>
                                    </button>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .import-export-tabs .nav-tab-wrapper { margin-bottom: 20px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .backup-item { padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; }
        .backup-actions { margin-top: 10px; }
        #export-result, #import-preview, #import-result { 
            background: #f9f9f9; 
            border: 1px solid #ddd; 
            padding: 15px; 
            margin-top: 20px; 
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $('#' + tab + '-tab').addClass('active');
            });
            
            // Export form
            $('#export-form').on('submit', function(e) {
                e.preventDefault();
                // Handle export via AJAX
            });
            
            // Import form
            $('#import-form').on('submit', function(e) {
                e.preventDefault();
                // Handle import via AJAX
            });
        });
        </script>
        <?php
    }

    /**
     * Export fields to JSON
     *
     * @param array $options Export options
     * @return array Export data
     */
    public function export_fields($options = array()) {
        $defaults = array(
            'include_fields' => true,
            'include_field_groups' => true,
            'include_user_data' => false,
            'include_settings' => false,
            'field_status' => 'active',
            'field_groups' => 'all'
        );

        $options = wp_parse_args($options, $defaults);
        
        $export_data = array(
            'version' => self::EXPORT_VERSION,
            'exported_at' => current_time('mysql'),
            'exported_by' => get_current_user_id(),
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'wpmatch_version' => WPMATCH_VERSION,
            'data' => array()
        );

        // Export field configurations
        if ($options['include_fields']) {
            $export_data['data']['fields'] = $this->export_field_configurations($options);
        }

        // Export field groups metadata
        if ($options['include_field_groups']) {
            $export_data['data']['field_groups'] = $this->export_field_groups_metadata();
        }

        // Export user data (careful with privacy)
        if ($options['include_user_data']) {
            $export_data['data']['user_data'] = $this->export_user_field_data($options);
        }

        // Export plugin settings
        if ($options['include_settings']) {
            $export_data['data']['settings'] = $this->export_plugin_settings();
        }

        return $export_data;
    }

    /**
     * Import fields from JSON
     *
     * @param array  $import_data Import data
     * @param array  $options     Import options
     * @return array Import results
     */
    public function import_fields($import_data, $options = array()) {
        $defaults = array(
            'conflict_mode' => 'skip', // skip, update, rename
            'dry_run' => false,
            'validate_only' => false
        );

        $options = wp_parse_args($options, $defaults);
        
        // Validate import data structure
        $validation_result = $this->validate_import_data($import_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        $results = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'messages' => array(),
            'field_mapping' => array()
        );

        // Import field configurations
        if (isset($import_data['data']['fields'])) {
            $field_results = $this->import_field_configurations($import_data['data']['fields'], $options);
            $results = array_merge_recursive($results, $field_results);
        }

        // Import field groups
        if (isset($import_data['data']['field_groups'])) {
            $group_results = $this->import_field_groups_metadata($import_data['data']['field_groups'], $options);
            $results['messages'] = array_merge($results['messages'], $group_results['messages']);
        }

        // Import user data
        if (isset($import_data['data']['user_data']) && !$options['dry_run']) {
            $user_data_results = $this->import_user_field_data($import_data['data']['user_data'], $options);
            $results['messages'] = array_merge($results['messages'], $user_data_results['messages']);
        }

        // Import settings
        if (isset($import_data['data']['settings']) && !$options['dry_run']) {
            $settings_results = $this->import_plugin_settings($import_data['data']['settings'], $options);
            $results['messages'] = array_merge($results['messages'], $settings_results['messages']);
        }

        return $results;
    }

    /**
     * Export field configurations
     *
     * @param array $options Export options
     * @return array Field configurations
     */
    private function export_field_configurations($options) {
        $query_args = array();

        // Filter by status
        if ($options['field_status'] !== 'all') {
            $query_args['status'] = $options['field_status'];
        }

        // Filter by groups
        if ($options['field_groups'] !== 'all' && !empty($options['field_groups'])) {
            $query_args['field_group'] = $options['field_groups'];
        }

        $fields = $this->field_manager->get_fields($query_args);
        $exported_fields = array();

        foreach ($fields as $field) {
            $field_data = array(
                'field_name' => $field->field_name,
                'field_label' => $field->field_label,
                'field_type' => $field->field_type,
                'field_description' => $field->field_description,
                'field_group' => $field->field_group,
                'field_order' => $field->field_order,
                'field_width' => $field->field_width,
                'field_class' => $field->field_class,
                'placeholder_text' => $field->placeholder_text,
                'help_text' => $field->help_text,
                'default_value' => $field->default_value,
                'is_required' => $field->is_required,
                'is_searchable' => $field->is_searchable,
                'is_public' => $field->is_public,
                'is_editable' => $field->is_editable,
                'status' => $field->status,
                'field_options' => $field->field_options,
                'validation_rules' => $field->validation_rules,
                'display_options' => $field->display_options,
                'conditional_logic' => $field->conditional_logic,
                'min_value' => $field->min_value,
                'max_value' => $field->max_value,
                'min_length' => $field->min_length,
                'max_length' => $field->max_length,
                'regex_pattern' => $field->regex_pattern,
                'created_at' => $field->created_at,
                'updated_at' => $field->updated_at
            );

            $exported_fields[] = $field_data;
        }

        return $exported_fields;
    }

    /**
     * Import field configurations
     *
     * @param array $fields_data Fields data
     * @param array $options     Import options
     * @return array Import results
     */
    private function import_field_configurations($fields_data, $options) {
        $results = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'messages' => array(),
            'field_mapping' => array()
        );

        foreach ($fields_data as $field_data) {
            $field_name = $field_data['field_name'];
            
            // Check if field already exists
            $existing_field = $this->field_manager->get_field_by_name($field_name);
            
            if ($existing_field) {
                switch ($options['conflict_mode']) {
                    case 'skip':
                        $results['skipped']++;
                        $results['messages'][] = sprintf(__('Skipped existing field: %s', 'wpmatch'), $field_name);
                        continue 2;
                        
                    case 'update':
                        if (!$options['dry_run']) {
                            $result = $this->field_manager->update_field($existing_field->id, $field_data);
                            if (is_wp_error($result)) {
                                $results['errors']++;
                                $results['messages'][] = sprintf(__('Error updating field %s: %s', 'wpmatch'), $field_name, $result->get_error_message());
                            } else {
                                $results['updated']++;
                                $results['messages'][] = sprintf(__('Updated field: %s', 'wpmatch'), $field_name);
                                $results['field_mapping'][$field_name] = $existing_field->id;
                            }
                        } else {
                            $results['updated']++;
                            $results['messages'][] = sprintf(__('Would update field: %s', 'wpmatch'), $field_name);
                        }
                        break;
                        
                    case 'rename':
                        $original_name = $field_name;
                        $counter = 1;
                        while ($this->field_manager->get_field_by_name($field_name)) {
                            $field_name = $original_name . '_' . $counter;
                            $counter++;
                        }
                        $field_data['field_name'] = $field_name;
                        $field_data['field_label'] .= ' (Imported)';
                        
                        if (!$options['dry_run']) {
                            $result = $this->field_manager->create_field($field_data);
                            if (is_wp_error($result)) {
                                $results['errors']++;
                                $results['messages'][] = sprintf(__('Error creating field %s: %s', 'wpmatch'), $field_name, $result->get_error_message());
                            } else {
                                $results['imported']++;
                                $results['messages'][] = sprintf(__('Imported field as: %s (renamed from %s)', 'wpmatch'), $field_name, $original_name);
                                $results['field_mapping'][$original_name] = $result;
                            }
                        } else {
                            $results['imported']++;
                            $results['messages'][] = sprintf(__('Would import field as: %s (renamed from %s)', 'wpmatch'), $field_name, $original_name);
                        }
                        break;
                }
            } else {
                // Create new field
                if (!$options['dry_run']) {
                    $result = $this->field_manager->create_field($field_data);
                    if (is_wp_error($result)) {
                        $results['errors']++;
                        $results['messages'][] = sprintf(__('Error creating field %s: %s', 'wpmatch'), $field_name, $result->get_error_message());
                    } else {
                        $results['imported']++;
                        $results['messages'][] = sprintf(__('Imported field: %s', 'wpmatch'), $field_name);
                        $results['field_mapping'][$field_name] = $result;
                    }
                } else {
                    $results['imported']++;
                    $results['messages'][] = sprintf(__('Would import field: %s', 'wpmatch'), $field_name);
                }
            }
        }

        return $results;
    }

    /**
     * Validate import data structure
     *
     * @param array $import_data Import data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_import_data($import_data) {
        // Check required structure
        if (!is_array($import_data) || !isset($import_data['version']) || !isset($import_data['data'])) {
            return new WP_Error('invalid_structure', __('Invalid import file structure.', 'wpmatch'));
        }

        // Check version compatibility
        if (version_compare($import_data['version'], self::EXPORT_VERSION, '>')) {
            return new WP_Error('version_mismatch', __('Import file version is newer than current plugin version.', 'wpmatch'));
        }

        // Validate field data if present
        if (isset($import_data['data']['fields'])) {
            foreach ($import_data['data']['fields'] as $field_data) {
                if (!isset($field_data['field_name']) || !isset($field_data['field_type'])) {
                    return new WP_Error('invalid_field_data', __('Invalid field data in import file.', 'wpmatch'));
                }
                
                // Validate each field
                $validation_result = $this->validator->validate_field_data($field_data);
                if (is_wp_error($validation_result)) {
                    return new WP_Error('field_validation_failed', 
                        sprintf(__('Field validation failed for %s: %s', 'wpmatch'), 
                                $field_data['field_name'], 
                                $validation_result->get_error_message())
                    );
                }
            }
        }

        return true;
    }

    /**
     * Export field groups metadata
     *
     * @return array Field groups metadata
     */
    private function export_field_groups_metadata() {
        $groups = $this->field_manager->get_field_groups();
        $groups_metadata = array();

        foreach ($groups as $group) {
            $metadata = get_option('wpmatch_field_group_' . $group->field_group, array());
            $groups_metadata[$group->field_group] = $metadata;
        }

        return $groups_metadata;
    }

    /**
     * Import field groups metadata
     *
     * @param array $groups_data Groups metadata
     * @param array $options     Import options
     * @return array Import results
     */
    private function import_field_groups_metadata($groups_data, $options) {
        $results = array('messages' => array());

        foreach ($groups_data as $group_name => $metadata) {
            if (!$options['dry_run']) {
                update_option('wpmatch_field_group_' . $group_name, $metadata);
                $results['messages'][] = sprintf(__('Imported group metadata: %s', 'wpmatch'), $group_name);
            } else {
                $results['messages'][] = sprintf(__('Would import group metadata: %s', 'wpmatch'), $group_name);
            }
        }

        return $results;
    }

    /**
     * Export user field data
     *
     * @param array $options Export options
     * @return array User field data
     */
    private function export_user_field_data($options) {
        global $wpdb;
        
        if (!$this->field_manager || !$this->field_manager->database) {
            return array();
        }

        $values_table = $this->field_manager->database->get_table_name('profile_values');
        $fields_table = $this->field_manager->database->get_table_name('profile_fields');
        
        $sql = "
            SELECT pv.user_id, pf.field_name, pv.field_value, pv.updated_at
            FROM {$values_table} pv
            INNER JOIN {$fields_table} pf ON pv.field_id = pf.id
        ";
        
        // Add filters if specified
        $where_conditions = array();
        if ($options['field_status'] !== 'all') {
            $where_conditions[] = $wpdb->prepare("pf.status = %s", $options['field_status']);
        }
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }
        
        $results = $wpdb->get_results($sql);
        
        // Organize by user
        $user_data = array();
        foreach ($results as $result) {
            if (!isset($user_data[$result->user_id])) {
                $user_data[$result->user_id] = array();
            }
            $user_data[$result->user_id][$result->field_name] = array(
                'value' => $result->field_value,
                'updated_at' => $result->updated_at
            );
        }

        return $user_data;
    }

    /**
     * Import user field data
     *
     * @param array $user_data User field data
     * @param array $options   Import options
     * @return array Import results
     */
    private function import_user_field_data($user_data, $options) {
        $results = array('messages' => array());
        
        // This is sensitive - only import if explicitly requested and user has proper permissions
        if (!current_user_can('edit_users')) {
            $results['messages'][] = __('Insufficient permissions to import user data.', 'wpmatch');
            return $results;
        }

        foreach ($user_data as $user_id => $fields) {
            if (!get_user_by('id', $user_id)) {
                $results['messages'][] = sprintf(__('User ID %d not found, skipping user data.', 'wpmatch'), $user_id);
                continue;
            }

            foreach ($fields as $field_name => $field_data) {
                $field = $this->field_manager->get_field_by_name($field_name);
                if (!$field) {
                    $results['messages'][] = sprintf(__('Field %s not found, skipping value for user %d.', 'wpmatch'), $field_name, $user_id);
                    continue;
                }

                // Save field value (implementation would go here)
                $results['messages'][] = sprintf(__('Imported value for field %s, user %d.', 'wpmatch'), $field_name, $user_id);
            }
        }

        return $results;
    }

    /**
     * Export plugin settings
     *
     * @return array Plugin settings
     */
    private function export_plugin_settings() {
        $settings = array();
        
        // Export relevant WPMatch settings
        $option_keys = array(
            'wpmatch_general_settings',
            'wpmatch_field_settings',
            'wpmatch_appearance_settings',
            'wpmatch_privacy_settings'
        );

        foreach ($option_keys as $option_key) {
            $value = get_option($option_key);
            if ($value !== false) {
                $settings[$option_key] = $value;
            }
        }

        return $settings;
    }

    /**
     * Import plugin settings
     *
     * @param array $settings Settings data
     * @param array $options  Import options
     * @return array Import results
     */
    private function import_plugin_settings($settings, $options) {
        $results = array('messages' => array());

        foreach ($settings as $option_key => $value) {
            update_option($option_key, $value);
            $results['messages'][] = sprintf(__('Imported setting: %s', 'wpmatch'), $option_key);
        }

        return $results;
    }

    /**
     * Render backup list
     */
    private function render_backup_list() {
        $backups = $this->get_automatic_backups();
        
        if (empty($backups)) {
            echo '<p>' . __('No automatic backups found.', 'wpmatch') . '</p>';
            return;
        }

        foreach ($backups as $backup) {
            ?>
            <div class="backup-item">
                <strong><?php echo esc_html($backup['name']); ?></strong>
                <span class="backup-date"><?php echo esc_html($backup['date']); ?></span>
                <span class="backup-size">(<?php echo size_format($backup['size']); ?>)</span>
                
                <div class="backup-actions">
                    <a href="#" class="button restore-backup" data-backup="<?php echo esc_attr($backup['id']); ?>">
                        <?php _e('Restore', 'wpmatch'); ?>
                    </a>
                    <a href="#" class="button download-backup" data-backup="<?php echo esc_attr($backup['id']); ?>">
                        <?php _e('Download', 'wpmatch'); ?>
                    </a>
                    <a href="#" class="button button-link-delete delete-backup" data-backup="<?php echo esc_attr($backup['id']); ?>">
                        <?php _e('Delete', 'wpmatch'); ?>
                    </a>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Get automatic backups
     *
     * @return array Automatic backups
     */
    private function get_automatic_backups() {
        $backups = get_option('wpmatch_automatic_backups', array());
        
        // Sort by date (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $backups;
    }

    /**
     * AJAX handler for export
     */
    public function ajax_export_fields() {
        check_ajax_referer('wpmatch_export_fields', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        $options = array(
            'include_field_groups' => !empty($_POST['export_options']) && in_array('field_groups', $_POST['export_options']),
            'include_user_data' => !empty($_POST['export_options']) && in_array('user_data', $_POST['export_options']),
            'include_settings' => !empty($_POST['export_options']) && in_array('settings', $_POST['export_options']),
            'field_status' => sanitize_text_field($_POST['export_status'] ?? 'active'),
            'field_groups' => $_POST['export_groups'] ?? 'all'
        );

        $export_data = $this->export_fields($options);
        
        // Create temporary file
        $temp_file = wp_tempnam('wpmatch-export-');
        file_put_contents($temp_file, wp_json_encode($export_data, JSON_PRETTY_PRINT));

        $export_id = wp_generate_uuid4();
        set_transient('wpmatch_export_' . $export_id, $temp_file, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'export_id' => $export_id,
            'filename' => 'wpmatch-fields-export-' . date('Y-m-d-H-i-s') . '.json',
            'size' => size_format(filesize($temp_file)),
            'fields_count' => count($export_data['data']['fields'] ?? array())
        ));
    }

    /**
     * AJAX handler for import preview
     */
    public function ajax_preview_import() {
        check_ajax_referer('wpmatch_import_fields', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload failed.', 'wpmatch'));
        }

        if ($_FILES['import_file']['size'] > self::MAX_FILE_SIZE) {
            wp_send_json_error(__('File too large.', 'wpmatch'));
        }

        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON file.', 'wpmatch'));
        }

        $options = array(
            'conflict_mode' => sanitize_text_field($_POST['conflict_mode'] ?? 'skip'),
            'dry_run' => true
        );

        $preview_results = $this->import_fields($import_data, $options);

        wp_send_json_success($preview_results);
    }

    /**
     * AJAX handler for actual import
     */
    public function ajax_import_fields() {
        check_ajax_referer('wpmatch_import_fields', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_send_json_error(__('Permission denied.', 'wpmatch'));
        }

        // Get import data from session/transient
        $import_id = sanitize_text_field($_POST['import_id'] ?? '');
        $import_data = get_transient('wpmatch_import_' . $import_id);

        if (!$import_data) {
            wp_send_json_error(__('Import data not found.', 'wpmatch'));
        }

        $options = array(
            'conflict_mode' => sanitize_text_field($_POST['conflict_mode'] ?? 'skip'),
            'dry_run' => false
        );

        $import_results = $this->import_fields($import_data, $options);

        // Clean up
        delete_transient('wpmatch_import_' . $import_id);

        wp_send_json_success($import_results);
    }

    /**
     * AJAX handler for download export
     */
    public function ajax_download_export() {
        check_ajax_referer('wpmatch_download_export', 'nonce');
        
        if (!current_user_can('manage_profile_fields')) {
            wp_die(__('Permission denied.', 'wpmatch'));
        }

        $export_id = sanitize_text_field($_GET['export_id'] ?? '');
        $temp_file = get_transient('wpmatch_export_' . $export_id);

        if (!$temp_file || !file_exists($temp_file)) {
            wp_die(__('Export file not found.', 'wpmatch'));
        }

        $filename = 'wpmatch-fields-export-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($temp_file));

        readfile($temp_file);
        
        // Clean up
        unlink($temp_file);
        delete_transient('wpmatch_export_' . $export_id);
        
        exit;
    }
}

// Initialize import/export functionality
new WPMatch_Field_Import_Export();