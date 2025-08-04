<?php
/**
 * Profile Field Manager class for CRUD operations
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Profile Field Manager class
 * 
 * Handles all CRUD operations for profile fields including creation,
 * retrieval, updating, and deletion with proper validation and security.
 */
class WPMatch_Profile_Field_Manager {

    /**
     * Database instance
     *
     * @var WPMatch_Database
     */
    private $database;

    /**
     * Field type registry
     *
     * @var WPMatch_Field_Type_Registry
     */
    private $type_registry;

    /**
     * Field validator
     *
     * @var WPMatch_Field_Validator
     */
    private $validator;

    /**
     * Cache key prefix
     *
     * @var string
     */
    private $cache_prefix = 'wpmatch_field_';

    /**
     * Constructor
     *
     * @param WPMatch_Database $database Database instance
     */
    public function __construct($database = null) {
        $this->database = $database;
        
        // Initialize dependencies
        add_action('init', array($this, 'init_dependencies'), 15);
    }

    /**
     * Initialize dependencies
     */
    public function init_dependencies() {
        // Load and initialize field type registry and validator
        require_once WPMATCH_INCLUDES_PATH . 'class-field-type-registry.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-field-validator.php';
        
        $this->type_registry = new WPMatch_Field_Type_Registry();
        $this->validator = new WPMatch_Field_Validator();
    }

    /**
     * Create a new profile field
     *
     * @param array $field_data Field configuration data
     * @return int|WP_Error Field ID on success, WP_Error on failure
     */
    public function create_field($field_data) {
        // Validate permissions
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to create profile fields.', 'wpmatch'));
        }

        // Validate field data
        $validation_result = $this->validator->validate_field_data($field_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Sanitize and prepare field data
        $sanitized_data = $this->sanitize_field_data($field_data);
        
        // Check for duplicate field name
        if ($this->field_name_exists($sanitized_data['field_name'])) {
            return new WP_Error('duplicate_field_name', __('A field with this name already exists.', 'wpmatch'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        // Prepare field data for insertion
        $insert_data = array(
            'field_name' => $sanitized_data['field_name'],
            'field_label' => $sanitized_data['field_label'],
            'field_type' => $sanitized_data['field_type'],
            'field_description' => $sanitized_data['field_description'] ?? '',
            'placeholder_text' => $sanitized_data['placeholder_text'] ?? '',
            'help_text' => $sanitized_data['help_text'] ?? '',
            'field_options' => $sanitized_data['field_options'] ? wp_json_encode($sanitized_data['field_options']) : null,
            'validation_rules' => $sanitized_data['validation_rules'] ? wp_json_encode($sanitized_data['validation_rules']) : null,
            'display_options' => $sanitized_data['display_options'] ? wp_json_encode($sanitized_data['display_options']) : null,
            'conditional_logic' => $sanitized_data['conditional_logic'] ? wp_json_encode($sanitized_data['conditional_logic']) : null,
            'is_required' => $sanitized_data['is_required'] ? 1 : 0,
            'is_searchable' => $sanitized_data['is_searchable'] ? 1 : 0,
            'is_public' => $sanitized_data['is_public'] ? 1 : 0,
            'is_editable' => $sanitized_data['is_editable'] ?? 1,
            'field_group' => $sanitized_data['field_group'] ?? 'basic',
            'field_order' => $this->get_next_field_order($sanitized_data['field_group'] ?? 'basic'),
            'status' => $sanitized_data['status'] ?? 'active',
            'min_value' => $sanitized_data['min_value'] ?? null,
            'max_value' => $sanitized_data['max_value'] ?? null,
            'min_length' => $sanitized_data['min_length'] ?? null,
            'max_length' => $sanitized_data['max_length'] ?? null,
            'regex_pattern' => $sanitized_data['regex_pattern'] ?? null,
            'default_value' => $sanitized_data['default_value'] ?? null,
            'field_width' => $sanitized_data['field_width'] ?? 'full',
            'field_class' => $sanitized_data['field_class'] ?? null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        // Insert field
        $result = $wpdb->insert($table_name, $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create profile field.', 'wpmatch'), $wpdb->last_error);
        }

        $field_id = $wpdb->insert_id;

        // Log field creation
        $this->log_field_change($field_id, 'created', null, $insert_data, __('Field created', 'wpmatch'));

        // Clear cache
        $this->clear_field_cache();

        /**
         * Fires after a profile field is created
         *
         * @param int   $field_id    The created field ID
         * @param array $field_data  The field data
         */
        do_action('wpmatch_field_created', $field_id, $sanitized_data);

        return $field_id;
    }

    /**
     * Update an existing profile field
     *
     * @param int   $field_id   Field ID to update
     * @param array $field_data Updated field data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_field($field_id, $field_data) {
        // Validate permissions
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to update profile fields.', 'wpmatch'));
        }

        // Get existing field
        $existing_field = $this->get_field($field_id);
        if (!$existing_field) {
            return new WP_Error('field_not_found', __('Profile field not found.', 'wpmatch'));
        }

        // Check if field is system field
        if (!empty($existing_field->is_system)) {
            return new WP_Error('system_field_protected', __('System fields cannot be modified.', 'wpmatch'));
        }

        // Validate field data
        $validation_result = $this->validator->validate_field_data($field_data, $field_id);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Sanitize field data
        $sanitized_data = $this->sanitize_field_data($field_data);

        // Check for duplicate field name (if name is being changed)
        if (isset($sanitized_data['field_name']) && 
            $sanitized_data['field_name'] !== $existing_field->field_name &&
            $this->field_name_exists($sanitized_data['field_name'])) {
            return new WP_Error('duplicate_field_name', __('A field with this name already exists.', 'wpmatch'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        // Prepare update data
        $update_data = array();
        $allowed_fields = array(
            'field_label', 'field_description', 'placeholder_text', 'help_text',
            'is_required', 'is_searchable', 'is_public', 'is_editable',
            'field_group', 'field_order', 'status', 'min_value', 'max_value',
            'min_length', 'max_length', 'regex_pattern', 'default_value',
            'field_width', 'field_class'
        );

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $sanitized_data)) {
                $update_data[$field] = $sanitized_data[$field];
            }
        }

        // Handle JSON fields
        $json_fields = array('field_options', 'validation_rules', 'display_options', 'conditional_logic');
        foreach ($json_fields as $field) {
            if (array_key_exists($field, $sanitized_data)) {
                $update_data[$field] = $sanitized_data[$field] ? wp_json_encode($sanitized_data[$field]) : null;
            }
        }

        // Add metadata
        $update_data['updated_by'] = get_current_user_id();
        $update_data['updated_at'] = current_time('mysql');

        // Update field
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $field_id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update profile field.', 'wpmatch'), $wpdb->last_error);
        }

        // Log field update
        $this->log_field_change($field_id, 'updated', $existing_field, $update_data, __('Field updated', 'wpmatch'));

        // Clear cache
        $this->clear_field_cache($field_id);

        /**
         * Fires after a profile field is updated
         *
         * @param int    $field_id      The updated field ID
         * @param array  $field_data    The new field data
         * @param object $existing_field The previous field data
         */
        do_action('wpmatch_field_updated', $field_id, $sanitized_data, $existing_field);

        return true;
    }

    /**
     * Delete a profile field
     *
     * @param int  $field_id    Field ID to delete
     * @param bool $force_delete Whether to force delete (bypass safety checks)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_field($field_id, $force_delete = false) {
        // Validate permissions
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to delete profile fields.', 'wpmatch'));
        }

        // Get existing field
        $existing_field = $this->get_field($field_id);
        if (!$existing_field) {
            return new WP_Error('field_not_found', __('Profile field not found.', 'wpmatch'));
        }

        // Check if field is system field
        if (!empty($existing_field->is_system) && !$force_delete) {
            return new WP_Error('system_field_protected', __('System fields cannot be deleted.', 'wpmatch'));
        }

        // Check for existing user data
        $user_data_count = $this->get_field_usage_count($field_id);
        if ($user_data_count > 0 && !$force_delete) {
            // Mark as deprecated instead of deleting
            $result = $this->update_field($field_id, array('status' => 'deprecated'));
            if (is_wp_error($result)) {
                return $result;
            }

            // Schedule deletion after retention period
            wp_schedule_single_event(
                time() + (30 * DAY_IN_SECONDS), // 30 days
                'wpmatch_cleanup_deprecated_field',
                array($field_id)
            );

            return new WP_Error('field_has_data', 
                sprintf(__('Field has user data and has been marked as deprecated. It will be permanently deleted in 30 days. %d users will be affected.', 'wpmatch'), $user_data_count)
            );
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        // Create backup of field configuration
        $backup_data = array(
            'field_id' => $field_id,
            'field_data' => $existing_field,
            'user_data_count' => $user_data_count,
            'deleted_by' => get_current_user_id(),
            'deleted_at' => current_time('mysql')
        );

        // Store backup in options table
        $backup_option_name = 'wpmatch_deleted_field_' . $field_id . '_' . time();
        update_option($backup_option_name, $backup_data, false);

        // Delete field
        $result = $wpdb->delete(
            $table_name,
            array('id' => $field_id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete profile field.', 'wpmatch'), $wpdb->last_error);
        }

        // Log field deletion
        $this->log_field_change($field_id, 'deleted', $existing_field, null, __('Field deleted', 'wpmatch'));

        // Clear cache
        $this->clear_field_cache();

        /**
         * Fires after a profile field is deleted
         *
         * @param int    $field_id       The deleted field ID
         * @param object $existing_field The deleted field data
         * @param int    $user_data_count Number of affected users
         */
        do_action('wpmatch_field_deleted', $field_id, $existing_field, $user_data_count);

        return true;
    }

    /**
     * Get a profile field by ID
     *
     * @param int $field_id Field ID
     * @return object|null Field object or null if not found
     */
    public function get_field($field_id) {
        // Try performance optimizer cache first
        if (class_exists('WPMatch_Performance_Optimizer')) {
            $field = WPMatch_Performance_Optimizer::get_cached_field($field_id);
            if ($field !== false) {
                return $field;
            }
        }

        // Fallback to standard cache
        $cache_key = $this->cache_prefix . 'id_' . $field_id;
        $field = wp_cache_get($cache_key);

        if ($field === false) {
            global $wpdb;
            $table_name = $this->database->get_table_name('profile_fields');

            $field = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $field_id
            ));

            if ($field) {
                // Decode JSON fields
                $this->decode_field_json($field);
                
                // Cache with performance optimizer
                if (class_exists('WPMatch_Performance_Optimizer')) {
                    WPMatch_Performance_Optimizer::cache_field($field_id, $field);
                }
                
                wp_cache_set($cache_key, $field, '', HOUR_IN_SECONDS);
            }
        }

        return $field;
    }

    /**
     * Get a profile field by name
     *
     * @param string $field_name Field name
     * @return object|null Field object or null if not found
     */
    public function get_field_by_name($field_name) {
        $cache_key = $this->cache_prefix . 'name_' . md5($field_name);
        $field = wp_cache_get($cache_key);

        if ($field === false) {
            global $wpdb;
            $table_name = $this->database->get_table_name('profile_fields');

            $field = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE field_name = %s",
                $field_name
            ));

            if ($field) {
                // Decode JSON fields
                $this->decode_field_json($field);
                wp_cache_set($cache_key, $field, '', HOUR_IN_SECONDS);
            }
        }

        return $field;
    }

    /**
     * Get multiple profile fields
     *
     * @param array $args Query arguments
     * @return array Array of field objects
     */
    public function get_fields($args = array()) {
        $defaults = array(
            'status' => 'active',
            'field_type' => null,
            'field_group' => null,
            'is_searchable' => null,
            'is_required' => null,
            'is_public' => null,
            'orderby' => 'field_order',
            'order' => 'ASC',
            'limit' => null,
            'offset' => 0,
            'fields' => '*'
        );

        $args = wp_parse_args($args, $defaults);

        // Use performance optimized version if available
        if (class_exists('WPMatch_Performance_Optimizer')) {
            $fields = WPMatch_Performance_Optimizer::get_optimized_fields($args);
            if (!empty($fields)) {
                // Decode JSON fields
                if ($args['fields'] === '*') {
                    foreach ($fields as $field) {
                        $this->decode_field_json($field);
                    }
                }
                return $fields;
            }
        }

        // Fallback to standard implementation
        $cache_key = $this->cache_prefix . 'query_' . md5(serialize($args));
        $fields = wp_cache_get($cache_key);

        if ($fields === false) {
            global $wpdb;
            $table_name = $this->database->get_table_name('profile_fields');

            // Build query
            $where_clauses = array();
            $where_values = array();

            if ($args['status']) {
                if (is_array($args['status'])) {
                    $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                    $where_clauses[] = "status IN ({$placeholders})";
                    $where_values = array_merge($where_values, $args['status']);
                } else {
                    $where_clauses[] = "status = %s";
                    $where_values[] = $args['status'];
                }
            }

            if ($args['field_type']) {
                if (is_array($args['field_type'])) {
                    $placeholders = implode(',', array_fill(0, count($args['field_type']), '%s'));
                    $where_clauses[] = "field_type IN ({$placeholders})";
                    $where_values = array_merge($where_values, $args['field_type']);
                } else {
                    $where_clauses[] = "field_type = %s";
                    $where_values[] = $args['field_type'];
                }
            }

            if ($args['field_group']) {
                $where_clauses[] = "field_group = %s";
                $where_values[] = $args['field_group'];
            }

            if ($args['is_searchable'] !== null) {
                $where_clauses[] = "is_searchable = %d";
                $where_values[] = $args['is_searchable'] ? 1 : 0;
            }

            if ($args['is_required'] !== null) {
                $where_clauses[] = "is_required = %d";
                $where_values[] = $args['is_required'] ? 1 : 0;
            }

            if ($args['is_public'] !== null) {
                $where_clauses[] = "is_public = %d";
                $where_values[] = $args['is_public'] ? 1 : 0;
            }

            // Build WHERE clause
            $where_sql = '';
            if (!empty($where_clauses)) {
                $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
            }

            // Build ORDER BY clause
            $allowed_orderby = array('id', 'field_name', 'field_label', 'field_type', 'field_group', 'field_order', 'created_at', 'updated_at');
            $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'field_order';
            $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
            $order_sql = "ORDER BY {$orderby} {$order}";

            // Build LIMIT clause
            $limit_sql = '';
            if ($args['limit']) {
                $limit_sql = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
            }

            // Execute query
            $sql = "SELECT {$args['fields']} FROM {$table_name} {$where_sql} {$order_sql} {$limit_sql}";
            
            if (!empty($where_values)) {
                $sql = $wpdb->prepare($sql, ...$where_values);
            }

            $fields = $wpdb->get_results($sql);

            // Decode JSON fields
            if ($fields && $args['fields'] === '*') {
                foreach ($fields as $field) {
                    $this->decode_field_json($field);
                }
            }

            wp_cache_set($cache_key, $fields, '', HOUR_IN_SECONDS);
        }

        return $fields ?: array();
    }

    /**
     * Get field groups with field counts
     *
     * @return array Array of group data
     */
    public function get_field_groups() {
        // Try performance optimizer cache first
        if (class_exists('WPMatch_Performance_Optimizer')) {
            $groups = WPMatch_Performance_Optimizer::get_cached_field_groups();
            if ($groups !== false) {
                return $groups;
            }
        }

        $cache_key = $this->cache_prefix . 'groups';
        $groups = wp_cache_get($cache_key);

        if ($groups === false) {
            global $wpdb;
            $table_name = $this->database->get_table_name('profile_fields');

            $groups = $wpdb->get_results("
                SELECT 
                    field_group,
                    COUNT(*) as field_count,
                    MAX(field_order) as max_order
                FROM {$table_name} USE INDEX (idx_field_composite)
                WHERE status = 'active'
                GROUP BY field_group
                ORDER BY field_group
            ");

            // Cache with performance optimizer
            if (class_exists('WPMatch_Performance_Optimizer')) {
                WPMatch_Performance_Optimizer::cache_field_groups($groups);
            }

            wp_cache_set($cache_key, $groups, '', HOUR_IN_SECONDS);
        }

        return $groups ?: array();
    }

    /**
     * Reorder fields within a group
     *
     * @param array $field_orders Array of field_id => order pairs
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function reorder_fields($field_orders) {
        // Validate permissions
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to reorder profile fields.', 'wpmatch'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        $wpdb->query('START TRANSACTION');

        try {
            foreach ($field_orders as $field_id => $order_data) {
                $field_id = absint($field_id);
                $order = absint($order_data['order']);
                $group = sanitize_text_field($order_data['group']);

                $result = $wpdb->update(
                    $table_name,
                    array(
                        'field_order' => $order,
                        'field_group' => $group,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $field_id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );

                if ($result === false) {
                    throw new Exception('Database update failed');
                }
            }

            $wpdb->query('COMMIT');

            // Clear cache
            $this->clear_field_cache();

            /**
             * Fires after fields are reordered
             *
             * @param array $field_orders The field order data
             */
            do_action('wpmatch_fields_reordered', $field_orders);

            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('reorder_failed', __('Failed to reorder fields.', 'wpmatch'), $e->getMessage());
        }
    }

    /**
     * Check if field name exists
     *
     * @param string $field_name Field name to check
     * @param int    $exclude_id Optional field ID to exclude from check
     * @return bool True if exists, false otherwise
     */
    public function field_name_exists($field_name, $exclude_id = null) {
        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        $sql = "SELECT id FROM {$table_name} WHERE field_name = %s";
        $params = array($field_name);

        if ($exclude_id) {
            $sql .= " AND id != %d";
            $params[] = $exclude_id;
        }

        $exists = $wpdb->get_var($wpdb->prepare($sql, ...$params));
        return !empty($exists);
    }

    /**
     * Get field usage count (number of users with values for this field)
     *
     * @param int $field_id Field ID
     * @return int Usage count
     */
    public function get_field_usage_count($field_id) {
        global $wpdb;
        $table_name = $this->database->get_table_name('profile_values');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$table_name} WHERE field_id = %d",
            $field_id
        ));
    }

    /**
     * Get next field order for a group
     *
     * @param string $group_name Group name
     * @return int Next order number
     */


    /**
     * Sanitize field data
     *
     * @param array $field_data Raw field data
     * @return array Sanitized field data
     */
    private function sanitize_field_data($field_data) {
        $sanitized = array();

        // String fields
        $string_fields = array(
            'field_name', 'field_label', 'field_type', 'field_description',
            'placeholder_text', 'help_text', 'field_group', 'status',
            'regex_pattern', 'default_value', 'field_width', 'field_class'
        );

        foreach ($string_fields as $field) {
            if (isset($field_data[$field])) {
                $sanitized[$field] = sanitize_text_field($field_data[$field]);
            }
        }

        // Textarea fields
        if (isset($field_data['field_description'])) {
            $sanitized['field_description'] = sanitize_textarea_field($field_data['field_description']);
        }

        // Boolean fields
        $boolean_fields = array('is_required', 'is_searchable', 'is_public', 'is_editable');
        foreach ($boolean_fields as $field) {
            if (isset($field_data[$field])) {
                $sanitized[$field] = (bool) $field_data[$field];
            }
        }

        // Numeric fields
        $numeric_fields = array('min_value', 'max_value', 'min_length', 'max_length', 'field_order');
        foreach ($numeric_fields as $field) {
            if (isset($field_data[$field]) && $field_data[$field] !== '') {
                $sanitized[$field] = is_numeric($field_data[$field]) ? $field_data[$field] : null;
            }
        }

        // Array/object fields (will be JSON encoded)
        $json_fields = array('field_options', 'validation_rules', 'display_options', 'conditional_logic');
        foreach ($json_fields as $field) {
            if (isset($field_data[$field])) {
                $sanitized[$field] = $field_data[$field];
            }
        }

        // Validate field name format
        if (isset($sanitized['field_name'])) {
            $sanitized['field_name'] = strtolower(preg_replace('/[^a-z0-9_]/', '_', $sanitized['field_name']));
        }

        return $sanitized;
    }

    /**
     * Decode JSON fields in field object
     *
     * @param object $field Field object
     */
    private function decode_field_json($field) {
        $json_fields = array('field_options', 'validation_rules', 'display_options', 'conditional_logic');
        
        foreach ($json_fields as $json_field) {
            if (isset($field->$json_field) && !empty($field->$json_field)) {
                $decoded = json_decode($field->$json_field, true);
                $field->$json_field = is_array($decoded) ? $decoded : null;
            } else {
                $field->$json_field = null;
            }
        }
    }

    /**
     * Log field changes for audit trail
     *
     * @param int    $field_id     Field ID
     * @param string $change_type  Type of change
     * @param mixed  $old_value    Previous value
     * @param mixed  $new_value    New value
     * @param string $reason       Reason for change
     */
    private function log_field_change($field_id, $change_type, $old_value, $new_value, $reason = '') {
        global $wpdb;
        $history_table = $this->database->get_table_name('field_history');

        // Only log if history table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$history_table}'") !== $history_table) {
            return;
        }

        $wpdb->insert($history_table, array(
            'field_id' => $field_id,
            'change_type' => $change_type,
            'old_value' => $old_value ? wp_json_encode($old_value) : null,
            'new_value' => $new_value ? wp_json_encode($new_value) : null,
            'changed_by' => get_current_user_id(),
            'change_reason' => $reason,
            'change_ip' => $this->get_user_ip(),
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Get user IP address
     *
     * @return string User IP address
     */
    private function get_user_ip() {
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
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Duplicate a field
     *
     * @param int $field_id Field ID to duplicate
     * @return int|WP_Error New field ID on success, WP_Error on failure
     */
    public function duplicate_field($field_id) {
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to duplicate fields.', 'wpmatch'));
        }

        $original_field = $this->get_field($field_id);
        if (!$original_field) {
            return new WP_Error('field_not_found', __('Original field not found.', 'wpmatch'));
        }

        // Create new field data based on original
        $new_field_data = array(
            'field_name' => $original_field->field_name . '_copy',
            'field_label' => $original_field->field_label . ' (Copy)',
            'field_type' => $original_field->field_type,
            'field_options' => $original_field->field_options,
            'field_description' => $original_field->field_description,
            'placeholder_text' => $original_field->placeholder_text,
            'help_text' => $original_field->help_text,
            'validation_rules' => $original_field->validation_rules,
            'is_required' => $original_field->is_required,
            'is_searchable' => $original_field->is_searchable,
            'is_public' => $original_field->is_public,
            'is_editable' => $original_field->is_editable,
            'field_order' => $this->get_next_field_order(),
            'field_group' => $original_field->field_group,
            'status' => 'draft', // Set to draft by default
            'min_value' => $original_field->min_value,
            'max_value' => $original_field->max_value,
            'min_length' => $original_field->min_length,
            'max_length' => $original_field->max_length,
            'regex_pattern' => $original_field->regex_pattern,
            'default_value' => $original_field->default_value,
            'field_width' => $original_field->field_width,
            'field_class' => $original_field->field_class,
            'conditional_logic' => $original_field->conditional_logic,
        );

        // Ensure unique field name
        $base_name = $new_field_data['field_name'];
        $counter = 1;
        while ($this->field_name_exists($new_field_data['field_name'])) {
            $new_field_data['field_name'] = $base_name . '_' . $counter;
            $counter++;
        }

        return $this->create_field($new_field_data);
    }

    /**
     * Update field status
     *
     * @param int    $field_id Field ID
     * @param string $status   New status
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_field_status($field_id, $status) {
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to update field status.', 'wpmatch'));
        }

        $allowed_statuses = array('active', 'inactive', 'draft', 'deprecated', 'archived');
        if (!in_array($status, $allowed_statuses)) {
            return new WP_Error('invalid_status', __('Invalid field status.', 'wpmatch'));
        }

        $field = $this->get_field($field_id);
        if (!$field) {
            return new WP_Error('field_not_found', __('Field not found.', 'wpmatch'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        $result = $wpdb->update(
            $table_name,
            array(
                'status' => $status,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $field_id),
            array('%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update field status.', 'wpmatch'));
        }

        // Log the change
        $this->log_field_change($field_id, 'status_change', $field->status, $status, 'Status updated via admin');

        // Clear cache
        $this->clear_field_cache($field_id);

        /**
         * Fires after field status is updated
         *
         * @param int    $field_id   Field ID
         * @param string $new_status New status
         * @param string $old_status Previous status
         */
        do_action('wpmatch_field_status_updated', $field_id, $status, $field->status);

        return true;
    }

    /**
     * Update field order
     *
     * @param int $field_id Field ID
     * @param int $order    New order
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_field_order($field_id, $order) {
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to update field order.', 'wpmatch'));
        }

        $field = $this->get_field($field_id);
        if (!$field) {
            return new WP_Error('field_not_found', __('Field not found.', 'wpmatch'));
        }

        $order = absint($order);
        if ($order < 0 || $order > 999) {
            return new WP_Error('invalid_order', __('Field order must be between 0 and 999.', 'wpmatch'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        $result = $wpdb->update(
            $table_name,
            array(
                'field_order' => $order,
                'updated_by' => get_current_user_id(),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $field_id),
            array('%d', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update field order.', 'wpmatch'));
        }

        // Clear cache
        $this->clear_field_cache($field_id);

        return true;
    }

    /**
     * Get next available field order
     *
     * @param string $field_group Optional field group
     * @return int Next order number
     */
    private function get_next_field_order($field_group = null) {
        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        $sql = "SELECT MAX(field_order) FROM {$table_name}";
        if ($field_group) {
            $sql .= $wpdb->prepare(" WHERE field_group = %s", $field_group);
        }

        $max_order = $wpdb->get_var($sql);
        return ($max_order ? $max_order : 0) + 10;
    }

    /**
     * Get field counts by status
     *
     * @return array Status counts
     */
    public function get_status_counts() {
        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        $results = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM {$table_name} 
            GROUP BY status
        ");

        $counts = array();
        foreach ($results as $result) {
            $counts[$result->status] = (int) $result->count;
        }

        return $counts;
    }

    /**
     * Get fields count for a query
     *
     * @param array $args Query arguments
     * @return int Fields count
     */
    public function get_fields_count($args = array()) {
        global $wpdb;
        $table_name = $this->database->get_table_name('profile_fields');

        $where_conditions = array();
        $where_values = array();

        // Build where conditions based on args (similar to get_fields)
        if (!empty($args['search'])) {
            $where_conditions[] = "(field_name LIKE %s OR field_label LIKE %s OR field_description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }

        if (!empty($args['field_type'])) {
            $where_conditions[] = "field_type = %s";
            $where_values[] = $args['field_type'];
        }

        if (!empty($args['field_group'])) {
            $where_conditions[] = "field_group = %s";
            $where_values[] = $args['field_group'];
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $sql = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";

        if (!empty($where_values)) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, ...$where_values));
        } else {
            return (int) $wpdb->get_var($sql);
        }
    }

    /**
     * Search profiles based on field criteria
     *
     * @param array $search_criteria Search criteria array
     * @param array $options Search options (limit, offset, etc.)
     * @return array Array of matching profile data
     */
    public function search_profiles($search_criteria = array(), $options = array()) {
        global $wpdb;

        if (empty($search_criteria) || !is_array($search_criteria)) {
            return array();
        }

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'last_active',
            'order' => 'DESC',
            'min_match_score' => 0.5
        );

        $options = wp_parse_args($options, $defaults);

        $profiles_table = $this->database->get_table_name('profiles');
        $values_table = $this->database->get_table_name('profile_values');
        $fields_table = $this->database->get_table_name('profile_fields');

        $join_conditions = array();
        $where_conditions = array("p.status = 'active'");
        $where_values = array();

        $join_count = 0;

        // Build search conditions for each field criteria
        foreach ($search_criteria as $field_name => $field_criteria) {
            $join_count++;
            $join_alias = "pv{$join_count}";
            $field_alias = "pf{$join_count}";

            $join_conditions[] = "LEFT JOIN {$values_table} {$join_alias} ON p.user_id = {$join_alias}.user_id";
            $join_conditions[] = "LEFT JOIN {$fields_table} {$field_alias} ON {$join_alias}.field_id = {$field_alias}.id";

            $where_conditions[] = "{$field_alias}.field_name = %s";
            $where_values[] = $field_name;

            // Handle different field criteria types
            if (is_array($field_criteria)) {
                if (isset($field_criteria['value'])) {
                    $where_conditions[] = "{$join_alias}.field_value = %s";
                    $where_values[] = $field_criteria['value'];
                }

                if (isset($field_criteria['min_value']) && is_numeric($field_criteria['min_value'])) {
                    $where_conditions[] = "{$join_alias}.field_value_numeric >= %f";
                    $where_values[] = $field_criteria['min_value'];
                }

                if (isset($field_criteria['max_value']) && is_numeric($field_criteria['max_value'])) {
                    $where_conditions[] = "{$join_alias}.field_value_numeric <= %f";
                    $where_values[] = $field_criteria['max_value'];
                }

                if (isset($field_criteria['contains'])) {
                    $where_conditions[] = "{$join_alias}.field_value LIKE %s";
                    $where_values[] = '%' . $wpdb->esc_like($field_criteria['contains']) . '%';
                }

                if (isset($field_criteria['in']) && is_array($field_criteria['in'])) {
                    $placeholders = implode(',', array_fill(0, count($field_criteria['in']), '%s'));
                    $where_conditions[] = "{$join_alias}.field_value IN ({$placeholders})";
                    $where_values = array_merge($where_values, $field_criteria['in']);
                }
            } else {
                // Simple value match
                $where_conditions[] = "{$join_alias}.field_value = %s";
                $where_values[] = $field_criteria;
            }
        }

        // Build the complete SQL query
        $joins = implode(' ', $join_conditions);
        $where_clause = implode(' AND ', $where_conditions);
        $order_clause = sprintf("ORDER BY p.%s %s", 
            sanitize_sql_orderby($options['orderby']), 
            sanitize_sql_orderby($options['order'])
        );

        $sql = "SELECT DISTINCT p.*, u.user_login, u.user_email
                FROM {$profiles_table} p
                INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
                {$joins}
                WHERE {$where_clause}
                {$order_clause}
                LIMIT %d OFFSET %d";

        $where_values[] = $options['limit'];
        $where_values[] = $options['offset'];

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$where_values));

        // Calculate match scores if criteria provided
        if (!empty($results) && $options['min_match_score'] > 0) {
            $scored_results = array();
            foreach ($results as $profile) {
                $score = $this->calculate_match_score($profile, $search_criteria);
                if ($score >= $options['min_match_score']) {
                    $profile->match_score = $score;
                    $scored_results[] = $profile;
                }
            }
            return $scored_results;
        }

        return $results;
    }

    /**
     * Calculate match score for a profile against search criteria
     *
     * @param object $profile Profile object
     * @param array $criteria Search criteria
     * @return float Match score between 0 and 1
     */
    private function calculate_match_score($profile, $criteria) {
        if (empty($criteria)) {
            return 1.0;
        }

        $total_criteria = count($criteria);
        $matched_criteria = 0;

        // Get profile field values
        $profile_values = $this->get_user_field_values($profile->user_id);

        foreach ($criteria as $field_name => $field_criteria) {
            if (!isset($profile_values[$field_name])) {
                continue;
            }

            $profile_value = $profile_values[$field_name];
            $is_match = false;

            if (is_array($field_criteria)) {
                if (isset($field_criteria['value']) && $profile_value === $field_criteria['value']) {
                    $is_match = true;
                }
                if (isset($field_criteria['contains']) && strpos($profile_value, $field_criteria['contains']) !== false) {
                    $is_match = true;
                }
                if (isset($field_criteria['in']) && in_array($profile_value, $field_criteria['in'])) {
                    $is_match = true;
                }
                // Add numeric range matching
                if (is_numeric($profile_value)) {
                    if (isset($field_criteria['min_value']) && $profile_value >= $field_criteria['min_value']) {
                        if (!isset($field_criteria['max_value']) || $profile_value <= $field_criteria['max_value']) {
                            $is_match = true;
                        }
                    }
                }
            } else {
                // Simple equality match
                $is_match = ($profile_value === $field_criteria);
            }

            if ($is_match) {
                $matched_criteria++;
            }
        }

        return $total_criteria > 0 ? ($matched_criteria / $total_criteria) : 0.0;
    }

    /**
     * Get user field values as associative array
     *
     * @param int $user_id User ID
     * @return array Field name => value pairs
     */
    private function get_user_field_values($user_id) {
        global $wpdb;

        if (empty($user_id)) {
            return array();
        }

        $values_table = $this->database->get_table_name('profile_values');
        $fields_table = $this->database->get_table_name('profile_fields');

        $sql = "SELECT pf.field_name, pv.field_value, pv.field_value_numeric, pv.field_value_date
                FROM {$values_table} pv
                INNER JOIN {$fields_table} pf ON pv.field_id = pf.id
                WHERE pv.user_id = %d AND pf.status = 'active'";

        $results = $wpdb->get_results($wpdb->prepare($sql, $user_id));

        $field_values = array();
        foreach ($results as $result) {
            // Use the most appropriate value type
            if (!empty($result->field_value_numeric)) {
                $field_values[$result->field_name] = $result->field_value_numeric;
            } elseif (!empty($result->field_value_date)) {
                $field_values[$result->field_name] = $result->field_value_date;
            } else {
                $field_values[$result->field_name] = $result->field_value;
            }
        }

        return $field_values;
    }

    /**
     * Handle various profile field requests
     *
     * @param string $action The action to perform
     * @param array $data Request data
     * @return mixed Response data or WP_Error on failure
     */
    public function handle_request($action, $data = array()) {
        if (empty($action)) {
            return new WP_Error('invalid_action', __('Action is required.', 'wpmatch'));
        }

        // Sanitize action
        $action = sanitize_text_field($action);

        switch ($action) {
            case 'get_field_options':
                return $this->handle_get_field_options($data);

            case 'validate_field_value':
                return $this->handle_validate_field_value($data);

            case 'get_field_stats':
                return $this->handle_get_field_stats($data);

            case 'export_fields':
                return $this->handle_export_fields($data);

            case 'import_fields':
                return $this->handle_import_fields($data);

            case 'get_user_profile_data':
                return $this->handle_get_user_profile_data($data);

            case 'update_user_profile_data':
                return $this->handle_update_user_profile_data($data);

            case 'search_field_values':
                return $this->handle_search_field_values($data);

            default:
                return new WP_Error('unknown_action', sprintf(__('Unknown action: %s', 'wpmatch'), $action));
        }
    }

    /**
     * Handle get field options request
     *
     * @param array $data Request data
     * @return array|WP_Error Field options or error
     */
    private function handle_get_field_options($data) {
        $field_id = isset($data['field_id']) ? absint($data['field_id']) : 0;
        if (!$field_id) {
            return new WP_Error('invalid_field_id', __('Valid field ID is required.', 'wpmatch'));
        }

        $field = $this->get_field($field_id);
        if (!$field) {
            return new WP_Error('field_not_found', __('Field not found.', 'wpmatch'));
        }

        return array(
            'field_id' => $field->id,
            'field_name' => $field->field_name,
            'field_type' => $field->field_type,
            'field_options' => json_decode($field->field_options, true),
            'validation_rules' => json_decode($field->validation_rules, true)
        );
    }

    /**
     * Handle validate field value request
     *
     * @param array $data Request data
     * @return array|WP_Error Validation result or error
     */
    private function handle_validate_field_value($data) {
        $field_id = isset($data['field_id']) ? absint($data['field_id']) : 0;
        $value = isset($data['value']) ? $data['value'] : '';

        if (!$field_id) {
            return new WP_Error('invalid_field_id', __('Valid field ID is required.', 'wpmatch'));
        }

        $field = $this->get_field($field_id);
        if (!$field) {
            return new WP_Error('field_not_found', __('Field not found.', 'wpmatch'));
        }

        // Basic validation logic (can be extended)
        $is_valid = true;
        $errors = array();

        // Required field check
        if ($field->is_required && empty($value)) {
            $is_valid = false;
            $errors[] = __('This field is required.', 'wpmatch');
        }

        // Length validation
        if (!empty($value)) {
            if ($field->min_length && strlen($value) < $field->min_length) {
                $is_valid = false;
                $errors[] = sprintf(__('Minimum length is %d characters.', 'wpmatch'), $field->min_length);
            }

            if ($field->max_length && strlen($value) > $field->max_length) {
                $is_valid = false;
                $errors[] = sprintf(__('Maximum length is %d characters.', 'wpmatch'), $field->max_length);
            }
        }

        // Numeric validation
        if ($field->field_type === 'number' && !empty($value)) {
            if (!is_numeric($value)) {
                $is_valid = false;
                $errors[] = __('Value must be numeric.', 'wpmatch');
            } else {
                $numeric_value = floatval($value);
                if ($field->min_value !== null && $numeric_value < $field->min_value) {
                    $is_valid = false;
                    $errors[] = sprintf(__('Minimum value is %s.', 'wpmatch'), $field->min_value);
                }
                if ($field->max_value !== null && $numeric_value > $field->max_value) {
                    $is_valid = false;
                    $errors[] = sprintf(__('Maximum value is %s.', 'wpmatch'), $field->max_value);
                }
            }
        }

        return array(
            'is_valid' => $is_valid,
            'errors' => $errors,
            'field_id' => $field_id,
            'validated_value' => $value
        );
    }

    /**
     * Handle get field statistics request
     *
     * @param array $data Request data
     * @return array|WP_Error Field statistics or error
     */
    private function handle_get_field_stats($data) {
        $field_id = isset($data['field_id']) ? absint($data['field_id']) : 0;
        if (!$field_id) {
            return new WP_Error('invalid_field_id', __('Valid field ID is required.', 'wpmatch'));
        }

        return array(
            'field_id' => $field_id,
            'usage_count' => $this->get_field_usage_count($field_id),
            'unique_values_count' => $this->get_field_unique_values_count($field_id),
            'completion_rate' => $this->get_field_completion_rate($field_id)
        );
    }

    /**
     * Handle export fields request
     *
     * @param array $data Request data
     * @return array|WP_Error Export data or error
     */
    private function handle_export_fields($data) {
        $field_ids = isset($data['field_ids']) ? $data['field_ids'] : array();
        
        if (empty($field_ids)) {
            // Export all fields if none specified
            $fields = $this->get_fields();
        } else {
            $fields = array();
            foreach ($field_ids as $field_id) {
                $field = $this->get_field(absint($field_id));
                if ($field) {
                    $fields[] = $field;
                }
            }
        }

        return array(
            'export_date' => current_time('mysql'),
            'field_count' => count($fields),
            'fields' => $fields
        );
    }

    /**
     * Handle import fields request
     *
     * @param array $data Request data
     * @return array|WP_Error Import result or error
     */
    private function handle_import_fields($data) {
        $fields_data = isset($data['fields']) ? $data['fields'] : array();
        
        if (empty($fields_data) || !is_array($fields_data)) {
            return new WP_Error('invalid_data', __('Invalid fields data provided.', 'wpmatch'));
        }

        $imported = 0;
        $errors = array();

        foreach ($fields_data as $field_data) {
            // Convert object to array if necessary
            if (is_object($field_data)) {
                $field_data = (array) $field_data;
            }

            // Remove ID to force new creation
            unset($field_data['id']);

            $result = $this->create_field($field_data);
            if (is_wp_error($result)) {
                $errors[] = sprintf(__('Failed to import field "%s": %s', 'wpmatch'), 
                    $field_data['field_name'] ?? 'unknown', 
                    $result->get_error_message()
                );
            } else {
                $imported++;
            }
        }

        return array(
            'imported_count' => $imported,
            'total_fields' => count($fields_data),
            'errors' => $errors,
            'success' => empty($errors) || $imported > 0
        );
    }

    /**
     * Handle get user profile data request
     *
     * @param array $data Request data
     * @return array|WP_Error Profile data or error
     */
    private function handle_get_user_profile_data($data) {
        $user_id = isset($data['user_id']) ? absint($data['user_id']) : 0;
        if (!$user_id) {
            return new WP_Error('invalid_user_id', __('Valid user ID is required.', 'wpmatch'));
        }

        $field_values = $this->get_user_field_values($user_id);
        $user_data = get_userdata($user_id);

        if (!$user_data) {
            return new WP_Error('user_not_found', __('User not found.', 'wpmatch'));
        }

        return array(
            'user_id' => $user_id,
            'user_login' => $user_data->user_login,
            'user_email' => $user_data->user_email,
            'display_name' => $user_data->display_name,
            'field_values' => $field_values
        );
    }

    /**
     * Handle update user profile data request
     *
     * @param array $data Request data
     * @return array|WP_Error Update result or error
     */
    private function handle_update_user_profile_data($data) {
        $user_id = isset($data['user_id']) ? absint($data['user_id']) : 0;
        $field_values = isset($data['field_values']) ? $data['field_values'] : array();

        if (!$user_id) {
            return new WP_Error('invalid_user_id', __('Valid user ID is required.', 'wpmatch'));
        }

        if (empty($field_values) || !is_array($field_values)) {
            return new WP_Error('invalid_data', __('Field values are required.', 'wpmatch'));
        }

        $updated = 0;
        $errors = array();

        foreach ($field_values as $field_name => $value) {
            $field = $this->get_field_by_name($field_name);
            if (!$field) {
                $errors[] = sprintf(__('Field not found: %s', 'wpmatch'), $field_name);
                continue;
            }

            // Validate the value
            $validation_result = $this->handle_validate_field_value(array(
                'field_id' => $field->id,
                'value' => $value
            ));

            if (is_wp_error($validation_result)) {
                $errors[] = $validation_result->get_error_message();
                continue;
            }

            if (!$validation_result['is_valid']) {
                $errors[] = sprintf(__('Invalid value for field %s: %s', 'wpmatch'), 
                    $field_name, 
                    implode(', ', $validation_result['errors'])
                );
                continue;
            }

            // Update the field value (implementation would depend on your field value storage system)
            // This is a simplified version
            $result = $this->update_user_field_value($user_id, $field->id, $value);
            if ($result) {
                $updated++;
            } else {
                $errors[] = sprintf(__('Failed to update field: %s', 'wpmatch'), $field_name);
            }
        }

        return array(
            'updated_count' => $updated,
            'total_fields' => count($field_values),
            'errors' => $errors,
            'success' => $updated > 0
        );
    }

    /**
     * Handle search field values request
     *
     * @param array $data Request data
     * @return array|WP_Error Search results or error
     */
    private function handle_search_field_values($data) {
        $field_name = isset($data['field_name']) ? sanitize_text_field($data['field_name']) : '';
        $search_term = isset($data['search_term']) ? sanitize_text_field($data['search_term']) : '';
        $limit = isset($data['limit']) ? absint($data['limit']) : 20;

        if (empty($field_name)) {
            return new WP_Error('invalid_field_name', __('Field name is required.', 'wpmatch'));
        }

        if (empty($search_term)) {
            return new WP_Error('invalid_search_term', __('Search term is required.', 'wpmatch'));
        }

        global $wpdb;
        $values_table = $this->database->get_table_name('profile_values');
        $fields_table = $this->database->get_table_name('profile_fields');

        $sql = "SELECT DISTINCT pv.field_value
                FROM {$values_table} pv
                INNER JOIN {$fields_table} pf ON pv.field_id = pf.id
                WHERE pf.field_name = %s 
                AND pv.field_value LIKE %s
                AND pv.field_value != ''
                ORDER BY pv.field_value
                LIMIT %d";

        $search_pattern = '%' . $wpdb->esc_like($search_term) . '%';
        $results = $wpdb->get_col($wpdb->prepare($sql, $field_name, $search_pattern, $limit));

        return array(
            'field_name' => $field_name,
            'search_term' => $search_term,
            'results' => $results,
            'count' => count($results)
        );
    }

    /**
     * Update user field value (simplified implementation)
     *
     * @param int $user_id User ID
     * @param int $field_id Field ID
     * @param mixed $value Field value
     * @return bool True on success, false on failure
     */
    private function update_user_field_value($user_id, $field_id, $value) {
        global $wpdb;
        $values_table = $this->database->get_table_name('profile_values');

        // This is a simplified implementation
        // In a real system, you'd handle different data types, privacy settings, etc.
        
        $field_data = array(
            'field_value' => $value,
            'updated_at' => current_time('mysql')
        );

        // Add numeric value if applicable
        if (is_numeric($value)) {
            $field_data['field_value_numeric'] = floatval($value);
        }

        // Check if record exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$values_table} WHERE user_id = %d AND field_id = %d",
            $user_id, $field_id
        ));

        if ($existing) {
            // Update existing record
            return $wpdb->update(
                $values_table,
                $field_data,
                array('user_id' => $user_id, 'field_id' => $field_id)
            ) !== false;
        } else {
            // Insert new record
            $field_data['user_id'] = $user_id;
            $field_data['field_id'] = $field_id;
            $field_data['created_at'] = current_time('mysql');
            
            return $wpdb->insert($values_table, $field_data) !== false;
        }
    }

    /**
     * Clear field cache
     *
     * @param int $field_id Optional specific field ID to clear
     */
    private function clear_field_cache($field_id = null) {
        if ($field_id) {
            wp_cache_delete($this->cache_prefix . 'id_' . $field_id);
        }

        // Clear general caches
        wp_cache_delete($this->cache_prefix . 'groups');
        
        // Clear query caches (this is simplified - in production you'd want more targeted cache clearing)
        wp_cache_flush();
    }
}