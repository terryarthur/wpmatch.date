<?php
/**
 * WPMatch Rate Limiter
 *
 * @package WPMatch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate limiting class for WPMatch
 */
class WPMatch_Rate_Limiter {

    /**
     * Rate limit rules
     */
    private static $rules = array(
        'message_send' => array('limit' => 10, 'window' => 300), // 10 messages per 5 minutes
        'profile_view' => array('limit' => 100, 'window' => 3600), // 100 views per hour
        'search_request' => array('limit' => 50, 'window' => 3600), // 50 searches per hour
        'like_action' => array('limit' => 50, 'window' => 3600), // 50 likes per hour
        'profile_update' => array('limit' => 5, 'window' => 300), // 5 updates per 5 minutes
        'photo_upload' => array('limit' => 10, 'window' => 3600), // 10 uploads per hour
        'registration' => array('limit' => 3, 'window' => 3600), // 3 registrations per hour (IP-based)
        'login_attempt' => array('limit' => 5, 'window' => 900) // 5 login attempts per 15 minutes
    );

    /**
     * Check if action is rate limited
     *
     * @param string $action Action name
     * @param int $user_id User ID (optional)
     * @param string $identifier Additional identifier (IP, etc.)
     * @return bool
     */
    public static function is_rate_limited($action, $user_id = null, $identifier = null) {
        if (!isset(self::$rules[$action])) {
            return false;
        }

        $rule = self::$rules[$action];
        $key = self::get_rate_limit_key($action, $user_id, $identifier);
        
        $attempts = self::get_attempts($key);
        
        return $attempts >= $rule['limit'];
    }

    /**
     * Record an attempt
     *
     * @param string $action Action name
     * @param int $user_id User ID (optional)
     * @param string $identifier Additional identifier (IP, etc.)
     * @return bool
     */
    public static function record_attempt($action, $user_id = null, $identifier = null) {
        if (!isset(self::$rules[$action])) {
            return false;
        }

        $rule = self::$rules[$action];
        $key = self::get_rate_limit_key($action, $user_id, $identifier);
        
        $attempts = self::get_attempts($key);
        $attempts++;
        
        return WPMatch_Cache::set($key, $attempts, $rule['window']);
    }

    /**
     * Get remaining attempts
     *
     * @param string $action Action name
     * @param int $user_id User ID (optional)
     * @param string $identifier Additional identifier (IP, etc.)
     * @return int
     */
    public static function get_remaining_attempts($action, $user_id = null, $identifier = null) {
        if (!isset(self::$rules[$action])) {
            return 0;
        }

        $rule = self::$rules[$action];
        $key = self::get_rate_limit_key($action, $user_id, $identifier);
        
        $attempts = self::get_attempts($key);
        
        return max(0, $rule['limit'] - $attempts);
    }

    /**
     * Get time until reset
     *
     * @param string $action Action name
     * @param int $user_id User ID (optional)
     * @param string $identifier Additional identifier (IP, etc.)
     * @return int Seconds until reset
     */
    public static function get_time_until_reset($action, $user_id = null, $identifier = null) {
        if (!isset(self::$rules[$action])) {
            return 0;
        }

        $rule = self::$rules[$action];
        $key = self::get_rate_limit_key($action, $user_id, $identifier);
        
        // This is a simplified approach - in a real implementation,
        // you might want to store timestamps for more accurate tracking
        return $rule['window'];
    }

    /**
     * Clear rate limit for user/action
     *
     * @param string $action Action name
     * @param int $user_id User ID (optional)
     * @param string $identifier Additional identifier (IP, etc.)
     * @return bool
     */
    public static function clear_rate_limit($action, $user_id = null, $identifier = null) {
        $key = self::get_rate_limit_key($action, $user_id, $identifier);
        return WPMatch_Cache::delete($key);
    }

    /**
     * Get current attempts count
     *
     * @param string $key Cache key
     * @return int
     */
    private static function get_attempts($key) {
        $attempts = WPMatch_Cache::get($key);
        return $attempts !== false ? intval($attempts) : 0;
    }

    /**
     * Generate rate limit cache key
     *
     * @param string $action Action name
     * @param int $user_id User ID (optional)
     * @param string $identifier Additional identifier (IP, etc.)
     * @return string
     */
    private static function get_rate_limit_key($action, $user_id = null, $identifier = null) {
        $key_parts = array('rate_limit', $action);
        
        if ($user_id) {
            $key_parts[] = 'user_' . $user_id;
        }
        
        if ($identifier) {
            $key_parts[] = $identifier;
        } else {
            // Use IP address as default identifier
            $key_parts[] = self::get_client_ip();
        }
        
        return implode('_', $key_parts);
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
     * Apply rate limiting to action
     *
     * @param string $action Action name
     * @param int $user_id User ID (optional)
     * @param string $identifier Additional identifier (IP, etc.)
     * @return array Result array with success status and message
     */
    public static function apply_rate_limit($action, $user_id = null, $identifier = null) {
        if (self::is_rate_limited($action, $user_id, $identifier)) {
            $remaining_time = self::get_time_until_reset($action, $user_id, $identifier);
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Rate limit exceeded. Please try again in %d minutes.', 'wpmatch'),
                    ceil($remaining_time / 60)
                ),
                'retry_after' => $remaining_time
            );
        }

        self::record_attempt($action, $user_id, $identifier);
        
        return array(
            'success' => true,
            'remaining_attempts' => self::get_remaining_attempts($action, $user_id, $identifier)
        );
    }

    /**
     * Get rate limit info
     *
     * @param string $action Action name
     * @param int $user_id User ID (optional)
     * @param string $identifier Additional identifier (IP, etc.)
     * @return array
     */
    public static function get_rate_limit_info($action, $user_id = null, $identifier = null) {
        if (!isset(self::$rules[$action])) {
            return array();
        }

        $rule = self::$rules[$action];
        
        return array(
            'action' => $action,
            'limit' => $rule['limit'],
            'window' => $rule['window'],
            'remaining_attempts' => self::get_remaining_attempts($action, $user_id, $identifier),
            'is_rate_limited' => self::is_rate_limited($action, $user_id, $identifier),
            'time_until_reset' => self::get_time_until_reset($action, $user_id, $identifier)
        );
    }

    /**
     * Update rate limit rule
     *
     * @param string $action Action name
     * @param int $limit Request limit
     * @param int $window Time window in seconds
     */
    public static function update_rule($action, $limit, $window) {
        self::$rules[$action] = array(
            'limit' => intval($limit),
            'window' => intval($window)
        );
    }

    /**
     * Get all rate limit rules
     *
     * @return array
     */
    public static function get_rules() {
        return self::$rules;
    }
}