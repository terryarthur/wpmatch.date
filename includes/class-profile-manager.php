<?php
/**
 * Profile management class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Profile Manager class
 */
class WPMatch_Profile_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_wpmatch_update_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_wpmatch_get_profile', array($this, 'ajax_get_profile'));
        add_action('wp_ajax_wpmatch_search_profiles', array($this, 'ajax_search_profiles'));
    }

    /**
     * Initialize profile management
     */
    public function init() {
        // Add custom profile fields
        add_action('show_user_profile', array($this, 'add_custom_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_profile_fields'));
        add_action('personal_options_update', array($this, 'save_custom_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_profile_fields'));
    }

    /**
     * Get user profile data
     *
     * @param int $user_id
     * @return object|null
     */
    public function get_profile($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$profiles_table} WHERE user_id = %d",
            $user_id
        ));

        if ($profile) {
            // Get custom field values
            $profile->custom_fields = $this->get_custom_field_values($user_id);
            
            // Get photos
            $profile->photos = $this->get_user_photos($user_id);
            
            // Get privacy settings
            $user_manager = wpmatch_plugin()->user_manager;
            $profile->privacy_settings = $user_manager->get_all_privacy_settings($user_id);
        }

        return $profile;
    }

    /**
     * Update user profile
     *
     * @param int $user_id
     * @param array $profile_data
     * @return bool|WP_Error
     */
    public function update_profile($user_id, $profile_data) {
        // Check permissions
        if (!WPMatch_Security::user_can('edit_own_profile', $user_id) && get_current_user_id() !== $user_id) {
            return new WP_Error('permission_denied', __('You do not have permission to edit this profile.', 'wpmatch'));
        }

        // Sanitize profile data
        $sanitized_data = WPMatch_Security::sanitize_profile_data($profile_data);

        // Validate data
        $validation_result = $this->validate_profile_data($sanitized_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Update profile
        $user_manager = wpmatch_plugin()->user_manager;
        $result = $user_manager->update_user_profile($user_id, $sanitized_data);

        if ($result) {
            // Update custom fields if provided
            if (isset($profile_data['custom_fields']) && is_array($profile_data['custom_fields'])) {
                $this->update_custom_field_values($user_id, $profile_data['custom_fields']);
            }

            do_action('wpmatch_profile_updated', $user_id, $sanitized_data);
            return true;
        }

        return new WP_Error('update_failed', __('Failed to update profile.', 'wpmatch'));
    }

    /**
     * Validate profile data
     *
     * @param array $data
     * @return bool|WP_Error
     */
    private function validate_profile_data($data) {
        $errors = array();

        // Validate display name
        if (isset($data['display_name'])) {
            if (empty($data['display_name'])) {
                $errors[] = __('Display name is required.', 'wpmatch');
            } elseif (strlen($data['display_name']) > 100) {
                $errors[] = __('Display name must be 100 characters or less.', 'wpmatch');
            }
        }

        // Validate age
        if (isset($data['age'])) {
            if (!WPMatch_Security::validate_age($data['age'])) {
                $errors[] = __('Age must be between 18 and 120.', 'wpmatch');
            }
        }

        // Validate about me
        if (isset($data['about_me']) && strlen($data['about_me']) > 2000) {
            $errors[] = __('About me section must be 2000 characters or less.', 'wpmatch');
        }

        // Validate height and weight
        if (isset($data['height']) && ($data['height'] < 0 || $data['height'] > 300)) {
            $errors[] = __('Height must be between 0 and 300 cm.', 'wpmatch');
        }

        if (isset($data['weight']) && ($data['weight'] < 0 || $data['weight'] > 500)) {
            $errors[] = __('Weight must be between 0 and 500 kg.', 'wpmatch');
        }

        // Validate coordinates
        if (isset($data['latitude']) && ($data['latitude'] < -90 || $data['latitude'] > 90)) {
            $errors[] = __('Invalid latitude value.', 'wpmatch');
        }

        if (isset($data['longitude']) && ($data['longitude'] < -180 || $data['longitude'] > 180)) {
            $errors[] = __('Invalid longitude value.', 'wpmatch');
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        return true;
    }

    /**
     * Search profiles
     *
     * @param array $args
     * @return array
     */
    public function search_profiles($args = array()) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        // Default arguments
        $defaults = array(
            'age_min' => 18,
            'age_max' => 99,
            'gender' => '',
            'looking_for' => '',
            'location' => '',
            'distance' => '',
            'latitude' => '',
            'longitude' => '',
            'online_only' => false,
            'has_photo' => false,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'last_active',
            'order' => 'DESC',
            'exclude_blocked' => true,
            'exclude_user' => get_current_user_id(),
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where_clauses = array('p.status = "active"');
        $where_values = array();

        // Age filter
        if ($args['age_min']) {
            $where_clauses[] = 'p.age >= %d';
            $where_values[] = $args['age_min'];
        }

        if ($args['age_max']) {
            $where_clauses[] = 'p.age <= %d';
            $where_values[] = $args['age_max'];
        }

        // Gender filter
        if ($args['gender']) {
            $where_clauses[] = 'p.gender = %s';
            $where_values[] = $args['gender'];
        }

        // Looking for filter
        if ($args['looking_for']) {
            $where_clauses[] = 'p.looking_for = %s';
            $where_values[] = $args['looking_for'];
        }

        // Online filter
        if ($args['online_only']) {
            $where_clauses[] = 'p.is_online = 1';
        }

        // Photo filter
        if ($args['has_photo']) {
            $photos_table = $database->get_table_name('photos');
            $where_clauses[] = "EXISTS (SELECT 1 FROM {$photos_table} ph WHERE ph.user_id = p.user_id AND ph.status = 'approved')";
        }

        // Exclude current user
        if ($args['exclude_user']) {
            $where_clauses[] = 'p.user_id != %d';
            $where_values[] = $args['exclude_user'];
        }

        // Distance filter
        if ($args['distance'] && $args['latitude'] && $args['longitude']) {
            $distance_clause = $this->build_distance_clause($args['latitude'], $args['longitude'], $args['distance']);
            $where_clauses[] = $distance_clause;
        }

        // Exclude blocked users
        if ($args['exclude_blocked'] && $args['exclude_user']) {
            $blocks_table = $database->get_table_name('blocks');
            $where_clauses[] = "NOT EXISTS (SELECT 1 FROM {$blocks_table} b WHERE (b.user_id = %d AND b.blocked_user_id = p.user_id) OR (b.user_id = p.user_id AND b.blocked_user_id = %d))";
            $where_values[] = $args['exclude_user'];
            $where_values[] = $args['exclude_user'];
        }

        // Build ORDER BY clause
        $allowed_orderby = array('last_active', 'created_at', 'age', 'profile_views', 'distance');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'last_active';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        if ($orderby === 'distance' && $args['latitude'] && $args['longitude']) {
            $distance_select = $this->build_distance_select($args['latitude'], $args['longitude']);
            $orderby_clause = "distance {$order}";
        } else {
            $distance_select = '';
            $orderby_clause = "p.{$orderby} {$order}";
        }

        // Build final query
        $where_clause = implode(' AND ', $where_clauses);
        
        $query = "SELECT p.*, u.display_name as user_display_name, u.user_email{$distance_select}
                  FROM {$profiles_table} p
                  INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
                  WHERE {$where_clause}
                  ORDER BY {$orderby_clause}
                  LIMIT %d OFFSET %d";

        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        // Prepare and execute query
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }

        $results = $wpdb->get_results($prepared_query);

        // Get total count for pagination
        $count_query = "SELECT COUNT(*)
                        FROM {$profiles_table} p
                        INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
                        WHERE {$where_clause}";

        $count_values = array_slice($where_values, 0, -2); // Remove limit and offset
        if (!empty($count_values)) {
            $prepared_count_query = $wpdb->prepare($count_query, $count_values);
        } else {
            $prepared_count_query = $count_query;
        }

        $total_count = $wpdb->get_var($prepared_count_query);

        // Add additional data to results
        foreach ($results as &$profile) {
            // Get primary photo
            $profile->primary_photo = $this->get_primary_photo($profile->user_id);
            
            // Get basic custom fields
            $profile->basic_info = $this->get_basic_custom_fields($profile->user_id);
        }

        return array(
            'profiles' => $results,
            'total' => $total_count,
            'found' => count($results),
        );
    }

    /**
     * Build distance clause for SQL query
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $distance
     * @return string
     */
    private function build_distance_clause($latitude, $longitude, $distance) {
        // Using Haversine formula for distance calculation
        $earth_radius = 6371; // km
        
        return "(
            {$earth_radius} * acos(
                cos(radians({$latitude})) * 
                cos(radians(p.latitude)) * 
                cos(radians(p.longitude) - radians({$longitude})) + 
                sin(radians({$latitude})) * 
                sin(radians(p.latitude))
            )
        ) <= {$distance}";
    }

    /**
     * Build distance select for SQL query
     *
     * @param float $latitude
     * @param float $longitude
     * @return string
     */
    private function build_distance_select($latitude, $longitude) {
        $earth_radius = 6371; // km
        
        return ", (
            {$earth_radius} * acos(
                cos(radians({$latitude})) * 
                cos(radians(p.latitude)) * 
                cos(radians(p.longitude) - radians({$longitude})) + 
                sin(radians({$latitude})) * 
                sin(radians(p.latitude))
            )
        ) as distance";
    }

    /**
     * Get custom field values for user
     *
     * @param int $user_id
     * @return array
     */
    public function get_custom_field_values($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $profile_values_table = $database->get_table_name('profile_values');
        $profile_fields_table = $database->get_table_name('profile_fields');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pv.field_value, pv.privacy, pf.field_name, pf.field_label, pf.field_type
             FROM {$profile_values_table} pv
             INNER JOIN {$profile_fields_table} pf ON pv.field_id = pf.id
             WHERE pv.user_id = %d AND pf.status = 'active'",
            $user_id
        ), ARRAY_A);

        $fields = array();
        foreach ($results as $row) {
            $fields[$row['field_name']] = array(
                'value' => $row['field_value'],
                'privacy' => $row['privacy'],
                'label' => $row['field_label'],
                'type' => $row['field_type'],
            );
        }

        return $fields;
    }

    /**
     * Update custom field values for user
     *
     * @param int $user_id
     * @param array $field_values
     * @return bool
     */
    public function update_custom_field_values($user_id, $field_values) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $profile_values_table = $database->get_table_name('profile_values');
        $profile_fields_table = $database->get_table_name('profile_fields');

        foreach ($field_values as $field_name => $field_data) {
            // Get field ID
            $field_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$profile_fields_table} WHERE field_name = %s AND status = 'active'",
                $field_name
            ));

            if (!$field_id) {
                continue;
            }

            $field_value = isset($field_data['value']) ? $field_data['value'] : $field_data;
            $privacy = isset($field_data['privacy']) ? $field_data['privacy'] : 'public';

            // Sanitize value
            $field_value = WPMatch_Security::sanitize_input($field_value, 'textarea');
            $privacy = WPMatch_Security::sanitize_input($privacy, 'key');

            // Update or insert field value
            $wpdb->replace(
                $profile_values_table,
                array(
                    'user_id' => $user_id,
                    'field_id' => $field_id,
                    'field_value' => $field_value,
                    'privacy' => $privacy,
                    'updated_at' => current_time('mysql'),
                )
            );
        }

        return true;
    }

    /**
     * Get user photos
     *
     * @param int $user_id
     * @param string $status
     * @return array
     */
    public function get_user_photos($user_id, $status = 'approved') {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        $photos = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$photos_table} 
             WHERE user_id = %d AND status = %s 
             ORDER BY is_primary DESC, photo_order ASC",
            $user_id, $status
        ));

        // Add attachment URLs
        foreach ($photos as &$photo) {
            $photo->url = wp_get_attachment_url($photo->attachment_id);
            $photo->thumbnail = wp_get_attachment_image_url($photo->attachment_id, 'thumbnail');
            $photo->medium = wp_get_attachment_image_url($photo->attachment_id, 'medium');
        }

        return $photos;
    }

    /**
     * Get primary photo for user
     *
     * @param int $user_id
     * @return object|null
     */
    public function get_primary_photo($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        $photo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$photos_table} 
             WHERE user_id = %d AND is_primary = 1 AND status = 'approved'
             LIMIT 1",
            $user_id
        ));

        if ($photo) {
            $photo->url = wp_get_attachment_url($photo->attachment_id);
            $photo->thumbnail = wp_get_attachment_image_url($photo->attachment_id, 'thumbnail');
            $photo->medium = wp_get_attachment_image_url($photo->attachment_id, 'medium');
        }

        return $photo;
    }

    /**
     * Get basic custom fields for display
     *
     * @param int $user_id
     * @return array
     */
    private function get_basic_custom_fields($user_id) {
        $custom_fields = $this->get_custom_field_values($user_id);
        $basic_fields = array('zodiac_sign', 'smoking', 'drinking', 'languages');
        
        $result = array();
        foreach ($basic_fields as $field) {
            if (isset($custom_fields[$field])) {
                $result[$field] = $custom_fields[$field];
            }
        }

        return $result;
    }

    /**
     * AJAX handler for updating profile
     */
    public function ajax_update_profile() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to update your profile.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $profile_data = $_POST['profile_data'] ?? array();

        $result = $this->update_profile($user_id, $profile_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Profile updated successfully.', 'wpmatch'));
    }

    /**
     * AJAX handler for getting profile
     */
    public function ajax_get_profile() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(__('Invalid user ID.', 'wpmatch'));
        }

        $profile = $this->get_profile($user_id);

        if (!$profile) {
            wp_send_json_error(__('Profile not found.', 'wpmatch'));
        }

        wp_send_json_success($profile);
    }

    /**
     * AJAX handler for searching profiles
     */
    public function ajax_search_profiles() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        $search_args = $_POST['search_args'] ?? array();
        
        // Sanitize search arguments
        $search_args = array_map(array('WPMatch_Security', 'sanitize_input'), $search_args);

        $results = $this->search_profiles($search_args);

        wp_send_json_success($results);
    }

    /**
     * Add custom profile fields to user profile page
     *
     * @param WP_User $user
     */
    public function add_custom_profile_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $profile = $this->get_profile($user->ID);
        ?>
        <h3><?php _e('Dating Profile Information', 'wpmatch'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="wpmatch_age"><?php _e('Age', 'wpmatch'); ?></label></th>
                <td>
                    <input type="number" name="wpmatch_age" id="wpmatch_age" 
                           value="<?php echo esc_attr($profile->age ?? ''); ?>" 
                           min="18" max="120" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="wpmatch_gender"><?php _e('Gender', 'wpmatch'); ?></label></th>
                <td>
                    <select name="wpmatch_gender" id="wpmatch_gender">
                        <option value=""><?php _e('Select Gender', 'wpmatch'); ?></option>
                        <option value="male" <?php selected($profile->gender ?? '', 'male'); ?>><?php _e('Male', 'wpmatch'); ?></option>
                        <option value="female" <?php selected($profile->gender ?? '', 'female'); ?>><?php _e('Female', 'wpmatch'); ?></option>
                        <option value="other" <?php selected($profile->gender ?? '', 'other'); ?>><?php _e('Other', 'wpmatch'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="wpmatch_location"><?php _e('Location', 'wpmatch'); ?></label></th>
                <td>
                    <input type="text" name="wpmatch_location" id="wpmatch_location" 
                           value="<?php echo esc_attr($profile->location ?? ''); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="wpmatch_about_me"><?php _e('About Me', 'wpmatch'); ?></label></th>
                <td>
                    <textarea name="wpmatch_about_me" id="wpmatch_about_me" rows="5" cols="30"><?php echo esc_textarea($profile->about_me ?? ''); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save custom profile fields
     *
     * @param int $user_id
     */
    public function save_custom_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $profile_data = array();

        if (isset($_POST['wpmatch_age'])) {
            $profile_data['age'] = absint($_POST['wpmatch_age']);
        }

        if (isset($_POST['wpmatch_gender'])) {
            $profile_data['gender'] = sanitize_key($_POST['wpmatch_gender']);
        }

        if (isset($_POST['wpmatch_location'])) {
            $profile_data['location'] = sanitize_text_field($_POST['wpmatch_location']);
        }

        if (isset($_POST['wpmatch_about_me'])) {
            $profile_data['about_me'] = sanitize_textarea_field($_POST['wpmatch_about_me']);
        }

        if (!empty($profile_data)) {
            $this->update_profile($user_id, $profile_data);
        }
    }
}