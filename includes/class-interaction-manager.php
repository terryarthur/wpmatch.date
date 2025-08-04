<?php
/**
 * User interaction management class (likes, matches, views, etc.)
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Interaction Manager class
 */
class WPMatch_Interaction_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_wpmatch_like_profile', array($this, 'ajax_like_profile'));
        add_action('wp_ajax_wpmatch_unlike_profile', array($this, 'ajax_unlike_profile'));
        add_action('wp_ajax_wpmatch_view_profile', array($this, 'ajax_view_profile'));
        add_action('wp_ajax_wpmatch_get_matches', array($this, 'ajax_get_matches'));
        add_action('wp_ajax_wpmatch_report_user', array($this, 'ajax_report_user'));
    }

    /**
     * Initialize interaction system
     */
    public function init() {
        // Hook into profile viewing
        add_action('wpmatch_profile_viewed', array($this, 'record_profile_view'), 10, 2);
    }

    /**
     * Record a user interaction
     *
     * @param int $user_id
     * @param int $target_user_id
     * @param string $interaction_type
     * @param string $interaction_value
     * @return bool|WP_Error
     */
    public function record_interaction($user_id, $target_user_id, $interaction_type, $interaction_value = '') {
        // Validate users
        if ($user_id === $target_user_id) {
            return new WP_Error('invalid_interaction', __('Cannot interact with yourself.', 'wpmatch'));
        }

        // Check if users are blocked
        if ($this->are_users_blocked($user_id, $target_user_id)) {
            return new WP_Error('users_blocked', __('Cannot interact with this user.', 'wpmatch'));
        }

        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');

        // Check for rate limiting on certain interactions
        if (in_array($interaction_type, array('like', 'view'))) {
            if (!WPMatch_Security::check_rate_limit($interaction_type, $user_id, 100, 3600)) {
                return new WP_Error('rate_limited', __('You are performing this action too frequently.', 'wpmatch'));
            }
        }

        // Insert or update interaction
        $interaction_data = array(
            'user_id' => $user_id,
            'target_user_id' => $target_user_id,
            'interaction_type' => $interaction_type,
            'interaction_value' => WPMatch_Security::sanitize_input($interaction_value, 'text'),
            'created_at' => current_time('mysql')
        );

        // Use REPLACE to handle duplicate key updates
        $result = $wpdb->replace($interactions_table, $interaction_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to record interaction.', 'wpmatch'));
        }

        // Handle special interaction types
        $this->handle_special_interactions($user_id, $target_user_id, $interaction_type);

        do_action('wpmatch_interaction_recorded', $user_id, $target_user_id, $interaction_type, $interaction_value);

        return true;
    }

    /**
     * Handle special interactions like matches
     *
     * @param int $user_id
     * @param int $target_user_id
     * @param string $interaction_type
     */
    private function handle_special_interactions($user_id, $target_user_id, $interaction_type) {
        if ($interaction_type === 'like') {
            // Check if target user also liked this user (mutual like = match)
            if ($this->has_user_liked($target_user_id, $user_id)) {
                $this->create_match($user_id, $target_user_id);
            }
        }
    }

    /**
     * Create a match between two users
     *
     * @param int $user1_id
     * @param int $user2_id
     */
    private function create_match($user1_id, $user2_id) {
        // Record mutual match interaction
        $this->record_match_interaction($user1_id, $user2_id);
        $this->record_match_interaction($user2_id, $user1_id);

        // Send notifications
        do_action('wpmatch_match_created', $user1_id, $user2_id);
    }

    /**
     * Record match interaction
     *
     * @param int $user_id
     * @param int $matched_user_id
     */
    private function record_match_interaction($user_id, $matched_user_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');

        $wpdb->replace($interactions_table, array(
            'user_id' => $user_id,
            'target_user_id' => $matched_user_id,
            'interaction_type' => 'match',
            'interaction_value' => 'mutual',
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Check if user has liked another user
     *
     * @param int $user_id
     * @param int $target_user_id
     * @return bool
     */
    public function has_user_liked($user_id, $target_user_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$interactions_table} 
             WHERE user_id = %d AND target_user_id = %d AND interaction_type = 'like'",
            $user_id, $target_user_id
        ));

        return $count > 0;
    }

    /**
     * Remove a like (unlike)
     *
     * @param int $user_id
     * @param int $target_user_id
     * @return bool|WP_Error
     */
    public function remove_like($user_id, $target_user_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');

        // Remove like interaction
        $result = $wpdb->delete(
            $interactions_table,
            array(
                'user_id' => $user_id,
                'target_user_id' => $target_user_id,
                'interaction_type' => 'like'
            )
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to remove like.', 'wpmatch'));
        }

        // Remove match if it existed
        $this->remove_match($user_id, $target_user_id);

        do_action('wpmatch_like_removed', $user_id, $target_user_id);

        return true;
    }

    /**
     * Remove match between users
     *
     * @param int $user1_id
     * @param int $user2_id
     */
    private function remove_match($user1_id, $user2_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');

        // Remove match interactions for both users
        $wpdb->delete(
            $interactions_table,
            array(
                'user_id' => $user1_id,
                'target_user_id' => $user2_id,
                'interaction_type' => 'match'
            )
        );

        $wpdb->delete(
            $interactions_table,
            array(
                'user_id' => $user2_id,
                'target_user_id' => $user1_id,
                'interaction_type' => 'match'
            )
        );
    }

    /**
     * Record profile view
     *
     * @param int $viewer_id
     * @param int $profile_user_id
     */
    public function record_profile_view($viewer_id, $profile_user_id) {
        if ($viewer_id && $viewer_id !== $profile_user_id) {
            $this->record_interaction($viewer_id, $profile_user_id, 'view');
            $this->update_profile_view_count($profile_user_id);
        }
    }

    /**
     * Update profile view count
     *
     * @param int $user_id
     */
    private function update_profile_view_count($user_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $profiles_table = $database->get_table_name('profiles');

        $wpdb->query($wpdb->prepare(
            "UPDATE {$profiles_table} SET profile_views = profile_views + 1 WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get user matches
     *
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_user_matches($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');
        $profiles_table = $database->get_table_name('profiles');

        $matches = $wpdb->get_results($wpdb->prepare(
            "SELECT i.target_user_id, i.created_at as match_date,
                    p.display_name, p.age, p.location, p.about_me,
                    u.user_email
             FROM {$interactions_table} i
             INNER JOIN {$profiles_table} p ON i.target_user_id = p.user_id
             INNER JOIN {$wpdb->users} u ON i.target_user_id = u.ID
             WHERE i.user_id = %d AND i.interaction_type = 'match'
             ORDER BY i.created_at DESC
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));

        // Add profile photos
        $profile_manager = wpmatch_plugin()->profile_manager;
        foreach ($matches as &$match) {
            $primary_photo = $profile_manager->get_primary_photo($match->target_user_id);
            $match->photo_url = $primary_photo ? $primary_photo->thumbnail : null;
        }

        return $matches;
    }

    /**
     * Get users who liked current user
     *
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_user_likes($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');
        $profiles_table = $database->get_table_name('profiles');

        $likes = $wpdb->get_results($wpdb->prepare(
            "SELECT i.user_id as liker_id, i.created_at as like_date,
                    p.display_name, p.age, p.location,
                    u.user_email
             FROM {$interactions_table} i
             INNER JOIN {$profiles_table} p ON i.user_id = p.user_id
             INNER JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE i.target_user_id = %d AND i.interaction_type = 'like'
             ORDER BY i.created_at DESC
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));

        // Add profile photos and mutual like status
        $profile_manager = wpmatch_plugin()->profile_manager;
        foreach ($likes as &$like) {
            $primary_photo = $profile_manager->get_primary_photo($like->liker_id);
            $like->photo_url = $primary_photo ? $primary_photo->thumbnail : null;
            $like->is_mutual = $this->has_user_liked($user_id, $like->liker_id);
        }

        return $likes;
    }

    /**
     * Get profile viewers
     *
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_profile_viewers($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');
        $profiles_table = $database->get_table_name('profiles');

        $viewers = $wpdb->get_results($wpdb->prepare(
            "SELECT i.user_id as viewer_id, MAX(i.created_at) as last_view,
                    COUNT(*) as view_count,
                    p.display_name, p.age, p.location,
                    u.user_email
             FROM {$interactions_table} i
             INNER JOIN {$profiles_table} p ON i.user_id = p.user_id
             INNER JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE i.target_user_id = %d AND i.interaction_type = 'view'
             GROUP BY i.user_id
             ORDER BY last_view DESC
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));

        // Add profile photos
        $profile_manager = wpmatch_plugin()->profile_manager;
        foreach ($viewers as &$viewer) {
            $primary_photo = $profile_manager->get_primary_photo($viewer->viewer_id);
            $viewer->photo_url = $primary_photo ? $primary_photo->thumbnail : null;
        }

        return $viewers;
    }

    /**
     * Report a user
     *
     * @param int $reporter_id
     * @param int $reported_user_id
     * @param string $report_type
     * @param string $report_reason
     * @param string $report_details
     * @return bool|WP_Error
     */
    public function report_user($reporter_id, $reported_user_id, $report_type, $report_reason, $report_details = '') {
        if (!WPMatch_Security::user_can('report_users', $reporter_id)) {
            return new WP_Error('permission_denied', __('You do not have permission to report users.', 'wpmatch'));
        }

        if ($reporter_id === $reported_user_id) {
            return new WP_Error('invalid_report', __('You cannot report yourself.', 'wpmatch'));
        }

        // Check for duplicate reports
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $reports_table = $database->get_table_name('reports');

        $existing_report = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reports_table} 
             WHERE reporter_id = %d AND reported_user_id = %d AND status = 'pending'",
            $reporter_id, $reported_user_id
        ));

        if ($existing_report > 0) {
            return new WP_Error('duplicate_report', __('You have already reported this user.', 'wpmatch'));
        }

        // Insert report
        $report_data = array(
            'reporter_id' => $reporter_id,
            'reported_user_id' => $reported_user_id,
            'report_type' => WPMatch_Security::sanitize_input($report_type, 'key'),
            'report_reason' => WPMatch_Security::sanitize_input($report_reason, 'text'),
            'report_details' => WPMatch_Security::sanitize_input($report_details, 'textarea'),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($reports_table, $report_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to submit report.', 'wpmatch'));
        }

        do_action('wpmatch_user_reported', $reporter_id, $reported_user_id, $report_type, $report_reason);

        return true;
    }

    /**
     * Check if users are blocked
     *
     * @param int $user1_id
     * @param int $user2_id
     * @return bool
     */
    private function are_users_blocked($user1_id, $user2_id) {
        $messaging_manager = wpmatch_plugin()->messaging_manager;
        return $messaging_manager && method_exists($messaging_manager, 'are_users_blocked') 
            ? $messaging_manager->are_users_blocked($user1_id, $user2_id) 
            : false;
    }

    /**
     * Get interaction statistics for a user
     *
     * @param int $user_id
     * @return array
     */
    public function get_user_interaction_stats($user_id) {
        global $wpdb;
        $database = wpmatch_plugin()->database;
        $interactions_table = $database->get_table_name('interactions');

        // Likes given and received
        $likes_given = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$interactions_table} 
             WHERE user_id = %d AND interaction_type = 'like'",
            $user_id
        ));

        $likes_received = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$interactions_table} 
             WHERE target_user_id = %d AND interaction_type = 'like'",
            $user_id
        ));

        // Matches
        $matches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$interactions_table} 
             WHERE user_id = %d AND interaction_type = 'match'",
            $user_id
        ));

        // Profile views received
        $profile_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$interactions_table} 
             WHERE target_user_id = %d AND interaction_type = 'view'",
            $user_id
        ));

        return array(
            'likes_given' => (int) $likes_given,
            'likes_received' => (int) $likes_received,
            'matches' => (int) $matches,
            'profile_views' => (int) $profile_views,
        );
    }

    /**
     * AJAX Handlers
     */

    /**
     * AJAX handler for liking profiles
     */
    public function ajax_like_profile() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to like profiles.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $target_user_id = absint($_POST['target_user_id'] ?? 0);

        if (!$target_user_id) {
            wp_send_json_error(__('Invalid user ID.', 'wpmatch'));
        }

        $result = $this->record_interaction($user_id, $target_user_id, 'like');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Check if it's a match
        $is_match = $this->has_user_liked($target_user_id, $user_id);

        wp_send_json_success(array(
            'message' => __('Profile liked successfully.', 'wpmatch'),
            'is_match' => $is_match
        ));
    }

    /**
     * AJAX handler for unliking profiles
     */
    public function ajax_unlike_profile() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $target_user_id = absint($_POST['target_user_id'] ?? 0);

        if (!$target_user_id) {
            wp_send_json_error(__('Invalid user ID.', 'wpmatch'));
        }

        $result = $this->remove_like($user_id, $target_user_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Like removed successfully.', 'wpmatch'));
    }

    /**
     * AJAX handler for recording profile views
     */
    public function ajax_view_profile() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $viewer_id = get_current_user_id();
        $profile_user_id = absint($_POST['profile_user_id'] ?? 0);

        if (!$profile_user_id) {
            wp_send_json_error(__('Invalid user ID.', 'wpmatch'));
        }

        $this->record_profile_view($viewer_id, $profile_user_id);

        wp_send_json_success(__('Profile view recorded.', 'wpmatch'));
    }

    /**
     * AJAX handler for getting matches
     */
    public function ajax_get_matches() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'wpmatch'));
        }

        $user_id = get_current_user_id();
        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $matches = $this->get_user_matches($user_id, $per_page, $offset);

        wp_send_json_success($matches);
    }

    /**
     * AJAX handler for reporting users
     */
    public function ajax_report_user() {
        check_ajax_referer('wpmatch_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to report users.', 'wpmatch'));
        }

        $reporter_id = get_current_user_id();
        $reported_user_id = absint($_POST['reported_user_id'] ?? 0);
        $report_type = $_POST['report_type'] ?? '';
        $report_reason = $_POST['report_reason'] ?? '';
        $report_details = $_POST['report_details'] ?? '';

        if (!$reported_user_id || !$report_type || !$report_reason) {
            wp_send_json_error(__('All required fields must be filled.', 'wpmatch'));
        }

        $result = $this->report_user($reporter_id, $reported_user_id, $report_type, $report_reason, $report_details);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Report submitted successfully. Our team will review it shortly.', 'wpmatch'));
    }
}