<?php
/**
 * WPMatch Multisite Manager
 *
 * @package WPMatch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multisite management class for WPMatch
 */
class WPMatch_Multisite_Manager {

    /**
     * Instance of this class
     *
     * @var WPMatch_Multisite_Manager
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return WPMatch_Multisite_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        if (is_multisite()) {
            $this->init_multisite_hooks();
        }
    }

    /**
     * Initialize multisite hooks
     */
    private function init_multisite_hooks() {
        add_action('wp_initialize_site', array($this, 'on_site_created'));
        add_action('wp_delete_site', array($this, 'on_site_deleted'));
        add_action('update_site_option_active_sitewide_plugins', array($this, 'on_network_plugin_activation'));
        add_filter('wpmu_drop_tables', array($this, 'add_custom_tables_to_drop'));
    }

    /**
     * Check if WPMatch is network activated
     *
     * @return bool
     */
    public static function is_network_activated() {
        if (!is_multisite()) {
            return false;
        }
        
        $network_plugins = get_site_option('active_sitewide_plugins');
        return isset($network_plugins[WPMATCH_PLUGIN_BASENAME]);
    }

    /**
     * Get all sites where WPMatch is active
     *
     * @return array
     */
    public static function get_active_sites() {
        if (!is_multisite()) {
            return array(get_current_blog_id());
        }
        
        $sites = get_sites(array(
            'fields' => 'ids',
            'number' => 0,
            'meta_query' => array(
                array(
                    'key' => 'wpmatch_active',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        // If network activated, include all sites
        if (self::is_network_activated()) {
            $all_sites = get_sites(array('fields' => 'ids', 'number' => 0));
            $sites = array_unique(array_merge($sites, $all_sites));
        }
        
        return $sites;
    }

    /**
     * Execute action across all active sites
     *
     * @param callable $callback Callback function to execute
     * @param array $args Arguments for callback
     * @return array Results from each site
     */
    public static function execute_across_sites($callback, $args = array()) {
        if (!is_multisite()) {
            return array(get_current_blog_id() => call_user_func_array($callback, $args));
        }
        
        $results = array();
        $active_sites = self::get_active_sites();
        $current_blog_id = get_current_blog_id();
        
        foreach ($active_sites as $site_id) {
            switch_to_blog($site_id);
            
            try {
                $results[$site_id] = call_user_func_array($callback, $args);
            } catch (Exception $e) {
                $results[$site_id] = new WP_Error('execution_error', $e->getMessage());
            }
            
            restore_current_blog();
        }
        
        return $results;
    }

    /**
     * Get network-wide user statistics
     *
     * @return array
     */
    public static function get_network_user_stats() {
        if (!is_multisite()) {
            return self::get_site_user_stats();
        }
        
        $stats = array(
            'total_users' => 0,
            'active_users_24h' => 0,
            'active_users_7d' => 0,
            'total_profiles' => 0,
            'verified_profiles' => 0,
            'premium_users' => 0,
            'sites' => array()
        );
        
        $active_sites = self::get_active_sites();
        $current_blog_id = get_current_blog_id();
        
        foreach ($active_sites as $site_id) {
            switch_to_blog($site_id);
            
            $site_stats = self::get_site_user_stats();
            $site_info = get_blog_details($site_id);
            
            $stats['total_users'] += $site_stats['total_users'];
            $stats['active_users_24h'] += $site_stats['active_users_24h'];
            $stats['active_users_7d'] += $site_stats['active_users_7d'];
            $stats['total_profiles'] += $site_stats['total_profiles'];
            $stats['verified_profiles'] += $site_stats['verified_profiles'];
            $stats['premium_users'] += $site_stats['premium_users'];
            
            $stats['sites'][$site_id] = array(
                'name' => $site_info->blogname,
                'url' => $site_info->siteurl,
                'stats' => $site_stats
            );
            
            restore_current_blog();
        }
        
        return $stats;
    }

    /**
     * Get user statistics for current site
     *
     * @return array
     */
    private static function get_site_user_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total users
        $stats['total_users'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users}"
        );
        
        // Active users in last 24 hours
        $stats['active_users_24h'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'last_activity' 
             AND meta_value > %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        
        // Active users in last 7 days
        $stats['active_users_7d'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'last_activity' 
             AND meta_value > %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        // Total profiles (users with profile data)
        $stats['total_profiles'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'profile_completed' AND meta_value = '1'"
        );
        
        // Verified profiles
        $stats['verified_profiles'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'profile_verified' AND meta_value = '1'"
        );
        
        // Premium users
        $stats['premium_users'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'subscription_status' AND meta_value = 'active'"
        );
        
        return $stats;
    }

    /**
     * Sync user data across network sites
     *
     * @param int $user_id User ID
     * @param array $meta_keys Meta keys to sync
     * @return bool
     */
    public static function sync_user_across_network($user_id, $meta_keys = array()) {
        if (!is_multisite()) {
            return true;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $current_blog_id = get_current_blog_id();
        $user_sites = get_blogs_of_user($user_id);
        
        // Default meta keys to sync
        if (empty($meta_keys)) {
            $meta_keys = array(
                'first_name',
                'last_name',
                'nickname',
                'description',
                'profile_photo',
                'birth_date',
                'gender',
                'location',
                'interests'
            );
        }
        
        // Get user meta from current site
        $user_meta = array();
        foreach ($meta_keys as $key) {
            $user_meta[$key] = get_user_meta($user_id, $key, true);
        }
        
        // Sync to all user's sites
        foreach ($user_sites as $site) {
            if ($site->userblog_id != $current_blog_id) {
                switch_to_blog($site->userblog_id);
                
                foreach ($user_meta as $key => $value) {
                    update_user_meta($user_id, $key, $value);
                }
                
                restore_current_blog();
            }
        }
        
        return true;
    }

    /**
     * Handle new site creation
     *
     * @param WP_Site $new_site New site object
     */
    public function on_site_created($new_site) {
        if (!self::is_network_activated()) {
            return;
        }
        
        switch_to_blog($new_site->blog_id);
        
        // Create WPMatch tables for new site
        if (class_exists('WPMatch_Activator')) {
            WPMatch_Activator::create_tables();
        }
        
        // Set default options
        $this->set_default_site_options();
        
        // Mark site as having WPMatch active
        update_blog_meta($new_site->blog_id, 'wpmatch_active', '1');
        
        restore_current_blog();
    }

    /**
     * Handle site deletion
     *
     * @param WP_Site $old_site Site being deleted
     */
    public function on_site_deleted($old_site) {
        // Custom tables will be dropped by the wpmu_drop_tables filter
        // Just clean up any network-wide references
        delete_blog_meta($old_site->blog_id, 'wpmatch_active');
    }

    /**
     * Handle network plugin activation
     *
     * @param array $plugins Active network plugins
     */
    public function on_network_plugin_activation($plugins) {
        if (isset($plugins[WPMATCH_PLUGIN_BASENAME])) {
            // Plugin was network activated
            $this->network_activate_wpmatch();
        }
    }

    /**
     * Network activate WPMatch
     */
    private function network_activate_wpmatch() {
        $sites = get_sites(array('fields' => 'ids', 'number' => 0));
        $current_blog_id = get_current_blog_id();
        
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            
            // Create tables if they don't exist
            if (class_exists('WPMatch_Activator')) {
                WPMatch_Activator::create_tables();
            }
            
            // Set default options
            $this->set_default_site_options();
            
            // Mark site as having WPMatch active
            update_blog_meta($site_id, 'wpmatch_active', '1');
            
            restore_current_blog();
        }
    }

    /**
     * Set default options for a site
     */
    private function set_default_site_options() {
        $default_options = array(
            'wpmatch_settings' => array(
                'allow_registration' => true,
                'require_email_verification' => true,
                'minimum_age' => 18,
                'enable_matching' => true,
                'enable_messaging' => true
            )
        );
        
        foreach ($default_options as $option_name => $option_value) {
            if (!get_option($option_name)) {
                update_option($option_name, $option_value);
            }
        }
    }

    /**
     * Add WPMatch tables to drop list when site is deleted
     *
     * @param array $tables Tables to drop
     * @return array
     */
    public function add_custom_tables_to_drop($tables) {
        global $wpdb;
        
        $wpmatch_tables = array(
            $wpdb->prefix . 'wpmatch_profile_fields',
            $wpdb->prefix . 'wpmatch_profile_field_values',
            $wpdb->prefix . 'wpmatch_messages',
            $wpdb->prefix . 'wpmatch_user_interactions',
            $wpdb->prefix . 'wpmatch_user_blocks',
            $wpdb->prefix . 'wpmatch_search_logs',
            $wpdb->prefix . 'wpmatch_notifications'
        );
        
        return array_merge($tables, $wpmatch_tables);
    }

    /**
     * Get network settings
     *
     * @return array
     */
    public static function get_network_settings() {
        $settings = get_site_option('wpmatch_network_settings', array());
        
        $defaults = array(
            'cross_site_matching' => false,
            'shared_user_profiles' => false,
            'central_messaging' => false,
            'network_admin_email' => get_site_option('admin_email'),
            'enable_network_stats' => true
        );
        
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Update network settings
     *
     * @param array $settings Settings array
     * @return bool
     */
    public static function update_network_settings($settings) {
        return update_site_option('wpmatch_network_settings', $settings);
    }

    /**
     * Search users across network
     *
     * @param array $criteria Search criteria
     * @param array $options Search options
     * @return array
     */
    public static function network_user_search($criteria = array(), $options = array()) {
        if (!is_multisite()) {
            $search_manager = WPMatch_Search_Manager::get_instance();
            return $search_manager->search_users($criteria, $options);
        }
        
        $network_settings = self::get_network_settings();
        if (!$network_settings['cross_site_matching']) {
            // Cross-site matching disabled, search current site only
            $search_manager = WPMatch_Search_Manager::get_instance();
            return $search_manager->search_users($criteria, $options);
        }
        
        $all_results = array();
        $active_sites = self::get_active_sites();
        $current_blog_id = get_current_blog_id();
        
        foreach ($active_sites as $site_id) {
            switch_to_blog($site_id);
            
            $search_manager = WPMatch_Search_Manager::get_instance();
            $site_results = $search_manager->search_users($criteria, $options);
            
            // Add site info to results
            foreach ($site_results as &$result) {
                $result['site_id'] = $site_id;
                $result['site_name'] = get_bloginfo('name');
            }
            
            $all_results = array_merge($all_results, $site_results);
            
            restore_current_blog();
        }
        
        // Sort combined results by compatibility score
        if (!empty($all_results) && isset($all_results[0]['compatibility_score'])) {
            usort($all_results, function($a, $b) {
                return $b['compatibility_score'] - $a['compatibility_score'];
            });
        }
        
        // Apply limit to combined results
        $limit = isset($options['limit']) ? $options['limit'] : 20;
        return array_slice($all_results, 0, $limit);
    }

    /**
     * Get network activity feed
     *
     * @param array $options Feed options
     * @return array
     */
    public static function get_network_activity_feed($options = array()) {
        if (!is_multisite()) {
            return array();
        }
        
        $defaults = array(
            'limit' => 50,
            'types' => array('registration', 'profile_update', 'match', 'message'),
            'since' => date('Y-m-d H:i:s', strtotime('-7 days'))
        );
        
        $options = wp_parse_args($options, $defaults);
        $activities = array();
        $active_sites = self::get_active_sites();
        $current_blog_id = get_current_blog_id();
        
        foreach ($active_sites as $site_id) {
            switch_to_blog($site_id);
            
            $site_activities = self::get_site_activities($options);
            
            // Add site info
            foreach ($site_activities as &$activity) {
                $activity['site_id'] = $site_id;
                $activity['site_name'] = get_bloginfo('name');
            }
            
            $activities = array_merge($activities, $site_activities);
            
            restore_current_blog();
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, $options['limit']);
    }

    /**
     * Get activities for current site
     *
     * @param array $options Activity options
     * @return array
     */
    private static function get_site_activities($options) {
        global $wpdb;
        
        $activities = array();
        
        // Get recent registrations
        if (in_array('registration', $options['types'])) {
            $registrations = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, user_login, display_name, user_registered as timestamp
                 FROM {$wpdb->users} 
                 WHERE user_registered > %s 
                 ORDER BY user_registered DESC 
                 LIMIT 20",
                $options['since']
            ));
            
            foreach ($registrations as $reg) {
                $activities[] = array(
                    'type' => 'registration',
                    'user_id' => $reg->ID,
                    'user_name' => $reg->display_name,
                    'timestamp' => $reg->timestamp,
                    'description' => sprintf(__('%s joined the site', 'wpmatch'), $reg->display_name)
                );
            }
        }
        
        return $activities;
    }

    /**
     * Check if user has access to cross-site features
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function user_has_network_access($user_id) {
        if (!is_multisite()) {
            return true;
        }
        
        $network_settings = self::get_network_settings();
        
        // Check if user is super admin
        if (is_super_admin($user_id)) {
            return true;
        }
        
        // Check if user has premium subscription
        $subscription_status = get_user_meta($user_id, 'subscription_status', true);
        if ($subscription_status === 'active') {
            return true;
        }
        
        // Check network settings
        return $network_settings['cross_site_matching'];
    }

    /**
     * Get user's network sites
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_user_network_sites($user_id) {
        if (!is_multisite()) {
            return array(get_current_blog_id());
        }
        
        $user_sites = get_blogs_of_user($user_id);
        $active_sites = self::get_active_sites();
        
        $network_sites = array();
        foreach ($user_sites as $site) {
            if (in_array($site->userblog_id, $active_sites)) {
                $network_sites[] = array(
                    'blog_id' => $site->userblog_id,
                    'blogname' => $site->blogname,
                    'siteurl' => $site->siteurl,
                    'path' => $site->path
                );
            }
        }
        
        return $network_sites;
    }
}