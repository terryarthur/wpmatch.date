<?php
/**
 * WPMatch Brute Force Protection
 *
 * @package WPMatch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Brute force protection class for WPMatch
 */
class WPMatch_Brute_Force_Protection {

    /**
     * Maximum login attempts before lockout
     */
    const MAX_ATTEMPTS = 5;

    /**
     * Lockout duration in seconds (30 minutes)
     */
    const LOCKOUT_DURATION = 1800;

    /**
     * Maximum lockouts before permanent ban (24 hours)
     */
    const MAX_LOCKOUTS = 3;

    /**
     * Permanent ban duration in seconds (24 hours)
     */
    const BAN_DURATION = 86400;

    /**
     * Initialize brute force protection
     */
    public static function init() {
        add_action('wp_login_failed', array(__CLASS__, 'on_login_failed'));
        add_action('wp_login', array(__CLASS__, 'on_login_success'), 10, 2);
        add_filter('authenticate', array(__CLASS__, 'check_login_attempt'), 30, 3);
        add_action('init', array(__CLASS__, 'check_ip_ban'));
    }

    /**
     * Handle failed login attempt
     *
     * @param string $username Username
     */
    public static function on_login_failed($username) {
        $ip = self::get_client_ip();
        
        // Record failed attempt
        self::record_failed_attempt($ip, $username);
        
        // Check if lockout is needed
        $attempts = self::get_failed_attempts($ip);
        
        if ($attempts >= self::MAX_ATTEMPTS) {
            self::lockout_ip($ip);
            
            // Check if permanent ban is needed
            $lockout_count = self::get_lockout_count($ip);
            if ($lockout_count >= self::MAX_LOCKOUTS) {
                self::ban_ip($ip);
            }
        }
        
        // Log the attempt
        self::log_login_attempt($ip, $username, false);
    }

    /**
     * Handle successful login
     *
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public static function on_login_success($user_login, $user) {
        $ip = self::get_client_ip();
        
        // Clear failed attempts on successful login
        self::clear_failed_attempts($ip);
        
        // Log successful login
        self::log_login_attempt($ip, $user_login, true);
    }

    /**
     * Check login attempt before authentication
     *
     * @param WP_User|WP_Error|null $user User object or error
     * @param string $username Username
     * @param string $password Password
     * @return WP_User|WP_Error
     */
    public static function check_login_attempt($user, $username, $password) {
        // Skip if already an error or empty credentials
        if (is_wp_error($user) || empty($username) || empty($password)) {
            return $user;
        }
        
        $ip = self::get_client_ip();
        
        // Check if IP is banned
        if (self::is_ip_banned($ip)) {
            return new WP_Error(
                'ip_banned',
                sprintf(
                    __('Your IP address has been banned due to too many failed login attempts. Please try again later.', 'wpmatch')
                )
            );
        }
        
        // Check if IP is locked out
        if (self::is_ip_locked_out($ip)) {
            $lockout_time = self::get_lockout_time_remaining($ip);
            return new WP_Error(
                'ip_locked_out',
                sprintf(
                    __('Too many failed login attempts. Please try again in %d minutes.', 'wpmatch'),
                    ceil($lockout_time / 60)
                )
            );
        }
        
        return $user;
    }

    /**
     * Check if current IP should be redirected or blocked
     */
    public static function check_ip_ban() {
        $ip = self::get_client_ip();
        
        if (self::is_ip_banned($ip)) {
            // Log the blocked attempt
            self::log_blocked_attempt($ip);
            
            // Send 403 status
            status_header(403);
            wp_die(
                __('Access Denied: Your IP address has been banned due to suspicious activity.', 'wpmatch'),
                __('Access Denied', 'wpmatch'),
                array('response' => 403)
            );
        }
    }

    /**
     * Record failed login attempt
     *
     * @param string $ip IP address
     * @param string $username Username
     */
    private static function record_failed_attempt($ip, $username) {
        $key = "failed_attempts_{$ip}";
        $attempts = WPMatch_Cache::get($key);
        
        if ($attempts === false) {
            $attempts = array();
        }
        
        $attempts[] = array(
            'username' => $username,
            'timestamp' => current_time('timestamp'),
            'user_agent' => self::get_user_agent()
        );
        
        // Keep only recent attempts (last hour)
        $cutoff_time = current_time('timestamp') - 3600;
        $attempts = array_filter($attempts, function($attempt) use ($cutoff_time) {
            return $attempt['timestamp'] > $cutoff_time;
        });
        
        WPMatch_Cache::set($key, $attempts, 3600);
    }

    /**
     * Get number of failed attempts for IP
     *
     * @param string $ip IP address
     * @return int
     */
    private static function get_failed_attempts($ip) {
        $key = "failed_attempts_{$ip}";
        $attempts = WPMatch_Cache::get($key);
        
        if ($attempts === false) {
            return 0;
        }
        
        // Count recent attempts (last 15 minutes)
        $cutoff_time = current_time('timestamp') - 900;
        $recent_attempts = array_filter($attempts, function($attempt) use ($cutoff_time) {
            return $attempt['timestamp'] > $cutoff_time;
        });
        
        return count($recent_attempts);
    }

    /**
     * Clear failed attempts for IP
     *
     * @param string $ip IP address
     */
    private static function clear_failed_attempts($ip) {
        $key = "failed_attempts_{$ip}";
        WPMatch_Cache::delete($key);
    }

    /**
     * Lock out IP address
     *
     * @param string $ip IP address
     */
    private static function lockout_ip($ip) {
        $key = "lockout_{$ip}";
        $lockout_data = array(
            'timestamp' => current_time('timestamp'),
            'duration' => self::LOCKOUT_DURATION
        );
        
        WPMatch_Cache::set($key, $lockout_data, self::LOCKOUT_DURATION);
        
        // Increment lockout count
        self::increment_lockout_count($ip);
        
        // Log the lockout
        self::log_security_event($ip, 'lockout', $lockout_data);
    }

    /**
     * Check if IP is locked out
     *
     * @param string $ip IP address
     * @return bool
     */
    private static function is_ip_locked_out($ip) {
        $key = "lockout_{$ip}";
        $lockout_data = WPMatch_Cache::get($key);
        
        if ($lockout_data === false) {
            return false;
        }
        
        $current_time = current_time('timestamp');
        return ($current_time - $lockout_data['timestamp']) < $lockout_data['duration'];
    }

    /**
     * Get remaining lockout time
     *
     * @param string $ip IP address
     * @return int Seconds remaining
     */
    private static function get_lockout_time_remaining($ip) {
        $key = "lockout_{$ip}";
        $lockout_data = WPMatch_Cache::get($key);
        
        if ($lockout_data === false) {
            return 0;
        }
        
        $current_time = current_time('timestamp');
        $elapsed_time = $current_time - $lockout_data['timestamp'];
        
        return max(0, $lockout_data['duration'] - $elapsed_time);
    }

    /**
     * Increment lockout count for IP
     *
     * @param string $ip IP address
     */
    private static function increment_lockout_count($ip) {
        $key = "lockout_count_{$ip}";
        $count = WPMatch_Cache::get($key);
        
        if ($count === false) {
            $count = 0;
        }
        
        $count++;
        WPMatch_Cache::set($key, $count, DAY_IN_SECONDS);
    }

    /**
     * Get lockout count for IP
     *
     * @param string $ip IP address
     * @return int
     */
    private static function get_lockout_count($ip) {
        $key = "lockout_count_{$ip}";
        $count = WPMatch_Cache::get($key);
        
        return $count !== false ? intval($count) : 0;
    }

    /**
     * Ban IP address
     *
     * @param string $ip IP address
     */
    private static function ban_ip($ip) {
        $key = "banned_ip_{$ip}";
        $ban_data = array(
            'timestamp' => current_time('timestamp'),
            'duration' => self::BAN_DURATION,
            'reason' => 'Multiple lockouts due to failed login attempts'
        );
        
        WPMatch_Cache::set($key, $ban_data, self::BAN_DURATION);
        
        // Also store in database option for persistence
        $banned_ips = get_option('wpmatch_banned_ips', array());
        $banned_ips[$ip] = $ban_data;
        update_option('wpmatch_banned_ips', $banned_ips);
        
        // Log the ban
        self::log_security_event($ip, 'ban', $ban_data);
        
        // Notify administrators
        self::notify_admin_of_ban($ip, $ban_data);
    }

    /**
     * Check if IP is banned
     *
     * @param string $ip IP address
     * @return bool
     */
    private static function is_ip_banned($ip) {
        // Check cache first
        $key = "banned_ip_{$ip}";
        $ban_data = WPMatch_Cache::get($key);
        
        if ($ban_data !== false) {
            $current_time = current_time('timestamp');
            if (($current_time - $ban_data['timestamp']) < $ban_data['duration']) {
                return true;
            } else {
                // Ban expired, remove it
                WPMatch_Cache::delete($key);
                self::remove_ip_ban($ip);
                return false;
            }
        }
        
        // Check database
        $banned_ips = get_option('wpmatch_banned_ips', array());
        if (isset($banned_ips[$ip])) {
            $ban_data = $banned_ips[$ip];
            $current_time = current_time('timestamp');
            
            if (($current_time - $ban_data['timestamp']) < $ban_data['duration']) {
                // Still banned, update cache
                WPMatch_Cache::set($key, $ban_data, $ban_data['duration'] - ($current_time - $ban_data['timestamp']));
                return true;
            } else {
                // Ban expired, remove it
                self::remove_ip_ban($ip);
                return false;
            }
        }
        
        return false;
    }

    /**
     * Remove IP ban
     *
     * @param string $ip IP address
     */
    private static function remove_ip_ban($ip) {
        $key = "banned_ip_{$ip}";
        WPMatch_Cache::delete($key);
        
        $banned_ips = get_option('wpmatch_banned_ips', array());
        if (isset($banned_ips[$ip])) {
            unset($banned_ips[$ip]);
            update_option('wpmatch_banned_ips', $banned_ips);
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
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
     * Log login attempt
     *
     * @param string $ip IP address
     * @param string $username Username
     * @param bool $success Success status
     */
    private static function log_login_attempt($ip, $username, $success) {
        $log_entry = array(
            'ip' => $ip,
            'username' => $username,
            'success' => $success,
            'timestamp' => current_time('mysql'),
            'user_agent' => self::get_user_agent()
        );
        
        // Store in transient for recent attempts
        $recent_attempts = get_transient('wpmatch_login_attempts') ?: array();
        $recent_attempts[] = $log_entry;
        
        // Keep only last 100 attempts
        $recent_attempts = array_slice($recent_attempts, -100);
        
        set_transient('wpmatch_login_attempts', $recent_attempts, DAY_IN_SECONDS);
    }

    /**
     * Log blocked attempt
     *
     * @param string $ip IP address
     */
    private static function log_blocked_attempt($ip) {
        $log_entry = array(
            'ip' => $ip,
            'timestamp' => current_time('mysql'),
            'user_agent' => self::get_user_agent(),
            'request_uri' => !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
        );
        
        // Store in transient for recent blocked attempts
        $blocked_attempts = get_transient('wpmatch_blocked_attempts') ?: array();
        $blocked_attempts[] = $log_entry;
        
        // Keep only last 50 attempts
        $blocked_attempts = array_slice($blocked_attempts, -50);
        
        set_transient('wpmatch_blocked_attempts', $blocked_attempts, DAY_IN_SECONDS);
    }

    /**
     * Log security event
     *
     * @param string $ip IP address
     * @param string $event_type Event type
     * @param array $data Additional data
     */
    private static function log_security_event($ip, $event_type, $data = array()) {
        $event_data = array(
            'ip' => $ip,
            'event_type' => $event_type,
            'timestamp' => current_time('mysql'),
            'data' => $data
        );
        
        // Store in transient for recent security events
        $security_events = get_transient('wpmatch_security_events') ?: array();
        $security_events[] = $event_data;
        
        // Keep only last 50 events
        $security_events = array_slice($security_events, -50);
        
        set_transient('wpmatch_security_events', $security_events, WEEK_IN_SECONDS);
    }

    /**
     * Notify administrator of IP ban
     *
     * @param string $ip IP address
     * @param array $ban_data Ban data
     */
    private static function notify_admin_of_ban($ip, $ban_data) {
        $subject = sprintf('[%s] IP Address Banned: %s', get_bloginfo('name'), $ip);
        
        $message = sprintf(
            "An IP address has been automatically banned due to suspicious activity:\n\n" .
            "IP Address: %s\n" .
            "Ban Time: %s\n" .
            "Duration: %d hours\n" .
            "Reason: %s\n" .
            "User Agent: %s\n",
            $ip,
            date('Y-m-d H:i:s', $ban_data['timestamp']),
            $ban_data['duration'] / 3600,
            $ban_data['reason'],
            self::get_user_agent()
        );
        
        wp_mail(get_option('admin_email'), $subject, $message);
    }

    /**
     * Get security statistics
     *
     * @return array
     */
    public static function get_security_stats() {
        $login_attempts = get_transient('wpmatch_login_attempts') ?: array();
        $blocked_attempts = get_transient('wpmatch_blocked_attempts') ?: array();
        $security_events = get_transient('wpmatch_security_events') ?: array();
        $banned_ips = get_option('wpmatch_banned_ips', array());
        
        // Count failed attempts in last 24 hours
        $failed_attempts_24h = 0;
        $cutoff_time = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        foreach ($login_attempts as $attempt) {
            if (!$attempt['success'] && $attempt['timestamp'] > $cutoff_time) {
                $failed_attempts_24h++;
            }
        }
        
        return array(
            'total_login_attempts' => count($login_attempts),
            'failed_attempts_24h' => $failed_attempts_24h,
            'blocked_attempts' => count($blocked_attempts),
            'security_events' => count($security_events),
            'banned_ips' => count($banned_ips),
            'active_lockouts' => self::count_active_lockouts()
        );
    }

    /**
     * Count active lockouts
     *
     * @return int
     */
    private static function count_active_lockouts() {
        // This would require iterating through cached lockout keys
        // For simplicity, return 0 - in a real implementation, you'd track this
        return 0;
    }

    /**
     * Manually ban IP address
     *
     * @param string $ip IP address
     * @param string $reason Ban reason
     * @param int $duration Ban duration in seconds
     * @return bool
     */
    public static function manual_ban_ip($ip, $reason = 'Manual ban', $duration = null) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        if ($duration === null) {
            $duration = self::BAN_DURATION;
        }
        
        $key = "banned_ip_{$ip}";
        $ban_data = array(
            'timestamp' => current_time('timestamp'),
            'duration' => $duration,
            'reason' => $reason,
            'manual' => true
        );
        
        WPMatch_Cache::set($key, $ban_data, $duration);
        
        // Store in database
        $banned_ips = get_option('wpmatch_banned_ips', array());
        $banned_ips[$ip] = $ban_data;
        update_option('wpmatch_banned_ips', $banned_ips);
        
        // Log the manual ban
        self::log_security_event($ip, 'manual_ban', $ban_data);
        
        return true;
    }

    /**
     * Manually unban IP address
     *
     * @param string $ip IP address
     * @return bool
     */
    public static function manual_unban_ip($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        self::remove_ip_ban($ip);
        
        // Log the manual unban
        self::log_security_event($ip, 'manual_unban');
        
        return true;
    }
}