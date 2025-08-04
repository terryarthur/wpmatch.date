<?php
/**
 * WPMatch Template Loader
 *
 * @package WPMatch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template loading and management class for WPMatch
 */
class WPMatch_Template_Loader {

    /**
     * Template directories
     */
    private static $template_paths = array();

    /**
     * Initialize template loader
     */
    public static function init() {
        self::setup_template_paths();
        
        add_filter('template_include', array(__CLASS__, 'template_loader'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_template_assets'));
        add_filter('single_template', array(__CLASS__, 'single_template_loader'));
        add_filter('archive_template', array(__CLASS__, 'archive_template_loader'));
    }

    /**
     * Setup template paths
     */
    private static function setup_template_paths() {
        self::$template_paths = array(
            'theme_templates' => get_stylesheet_directory() . '/wpmatch/',
            'plugin_templates' => WPMATCH_PLUGIN_PATH . 'templates/',
            'child_theme_templates' => get_template_directory() . '/wpmatch/'
        );
    }

    /**
     * Main template loader
     *
     * @param string $template Template path
     * @return string
     */
    public static function template_loader($template) {
        global $wp_query;
        
        // Check for WPMatch-specific query vars
        if (get_query_var('wp_dating_profile')) {
            return self::get_template('single-profile.php');
        }
        
        if (get_query_var('wp_dating_search')) {
            return self::get_template('search-profiles.php');
        }
        
        if (get_query_var('wp_dating_messages')) {
            return self::get_template('messages.php');
        }
        
        // Check for dating story post type
        if (is_singular('dating_story')) {
            return self::get_template('single-dating-story.php');
        }
        
        if (is_post_type_archive('dating_story')) {
            return self::get_template('archive-dating-stories.php');
        }
        
        return $template;
    }

    /**
     * Single template loader
     *
     * @param string $template Template path
     * @return string
     */
    public static function single_template_loader($template) {
        global $post;
        
        if ($post->post_type == 'dating_story') {
            $custom_template = self::get_template('single-dating-story.php');
            if ($custom_template) {
                return $custom_template;
            }
        }
        
        return $template;
    }

    /**
     * Archive template loader
     *
     * @param string $template Template path
     * @return string
     */
    public static function archive_template_loader($template) {
        if (is_post_type_archive('dating_story')) {
            $custom_template = self::get_template('archive-dating-stories.php');
            if ($custom_template) {
                return $custom_template;
            }
        }
        
        return $template;
    }

    /**
     * Get template file
     *
     * @param string $template_name Template name
     * @param array $args Template arguments
     * @param string $template_path Template path
     * @param string $default_path Default path
     * @return string
     */
    public static function get_template($template_name, $args = array(), $template_path = '', $default_path = '') {
        if (!empty($args) && is_array($args)) {
            extract($args);
        }
        
        $located = self::locate_template($template_name, $template_path, $default_path);
        
        if (!file_exists($located)) {
            return false;
        }
        
        return $located;
    }

    /**
     * Locate template file
     *
     * @param string $template_name Template name
     * @param string $template_path Template path
     * @param string $default_path Default path
     * @return string
     */
    public static function locate_template($template_name, $template_path = '', $default_path = '') {
        if (!$template_path) {
            $template_path = 'wpmatch/';
        }
        
        if (!$default_path) {
            $default_path = WPMATCH_PLUGIN_PATH . 'templates/';
        }
        
        // Look within passed path within the theme - this is priority
        $template = locate_template(array(
            trailingslashit($template_path) . $template_name,
            $template_name
        ));
        
        // Get default template
        if (!$template) {
            $template = $default_path . $template_name;
        }
        
        return apply_filters('wpmatch_locate_template', $template, $template_name, $template_path);
    }

    /**
     * Include template file
     *
     * @param string $template_name Template name
     * @param array $args Template arguments
     * @param string $template_path Template path
     * @param string $default_path Default path
     */
    public static function get_template_part($template_name, $args = array(), $template_path = '', $default_path = '') {
        $template = self::get_template($template_name, $args, $template_path, $default_path);
        
        if ($template) {
            if (!empty($args) && is_array($args)) {
                extract($args);
            }
            
            do_action('wpmatch_before_template_part', $template_name, $template, $args);
            
            include $template;
            
            do_action('wpmatch_after_template_part', $template_name, $template, $args);
        }
    }

    /**
     * Get template HTML
     *
     * @param string $template_name Template name
     * @param array $args Template arguments
     * @param string $template_path Template path
     * @param string $default_path Default path
     * @return string
     */
    public static function get_template_html($template_name, $args = array(), $template_path = '', $default_path = '') {
        ob_start();
        self::get_template_part($template_name, $args, $template_path, $default_path);
        return ob_get_clean();
    }

    /**
     * Load profile template
     *
     * @param int $user_id User ID
     * @param array $args Additional arguments
     */
    public static function load_profile_template($user_id, $args = array()) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $profile_args = wp_parse_args($args, array(
            'user' => $user,
            'user_id' => $user_id,
            'profile_data' => get_user_meta($user_id),
            'is_own_profile' => (get_current_user_id() == $user_id),
            'can_message' => self::can_user_message($user_id),
            'profile_fields' => self::get_profile_fields($user_id)
        ));
        
        self::get_template_part('profile/profile-card.php', $profile_args);
    }

    /**
     * Load search results template
     *
     * @param array $search_results Search results
     * @param array $search_criteria Search criteria
     */
    public static function load_search_results_template($search_results, $search_criteria = array()) {
        $search_args = array(
            'results' => $search_results,
            'criteria' => $search_criteria,
            'total_results' => count($search_results),
            'current_user_id' => get_current_user_id()
        );
        
        self::get_template_part('search/search-results.php', $search_args);
    }

    /**
     * Load messaging template
     *
     * @param int $conversation_id Conversation ID
     * @param array $args Additional arguments
     */
    public static function load_messaging_template($conversation_id = null, $args = array()) {
        $current_user_id = get_current_user_id();
        
        if ($conversation_id) {
            $messages = self::get_conversation_messages($conversation_id);
            $other_user_id = self::get_other_user_in_conversation($conversation_id, $current_user_id);
        } else {
            $messages = array();
            $other_user_id = null;
        }
        
        $messaging_args = wp_parse_args($args, array(
            'conversation_id' => $conversation_id,
            'messages' => $messages,
            'other_user_id' => $other_user_id,
            'current_user_id' => $current_user_id,
            'conversations' => self::get_user_conversations($current_user_id)
        ));
        
        self::get_template_part('messaging/messages.php', $messaging_args);
    }

    /**
     * Enqueue template assets
     */
    public static function enqueue_template_assets() {
        if (self::is_wpmatch_page()) {
            wp_enqueue_style(
                'wpmatch-templates',
                WPMATCH_PLUGIN_URL . 'assets/css/templates.css',
                array(),
                WPMATCH_VERSION
            );
            
            wp_enqueue_script(
                'wpmatch-templates',
                WPMATCH_PLUGIN_URL . 'assets/js/templates.js',
                array('jquery'),
                WPMATCH_VERSION,
                true
            );
            
            wp_localize_script('wpmatch-templates', 'wpMatchTemplates', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpmatch_template_nonce'),
                'strings' => array(
                    'loading' => __('Loading...', 'wpmatch'),
                    'error' => __('An error occurred. Please try again.', 'wpmatch'),
                    'confirm_delete' => __('Are you sure you want to delete this?', 'wpmatch')
                )
            ));
        }
    }

    /**
     * Check if current page is a WPMatch page
     *
     * @return bool
     */
    private static function is_wpmatch_page() {
        return (
            get_query_var('wp_dating_profile') ||
            get_query_var('wp_dating_search') ||
            get_query_var('wp_dating_messages') ||
            is_singular('dating_story') ||
            is_post_type_archive('dating_story')
        );
    }

    /**
     * Get available template files
     *
     * @param string $template_type Template type
     * @return array
     */
    public static function get_available_templates($template_type = '') {
        $templates = array();
        
        foreach (self::$template_paths as $path_key => $path) {
            if (is_dir($path)) {
                $template_files = glob($path . '*.php');
                foreach ($template_files as $file) {
                    $template_name = basename($file);
                    if (empty($template_type) || strpos($template_name, $template_type) === 0) {
                        $templates[$template_name] = array(
                            'name' => $template_name,
                            'path' => $file,
                            'source' => $path_key
                        );
                    }
                }
            }
        }
        
        return $templates;
    }

    /**
     * Create template override
     *
     * @param string $template_name Template name
     * @param string $content Template content
     * @return bool
     */
    public static function create_template_override($template_name, $content) {
        $theme_template_dir = get_stylesheet_directory() . '/wpmatch/';
        
        if (!is_dir($theme_template_dir)) {
            wp_mkdir_p($theme_template_dir);
        }
        
        $template_file = $theme_template_dir . $template_name;
        
        return file_put_contents($template_file, $content) !== false;
    }

    /**
     * Get template content
     *
     * @param string $template_name Template name
     * @return string|false
     */
    public static function get_template_content($template_name) {
        $template_path = self::locate_template($template_name);
        
        if (file_exists($template_path)) {
            return file_get_contents($template_path);
        }
        
        return false;
    }

    /**
     * Check if user can message another user
     *
     * @param int $user_id User ID to message
     * @return bool
     */
    private static function can_user_message($user_id) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id || $current_user_id == $user_id) {
            return false;
        }
        
        // Check if messaging is enabled
        $settings = get_option('wpmatch_settings', array());
        if (!isset($settings['enable_messaging']) || !$settings['enable_messaging']) {
            return false;
        }
        
        // Check if user is blocked
        global $wpdb;
        $is_blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_blocks 
             WHERE (user_id = %d AND blocked_user_id = %d) 
             OR (user_id = %d AND blocked_user_id = %d)",
            $current_user_id, $user_id, $user_id, $current_user_id
        ));
        
        return !$is_blocked;
    }

    /**
     * Get profile fields for user
     *
     * @param int $user_id User ID
     * @return array
     */
    private static function get_profile_fields($user_id) {
        global $wpdb;
        
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT pf.*, pfv.value 
             FROM {$wpdb->prefix}wpmatch_profile_fields pf
             LEFT JOIN {$wpdb->prefix}wpmatch_profile_field_values pfv 
                ON pf.id = pfv.field_id AND pfv.user_id = %d
             WHERE pf.status = 'active' 
             ORDER BY pf.field_order ASC",
            $user_id
        ), ARRAY_A);
        
        return $fields;
    }

    /**
     * Get conversation messages
     *
     * @param int $conversation_id Conversation ID
     * @return array
     */
    private static function get_conversation_messages($conversation_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpmatch_messages 
             WHERE conversation_id = %d 
             ORDER BY created_at ASC",
            $conversation_id
        ), ARRAY_A);
    }

    /**
     * Get other user in conversation
     *
     * @param int $conversation_id Conversation ID
     * @param int $current_user_id Current user ID
     * @return int|null
     */
    private static function get_other_user_in_conversation($conversation_id, $current_user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT CASE 
                WHEN sender_id = %d THEN receiver_id 
                ELSE sender_id 
             END as other_user_id
             FROM {$wpdb->prefix}wpmatch_messages 
             WHERE conversation_id = %d 
             LIMIT 1",
            $current_user_id, $conversation_id
        ));
    }

    /**
     * Get user conversations
     *
     * @param int $user_id User ID
     * @return array
     */
    private static function get_user_conversations($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT conversation_id, 
                    CASE WHEN sender_id = %d THEN receiver_id ELSE sender_id END as other_user_id,
                    MAX(created_at) as last_message_time,
                    COUNT(*) as message_count
             FROM {$wpdb->prefix}wpmatch_messages 
             WHERE sender_id = %d OR receiver_id = %d
             GROUP BY conversation_id, other_user_id
             ORDER BY last_message_time DESC",
            $user_id, $user_id, $user_id
        ), ARRAY_A);
    }

    /**
     * Register template hook
     *
     * @param string $hook_name Hook name
     * @param string $template_name Template name
     * @param int $priority Priority
     */
    public static function register_template_hook($hook_name, $template_name, $priority = 10) {
        add_action($hook_name, function($args = array()) use ($template_name) {
            self::get_template_part($template_name, $args);
        }, $priority);
    }

    /**
     * Get template variables
     *
     * @param string $template_name Template name
     * @return array
     */
    public static function get_template_variables($template_name) {
        $variables = array();
        
        // Define common variables for different template types
        switch (true) {
            case strpos($template_name, 'profile') !== false:
                $variables = array(
                    'user' => 'User object',
                    'user_id' => 'User ID',
                    'profile_data' => 'User profile data',
                    'is_own_profile' => 'Boolean if viewing own profile',
                    'can_message' => 'Boolean if current user can message this user',
                    'profile_fields' => 'Array of profile fields'
                );
                break;
                
            case strpos($template_name, 'search') !== false:
                $variables = array(
                    'results' => 'Array of search results',
                    'criteria' => 'Search criteria used',
                    'total_results' => 'Total number of results',
                    'current_user_id' => 'Current user ID'
                );
                break;
                
            case strpos($template_name, 'message') !== false:
                $variables = array(
                    'conversation_id' => 'Conversation ID',
                    'messages' => 'Array of messages',
                    'other_user_id' => 'Other user in conversation',
                    'current_user_id' => 'Current user ID',
                    'conversations' => 'Array of user conversations'
                );
                break;
        }
        
        return apply_filters('wpmatch_template_variables', $variables, $template_name);
    }
}