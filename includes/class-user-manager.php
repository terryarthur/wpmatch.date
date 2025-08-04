<?php
/**
 * User management class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch User Manager class
 */
class WPMatch_User_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('user_register', array($this, 'on_user_register'));
        add_action('delete_user', array($this, 'on_user_delete'));
    }

    /**
     * Initialize user management
     */
    public function init() {
        $this->create_roles();
        $this->add_capabilities();
    }

    /**
     * Create custom user roles
     */
    public function create_roles() {
        // Dating Member role
        add_role('dating_member', __('Dating Member', 'wpmatch'), array(
            'read' => true,
            'edit_own_profile' => true,
            'upload_photos' => true,
            'send_messages' => true,
            'search_profiles' => true,
            'view_profiles' => true,
            'report_users' => true,
            'block_users' => true,
        ));

        // Dating Moderator role
        add_role('dating_moderator', __('Dating Moderator', 'wpmatch'), array(
            'read' => true,
            'edit_own_profile' => true,
            'upload_photos' => true,
            'send_messages' => true,
            'search_profiles' => true,
            'view_profiles' => true,
            'report_users' => true,
            'block_users' => true,
            'moderate_profiles' => true,
            'moderate_photos' => true,
            'moderate_messages' => true,
            'view_reports' => true,
            'manage_reports' => true,
            'suspend_users' => true,
        ));

        // Dating Admin role (inherits all capabilities)
        add_role('dating_admin', __('Dating Admin', 'wpmatch'), array(
            'read' => true,
            'edit_own_profile' => true,
            'upload_photos' => true,
            'send_messages' => true,
            'search_profiles' => true,
            'view_profiles' => true,
            'report_users' => true,
            'block_users' => true,
            'moderate_profiles' => true,
            'moderate_photos' => true,
            'moderate_messages' => true,
            'view_reports' => true,
            'manage_reports' => true,
            'suspend_users' => true,
            'manage_dating_settings' => true,
            'manage_profile_fields' => true,
            'view_dating_analytics' => true,
            'export_user_data' => true,
        ));

        // Add capabilities to Administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $dating_capabilities = array(
                'edit_own_profile',
                'upload_photos',
                'send_messages',
                'search_profiles',
                'view_profiles',
                'report_users',
                'block_users',
                'moderate_profiles',
                'moderate_photos',
                'moderate_messages',
                'view_reports',
                'manage_reports',
                'suspend_users',
                'manage_dating_settings',
                'manage_profile_fields',
                'view_dating_analytics',
                'export_user_data',
            );

            foreach ($dating_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * Add custom capabilities to existing roles
     */
    private function add_capabilities() {
        // Add basic dating capabilities to subscriber role
        $subscriber_role = get_role('subscriber');
        if ($subscriber_role) {
            $subscriber_capabilities = array(
                'edit_own_profile',
                'upload_photos',
                'send_messages',
                'search_profiles',
                'view_profiles',
                'report_users',
                'block_users',
            );

            foreach ($subscriber_capabilities as $cap) {
                $subscriber_role->add_cap($cap);
            }
        }
    }

    /**
     * Handle user registration
     *
     * @param int $user_id
     */
    public function on_user_register($user_id) {
        // Assign dating_member role to new users
        $user = new WP_User($user_id);
        $user->set_role('dating_member');

        // Create initial profile entry
        $this->create_initial_profile($user_id);

        // Set default privacy settings
        $this->set_default_privacy_settings($user_id);

        // Send welcome email (optional)
        do_action('wpmatch_user_registered', $user_id);
    }

    /**
     * Create initial profile for new user
     *
     * @param int $user_id
     */
    private function create_initial_profile($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }

        $profile_data = array(
            'user_id' => $user_id,
            'display_name' => $user->display_name ?: $user->user_login,
            'status' => 'incomplete',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $wpdb->insert($profiles_table, $profile_data);
    }

    /**
     * Set default privacy settings for new user
     *
     * @param int $user_id
     */
    private function set_default_privacy_settings($user_id) {
        $default_settings = array(
            'profile_visibility' => 'public',
            'show_online_status' => '1',
            'allow_messages' => '1',
            'allow_contact_requests' => '1',
            'show_age' => '1',
            'show_location' => '1',
            'email_notifications' => '1',
            'email_new_messages' => '1',
            'email_profile_views' => '0',
            'email_matches' => '1',
        );

        foreach ($default_settings as $setting_name => $setting_value) {
            $this->update_privacy_setting($user_id, $setting_name, $setting_value);
        }
    }

    /**
     * Handle user deletion
     *
     * @param int $user_id
     */
    public function on_user_delete($user_id) {
        // Clean up user data (profiles, messages, photos, etc.)
        $this->cleanup_user_data($user_id);
    }

    /**
     * Clean up user data on deletion
     *
     * @param int $user_id
     */
    private function cleanup_user_data($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;

        // Delete profile
        $wpdb->delete($database->get_table_name('profiles'), array('user_id' => $user_id));

        // Delete profile values
        $wpdb->delete($database->get_table_name('profile_values'), array('user_id' => $user_id));

        // Delete photos
        $wpdb->delete($database->get_table_name('photos'), array('user_id' => $user_id));

        // Delete conversations (both as participant)
        $conversation_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$database->get_table_name('conversations')} 
             WHERE participant_1 = %d OR participant_2 = %d",
            $user_id, $user_id
        ));

        if ($conversation_ids) {
            $conversation_ids_str = implode(',', array_map('intval', $conversation_ids));
            $wpdb->query("DELETE FROM {$database->get_table_name('messages')} WHERE conversation_id IN ($conversation_ids_str)");
            $wpdb->query("DELETE FROM {$database->get_table_name('conversations')} WHERE id IN ($conversation_ids_str)");
        }

        // Delete interactions
        $wpdb->delete($database->get_table_name('interactions'), array('user_id' => $user_id));
        $wpdb->delete($database->get_table_name('interactions'), array('target_user_id' => $user_id));

        // Delete privacy settings
        $wpdb->delete($database->get_table_name('privacy_settings'), array('user_id' => $user_id));

        // Delete verification records
        $wpdb->delete($database->get_table_name('verification'), array('user_id' => $user_id));

        // Delete blocks
        $wpdb->delete($database->get_table_name('blocks'), array('user_id' => $user_id));
        $wpdb->delete($database->get_table_name('blocks'), array('blocked_user_id' => $user_id));

        // Delete reports
        $wpdb->delete($database->get_table_name('reports'), array('reporter_id' => $user_id));
        $wpdb->delete($database->get_table_name('reports'), array('reported_user_id' => $user_id));
    }

    /**
     * Check if user has dating capability
     *
     * @param string $capability
     * @param int $user_id
     * @return bool
     */
    public function user_can($capability, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return user_can($user_id, $capability);
    }

    /**
     * Get user profile data
     *
     * @param int $user_id
     * @return object|null
     */
    public function get_user_profile($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$profiles_table} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Update user profile
     *
     * @param int $user_id
     * @param array $profile_data
     * @return bool
     */
    public function update_user_profile($user_id, $profile_data) {
        global $wpdb;

        // Sanitize and validate data
        $profile_data = $this->sanitize_profile_data($profile_data);

        if (empty($profile_data)) {
            return false;
        }

        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        // Add updated timestamp
        $profile_data['updated_at'] = current_time('mysql');

        // Update profile
        $result = $wpdb->update(
            $profiles_table,
            $profile_data,
            array('user_id' => $user_id)
        );

        if ($result !== false) {
            // Update profile completion percentage
            $this->update_profile_completion($user_id);
            do_action('wpmatch_profile_updated', $user_id, $profile_data);
            return true;
        }

        return false;
    }

    /**
     * Sanitize profile data
     *
     * @param array $data
     * @return array
     */
    private function sanitize_profile_data($data) {
        $sanitized = array();
        $allowed_fields = array(
            'display_name', 'about_me', 'age', 'gender', 'looking_for',
            'location', 'latitude', 'longitude', 'height', 'weight',
            'relationship_status', 'children', 'education', 'profession', 'interests'
        );

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                switch ($key) {
                    case 'display_name':
                    case 'location':
                    case 'education':
                    case 'profession':
                        $sanitized[$key] = sanitize_text_field($value);
                        break;
                    case 'about_me':
                    case 'interests':
                        $sanitized[$key] = sanitize_textarea_field($value);
                        break;
                    case 'age':
                    case 'height':
                    case 'weight':
                        $sanitized[$key] = absint($value);
                        break;
                    case 'latitude':
                    case 'longitude':
                        $sanitized[$key] = floatval($value);
                        break;
                    case 'gender':
                    case 'looking_for':
                    case 'relationship_status':
                    case 'children':
                        $sanitized[$key] = sanitize_key($value);
                        break;
                }
            }
        }

        return $sanitized;
    }

    /**
     * Update profile completion percentage
     *
     * @param int $user_id
     */
    private function update_profile_completion($user_id) {
        $profile = $this->get_user_profile($user_id);
        if (!$profile) {
            return;
        }

        $required_fields = array(
            'display_name', 'about_me', 'age', 'gender', 'looking_for', 'location'
        );

        $completed_fields = 0;
        foreach ($required_fields as $field) {
            if (!empty($profile->$field)) {
                $completed_fields++;
            }
        }

        // Check if user has profile photo
        $has_photo = $this->user_has_photos($user_id);
        if ($has_photo) {
            $completed_fields++;
        }

        $total_fields = count($required_fields) + 1; // +1 for photo
        $completion_percentage = round(($completed_fields / $total_fields) * 100);

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        $wpdb->update(
            $profiles_table,
            array('profile_completion' => $completion_percentage),
            array('user_id' => $user_id)
        );
    }

    /**
     * Check if user has photos
     *
     * @param int $user_id
     * @return bool
     */
    private function user_has_photos($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $photos_table = $database->get_table_name('photos');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$photos_table} WHERE user_id = %d AND status = 'approved'",
            $user_id
        ));

        return $count > 0;
    }

    /**
     * Update privacy setting
     *
     * @param int $user_id
     * @param string $setting_name
     * @param string $setting_value
     * @return bool
     */
    public function update_privacy_setting($user_id, $setting_name, $setting_value) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $privacy_table = $database->get_table_name('privacy_settings');

        // Sanitize inputs
        $setting_name = sanitize_key($setting_name);
        $setting_value = sanitize_text_field($setting_value);

        $result = $wpdb->replace(
            $privacy_table,
            array(
                'user_id' => $user_id,
                'setting_name' => $setting_name,
                'setting_value' => $setting_value,
                'updated_at' => current_time('mysql')
            )
        );

        return $result !== false;
    }

    /**
     * Get privacy setting
     *
     * @param int $user_id
     * @param string $setting_name
     * @param string $default
     * @return string
     */
    public function get_privacy_setting($user_id, $setting_name, $default = '') {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $privacy_table = $database->get_table_name('privacy_settings');

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$privacy_table} WHERE user_id = %d AND setting_name = %s",
            $user_id, sanitize_key($setting_name)
        ));

        return $value !== null ? $value : $default;
    }

    /**
     * Get all privacy settings for user
     *
     * @param int $user_id
     * @return array
     */
    public function get_all_privacy_settings($user_id) {
        global $wpdb;

        $database = wpmatch_plugin()->database;
        $privacy_table = $database->get_table_name('privacy_settings');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT setting_name, setting_value FROM {$privacy_table} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $settings = array();
        foreach ($results as $row) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * Check if user is online
     *
     * @param int $user_id
     * @return bool
     */
    public function is_user_online($user_id) {
        $profile = $this->get_user_profile($user_id);
        if (!$profile) {
            return false;
        }

        // Consider user online if last active within 5 minutes
        if ($profile->last_active) {
            $last_active = strtotime($profile->last_active);
            $current_time = current_time('timestamp');
            return ($current_time - $last_active) <= 300; // 5 minutes
        }

        return false;
    }

    /**
     * Update user last active time
     *
     * @param int $user_id
     */
    public function update_last_active($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return;
        }

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        $wpdb->update(
            $profiles_table,
            array(
                'last_active' => current_time('mysql'),
                'is_online' => 1
            ),
            array('user_id' => $user_id)
        );
    }

    /**
     * Remove custom roles on plugin deactivation
     */
    public static function remove_roles() {
        remove_role('dating_member');
        remove_role('dating_moderator');
        remove_role('dating_admin');

        // Remove capabilities from other roles
        $roles_to_clean = array('administrator', 'subscriber');
        $dating_capabilities = array(
            'edit_own_profile', 'upload_photos', 'send_messages', 'search_profiles',
            'view_profiles', 'report_users', 'block_users', 'moderate_profiles',
            'moderate_photos', 'moderate_messages', 'view_reports', 'manage_reports',
            'suspend_users', 'manage_dating_settings', 'manage_profile_fields',
            'view_dating_analytics', 'export_user_data'
        );

        foreach ($roles_to_clean as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($dating_capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
}