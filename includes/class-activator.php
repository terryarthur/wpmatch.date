<?php
/**
 * Plugin activation class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Activator class
 */
class WPMatch_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        // Check requirements
        self::check_requirements();

        // Create database tables
        self::create_database_tables();

        // Create user roles and capabilities
        self::create_user_roles();

        // Set default options
        self::set_default_options();

        // Schedule events
        self::schedule_events();

        // Create upload directories
        self::create_upload_directories();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option('wpmatch_activated', true);
        update_option('wpmatch_activation_time', current_time('mysql'));

        do_action('wpmatch_activated');
    }

    /**
     * Check plugin requirements
     */
    private static function check_requirements() {
        global $wp_version;

        // Check WordPress version
        if (version_compare($wp_version, '5.9', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WPMatch requires WordPress 5.9 or higher.', 'wpmatch'));
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WPMatch requires PHP 7.4 or higher.', 'wpmatch'));
        }

        // Check for required PHP extensions
        $required_extensions = array('mysqli', 'gd', 'curl', 'json');
        $missing_extensions = array();

        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }

        if (!empty($missing_extensions)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(sprintf(
                __('WPMatch requires the following PHP extensions: %s', 'wpmatch'),
                implode(', ', $missing_extensions)
            ));
        }
    }

    /**
     * Create database tables
     */
    private static function create_database_tables() {
        require_once WPMATCH_INCLUDES_PATH . 'class-database.php';
        $database = new WPMatch_Database();
        $database->create_tables();
    }

    /**
     * Create user roles and capabilities
     */
    private static function create_user_roles() {
        require_once WPMATCH_INCLUDES_PATH . 'class-user-manager.php';
        $user_manager = new WPMatch_User_Manager();
        $user_manager->create_roles();
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_options = array(
            'wpmatch_general_settings' => array(
                'site_name' => get_bloginfo('name'),
                'enable_registration' => '1',
                'require_email_verification' => '1',
                'min_age' => 18,
                'max_age' => 99,
                'default_profile_privacy' => 'public',
                'enable_geolocation' => '1',
                'distance_unit' => 'km',
                'max_photos_per_user' => 10,
                'require_photo_approval' => '1',
                'enable_success_stories' => '1',
            ),
            'wpmatch_messaging_settings' => array(
                'enable_messaging' => '1',
                'message_max_length' => 1000,
                'enable_message_attachments' => '1',
                'message_retention_days' => 365,
                'enable_read_receipts' => '1',
            ),
            'wpmatch_privacy_settings' => array(
                'enable_blocking' => '1',
                'enable_reporting' => '1',
                'auto_delete_inactive_users' => '0',
                'inactive_user_days' => 365,
                'enable_gdpr_tools' => '1',
            ),
            'wpmatch_security_settings' => array(
                'enable_rate_limiting' => '1',
                'max_login_attempts' => 5,
                'login_lockout_duration' => 3600,
                'enable_ip_blocking' => '0',
                'blocked_email_domains' => array(),
                'blocked_countries' => array(),
            ),
            'wpmatch_notification_settings' => array(
                'enable_email_notifications' => '1',
                'from_email' => get_option('admin_email'),
                'from_name' => get_bloginfo('name'),
                'new_message_notification' => '1',
                'profile_view_notification' => '0',
                'match_notification' => '1',
            ),
        );

        foreach ($default_options as $option_name => $option_value) {
            if (!get_option($option_name)) {
                update_option($option_name, $option_value);
            }
        }

        // Set plugin version
        update_option('wpmatch_version', WPMATCH_VERSION);
    }

    /**
     * Schedule events
     */
    private static function schedule_events() {
        // Schedule cleanup of expired verification tokens
        if (!wp_next_scheduled('wpmatch_cleanup_expired_tokens')) {
            wp_schedule_event(time(), 'daily', 'wpmatch_cleanup_expired_tokens');
        }

        // Schedule cleanup of old messages (if enabled)
        if (!wp_next_scheduled('wpmatch_cleanup_old_messages')) {
            wp_schedule_event(time(), 'weekly', 'wpmatch_cleanup_old_messages');
        }

        // Schedule user activity updates
        if (!wp_next_scheduled('wpmatch_update_user_activity')) {
            wp_schedule_event(time(), 'hourly', 'wpmatch_update_user_activity');
        }

        // Schedule profile completion reminders
        if (!wp_next_scheduled('wpmatch_profile_completion_reminders')) {
            wp_schedule_event(time(), 'daily', 'wpmatch_profile_completion_reminders');
        }
    }

    /**
     * Create upload directories
     */
    private static function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $wpmatch_upload_dir = $upload_dir['basedir'] . '/wpmatch';

        // Create main directory
        if (!file_exists($wpmatch_upload_dir)) {
            wp_mkdir_p($wpmatch_upload_dir);
        }

        // Create subdirectories
        $subdirectories = array('profiles', 'temp', 'verified');
        
        foreach ($subdirectories as $subdir) {
            $dir_path = $wpmatch_upload_dir . '/' . $subdir;
            if (!file_exists($dir_path)) {
                wp_mkdir_p($dir_path);
            }

            // Create .htaccess for security
            $htaccess_file = $dir_path . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                $htaccess_content = "Options -Indexes\n";
                $htaccess_content .= "deny from all\n";
                $htaccess_content .= "<Files ~ \"\\.(jpg|jpeg|png|gif|webp)$\">\n";
                $htaccess_content .= "    allow from all\n";
                $htaccess_content .= "</Files>\n";
                
                file_put_contents($htaccess_file, $htaccess_content);
            }
        }

        // Create index.php files for additional security
        $index_content = "<?php\n// Silence is golden.\n";
        
        file_put_contents($wpmatch_upload_dir . '/index.php', $index_content);
        
        foreach ($subdirectories as $subdir) {
            file_put_contents($wpmatch_upload_dir . '/' . $subdir . '/index.php', $index_content);
        }
    }

    /**
     * Create default pages
     */
    private static function create_default_pages() {
        $pages = array(
            'wpmatch-profiles' => array(
                'title' => __('Browse Profiles', 'wpmatch'),
                'content' => '[wpmatch_profile_search]',
                'template' => 'page-wpmatch-profiles.php'
            ),
            'wpmatch-profile' => array(
                'title' => __('My Profile', 'wpmatch'),
                'content' => '[wpmatch_user_profile]',
                'template' => 'page-wpmatch-profile.php'
            ),
            'wpmatch-messages' => array(
                'title' => __('Messages', 'wpmatch'),
                'content' => '[wpmatch_messages]',
                'template' => 'page-wpmatch-messages.php'
            ),
            'wpmatch-register' => array(
                'title' => __('Join Us', 'wpmatch'),
                'content' => '[wpmatch_registration_form]',
                'template' => 'page-wpmatch-register.php'
            ),
            'wpmatch-success-stories' => array(
                'title' => __('Success Stories', 'wpmatch'),
                'content' => '[wpmatch_success_stories]',
                'template' => 'page-wpmatch-success-stories.php'
            ),
        );

        foreach ($pages as $slug => $page_data) {
            // Check if page already exists
            $existing_page = get_page_by_path($slug);
            
            if (!$existing_page) {
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_name' => $slug,
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => 1,
                ));

                if ($page_id && !is_wp_error($page_id)) {
                    // Store page ID for future reference
                    update_option('wpmatch_page_' . str_replace('-', '_', $slug), $page_id);
                }
            }
        }
    }

    /**
     * Add capabilities to existing users
     */
    private static function add_capabilities_to_existing_users() {
        $users = get_users(array('role' => 'subscriber'));
        
        foreach ($users as $user) {
            $user_obj = new WP_User($user->ID);
            
            // Add basic dating capabilities
            $capabilities = array(
                'edit_own_profile',
                'upload_photos',
                'send_messages',
                'search_profiles',
                'view_profiles',
                'report_users',
                'block_users',
            );

            foreach ($capabilities as $cap) {
                $user_obj->add_cap($cap);
            }
        }
    }

    /**
     * Set up cron schedules
     */
    private static function setup_cron_schedules() {
        // Add custom cron intervals
        add_filter('cron_schedules', function($schedules) {
            $schedules['wpmatch_hourly'] = array(
                'interval' => 3600,
                'display' => __('Every Hour', 'wpmatch')
            );
            
            $schedules['wpmatch_weekly'] = array(
                'interval' => 604800,
                'display' => __('Every Week', 'wpmatch')
            );
            
            return $schedules;
        });
    }

    /**
     * Log activation
     */
    private static function log_activation() {
        $activation_data = array(
            'timestamp' => current_time('mysql'),
            'version' => WPMATCH_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'user_id' => get_current_user_id(),
            'site_url' => get_site_url(),
        );

        update_option('wpmatch_last_activation', $activation_data);
    }
}