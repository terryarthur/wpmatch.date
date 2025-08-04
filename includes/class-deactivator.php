<?php
/**
 * Plugin deactivation class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Deactivator class
 */
class WPMatch_Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear any cached data
        self::clear_cache();

        // Log deactivation
        self::log_deactivation();

        // Set deactivation flag
        update_option('wpmatch_deactivated', true);
        update_option('wpmatch_deactivation_time', current_time('mysql'));

        do_action('wpmatch_deactivated');
    }

    /**
     * Clear all scheduled events
     */
    private static function clear_scheduled_events() {
        $scheduled_events = array(
            'wpmatch_cleanup_expired_tokens',
            'wpmatch_cleanup_old_messages',
            'wpmatch_update_user_activity',
            'wpmatch_profile_completion_reminders',
        );

        foreach ($scheduled_events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }

        // Clear all WPMatch-related cron events
        $cron_array = get_option('cron');
        if (is_array($cron_array)) {
            foreach ($cron_array as $timestamp => $cron) {
                if (is_array($cron)) {
                    foreach ($cron as $hook => $dings) {
                        if (strpos($hook, 'wpmatch_') === 0) {
                            wp_unschedule_event($timestamp, $hook);
                        }
                    }
                }
            }
        }
    }

    /**
     * Clear plugin cache
     */
    private static function clear_cache() {
        // Clear object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear transients
        self::clear_transients();

        // Clear any file-based cache
        self::clear_file_cache();
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients() {
        global $wpdb;

        // Delete all WPMatch transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpmatch_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpmatch_%'");

        // Delete site transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_wpmatch_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_timeout_wpmatch_%'");
    }

    /**
     * Clear file-based cache
     */
    private static function clear_file_cache() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/wpmatch/cache';

        if (file_exists($cache_dir)) {
            self::delete_directory_contents($cache_dir);
        }
    }

    /**
     * Delete directory contents recursively
     *
     * @param string $dir
     */
    private static function delete_directory_contents($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $file_path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($file_path)) {
                self::delete_directory_contents($file_path);
                rmdir($file_path);
            } else {
                unlink($file_path);
            }
        }
    }

    /**
     * Clean up user sessions and temporary data
     */
    private static function cleanup_user_sessions() {
        global $wpdb;

        // Clear any active user sessions related to WPMatch
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpmatch_session_%'");
        
        // Clear temporary user data
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpmatch_temp_%'");
    }

    /**
     * Handle graceful user experience during deactivation
     */
    private static function handle_user_experience() {
        // Update user roles back to default if they were dating-specific
        $users_query = new WP_User_Query(array(
            'role__in' => array('dating_member', 'dating_moderator', 'dating_admin'),
            'fields' => 'ID'
        ));

        $users = $users_query->get_results();

        foreach ($users as $user_id) {
            $user = new WP_User($user_id);
            
            // Remove dating-specific roles and assign subscriber
            $user->remove_role('dating_member');
            $user->remove_role('dating_moderator');
            $user->remove_role('dating_admin');
            
            // Add subscriber role if user has no roles
            if (empty($user->roles)) {
                $user->add_role('subscriber');
            }
        }
    }

    /**
     * Preserve essential data during deactivation
     */
    private static function preserve_essential_data() {
        // Mark that data should be preserved
        update_option('wpmatch_preserve_data', true);
        
        // Store current plugin version for potential reactivation
        update_option('wpmatch_last_version', WPMATCH_VERSION);
        
        // Store deactivation reason if provided
        if (isset($_POST['wpmatch_deactivation_reason'])) {
            update_option('wpmatch_deactivation_reason', sanitize_text_field($_POST['wpmatch_deactivation_reason']));
        }
    }

    /**
     * Send deactivation feedback (optional)
     */
    private static function send_deactivation_feedback() {
        // Only send if user opted in and provided feedback
        if (get_option('wpmatch_send_feedback', false)) {
            $feedback_data = array(
                'site_url' => get_site_url(),
                'plugin_version' => WPMATCH_VERSION,
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'deactivation_reason' => get_option('wpmatch_deactivation_reason', ''),
                'user_count' => count_users(),
                'timestamp' => current_time('mysql'),
            );

            // Send feedback (implement according to your feedback system)
            wp_remote_post('https://api.wpmatch.com/feedback/deactivation', array(
                'body' => wp_json_encode($feedback_data),
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 5,
                'blocking' => false, // Non-blocking request
            ));
        }
    }

    /**
     * Log deactivation
     */
    private static function log_deactivation() {
        $deactivation_data = array(
            'timestamp' => current_time('mysql'),
            'version' => WPMATCH_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'user_id' => get_current_user_id(),
            'site_url' => get_site_url(),
            'reason' => get_option('wpmatch_deactivation_reason', 'unknown'),
        );

        update_option('wpmatch_last_deactivation', $deactivation_data);

        // Log to debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPMatch Deactivated: ' . wp_json_encode($deactivation_data));
        }
    }

    /**
     * Show deactivation notice
     */
    public static function deactivation_notice() {
        $screen = get_current_screen();
        
        if ($screen && $screen->base === 'plugins' && get_option('wpmatch_deactivated')) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <?php _e('WPMatch has been deactivated. Your data has been preserved and will be available if you reactivate the plugin.', 'wpmatch'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wpmatch-feedback'); ?>" class="button">
                        <?php _e('Provide Feedback', 'wpmatch'); ?>
                    </a>
                </p>
            </div>
            <?php
            
            // Remove the notice flag
            delete_option('wpmatch_deactivated');
        }
    }

    /**
     * Complete cleanup (called during uninstall)
     */
    public static function complete_cleanup() {
        // Remove user roles
        require_once WPMATCH_INCLUDES_PATH . 'class-user-manager.php';
        WPMatch_User_Manager::remove_roles();

        // Drop database tables
        require_once WPMATCH_INCLUDES_PATH . 'class-database.php';
        $database = new WPMatch_Database();
        $database->drop_tables();

        // Remove all options
        self::remove_all_options();

        // Remove upload directories
        self::remove_upload_directories();

        // Clear all cache and transients
        self::clear_cache();

        // Remove any remaining user meta
        self::cleanup_user_meta();
    }

    /**
     * Remove all plugin options
     */
    private static function remove_all_options() {
        global $wpdb;

        // Remove all WPMatch options
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpmatch_%'");
    }

    /**
     * Remove upload directories
     */
    private static function remove_upload_directories() {
        $upload_dir = wp_upload_dir();
        $wpmatch_upload_dir = $upload_dir['basedir'] . '/wpmatch';

        if (file_exists($wpmatch_upload_dir)) {
            self::delete_directory_contents($wpmatch_upload_dir);
            rmdir($wpmatch_upload_dir);
        }
    }

    /**
     * Clean up user meta
     */
    private static function cleanup_user_meta() {
        global $wpdb;

        // Remove all WPMatch-related user meta
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpmatch_%'");
    }
}