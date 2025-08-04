<?php
/**
 * Plugin Name: WPMatch
 * Plugin URI: https://github.com/wpmatch/wpmatch
 * Description: A comprehensive, modular WordPress dating plugin with unlimited usage and premium extensions.
 * Version: 1.0.0
 * Author: WPMatch Team
 * Author URI: https://wpmatch.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmatch
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package WPMatch
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPMATCH_VERSION', '1.0.0');
define('WPMATCH_PLUGIN_FILE', __FILE__);
define('WPMATCH_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WPMATCH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPMATCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPMATCH_PLUGIN_ASSETS_URL', WPMATCH_PLUGIN_URL . 'assets/');
define('WPMATCH_INCLUDES_PATH', WPMATCH_PLUGIN_PATH . 'includes/');
define('WPMATCH_ADMIN_PATH', WPMATCH_PLUGIN_PATH . 'admin/');
define('WPMATCH_PUBLIC_PATH', WPMATCH_PLUGIN_PATH . 'public/');

// Minimum requirements check
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('WPMatch requires PHP 7.4 or higher. Please update your PHP version.', 'wpmatch');
        echo '</p></div>';
    });
    return;
}

/**
 * Main plugin class
 */
class WPMatch_Plugin {

    /**
     * Plugin instance
     *
     * @var WPMatch_Plugin
     */
    private static $instance = null;

    /**
     * Database manager instance
     *
     * @var WPMatch_Database
     */
    public $database;

    /**
     * User manager instance
     *
     * @var WPMatch_User_Manager
     */
    public $user_manager;

    /**
     * Profile manager instance
     *
     * @var WPMatch_Profile_Manager
     */
    public $profile_manager;

    /**
     * Security manager instance
     *
     * @var WPMatch_Security
     */
    public $security;

    /**
     * Admin manager instance
     *
     * @var WPMatch_Admin
     */
    public $admin;

    /**
     * Public manager instance
     *
     * @var WPMatch_Public
     */
    public $public;

    /**
     * Messaging manager instance
     *
     * @var WPMatch_Messaging_Manager
     */
    public $messaging_manager;

    /**
     * Interaction manager instance
     *
     * @var WPMatch_Interaction_Manager
     */
    public $interaction_manager;

    /**
     * Get plugin instance
     *
     * @return WPMatch_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once WPMATCH_INCLUDES_PATH . 'class-database.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-security.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-security-enhancements.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-performance-optimizer.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-user-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-profile-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-media-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-messaging-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-interaction-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-frontend-field-renderer.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-field-import-export.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-default-dating-fields.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-activator.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-deactivator.php';

        // Utility classes
        require_once WPMATCH_INCLUDES_PATH . 'class-cache.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-search-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-rate-limiter.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-session-validator.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-brute-force-protection.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-encryption.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-multisite-manager.php';
        require_once WPMATCH_INCLUDES_PATH . 'class-template-loader.php';

        // Admin classes
        if (is_admin()) {
            require_once WPMATCH_ADMIN_PATH . 'class-admin.php';
            require_once WPMATCH_ADMIN_PATH . 'class-profile-fields-list-table.php';
        }

        // Public classes
        if (!is_admin()) {
            require_once WPMATCH_PUBLIC_PATH . 'class-public.php';
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin lifecycle hooks
        register_activation_hook(WPMATCH_PLUGIN_FILE, array('WPMatch_Activator', 'activate'));
        register_deactivation_hook(WPMATCH_PLUGIN_FILE, array('WPMatch_Deactivator', 'deactivate'));

        // WordPress hooks
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->database = new WPMatch_Database();
        $this->security = new WPMatch_Security();
        $this->user_manager = new WPMatch_User_Manager();
        $this->profile_manager = new WPMatch_Profile_Manager();
        $this->messaging_manager = new WPMatch_Messaging_Manager();
        $this->interaction_manager = new WPMatch_Interaction_Manager();

        if (is_admin()) {
            $this->admin = new WPMatch_Admin();
        }

        if (!is_admin()) {
            $this->public = new WPMatch_Public();
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WordPress and plugin requirements are met
        if (!$this->check_requirements()) {
            return;
        }

        // Initialize components
        do_action('wp_dating_before_init');
        
        // Setup custom post types and taxonomies
        $this->register_post_types();
        
        // Setup rewrite rules
        $this->setup_rewrite_rules();
        
        do_action('wp_dating_after_init');
    }

    /**
     * Check plugin requirements
     *
     * @return bool
     */
    private function check_requirements() {
        global $wp_version;

        // Check WordPress version
        if (version_compare($wp_version, '5.9', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('WP Dating Plugin requires WordPress 5.9 or higher.', 'wpmatch');
                echo '</p></div>';
            });
            return false;
        }

        return true;
    }

    /**
     * Register custom post types
     */
    private function register_post_types() {
        // Register success stories post type
        register_post_type('dating_story', array(
            'labels' => array(
                'name' => __('Success Stories', 'wpmatch'),
                'singular_name' => __('Success Story', 'wpmatch'),
                'add_new' => __('Add New Story', 'wpmatch'),
                'add_new_item' => __('Add New Success Story', 'wpmatch'),
                'edit_item' => __('Edit Success Story', 'wpmatch'),
                'new_item' => __('New Success Story', 'wpmatch'),
                'view_item' => __('View Success Story', 'wpmatch'),
                'search_items' => __('Search Success Stories', 'wpmatch'),
                'not_found' => __('No success stories found', 'wpmatch'),
                'not_found_in_trash' => __('No success stories found in trash', 'wpmatch'),
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'success-stories'),
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'menu_icon' => 'dashicons-heart',
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'edit_dating_stories',
                'edit_posts' => 'edit_dating_stories',
                'edit_others_posts' => 'edit_others_dating_stories',
                'publish_posts' => 'publish_dating_stories',
                'read_private_posts' => 'read_private_dating_stories',
                'delete_posts' => 'delete_dating_stories',
                'delete_private_posts' => 'delete_private_dating_stories',
                'delete_published_posts' => 'delete_published_dating_stories',
                'delete_others_posts' => 'delete_others_dating_stories',
                'edit_private_posts' => 'edit_private_dating_stories',
                'edit_published_posts' => 'edit_published_dating_stories',
            ),
        ));
    }

    /**
     * Setup rewrite rules
     */
    private function setup_rewrite_rules() {
        // Profile pages
        add_rewrite_rule(
            '^profile/([^/]+)/?$',
            'index.php?wp_dating_profile=$matches[1]',
            'top'
        );

        // Search pages
        add_rewrite_rule(
            '^search/?$',
            'index.php?wp_dating_search=1',
            'top'
        );

        // Messages pages
        add_rewrite_rule(
            '^messages/?$',
            'index.php?wp_dating_messages=1',
            'top'
        );

        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'wp_dating_profile';
            $vars[] = 'wp_dating_search';
            $vars[] = 'wp_dating_messages';
            return $vars;
        });
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wpmatch',
            false,
            dirname(WPMATCH_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_public_scripts() {
        if (!is_admin()) {
            wp_enqueue_style(
                'wpmatch-public',
                WPMATCH_PLUGIN_ASSETS_URL . 'css/public.css',
                array(),
                WPMATCH_VERSION
            );

            wp_enqueue_script(
                'wpmatch-public',
                WPMATCH_PLUGIN_ASSETS_URL . 'js/public.js',
                array('jquery'),
                WPMATCH_VERSION,
                true
            );

            // Localize script
            wp_localize_script('wpmatch-public', 'wpDating', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_dating_nonce'),
                'strings' => array(
                    'loading' => __('Loading...', 'wpmatch'),
                    'error' => __('An error occurred. Please try again.', 'wpmatch'),
                ),
            ));
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'wpmatch') === false) {
            return;
        }

        wp_enqueue_style(
            'wpmatch-admin',
            WPMATCH_PLUGIN_ASSETS_URL . 'css/admin.css',
            array(),
            WPMATCH_VERSION
        );

        wp_enqueue_script(
            'wpmatch-admin',
            WPMATCH_PLUGIN_ASSETS_URL . 'js/admin.js',
            array('jquery', 'wp-util'),
            WPMATCH_VERSION,
            true
        );

        // Localize script
        wp_localize_script('wpmatch-admin', 'wpDatingAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_dating_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'wpmatch'),
                'saved' => __('Settings saved successfully.', 'wpmatch'),
            ),
        ));
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return WPMATCH_VERSION;
    }

    /**
     * Get plugin path
     *
     * @return string
     */
    public function get_plugin_path() {
        return WPMATCH_PLUGIN_PATH;
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public function get_plugin_url() {
        return WPMATCH_PLUGIN_URL;
    }
}

/**
 * Initialize the plugin
 */
function wpmatch_plugin() {
    return WPMatch_Plugin::get_instance();
}

// Initialize plugin
wpmatch_plugin();