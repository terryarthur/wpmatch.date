<?php
/**
 * WPMatch Session Validator
 *
 * @package WPMatch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session validation and security class for WPMatch
 */
class WPMatch_Session_Validator {

    /**
     * Session timeout in seconds (30 minutes)
     */
    const SESSION_TIMEOUT = 1800;

    /**
     * Maximum session age in seconds (24 hours)
     */
    const MAX_SESSION_AGE = 86400;

    /**
     * Initialize session validator
     */
    public static function init() {
        add_action('wp_login', array(__CLASS__, 'on_user_login'), 10, 2);
        add_action('wp_logout', array(__CLASS__, 'on_user_logout'));
        add_action('init', array(__CLASS__, 'validate_session'));
        add_filter('auth_cookie_expiration', array(__CLASS__, 'extend_auth_cookie_expiration'));
    }

    /**
     * Validate current session
     *
     * @return bool
     */
    public static function validate_session() {
        if (!is_user_logged_in()) {
            return true; // No session to validate
        }

        $user_id = get_current_user_id();
        $session_data = self::get_session_data($user_id);

        if (!$session_data) {
            return true; // No session data, let WordPress handle it
        }

        // Check session timeout
        if (self::is_session_expired($session_data)) {
            self::invalidate_session($user_id);
            wp_logout();
            return false;
        }

        // Check for suspicious activity
        if (self::detect_suspicious_activity($user_id, $session_data)) {
            self::invalidate_session($user_id);
            wp_logout();
            return false;
        }

        // Update last activity
        self::update_session_activity($user_id);

        return true;
    }

    /**
     * Handle user login
     *
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public static function on_user_login($user_login, $user) {
        $session_data = array(
            'user_id' => $user->ID,
            'login_time' => current_time('timestamp'),
            'last_activity' => current_time('timestamp'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'session_token' => self::generate_session_token(),
            'login_count' => self::get_login_count($user->ID) + 1
        );

        self::store_session_data($user->ID, $session_data);
        
        // Clean up old sessions
        self::cleanup_old_sessions($user->ID);
        
        // Log successful login
        self::log_login_attempt($user->ID, true);
    }

    /**
     * Handle user logout
     */
    public static function on_user_logout() {
        $user_id = get_current_user_id();
        if ($user_id) {
            self::invalidate_session($user_id);
        }
    }

    /**
     * Get session data for user
     *
     * @param int $user_id User ID
     * @return array|false
     */
    private static function get_session_data($user_id) {
        return WPMatch_Cache::get("session_data_{$user_id}");
    }

    /**
     * Store session data
     *
     * @param int $user_id User ID
     * @param array $session_data Session data
     */
    private static function store_session_data($user_id, $session_data) {
        WPMatch_Cache::set("session_data_{$user_id}", $session_data, self::MAX_SESSION_AGE);
        
        // Also store in user meta as backup
        update_user_meta($user_id, '_wpmatch_session_data', $session_data);
    }

    /**
     * Check if session is expired
     *
     * @param array $session_data Session data
     * @return bool
     */
    private static function is_session_expired($session_data) {
        $current_time = current_time('timestamp');
        
        // Check last activity timeout
        if (($current_time - $session_data['last_activity']) > self::SESSION_TIMEOUT) {
            return true;
        }
        
        // Check maximum session age
        if (($current_time - $session_data['login_time']) > self::MAX_SESSION_AGE) {
            return true;
        }
        
        return false;
    }

    /**
     * Detect suspicious activity
     *
     * @param int $user_id User ID
     * @param array $session_data Session data
     * @return bool
     */
    private static function detect_suspicious_activity($user_id, $session_data) {
        // Check IP address change
        $current_ip = self::get_client_ip();
        if ($session_data['ip_address'] !== $current_ip) {
            // Allow IP changes for mobile users, but log them
            self::log_security_event($user_id, 'ip_change', array(
                'old_ip' => $session_data['ip_address'],
                'new_ip' => $current_ip
            ));
        }

        // Check user agent change
        $current_ua = self::get_user_agent();
        if ($session_data['user_agent'] !== $current_ua) {
            // User agent changes are more suspicious
            self::log_security_event($user_id, 'user_agent_change', array(
                'old_ua' => $session_data['user_agent'],
                'new_ua' => $current_ua
            ));
            return true;
        }

        // Check for concurrent sessions from different IPs
        if (self::has_concurrent_sessions($user_id, $current_ip)) {
            self::log_security_event($user_id, 'concurrent_sessions', array(
                'ip' => $current_ip
            ));
            return true;
        }

        return false;
    }

    /**
     * Update session activity
     *
     * @param int $user_id User ID
     */
    private static function update_session_activity($user_id) {
        $session_data = self::get_session_data($user_id);
        if ($session_data) {
            $session_data['last_activity'] = current_time('timestamp');
            self::store_session_data($user_id, $session_data);
        }
        
        // Update user meta for last activity
        update_user_meta($user_id, 'last_activity', current_time('mysql'));
    }

    /**
     * Invalidate session
     *
     * @param int $user_id User ID
     */
    private static function invalidate_session($user_id) {
        WPMatch_Cache::delete("session_data_{$user_id}");
        delete_user_meta($user_id, '_wpmatch_session_data');
        
        // Destroy all user sessions
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();
    }

    /**
     * Generate secure session token
     *
     * @return string
     */
    private static function generate_session_token() {
        return wp_generate_password(32, false);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        return WPMatch_Rate_Limiter::get_client_ip();
    }

    /**
     * Get user agent
     *
     * @return string
     */
    private static function get_user_agent() {
        return !empty($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    }

    /**
     * Get login count for user
     *
     * @param int $user_id User ID
     * @return int
     */
    private static function get_login_count($user_id) {
        $count = get_user_meta($user_id, '_login_count', true);
        return $count ? intval($count) : 0;
    }

    /**
     * Clean up old sessions
     *
     * @param int $user_id User ID
     */
    private static function cleanup_old_sessions($user_id) {
        // WordPress handles this automatically, but we can add custom cleanup
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_others(wp_get_session_token());
    }

    /**
     * Check for concurrent sessions
     *
     * @param int $user_id User ID
     * @param string $current_ip Current IP address
     * @return bool
     */
    private static function has_concurrent_sessions($user_id, $current_ip) {
        // This would require more complex session tracking
        // For now, just check if there are multiple active sessions
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $all_sessions = $sessions->get_all();
        
        return count($all_sessions) > 1;
    }

    /**
     * Log login attempt
     *
     * @param int $user_id User ID
     * @param bool $success Success status
     */
    private static function log_login_attempt($user_id, $success) {
        $log_data = array(
            'user_id' => $user_id,
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'timestamp' => current_time('mysql'),
            'success' => $success
        );
        
        // Store in transient for recent login attempts
        $recent_attempts = get_transient("login_attempts_{$user_id}") ?: array();
        $recent_attempts[] = $log_data;
        
        // Keep only last 10 attempts
        $recent_attempts = array_slice($recent_attempts, -10);
        
        set_transient("login_attempts_{$user_id}", $recent_attempts, DAY_IN_SECONDS);
    }

    /**
     * Log security event
     *
     * @param int $user_id User ID
     * @param string $event_type Event type
     * @param array $data Additional data
     */
    private static function log_security_event($user_id, $event_type, $data = array()) {
        $event_data = array(
            'user_id' => $user_id,
            'event_type' => $event_type,
            'timestamp' => current_time('mysql'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'data' => $data
        );
        
        // Store in transient for recent security events
        $recent_events = get_transient("security_events_{$user_id}") ?: array();
        $recent_events[] = $event_data;
        
        // Keep only last 20 events
        $recent_events = array_slice($recent_events, -20);
        
        set_transient("security_events_{$user_id}", $recent_events, WEEK_IN_SECONDS);
        
        // If it's a high-risk event, notify administrators
        if (in_array($event_type, array('concurrent_sessions', 'user_agent_change'))) {
            self::notify_admins_of_security_event($user_id, $event_type, $data);
        }
    }

    /**
     * Notify administrators of security events
     *
     * @param int $user_id User ID
     * @param string $event_type Event type
     * @param array $data Additional data
     */
    private static function notify_admins_of_security_event($user_id, $event_type, $data) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = sprintf('[%s] Security Alert: %s', get_bloginfo('name'), ucwords(str_replace('_', ' ', $event_type)));
        
        $message = sprintf(
            "A security event has been detected:\n\n" .
            "User: %s (%s)\n" .
            "Event: %s\n" .
            "Time: %s\n" .
            "IP Address: %s\n" .
            "Additional Data: %s\n",
            $user->display_name,
            $user->user_email,
            ucwords(str_replace('_', ' ', $event_type)),
            current_time('mysql'),
            self::get_client_ip(),
            print_r($data, true)
        );
        
        wp_mail(get_option('admin_email'), $subject, $message);
    }

    /**
     * Extend auth cookie expiration for remember me
     *
     * @param int $expiration Current expiration
     * @return int
     */
    public static function extend_auth_cookie_expiration($expiration) {
        // Extend to 30 days for remember me
        return 30 * DAY_IN_SECONDS;
    }

    /**
     * Get session info for user
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_session_info($user_id) {
        $session_data = self::get_session_data($user_id);
        if (!$session_data) {
            return array();
        }
        
        return array(
            'login_time' => $session_data['login_time'],
            'last_activity' => $session_data['last_activity'],
            'ip_address' => $session_data['ip_address'],
            'session_age' => current_time('timestamp') - $session_data['login_time'],
            'time_since_activity' => current_time('timestamp') - $session_data['last_activity'],
            'is_expired' => self::is_session_expired($session_data)
        );
    }
}