<?php
/**
 * WPMatch Dating Theme functions and definitions
 *
 * @package WPMatch_Theme
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define theme constants
define('WPMATCH_THEME_VERSION', '1.0.0');
define('WPMATCH_THEME_PATH', get_template_directory());
define('WPMATCH_THEME_URL', get_template_directory_uri());

/**
 * Theme setup
 */
function wpmatch_theme_setup() {
    // Make theme available for translation
    load_theme_textdomain('wpmatch-theme', WPMATCH_THEME_PATH . '/languages');

    // Add theme support
    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('wp-block-styles');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');

    // Add support for block editor
    add_theme_support('editor-color-palette', array(
        array(
            'name' => __('Primary', 'wpmatch-theme'),
            'slug' => 'primary',
            'color' => '#e91e63',
        ),
        array(
            'name' => __('Secondary', 'wpmatch-theme'),
            'slug' => 'secondary',
            'color' => '#ff5722',
        ),
        array(
            'name' => __('Dark', 'wpmatch-theme'),
            'slug' => 'dark',
            'color' => '#212121',
        ),
        array(
            'name' => __('Light Gray', 'wpmatch-theme'),
            'slug' => 'light-gray',
            'color' => '#bdbdbd',
        ),
        array(
            'name' => __('White', 'wpmatch-theme'),
            'slug' => 'white',
            'color' => '#ffffff',
        ),
    ));

    // Add image sizes for dating profiles
    add_image_size('wpmatch-profile-card', 300, 400, true);
    add_image_size('wpmatch-profile-thumb', 80, 80, true);
    add_image_size('wpmatch-hero-banner', 1200, 600, true);

    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Navigation', 'wpmatch-theme'),
        'footer' => __('Footer Navigation', 'wpmatch-theme'),
        'dating' => __('Dating Navigation', 'wpmatch-theme'),
    ));
}
add_action('after_setup_theme', 'wpmatch_theme_setup');

/**
 * Enqueue theme styles and scripts
 */
function wpmatch_theme_scripts() {
    // Enqueue theme styles
    wp_enqueue_style(
        'wpmatch-theme-style',
        WPMATCH_THEME_URL . '/style.css',
        array(),
        WPMATCH_THEME_VERSION
    );

    // Enqueue dating-specific styles
    wp_enqueue_style(
        'wpmatch-theme-dating',
        WPMATCH_THEME_URL . '/assets/css/dating.css',
        array('wpmatch-theme-style'),
        WPMATCH_THEME_VERSION
    );

    // Enqueue theme scripts
    wp_enqueue_script(
        'wpmatch-theme-script',
        WPMATCH_THEME_URL . '/assets/js/theme.js',
        array('jquery'),
        WPMATCH_THEME_VERSION,
        true
    );

    // Localize script for AJAX
    wp_localize_script('wpmatch-theme-script', 'wpmatchTheme', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpmatch_theme_nonce'),
        'strings' => array(
            'loading' => __('Loading...', 'wpmatch-theme'),
            'error' => __('An error occurred. Please try again.', 'wpmatch-theme'),
            'loadMore' => __('Load More', 'wpmatch-theme'),
        ),
    ));

    // Add inline styles for dynamic theming
    $custom_css = wpmatch_theme_get_custom_css();
    if ($custom_css) {
        wp_add_inline_style('wpmatch-theme-style', $custom_css);
    }
}
add_action('wp_enqueue_scripts', 'wpmatch_theme_scripts');

/**
 * Enqueue block editor assets
 */
function wpmatch_theme_block_editor_assets() {
    wp_enqueue_style(
        'wpmatch-theme-editor-styles',
        WPMATCH_THEME_URL . '/assets/css/editor-styles.css',
        array(),
        WPMATCH_THEME_VERSION
    );
}
add_action('enqueue_block_editor_assets', 'wpmatch_theme_block_editor_assets');

/**
 * Register widget areas
 */
function wpmatch_theme_widgets_init() {
    register_sidebar(array(
        'name' => __('Dating Sidebar', 'wpmatch-theme'),
        'id' => 'dating-sidebar',
        'description' => __('Add widgets for dating pages.', 'wpmatch-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ));

    register_sidebar(array(
        'name' => __('Profile Sidebar', 'wpmatch-theme'),
        'id' => 'profile-sidebar',
        'description' => __('Add widgets for profile pages.', 'wpmatch-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ));
}
add_action('widgets_init', 'wpmatch_theme_widgets_init');

/**
 * Add custom classes to body
 */
function wpmatch_theme_body_classes($classes) {
    // Add class if WPMatch plugin is active
    if (function_exists('wpmatch_plugin')) {
        $classes[] = 'wpmatch-active';
    }

    // Add class for dating pages
    if (get_query_var('wpmatch_profile') || 
        get_query_var('wpmatch_search') || 
        get_query_var('wpmatch_messages')) {
        $classes[] = 'wpmatch-page';
    }

    // Add class for logged-in users
    if (is_user_logged_in()) {
        $classes[] = 'user-logged-in';
    }

    return $classes;
}
add_filter('body_class', 'wpmatch_theme_body_classes');

/**
 * Template hierarchy for WPMatch pages
 */
function wpmatch_theme_template_include($template) {
    // Handle dating profile pages
    if (get_query_var('wpmatch_profile')) {
        $dating_template = locate_template('templates/dating-profile.html');
        if ($dating_template) {
            return $dating_template;
        }
    }

    // Handle dating search pages
    if (get_query_var('wpmatch_search')) {
        $search_template = locate_template('templates/dating-search.html');
        if ($search_template) {
            return $search_template;
        }
    }

    // Handle dating messages pages
    if (get_query_var('wpmatch_messages')) {
        $messages_template = locate_template('templates/dating-messages.html');
        if ($messages_template) {
            return $messages_template;
        }
    }

    return $template;
}
add_filter('template_include', 'wpmatch_theme_template_include');

/**
 * Customizer additions
 */
function wpmatch_theme_customize_register($wp_customize) {
    // Dating Theme Options Section
    $wp_customize->add_section('wpmatch_theme_options', array(
        'title' => __('Dating Theme Options', 'wpmatch-theme'),
        'priority' => 30,
    ));

    // Primary color setting
    $wp_customize->add_setting('wpmatch_primary_color', array(
        'default' => '#e91e63',
        'sanitize_callback' => 'sanitize_hex_color',
    ));

    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'wpmatch_primary_color', array(
        'label' => __('Primary Color', 'wpmatch-theme'),
        'section' => 'wpmatch_theme_options',
        'settings' => 'wpmatch_primary_color',
    )));

    // Secondary color setting
    $wp_customize->add_setting('wpmatch_secondary_color', array(
        'default' => '#ff5722',
        'sanitize_callback' => 'sanitize_hex_color',
    ));

    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'wpmatch_secondary_color', array(
        'label' => __('Secondary Color', 'wpmatch-theme'),
        'section' => 'wpmatch_theme_options',
        'settings' => 'wpmatch_secondary_color',
    )));

    // Hero background image
    $wp_customize->add_setting('wpmatch_hero_background', array(
        'default' => '',
        'sanitize_callback' => 'esc_url_raw',
    ));

    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'wpmatch_hero_background', array(
        'label' => __('Hero Background Image', 'wpmatch-theme'),
        'section' => 'wpmatch_theme_options',
        'settings' => 'wpmatch_hero_background',
    )));

    // Enable dating features
    $wp_customize->add_setting('wpmatch_enable_dating_features', array(
        'default' => true,
        'sanitize_callback' => 'wp_validate_boolean',
    ));

    $wp_customize->add_control('wpmatch_enable_dating_features', array(
        'label' => __('Enable Dating Features', 'wpmatch-theme'),
        'description' => __('Show dating-specific UI elements and layouts.', 'wpmatch-theme'),
        'section' => 'wpmatch_theme_options',
        'type' => 'checkbox',
    ));
}
add_action('customize_register', 'wpmatch_theme_customize_register');

/**
 * Generate custom CSS based on customizer settings
 */
function wpmatch_theme_get_custom_css() {
    $primary_color = get_theme_mod('wpmatch_primary_color', '#e91e63');
    $secondary_color = get_theme_mod('wpmatch_secondary_color', '#ff5722');
    $hero_background = get_theme_mod('wpmatch_hero_background', '');

    $css = '';

    if ($primary_color !== '#e91e63') {
        $css .= ':root { --wp--preset--color--primary: ' . esc_attr($primary_color) . '; }';
    }

    if ($secondary_color !== '#ff5722') {
        $css .= ':root { --wp--preset--color--secondary: ' . esc_attr($secondary_color) . '; }';
    }

    if ($hero_background) {
        $css .= '.wp-block-cover.is-style-hero-banner { background-image: url(' . esc_url($hero_background) . '); }';
    }

    return $css;
}

/**
 * Add custom block styles
 */
function wpmatch_theme_register_block_styles() {
    // Button styles
    register_block_style('core/button', array(
        'name' => 'dating-primary',
        'label' => __('Dating Primary', 'wpmatch-theme'),
    ));

    register_block_style('core/button', array(
        'name' => 'dating-outline',
        'label' => __('Dating Outline', 'wpmatch-theme'),
    ));

    // Cover block styles
    register_block_style('core/cover', array(
        'name' => 'hero-banner',
        'label' => __('Hero Banner', 'wpmatch-theme'),
    ));

    // Group block styles
    register_block_style('core/group', array(
        'name' => 'profile-card',
        'label' => __('Profile Card', 'wpmatch-theme'),
    ));

    register_block_style('core/group', array(
        'name' => 'dating-section',
        'label' => __('Dating Section', 'wpmatch-theme'),
    ));
}
add_action('init', 'wpmatch_theme_register_block_styles');

/**
 * Register custom block patterns
 */
function wpmatch_theme_register_block_patterns() {
    if (function_exists('register_block_pattern_category')) {
        register_block_pattern_category('wpmatch', array(
            'label' => __('Dating', 'wpmatch-theme'),
        ));
    }
}
add_action('init', 'wpmatch_theme_register_block_patterns');

/**
 * Add theme support for custom logo
 */
function wpmatch_theme_custom_logo_setup() {
    $defaults = array(
        'height' => 60,
        'width' => 240,
        'flex-height' => true,
        'flex-width' => true,
        'header-text' => array('site-title', 'site-description'),
        'unlink-homepage-logo' => true,
    );

    add_theme_support('custom-logo', $defaults);
}
add_action('after_setup_theme', 'wpmatch_theme_custom_logo_setup');

/**
 * Include additional theme files
 */
require_once WPMATCH_THEME_PATH . '/inc/template-functions.php';
require_once WPMATCH_THEME_PATH . '/inc/block-patterns.php';

// Load custom blocks if they exist
if (file_exists(WPMATCH_THEME_PATH . '/blocks/blocks.php')) {
    require_once WPMATCH_THEME_PATH . '/blocks/blocks.php';
}