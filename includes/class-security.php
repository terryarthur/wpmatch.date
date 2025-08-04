<?php
/**
 * Security management class
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Security class
 */
class WPMatch_Security {

    /**
     * Rate limiting cache
     *
     * @var array
     */
    private static $rate_limits = array();

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize security features
     */
    public function init() {
        // Security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // CSRF protection
        add_action('wp_ajax_wpmatch_*', array($this, 'verify_nonce'), 1);
        add_action('wp_ajax_nopriv_wpmatch_*', array($this, 'verify_nonce'), 1);
        
        // Rate limiting
        add_action('wp_ajax_wpmatch_send_message', array($this, 'check_message_rate_limit'), 1);
        add_action('wp_ajax_wpmatch_upload_photo', array($this, 'check_upload_rate_limit'), 1);
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
        }
    }

    /**
     * Verify nonce for AJAX requests
     */
    public function verify_nonce() {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if (strpos($action, 'wpmatch_') === 0) {
            $nonce_field = is_user_logged_in() ? 'wpmatch_nonce' : 'wpmatch_public_nonce';
            
            if (!wp_verify_nonce($_POST[$nonce_field] ?? '', $action)) {
                wp_die(__('Security check failed. Please refresh the page and try again.', 'wpmatch'));
            }
        }
    }

    /**
     * Sanitize user input
     *
     * @param mixed $input
     * @param string $type
     * @return mixed
     */
    public static function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            
            case 'url':
                return esc_url_raw($input);
            
            case 'textarea':
                return sanitize_textarea_field($input);
            
            case 'html':
                return wp_kses_post($input);
            
            case 'int':
                return absint($input);
            
            case 'float':
                return floatval($input);
            
            case 'key':
                return sanitize_key($input);
            
            case 'filename':
                return sanitize_file_name($input);
            
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * Escape output for display
     *
     * @param mixed $output
     * @param string $context
     * @return mixed
     */
    public static function escape_output($output, $context = 'html') {
        switch ($context) {
            case 'attr':
                return esc_attr($output);
            
            case 'url':
                return esc_url($output);
            
            case 'js':
                return esc_js($output);
            
            case 'textarea':
                return esc_textarea($output);
            
            case 'html':
            default:
                return esc_html($output);
        }
    }

    /**
     * Validate file upload
     *
     * @param array $file
     * @param array $allowed_types
     * @param int $max_size
     * @return bool|WP_Error
     */
    public static function validate_file_upload($file, $allowed_types = array('image'), $max_size = 5242880) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload error.', 'wpmatch'));
        }

        // Check file size
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File is too large.', 'wpmatch'));
        }

        // Check file type
        $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        
        if (!$file_type['type']) {
            return new WP_Error('invalid_file_type', __('Invalid file type.', 'wpmatch'));
        }

        // Validate against allowed types
        if (in_array('image', $allowed_types)) {
            $allowed_mime_types = array(
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp'
            );
            
            if (!in_array($file_type['type'], $allowed_mime_types)) {
                return new WP_Error('invalid_image_type', __('Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.', 'wpmatch'));
            }
        }

        return true;
    }

    /**
     * Check rate limit for specific action
     *
     * @param string $action
     * @param int $user_id
     * @param int $limit
     * @param int $window
     * @return bool
     */
    public static function check_rate_limit($action, $user_id = null, $limit = 10, $window = 3600) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $key = $action . '_' . $user_id;
        $current_time = time();

        // Initialize if not exists
        if (!isset(self::$rate_limits[$key])) {
            self::$rate_limits[$key] = array(
                'count' => 0,
                'window_start' => $current_time
            );
        }

        $rate_limit = &self::$rate_limits[$key];

        // Reset window if expired
        if ($current_time - $rate_limit['window_start'] > $window) {
            $rate_limit['count'] = 0;
            $rate_limit['window_start'] = $current_time;
        }

        // Check limit
        if ($rate_limit['count'] >= $limit) {
            return false;
        }

        // Increment counter
        $rate_limit['count']++;
        
        return true;
    }

    /**
     * Check message rate limit
     */
    public function check_message_rate_limit() {
        if (!self::check_rate_limit('send_message', null, 30, 3600)) {
            wp_die(__('You are sending messages too frequently. Please wait before sending another message.', 'wpmatch'));
        }
    }

    /**
     * Check upload rate limit
     */
    public function check_upload_rate_limit() {
        if (!self::check_rate_limit('upload_photo', null, 10, 3600)) {
            wp_die(__('You are uploading files too frequently. Please wait before uploading another file.', 'wpmatch'));
        }
    }

    /**
     * Validate user capability for action
     *
     * @param string $capability
     * @param int $user_id
     * @return bool
     */
    public static function user_can($capability, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        return user_can($user_id, $capability);
    }

    /**
     * Log security event
     *
     * @param string $event
     * @param array $data
     * @param string $level
     */
    public static function log_security_event($event, $data = array(), $level = 'info') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_data = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_user_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'data' => $data,
            'level' => $level
        );

        error_log('WPMatch Security Event: ' . wp_json_encode($log_data));
    }

    /**
     * Get user IP address
     *
     * @return string
     */
    public static function get_user_ip() {
        // Check for IP from various headers
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // CloudFlare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Generate secure token
     *
     * @param int $length
     * @return string
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash password securely
     *
     * @param string $password
     * @return string
     */
    public static function hash_password($password) {
        return wp_hash_password($password);
    }

    /**
     * Verify password
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verify_password($password, $hash) {
        return wp_check_password($password, $hash);
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    public static function encrypt_data($data, $key = null) {
        if (!$key) {
            $key = defined('AUTH_KEY') ? AUTH_KEY : 'wpmatch_default_key';
        }

        $key = hash('sha256', $key);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted_data
     * @param string $key
     * @return string|false
     */
    public static function decrypt_data($encrypted_data, $key = null) {
        if (!$key) {
            $key = defined('AUTH_KEY') ? AUTH_KEY : 'wpmatch_default_key';
        }

        $key = hash('sha256', $key);
        $data = base64_decode($encrypted_data);
        
        if (strlen($data) < 16) {
            return false;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Validate email domain
     *
     * @param string $email
     * @return bool
     */
    public static function validate_email_domain($email) {
        $domain = substr(strrchr($email, '@'), 1);
        
        // Get blocked domains from settings
        $blocked_domains = get_option('wpmatch_blocked_email_domains', array());
        
        if (in_array($domain, $blocked_domains)) {
            return false;
        }

        // Check if domain exists
        return checkdnsrr($domain, 'MX');
    }

    /**
     * Check if IP is blocked
     *
     * @param string $ip
     * @return bool
     */
    public static function is_ip_blocked($ip = null) {
        if (!$ip) {
            $ip = self::get_user_ip();
        }

        $blocked_ips = get_option('wpmatch_blocked_ips', array());
        $blocked_countries = get_option('wpmatch_blocked_countries', array());

        // Check IP blacklist
        if (in_array($ip, $blocked_ips)) {
            return true;
        }

        // Check country blocking (requires GeoIP)
        if (!empty($blocked_countries) && function_exists('geoip_country_code_by_name')) {
            $country = geoip_country_code_by_name($ip);
            if (in_array($country, $blocked_countries)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize profile data comprehensively
     *
     * @param array $data
     * @return array
     */
    public static function sanitize_profile_data($data) {
        $sanitized = array();

        $field_types = array(
            'display_name' => 'text',
            'about_me' => 'textarea',
            'age' => 'int',
            'gender' => 'key',
            'looking_for' => 'key',
            'location' => 'text',
            'latitude' => 'float',
            'longitude' => 'float',
            'height' => 'int',
            'weight' => 'int',
            'relationship_status' => 'key',
            'children' => 'key',
            'education' => 'text',
            'profession' => 'text',
            'interests' => 'textarea'
        );

        foreach ($data as $key => $value) {
            if (isset($field_types[$key])) {
                $sanitized[$key] = self::sanitize_input($value, $field_types[$key]);
            }
        }

        return $sanitized;
    }

    /**
     * Validate age
     *
     * @param int $age
     * @return bool
     */
    public static function validate_age($age) {
        return $age >= 18 && $age <= 120;
    }

    /**
     * Clean malicious content from text
     *
     * @param string $content
     * @return string
     */
    public static function clean_content($content) {
        // Remove potential XSS
        $content = wp_kses($content, array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'em' => array(),
            'u' => array(),
        ));

        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Trim
        $content = trim($content);

        return $content;
    }
}