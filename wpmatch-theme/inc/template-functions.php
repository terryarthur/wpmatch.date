<?php
/**
 * Template functions for WPMatch Dating Theme
 *
 * @package WPMatch_Theme
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the WPMatch plugin instance
 *
 * @return object|false
 */
function wpmatch_theme_get_plugin() {
    if (function_exists('wpmatch_plugin')) {
        return wpmatch_plugin();
    }
    return false;
}

/**
 * Check if WPMatch plugin is active and available
 *
 * @return bool
 */
function wpmatch_theme_plugin_active() {
    return function_exists('wpmatch_plugin');
}

/**
 * Render profile card for search results
 *
 * @param object $profile Profile data
 * @param array $args Additional arguments
 */
function wpmatch_theme_render_profile_card($profile, $args = array()) {
    if (!$profile) {
        return;
    }

    $defaults = array(
        'show_actions' => true,
        'show_distance' => true,
        'card_class' => '',
    );
    $args = wp_parse_args($args, $defaults);

    $primary_photo = '';
    if (isset($profile->primary_photo) && $profile->primary_photo) {
        $primary_photo = wp_get_attachment_image_url($profile->primary_photo->attachment_id, 'wpmatch-profile-card');
    }

    $profile_url = home_url('/profile/' . $profile->user_login);
    $age = isset($profile->age) ? $profile->age : '';
    $location = isset($profile->location) ? $profile->location : '';
    $distance = isset($profile->distance) ? round($profile->distance, 1) . ' km' : '';
    ?>
    <div class="wpmatch-profile-card <?php echo esc_attr($args['card_class']); ?>" data-profile-url="<?php echo esc_url($profile_url); ?>" data-user-id="<?php echo esc_attr($profile->user_id); ?>">
        <div class="profile-image">
            <?php if ($primary_photo): ?>
                <img src="<?php echo esc_url($primary_photo); ?>" alt="<?php echo esc_attr($profile->display_name); ?>" loading="lazy">
            <?php else: ?>
                <div class="no-photo">
                    <span class="placeholder-icon">👤</span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($profile->is_online) && $profile->is_online): ?>
                <span class="online-indicator" title="<?php _e('Online now', 'wpmatch-theme'); ?>"></span>
            <?php endif; ?>
        </div>

        <div class="profile-info">
            <h3 class="profile-name">
                <a href="<?php echo esc_url($profile_url); ?>">
                    <?php echo esc_html($profile->display_name); ?>
                </a>
            </h3>

            <div class="profile-details">
                <?php if ($age): ?>
                    <span class="age"><?php echo esc_html($age); ?></span>
                <?php endif; ?>
                
                <?php if ($location): ?>
                    <span class="location"><?php echo esc_html($location); ?></span>
                <?php endif; ?>
                
                <?php if ($args['show_distance'] && $distance): ?>
                    <span class="distance"><?php echo esc_html($distance); ?></span>
                <?php endif; ?>
            </div>

            <?php if (isset($profile->basic_info) && !empty($profile->basic_info)): ?>
                <div class="profile-interests">
                    <?php foreach (array_slice($profile->basic_info, 0, 3) as $field => $info): ?>
                        <span class="interest-tag"><?php echo esc_html($info['value']); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($args['show_actions'] && is_user_logged_in()): ?>
                <div class="profile-actions">
                    <button class="profile-like-btn" data-user-id="<?php echo esc_attr($profile->user_id); ?>" title="<?php _e('Like', 'wpmatch-theme'); ?>">
                        <span class="icon">🤍</span>
                    </button>
                    
                    <a href="<?php echo esc_url($profile_url); ?>" class="profile-view-btn" data-user-id="<?php echo esc_attr($profile->user_id); ?>">
                        <?php _e('View Profile', 'wpmatch-theme'); ?>
                    </a>
                    
                    <a href="<?php echo esc_url(home_url('/messages/?user=' . $profile->user_id)); ?>" class="profile-message-btn">
                        <?php _e('Message', 'wpmatch-theme'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render search filters
 *
 * @param array $current_filters Current filter values
 */
function wpmatch_theme_render_search_filters($current_filters = array()) {
    $defaults = array(
        'age_min' => 18,
        'age_max' => 99,
        'gender' => '',
        'looking_for' => '',
        'distance' => '',
        'location' => '',
        'online_only' => false,
        'has_photo' => false,
    );
    $filters = wp_parse_args($current_filters, $defaults);
    ?>
    <form class="wpmatch-search-form">
        <div class="filter-row">
            <div class="filter-group">
                <label><?php _e('Age Range', 'wpmatch-theme'); ?></label>
                <div class="age-range">
                    <select name="age_min">
                        <?php for ($age = 18; $age <= 80; $age += 2): ?>
                            <option value="<?php echo $age; ?>" <?php selected($filters['age_min'], $age); ?>>
                                <?php echo $age; ?>+
                            </option>
                        <?php endfor; ?>
                    </select>
                    <span><?php _e('to', 'wpmatch-theme'); ?></span>
                    <select name="age_max">
                        <?php for ($age = 20; $age <= 99; $age += 2): ?>
                            <option value="<?php echo $age; ?>" <?php selected($filters['age_max'], $age); ?>>
                                <?php echo $age; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="filter-group">
                <label><?php _e('Gender', 'wpmatch-theme'); ?></label>
                <select name="gender">
                    <option value=""><?php _e('Any', 'wpmatch-theme'); ?></option>
                    <option value="male" <?php selected($filters['gender'], 'male'); ?>><?php _e('Male', 'wpmatch-theme'); ?></option>
                    <option value="female" <?php selected($filters['gender'], 'female'); ?>><?php _e('Female', 'wpmatch-theme'); ?></option>
                    <option value="other" <?php selected($filters['gender'], 'other'); ?>><?php _e('Other', 'wpmatch-theme'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label><?php _e('Looking For', 'wpmatch-theme'); ?></label>
                <select name="looking_for">
                    <option value=""><?php _e('Any', 'wpmatch-theme'); ?></option>
                    <option value="male" <?php selected($filters['looking_for'], 'male'); ?>><?php _e('Male', 'wpmatch-theme'); ?></option>
                    <option value="female" <?php selected($filters['looking_for'], 'female'); ?>><?php _e('Female', 'wpmatch-theme'); ?></option>
                    <option value="other" <?php selected($filters['looking_for'], 'other'); ?>><?php _e('Other', 'wpmatch-theme'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <label><?php _e('Distance', 'wpmatch-theme'); ?></label>
                <select name="distance">
                    <option value=""><?php _e('Any distance', 'wpmatch-theme'); ?></option>
                    <option value="5" <?php selected($filters['distance'], '5'); ?>><?php _e('Within 5 km', 'wpmatch-theme'); ?></option>
                    <option value="10" <?php selected($filters['distance'], '10'); ?>><?php _e('Within 10 km', 'wpmatch-theme'); ?></option>
                    <option value="25" <?php selected($filters['distance'], '25'); ?>><?php _e('Within 25 km', 'wpmatch-theme'); ?></option>
                    <option value="50" <?php selected($filters['distance'], '50'); ?>><?php _e('Within 50 km', 'wpmatch-theme'); ?></option>
                    <option value="100" <?php selected($filters['distance'], '100'); ?>><?php _e('Within 100 km', 'wpmatch-theme'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <button type="submit" class="button button-primary">
                    <?php _e('Search', 'wpmatch-theme'); ?>
                </button>
            </div>
        </div>

        <div class="filter-row">
            <div class="filter-group">
                <label>
                    <input type="checkbox" name="online_only" value="1" <?php checked($filters['online_only']); ?>>
                    <?php _e('Online now', 'wpmatch-theme'); ?>
                </label>
            </div>

            <div class="filter-group">
                <label>
                    <input type="checkbox" name="has_photo" value="1" <?php checked($filters['has_photo']); ?>>
                    <?php _e('Has photo', 'wpmatch-theme'); ?>
                </label>
            </div>
        </div>
    </form>
    <?php
}

/**
 * Render message thread
 *
 * @param array $messages Array of message objects
 * @param int $current_user_id Current user ID
 */
function wpmatch_theme_render_message_thread($messages, $current_user_id) {
    if (empty($messages)) {
        echo '<p class="no-messages">' . __('No messages yet. Start the conversation!', 'wpmatch-theme') . '</p>';
        return;
    }

    foreach ($messages as $message) {
        $is_sender = ($message->sender_id == $current_user_id);
        $sender_class = $is_sender ? 'sent' : 'received';
        $sender_name = $is_sender ? __('You', 'wpmatch-theme') : esc_html($message->sender_name);
        ?>
        <div class="message <?php echo esc_attr($sender_class); ?>" data-message-id="<?php echo esc_attr($message->id); ?>">
            <div class="message-content">
                <div class="message-text"><?php echo wp_kses_post(nl2br($message->message_content)); ?></div>
                <div class="message-meta">
                    <span class="sender"><?php echo $sender_name; ?></span>
                    <span class="time"><?php echo esc_html(human_time_diff(strtotime($message->created_at), current_time('timestamp')) . ' ago'); ?></span>
                    <?php if ($is_sender && $message->is_read): ?>
                        <span class="read-status">✓</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

/**
 * Get user's dating profile completion percentage
 *
 * @param int $user_id User ID
 * @return int Completion percentage
 */
function wpmatch_theme_get_profile_completion($user_id) {
    if (!wpmatch_theme_plugin_active()) {
        return 0;
    }

    $plugin = wpmatch_theme_get_plugin();
    if (!$plugin || !$plugin->profile_manager) {
        return 0;
    }

    $profile = $plugin->profile_manager->get_profile($user_id);
    if (!$profile) {
        return 0;
    }

    $total_fields = 10; // Total number of profile fields
    $completed_fields = 0;

    // Check basic fields
    if (!empty($profile->display_name)) $completed_fields++;
    if (!empty($profile->age)) $completed_fields++;
    if (!empty($profile->gender)) $completed_fields++;
    if (!empty($profile->location)) $completed_fields++;
    if (!empty($profile->about_me)) $completed_fields++;
    if (!empty($profile->interests)) $completed_fields++;

    // Check if user has photos
    if (isset($profile->photos) && !empty($profile->photos)) {
        $completed_fields += 2; // Weight photos more
    }

    // Check custom fields
    if (isset($profile->custom_fields) && !empty($profile->custom_fields)) {
        $completed_fields += 2; // Weight custom fields
    }

    return min(100, round(($completed_fields / $total_fields) * 100));
}

/**
 * Display profile completion widget
 *
 * @param int $user_id User ID
 */
function wpmatch_theme_profile_completion_widget($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return;
    }

    $completion = wpmatch_theme_get_profile_completion($user_id);
    $completion_class = '';
    
    if ($completion >= 80) {
        $completion_class = 'high';
    } elseif ($completion >= 50) {
        $completion_class = 'medium';
    } else {
        $completion_class = 'low';
    }
    ?>
    <div class="profile-completion-widget <?php echo esc_attr($completion_class); ?>">
        <div class="completion-header">
            <h4><?php _e('Profile Completion', 'wpmatch-theme'); ?></h4>
            <span class="completion-percentage"><?php echo esc_html($completion); ?>%</span>
        </div>
        
        <div class="completion-bar">
            <div class="completion-fill" style="width: <?php echo esc_attr($completion); ?>%;"></div>
        </div>
        
        <?php if ($completion < 100): ?>
            <div class="completion-tips">
                <p><?php _e('Complete your profile to get more matches!', 'wpmatch-theme'); ?></p>
                <a href="<?php echo esc_url(home_url('/profile/')); ?>" class="button button-small">
                    <?php _e('Complete Profile', 'wpmatch-theme'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Get formatted user age from birthdate
 *
 * @param string $birthdate Birthdate in Y-m-d format
 * @return int Age in years
 */
function wpmatch_theme_get_age_from_birthdate($birthdate) {
    if (empty($birthdate)) {
        return 0;
    }
    
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    return $birth->diff($today)->y;
}

/**
 * Format distance for display
 *
 * @param float $distance Distance in kilometers
 * @param string $unit Unit (km or miles)
 * @return string Formatted distance
 */
function wpmatch_theme_format_distance($distance, $unit = 'km') {
    if ($distance < 1) {
        return __('Less than 1 km away', 'wpmatch-theme');
    } elseif ($distance < 10) {
        return sprintf(__('%.1f %s away', 'wpmatch-theme'), $distance, $unit);
    } else {
        return sprintf(__('%d %s away', 'wpmatch-theme'), round($distance), $unit);
    }
}

/**
 * Get user's online status
 *
 * @param int $user_id User ID
 * @return bool True if user is online
 */
function wpmatch_theme_is_user_online($user_id) {
    if (!wpmatch_theme_plugin_active()) {
        return false;
    }

    $last_active = get_user_meta($user_id, 'wpmatch_last_active', true);
    if (!$last_active) {
        return false;
    }

    $online_threshold = 15 * MINUTE_IN_SECONDS; // 15 minutes
    return (current_time('timestamp') - strtotime($last_active)) <= $online_threshold;
}

/**
 * Render breadcrumb navigation
 *
 * @param array $breadcrumbs Array of breadcrumb items
 */
function wpmatch_theme_render_breadcrumbs($breadcrumbs) {
    if (empty($breadcrumbs)) {
        return;
    }
    ?>
    <nav class="wpmatch-breadcrumbs" aria-label="<?php _e('Breadcrumb navigation', 'wpmatch-theme'); ?>">
        <ol class="breadcrumb-list">
            <?php foreach ($breadcrumbs as $index => $item): ?>
                <li class="breadcrumb-item">
                    <?php if (isset($item['url']) && $item['url']): ?>
                        <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a>
                    <?php else: ?>
                        <span><?php echo esc_html($item['title']); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($index < count($breadcrumbs) - 1): ?>
                        <span class="breadcrumb-separator">/</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php
}

/**
 * Get dating-specific page title
 *
 * @return string Page title
 */
function wpmatch_theme_get_page_title() {
    if (get_query_var('wpmatch_profile')) {
        $username = get_query_var('wpmatch_profile');
        $user = get_user_by('login', $username);
        if ($user) {
            return sprintf(__('%s\'s Profile', 'wpmatch-theme'), $user->display_name);
        }
        return __('Profile', 'wpmatch-theme');
    }

    if (get_query_var('wpmatch_search')) {
        return __('Browse Profiles', 'wpmatch-theme');
    }

    if (get_query_var('wpmatch_messages')) {
        return __('Messages', 'wpmatch-theme');
    }

    return get_the_title();
}

/**
 * Enqueue dating-specific scripts and styles for specific pages
 */
function wpmatch_theme_conditional_scripts() {
    // Enqueue map scripts for profile pages with location
    if (get_query_var('wpmatch_profile') || get_query_var('wpmatch_search')) {
        wp_enqueue_script('wpmatch-maps', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', array(), '1.7.1', true);
        wp_enqueue_style('wpmatch-maps', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css', array(), '1.7.1');
    }

    // Enqueue real-time messaging scripts
    if (get_query_var('wpmatch_messages')) {
        wp_enqueue_script('wpmatch-realtime', WPMATCH_THEME_URL . '/assets/js/realtime.js', array('jquery'), WPMATCH_THEME_VERSION, true);
    }
}
add_action('wp_enqueue_scripts', 'wpmatch_theme_conditional_scripts');