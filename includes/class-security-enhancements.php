<?php
/**
 * Enhanced Security Features for WPMatch
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Security Enhancements class
 * 
 * Provides advanced security features including enhanced CSRF protection,
 * session monitoring, brute force protection, and input validation.
 */
class WPMatch_Security_Enhancements {

    /**
     * Failed login attempts tracking
     *
     * @var array
     */
    private static $failed_attempts = array();

    /**
     * Security options
     *
     * @var array
     */
    private static $security_options = array(
        'max_login_attempts' => 5,
        'lockout_duration' => 1800, // 30 minutes
        'session_timeout' => 7200,  // 2 hours
        'enable_double_auth' => true,
        'require_secure_passwords' => true
    );

    /**
     * Initialize security enhancements
     */
    public static function init() {
        // Enhanced CSRF protection
        add_action('wp_ajax_wpmatch_field_action', array(__CLASS__, 'enhanced_csrf_check'), 1);
        add_action('wp_ajax_wpmatch_create_field', array(__CLASS__, 'enhanced_csrf_check'), 1);
        add_action('wp_ajax_wpmatch_update_field', array(__CLASS__, 'enhanced_csrf_check'), 1);
        add_action('wp_ajax_wpmatch_delete_field', array(__CLASS__, 'enhanced_csrf_check'), 1);
        
        // Session security
        add_action('wp_login', array(__CLASS__, 'track_login_session'), 10, 2);
        add_action('wp_logout', array(__CLASS__, 'cleanup_session_data'));
        add_action('init', array(__CLASS__, 'validate_session_security'));
        
        // Brute force protection
        add_action('wp_login_failed', array(__CLASS__, 'handle_failed_login'));
        add_filter('authenticate', array(__CLASS__, 'check_brute_force_protection'), 30, 3);
        
        // Enhanced input validation
        add_filter('wpmatch_sanitize_field_input', array(__CLASS__, 'enhanced_input_sanitization'), 10, 3);
        add_filter('wpmatch_validate_field_input', array(__CLASS__, 'enhanced_input_validation'), 10, 3);
        
        // Security headers
        add_action('send_headers', array(__CLASS__, 'add_security_headers'));
        
        // Admin security enhancements
        if (is_admin()) {
            add_action('admin_init', array(__CLASS__, 'admin_security_checks'));
            add_filter('wp_die_handler', array(__CLASS__, 'custom_die_handler'));
        }
    }

    /**
     * Enhanced CSRF protection with double verification
     */
    public static function enhanced_csrf_check() {
        if (!is_admin() || !current_user_can('manage_profile_fields')) {
            return;
        }

        // Standard nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpmatch_field_admin_nonce')) {
            wp_die(__('Security check failed. Please refresh the page and try again.', 'wpmatch'), 
                   __('Security Error', 'wpmatch'), 
                   array('response' => 403));
        }

        // Additional security checks
        self::validate_request_origin();
        self::validate_user_session();
        self::check_concurrent_sessions();
    }

    /**
     * Validate request origin
     */
    private static function validate_request_origin() {
        $referer = wp_get_referer();
        $admin_url = admin_url();
        
        if (!$referer || strpos($referer, $admin_url) !== 0) {
            wp_die(__('Invalid request origin detected.', 'wpmatch'), 
                   __('Security Error', 'wpmatch'), 
                   array('response' => 403));
        }
    }

    /**
     * Validate user session integrity
     */
    private static function validate_user_session() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $session_token = wp_get_session_token();
        $stored_hash = get_user_meta($user_id, 'wpmatch_session_hash', true);
        $current_hash = hash('sha256', $session_token . wp_salt('auth'));
        
        if ($stored_hash && $stored_hash !== $current_hash) {
            wp_logout();
            wp_die(__('Session integrity check failed. Please log in again.', 'wpmatch'), 
                   __('Session Error', 'wpmatch'), 
                   array('response' => 401));
        }
    }

    /**
     * Check for concurrent sessions
     */
    private static function check_concurrent_sessions() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $sessions = WP_Session_Tokens::get_instance($user_id);
        $all_sessions = $sessions->get_all();
        
        // Allow maximum 3 concurrent sessions
        if (count($all_sessions) > 3) {
            // Keep only the 3 most recent sessions
            $sorted_sessions = $all_sessions;
            uasort($sorted_sessions, function($a, $b) {
                return $b['login'] - $a['login'];
            });
            
            $keep_sessions = array_slice($sorted_sessions, 0, 3, true);
            $remove_sessions = array_diff_key($all_sessions, $keep_sessions);
            
            foreach ($remove_sessions as $token => $session) {
                $sessions->destroy($token);
            }
        }
    }

    /**
     * Track login session
     *
     * @param string  $user_login Username
     * @param WP_User $user       User object
     */
    public static function track_login_session($user_login, $user) {
        if (!$user instanceof WP_User) {
            return;
        }

        $session_data = array(
            'login_time' => current_time('mysql'),
            'ip_address' => self::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id() ?: wp_generate_uuid4()
        );

        // Store session hash for integrity checking
        $session_token = wp_get_session_token();
        $session_hash = hash('sha256', $session_token . wp_salt('auth'));
        update_user_meta($user->ID, 'wpmatch_session_hash', $session_hash);
        
        // Log successful login
        update_user_meta($user->ID, 'wpmatch_last_login', $session_data);
        
        // Clear failed attempts
        delete_transient('wpmatch_failed_attempts_' . self::get_client_ip());
        
        do_action('wpmatch_user_logged_in', $user, $session_data);
    }

    /**
     * Cleanup session data on logout
     */
    public static function cleanup_session_data() {
        $user_id = get_current_user_id();
        if ($user_id) {
            delete_user_meta($user_id, 'wpmatch_session_hash');
        }
    }

    /**
     * Validate session security on each request
     */
    public static function validate_session_security() {
        if (!is_user_logged_in() || !is_admin()) {
            return;
        }

        $user_id = get_current_user_id();
        $last_login = get_user_meta($user_id, 'wpmatch_last_login', true);
        
        if ($last_login && is_array($last_login)) {
            // Check session timeout
            $login_time = strtotime($last_login['login_time']);
            if ((time() - $login_time) > self::$security_options['session_timeout']) {
                wp_logout();
                wp_redirect(wp_login_url());
                exit;
            }
            
            // Check IP consistency (optional - can be disabled for mobile users)
            $current_ip = self::get_client_ip();
            if (apply_filters('wpmatch_enforce_ip_consistency', false) && 
                $last_login['ip_address'] !== $current_ip) {
                wp_logout();
                wp_die(__('IP address changed during session. Please log in again.', 'wpmatch'));
            }
        }
    }

    /**
     * Handle failed login attempts
     *
     * @param string $username Username that failed login
     */
    public static function handle_failed_login($username) {
        $ip = self::get_client_ip();
        $cache_key = 'wpmatch_failed_attempts_' . $ip;
        
        $attempts = get_transient($cache_key);
        if (!$attempts) {
            $attempts = array();
        }
        
        $attempts[] = array(
            'username' => $username,
            'timestamp' => time(),
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        // Keep only attempts from last hour
        $one_hour_ago = time() - 3600;
        $attempts = array_filter($attempts, function($attempt) use ($one_hour_ago) {
            return $attempt['timestamp'] > $one_hour_ago;
        });
        
        set_transient($cache_key, $attempts, 3600);
        
        // Log suspicious activity
        if (count($attempts) >= self::$security_options['max_login_attempts']) {
            do_action('wpmatch_brute_force_detected', $ip, $attempts);
            
            // Optional: Send admin notification
            if (apply_filters('wpmatch_notify_brute_force', true)) {
                self::notify_admin_brute_force($ip, $attempts);
            }
        }
    }

    /**
     * Check brute force protection
     *
     * @param WP_User|WP_Error|null $user     User object or error
     * @param string                $username Username
     * @param string                $password Password
     * @return WP_User|WP_Error User object or error
     */
    public static function check_brute_force_protection($user, $username, $password) {
        if (empty($username) || empty($password)) {
            return $user;
        }

        $ip = self::get_client_ip();
        $cache_key = 'wpmatch_failed_attempts_' . $ip;
        $attempts = get_transient($cache_key);
        
        if ($attempts && count($attempts) >= self::$security_options['max_login_attempts']) {
            $lockout_key = 'wpmatch_lockout_' . $ip;
            $lockout_time = get_transient($lockout_key);
            
            if ($lockout_time === false) {
                // Set lockout
                set_transient($lockout_key, time(), self::$security_options['lockout_duration']);
                $lockout_time = time();
            }
            
            $remaining_time = $lockout_time + self::$security_options['lockout_duration'] - time();
            
            if ($remaining_time > 0) {
                return new WP_Error('too_many_attempts', 
                    sprintf(__('Too many login attempts. Please try again in %d minutes.', 'wpmatch'), 
                            ceil($remaining_time / 60))
                );
            }
        }
        
        return $user;
    }

    /**
     * Enhanced input sanitization
     *
     * @param mixed  $value      Input value
     * @param object $field      Field configuration
     * @param string $context    Sanitization context
     * @return mixed Sanitized value
     */
    public static function enhanced_input_sanitization($value, $field, $context = 'save') {
        // Remove potentially dangerous characters
        if (is_string($value)) {
            // Remove null bytes
            $value = str_replace(chr(0), '', $value);
            
            // Remove control characters except tabs, newlines, and carriage returns
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
            
            // Detect and prevent XSS attempts
            if (self::contains_xss($value)) {
                return new WP_Error('xss_detected', __('Potentially malicious content detected.', 'wpmatch'));
            }
            
            // Prevent SQL injection patterns
            if (self::contains_sql_injection($value)) {
                return new WP_Error('sql_injection_detected', __('Invalid characters detected.', 'wpmatch'));
            }
        }
        
        // Field-type specific sanitization
        if (isset($field->field_type)) {
            switch ($field->field_type) {
                case 'email':
                    $value = sanitize_email($value);
                    break;
                    
                case 'url':
                    $value = esc_url_raw($value);
                    break;
                    
                case 'text':
                    $value = sanitize_text_field($value);
                    break;
                    
                case 'textarea':
                    $value = sanitize_textarea_field($value);
                    break;
                    
                case 'number':
                    $value = is_numeric($value) ? $value : '';
                    break;
                    
                default:
                    $value = sanitize_text_field($value);
            }
        }
        
        return $value;
    }

    /**
     * Enhanced input validation
     *
     * @param bool|WP_Error $is_valid Current validation status
     * @param mixed         $value    Input value
     * @param object        $field    Field configuration
     * @return bool|WP_Error Validation result
     */
    public static function enhanced_input_validation($is_valid, $value, $field) {
        if (is_wp_error($is_valid)) {
            return $is_valid;
        }
        
        // Additional security validations
        if (is_string($value)) {
            // Check for excessively long input
            if (strlen($value) > 10000) {
                return new WP_Error('input_too_long', __('Input exceeds maximum allowed length.', 'wpmatch'));
            }
            
            // Check for suspicious patterns
            if (self::contains_suspicious_patterns($value)) {
                return new WP_Error('suspicious_input', __('Input contains suspicious patterns.', 'wpmatch'));
            }
        }
        
        return $is_valid;
    }

    /**
     * Check for XSS attempts
     *
     * @param string $value Input value
     * @return bool True if XSS detected
     */
    private static function contains_xss($value) {
        $xss_patterns = array(
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i',
            '/<link\b/i',
            '/<meta\b/i',
            '/expression\s*\(/i',
            '/vbscript:/i',
            '/data:text\/html/i'
        );
        
        foreach ($xss_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for SQL injection attempts
     *
     * @param string $value Input value
     * @return bool True if SQL injection detected
     */
    private static function contains_sql_injection($value) {
        $sql_patterns = array(
            '/(\bunion\b.*\bselect\b)|(\bselect\b.*\bunion\b)/i',
            '/\b(select|insert|update|delete|drop|create|alter|exec|execute)\b.*\b(from|into|table|database|schema)\b/i',
            '/(\'\s*(or|and)\s*\')|(\'\s*(or|and)\s*\d)/i',
            '/\b(script|javascript|vbscript|onload|onerror|onclick)\b/i',
            '/(\-\-)|(\#)|(\s*;\s*)/i'
        );
        
        foreach ($sql_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for suspicious patterns
     *
     * @param string $value Input value
     * @return bool True if suspicious patterns detected
     */
    private static function contains_suspicious_patterns($value) {
        $suspicious_patterns = array(
            '/\.\.\//i',  // Directory traversal
            '/\/etc\/passwd/i',  // System file access
            '/\beval\s*\(/i',  // Code evaluation
            '/\b(system|exec|shell_exec|passthru|popen)\s*\(/i',  // System commands
            '/\b(base64_decode|gzinflate|str_rot13)\s*\(/i',  // Obfuscation functions
            '/\$\w+\s*\(/i',  // Variable functions
            '/\\\\x[0-9a-f]{2}/i',  // Hex encoded characters
            '/%[0-9a-f]{2}/i'  // URL encoded characters (excessive)
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Add security headers
     */
    public static function add_security_headers() {
        if (!is_admin()) {
            return;
        }
        
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS filtering
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy for admin pages
        if (current_user_can('manage_options')) {
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';";
            header('Content-Security-Policy: ' . $csp);
        }
    }

    /**
     * Admin security checks
     */
    public static function admin_security_checks() {
        // Check for admin user session hijacking
        if (current_user_can('administrator')) {
            $user_id = get_current_user_id();
            $last_ip = get_user_meta($user_id, 'wpmatch_last_admin_ip', true);
            $current_ip = self::get_client_ip();
            
            if ($last_ip && $last_ip !== $current_ip) {
                // Log IP change for admin users
                do_action('wpmatch_admin_ip_changed', $user_id, $last_ip, $current_ip);
                
                // Optional: Force re-authentication for IP changes
                if (apply_filters('wpmatch_force_reauth_on_ip_change', false)) {
                    wp_logout();
                    wp_redirect(wp_login_url());
                    exit;
                }
            }
            
            update_user_meta($user_id, 'wpmatch_last_admin_ip', $current_ip);
        }
    }

    /**
     * Custom die handler for security errors
     *
     * @param callable $handler Current die handler
     * @return callable Modified die handler
     */
    public static function custom_die_handler($handler) {
        return function($message, $title = '', $args = array()) use ($handler) {
            // Log security-related errors
            if (is_string($message) && strpos(strtolower($message), 'security') !== false) {
                error_log(sprintf('[WPMatch Security] %s - %s', $title, $message));
                
                // Optional: Send admin notification for security errors
                if (apply_filters('wpmatch_notify_security_errors', false)) {
                    wp_mail(get_option('admin_email'), 
                           'WPMatch Security Alert', 
                           "Security error detected: {$message}");
                }
            }
            
            return call_user_func($handler, $message, $title, $args);
        };
    }

    /**
     * Notify admin of brute force attempts
     *
     * @param string $ip       IP address
     * @param array  $attempts Failed attempts
     */
    private static function notify_admin_brute_force($ip, $attempts) {
        $subject = '[WPMatch Security] Brute Force Attack Detected';
        $message = sprintf(
            "A brute force attack has been detected from IP address: %s\n\n" .
            "Number of failed attempts: %d\n" .
            "Time range: %s to %s\n\n" .
            "Please consider blocking this IP address.",
            $ip,
            count($attempts),
            date('Y-m-d H:i:s', $attempts[0]['timestamp']),
            date('Y-m-d H:i:s', end($attempts)['timestamp'])
        );
        
        wp_mail(get_option('admin_email'), $subject, $message);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get security options
     *
     * @return array Security options
     */
    public static function get_security_options() {
        return apply_filters('wpmatch_security_options', self::$security_options);
    }

    /**
     * Update security options
     *
     * @param array $options New security options
     */
    public static function update_security_options($options) {
        self::$security_options = array_merge(self::$security_options, $options);
    }
}

// Initialize security enhancements
WPMatch_Security_Enhancements::init();