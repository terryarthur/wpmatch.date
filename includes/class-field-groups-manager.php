<?php
/**
 * Field Groups Manager class for managing profile field groups
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Field Groups Manager class
 * 
 * Handles CRUD operations for profile field groups including creation,
 * retrieval, updating, and deletion with proper validation and security.
 */
class WPMatch_Field_Groups_Manager {

    /**
     * Database instance
     *
     * @var WPMatch_Database
     */
    private $database;

    /**
     * Cache key prefix
     *
     * @var string
     */
    private $cache_prefix = 'wpmatch_field_group_';

    /**
     * Constructor
     *
     * @param WPMatch_Database $database Database instance
     */
    public function __construct($database = null) {
        $this->database = $database;
    }

    /**
     * Create a new field group
     *
     * @param array $group_data Group configuration data
     * @return int|WP_Error Group ID on success, WP_Error on failure
     */
    public function create_group($group_data) {
        // Validate permissions
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to create field groups.', 'wpmatch'));
        }

        // Validate group data
        $validation_result = $this->validate_group_data($group_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Sanitize and prepare group data
        $sanitized_data = $this->sanitize_group_data($group_data);
        
        // Check for duplicate group name
        if ($this->group_name_exists($sanitized_data['group_name'])) {
            return new WP_Error('duplicate_group_name', __('A group with this name already exists.', 'wpmatch'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('field_groups');

        // Add creation metadata
        $sanitized_data['created_at'] = current_time('mysql');
        $sanitized_data['updated_at'] = current_time('mysql');

        // Insert group
        $result = $wpdb->insert($table_name, $sanitized_data);

        if ($result === false) {
            return new WP_Error('insert_failed', __('Failed to create field group.', 'wpmatch'));
        }

        $group_id = $wpdb->insert_id;

        // Clear cache
        $this->clear_groups_cache();

        /**
         * Fires after a field group is created
         *
         * @param int   $group_id   Group ID
         * @param array $group_data Group data
         */
        do_action('wpmatch_field_group_created', $group_id, $sanitized_data);

        return $group_id;
    }

    /**
     * Update an existing field group
     *
     * @param int   $group_id   Group ID
     * @param array $group_data Group configuration data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_group($group_id, $group_data) {
        // Validate permissions
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to update field groups.', 'wpmatch'));
        }

        // Check if group exists
        $existing_group = $this->get_group($group_id);
        if (!$existing_group) {
            return new WP_Error('group_not_found', __('Field group not found.', 'wpmatch'));
        }

        // Validate group data
        $validation_result = $this->validate_group_data($group_data, $group_id);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Sanitize and prepare group data
        $sanitized_data = $this->sanitize_group_data($group_data);
        
        // Check for duplicate group name (excluding current group)
        if (isset($sanitized_data['group_name']) && 
            $sanitized_data['group_name'] !== $existing_group->group_name &&
            $this->group_name_exists($sanitized_data['group_name'])) {
            return new WP_Error('duplicate_group_name', __('A group with this name already exists.', 'wpmatch'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('field_groups');

        // Add update metadata
        $sanitized_data['updated_at'] = current_time('mysql');

        // Update group
        $result = $wpdb->update(
            $table_name,
            $sanitized_data,
            array('id' => $group_id),
            null,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update field group.', 'wpmatch'));
        }

        // Clear cache
        $this->clear_groups_cache();
        $this->clear_group_cache($group_id);

        /**
         * Fires after a field group is updated
         *
         * @param int   $group_id     Group ID
         * @param array $group_data   New group data
         * @param object $old_group   Previous group data
         */
        do_action('wpmatch_field_group_updated', $group_id, $sanitized_data, $existing_group);

        return true;
    }

    /**
     * Delete a field group
     *
     * @param int $group_id Group ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_group($group_id) {
        // Validate permissions
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to delete field groups.', 'wpmatch'));
        }

        // Check if group exists
        $group = $this->get_group($group_id);
        if (!$group) {
            return new WP_Error('group_not_found', __('Field group not found.', 'wpmatch'));
        }

        // Check if group has fields
        $field_count = $this->get_group_field_count($group_id);
        if ($field_count > 0) {
            return new WP_Error('group_has_fields', 
                sprintf(__('Cannot delete group that contains %d fields. Please move or delete the fields first.', 'wpmatch'), $field_count)
            );
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('field_groups');

        /**
         * Fires before a field group is deleted
         *
         * @param int    $group_id Group ID
         * @param object $group    Group data
         */
        do_action('wpmatch_field_group_before_delete', $group_id, $group);

        // Delete group
        $result = $wpdb->delete(
            $table_name,
            array('id' => $group_id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('delete_failed', __('Failed to delete field group.', 'wpmatch'));
        }

        // Clear cache
        $this->clear_groups_cache();
        $this->clear_group_cache($group_id);

        /**
         * Fires after a field group is deleted
         *
         * @param int    $group_id Group ID
         * @param object $group    Group data
         */
        do_action('wpmatch_field_group_deleted', $group_id, $group);

        return true;
    }

    /**
     * Get a field group by ID
     *
     * @param int $group_id Group ID
     * @return object|null Group object or null if not found
     */
    public function get_group($group_id) {
        // Check cache first
        $cache_key = $this->cache_prefix . $group_id;
        $group = wp_cache_get($cache_key, 'wpmatch');
        
        if ($group !== false) {
            return $group;
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('field_groups');

        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $group_id
        ));

        // Cache the result
        if ($group) {
            wp_cache_set($cache_key, $group, 'wpmatch', HOUR_IN_SECONDS);
        }

        return $group;
    }

    /**
     * Get a field group by name
     *
     * @param string $group_name Group name
     * @return object|null Group object or null if not found
     */
    public function get_group_by_name($group_name) {
        global $wpdb;
        $table_name = $this->database->get_table_name('field_groups');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE group_name = %s",
            $group_name
        ));
    }

    /**
     * Get all field groups
     *
     * @param array $args Query arguments
     * @return array Array of group objects
     */
    public function get_groups($args = array()) {
        $defaults = array(
            'status' => '',
            'order_by' => 'group_order',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0,
        );

        $args = wp_parse_args($args, $defaults);

        // Check cache
        $cache_key = 'wpmatch_field_groups_' . md5(serialize($args));
        $groups = wp_cache_get($cache_key, 'wpmatch');
        
        if ($groups !== false) {
            return $groups;
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('field_groups');

        // Build query
        $where_conditions = array();
        $where_values = array();

        if (!empty($args['status'])) {
            $where_conditions[] = "is_active = %d";
            $where_values[] = ($args['status'] === 'active') ? 1 : 0;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        // Order clause
        $allowed_order_by = array('id', 'group_name', 'group_label', 'group_order', 'created_at');
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'group_order';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $order_clause = "ORDER BY {$order_by} {$order}";

        // Limit clause
        $limit_clause = '';
        if ($args['limit'] > 0) {
            $limit_clause = $wpdb->prepare("LIMIT %d", $args['limit']);
            if ($args['offset'] > 0) {
                $limit_clause = $wpdb->prepare("LIMIT %d, %d", $args['offset'], $args['limit']);
            }
        }

        $query = "SELECT * FROM {$table_name} {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($where_values)) {
            $groups = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $groups = $wpdb->get_results($query);
        }

        // Cache the result
        wp_cache_set($cache_key, $groups, 'wpmatch', HOUR_IN_SECONDS);

        return $groups ?: array();
    }

    /**
     * Update group order
     *
     * @param array $group_orders Array of group_id => order pairs
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_group_order($group_orders) {
        if (!current_user_can('manage_profile_fields')) {
            return new WP_Error('permission_denied', __('You do not have permission to update group order.', 'wpmatch'));
        }

        global $wpdb;
        $table_name = $this->database->get_table_name('field_groups');

        foreach ($group_orders as $group_id => $order) {
            $wpdb->update(
                $table_name,
                array('group_order' => intval($order)),
                array('id' => intval($group_id)),
                array('%d'),
                array('%d')
            );
        }

        // Clear cache
        $this->clear_groups_cache();

        return true;
    }

    /**
     * Get count of fields in a group
     *
     * @param int $group_id Group ID
     * @return int Field count
     */
    public function get_group_field_count($group_id) {
        $group = $this->get_group($group_id);
        if (!$group) {
            return 0;
        }

        global $wpdb;
        $fields_table = $this->database->get_table_name('profile_fields');

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$fields_table} WHERE field_group = %s",
            $group->group_name
        ));
    }

    /**
     * Get fields in a group
     *
     * @param int   $group_id Group ID
     * @param array $args     Query arguments
     * @return array Array of field objects
     */
    public function get_group_fields($group_id, $args = array()) {
        $group = $this->get_group($group_id);
        if (!$group) {
            return array();
        }

        // Load field manager
        $field_manager = new WPMatch_Profile_Field_Manager();
        
        $field_args = wp_parse_args($args, array(
            'group' => $group->group_name,
            'order_by' => 'field_order',
            'order' => 'ASC'
        ));

        return $field_manager->get_fields($field_args);
    }

    /**
     * Validate group data
     *
     * @param array $group_data Group data
     * @param int   $group_id   Group ID for updates
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_group_data($group_data, $group_id = null) {
        $errors = array();

        // Validate required fields
        $required_fields = array('group_name', 'group_label');
        foreach ($required_fields as $required_field) {
            if (empty($group_data[$required_field])) {
                $errors[] = sprintf(__('The %s field is required.', 'wpmatch'), str_replace('_', ' ', $required_field));
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', __('Validation failed.', 'wpmatch'), $errors);
        }

        // Validate group name format
        if (!preg_match('/^[a-z][a-z0-9_]*[a-z0-9]$/', $group_data['group_name'])) {
            $errors[] = __('Group name must start with a letter, contain only lowercase letters, numbers, and underscores, and end with a letter or number.', 'wpmatch');
        }

        // Validate group name length
        if (strlen($group_data['group_name']) > 100) {
            $errors[] = __('Group name must be 100 characters or less.', 'wpmatch');
        }

        // Validate group label length
        if (strlen($group_data['group_label']) > 255) {
            $errors[] = __('Group label must be 255 characters or less.', 'wpmatch');
        }

        // Validate group description length
        if (isset($group_data['group_description']) && strlen($group_data['group_description']) > 1000) {
            $errors[] = __('Group description must be 1000 characters or less.', 'wpmatch');
        }

        // Validate group order
        if (isset($group_data['group_order'])) {
            $order = intval($group_data['group_order']);
            if ($order < 0 || $order > 999) {
                $errors[] = __('Group order must be between 0 and 999.', 'wpmatch');
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', __('Validation failed.', 'wpmatch'), $errors);
        }

        return true;
    }

    /**
     * Sanitize group data
     *
     * @param array $group_data Group data
     * @return array Sanitized group data
     */
    private function sanitize_group_data($group_data) {
        $sanitized = array();

        if (isset($group_data['group_name'])) {
            $sanitized['group_name'] = sanitize_text_field($group_data['group_name']);
        }

        if (isset($group_data['group_label'])) {
            $sanitized['group_label'] = sanitize_text_field($group_data['group_label']);
        }

        if (isset($group_data['group_description'])) {
            $sanitized['group_description'] = sanitize_textarea_field($group_data['group_description']);
        }

        if (isset($group_data['group_icon'])) {
            $sanitized['group_icon'] = sanitize_text_field($group_data['group_icon']);
        }

        if (isset($group_data['group_order'])) {
            $sanitized['group_order'] = intval($group_data['group_order']);
        }

        if (isset($group_data['is_active'])) {
            $sanitized['is_active'] = (bool) $group_data['is_active'];
        }

        return $sanitized;
    }

    /**
     * Check if group name exists
     *
     * @param string $group_name Group name
     * @param int    $exclude_id Group ID to exclude
     * @return bool True if exists, false otherwise
     */
    private function group_name_exists($group_name, $exclude_id = null) {
        global $wpdb;
        $table_name = $this->database->get_table_name('field_groups');

        $query = "SELECT id FROM {$table_name} WHERE group_name = %s";
        $params = array($group_name);

        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }

        return (bool) $wpdb->get_var($wpdb->prepare($query, $params));
    }

    /**
     * Clear groups cache
     */
    private function clear_groups_cache() {
        wp_cache_delete_group('wpmatch_field_groups');
    }

    /**
     * Clear individual group cache
     *
     * @param int $group_id Group ID
     */
    private function clear_group_cache($group_id) {
        $cache_key = $this->cache_prefix . $group_id;
        wp_cache_delete($cache_key, 'wpmatch');
    }

    /**
     * Get group options for select dropdown
     *
     * @param array $args Query arguments
     * @return array Array of group_name => group_label pairs
     */
    public function get_groups_for_select($args = array()) {
        $groups = $this->get_groups($args);
        $options = array();

        foreach ($groups as $group) {
            $options[$group->group_name] = $group->group_label;
        }

        return $options;
    }

    /**
     * Export groups to array
     *
     * @param array $group_ids Group IDs to export (empty for all)
     * @return array Export data
     */
    public function export_groups($group_ids = array()) {
        $args = array();
        if (!empty($group_ids)) {
            $args['include'] = $group_ids;
        }

        $groups = $this->get_groups($args);
        $export_data = array();

        foreach ($groups as $group) {
            $export_data[] = array(
                'group_name' => $group->group_name,
                'group_label' => $group->group_label,
                'group_description' => $group->group_description,
                'group_icon' => $group->group_icon,
                'group_order' => $group->group_order,
                'is_active' => $group->is_active,
            );
        }

        return $export_data;
    }

    /**
     * Import groups from array
     *
     * @param array $groups_data Import data
     * @param array $options     Import options
     * @return array|WP_Error Import results or error
     */
    public function import_groups($groups_data, $options = array()) {
        $defaults = array(
            'update_existing' => false,
            'skip_duplicates' => true,
        );

        $options = wp_parse_args($options, $defaults);
        $results = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        foreach ($groups_data as $group_data) {
            // Check if group exists
            $existing_group = $this->get_group_by_name($group_data['group_name']);
            
            if ($existing_group) {
                if ($options['update_existing']) {
                    $result = $this->update_group($existing_group->id, $group_data);
                    if (is_wp_error($result)) {
                        $results['errors'][] = sprintf(
                            __('Failed to update group "%s": %s', 'wpmatch'),
                            $group_data['group_name'],
                            $result->get_error_message()
                        );
                    } else {
                        $results['updated']++;
                    }
                } else {
                    $results['skipped']++;
                }
            } else {
                $result = $this->create_group($group_data);
                if (is_wp_error($result)) {
                    $results['errors'][] = sprintf(
                        __('Failed to create group "%s": %s', 'wpmatch'),
                        $group_data['group_name'],
                        $result->get_error_message()
                    );
                } else {
                    $results['imported']++;
                }
            }
        }

        return $results;
    }
}