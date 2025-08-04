<?php
/**
 * WPMatch Encryption Manager
 *
 * @package WPMatch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption and data security class for WPMatch
 */
class WPMatch_Encryption {

    /**
     * Encryption method
     */
    const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Hash algorithm for HMAC
     */
    const HASH_ALGO = 'sha256';

    /**
     * Salt for key derivation
     */
    const KEY_SALT = 'wpmatch_encryption_salt';

    /**
     * Get encryption key
     *
     * @return string
     */
    private static function get_encryption_key() {
        // Use WordPress auth key as base
        $base_key = defined('AUTH_KEY') ? AUTH_KEY : 'default_key';
        
        // Derive a specific key for WPMatch
        return hash_pbkdf2('sha256', $base_key, self::KEY_SALT, 10000, 32, true);
    }

    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string|false Encrypted data or false on failure
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return false;
        }

        try {
            $key = self::get_encryption_key();
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));
            
            $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $iv);
            
            if ($encrypted === false) {
                return false;
            }
            
            // Create HMAC for integrity
            $hmac = hash_hmac(self::HASH_ALGO, $encrypted, $key);
            
            // Combine IV, HMAC, and encrypted data
            return base64_encode($iv . $hmac . $encrypted);
            
        } catch (Exception $e) {
            error_log('WPMatch Encryption Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypt data
     *
     * @param string $encrypted_data Encrypted data
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt($encrypted_data) {
        if (empty($encrypted_data)) {
            return false;
        }

        try {
            $data = base64_decode($encrypted_data);
            
            if ($data === false) {
                return false;
            }
            
            $key = self::get_encryption_key();
            $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
            $hmac_length = 64; // sha256 hex length
            
            // Extract components
            $iv = substr($data, 0, $iv_length);
            $hmac = substr($data, $iv_length, $hmac_length);
            $encrypted = substr($data, $iv_length + $hmac_length);
            
            // Verify HMAC
            $calculated_hmac = hash_hmac(self::HASH_ALGO, $encrypted, $key);
            
            if (!hash_equals($hmac, $calculated_hmac)) {
                return false; // Data integrity check failed
            }
            
            // Decrypt
            $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log('WPMatch Decryption Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hash password securely
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hash_password($password) {
        return wp_hash_password($password);
    }

    /**
     * Verify password against hash
     *
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool
     */
    public static function verify_password($password, $hash) {
        return wp_check_password($password, $hash);
    }

    /**
     * Generate random token
     *
     * @param int $length Token length
     * @return string
     */
    public static function generate_token($length = 32) {
        return wp_generate_password($length, false);
    }

    /**
     * Generate secure random string
     *
     * @param int $length String length
     * @return string
     */
    public static function generate_random_string($length = 16) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_string = '';
        
        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[wp_rand(0, strlen($characters) - 1)];
        }
        
        return $random_string;
    }

    /**
     * Encrypt sensitive user data
     *
     * @param int $user_id User ID
     * @param string $field_key Field key
     * @param mixed $data Data to encrypt
     * @return bool
     */
    public static function encrypt_user_data($user_id, $field_key, $data) {
        if (empty($data)) {
            return delete_user_meta($user_id, '_encrypted_' . $field_key);
        }
        
        $encrypted_data = self::encrypt(maybe_serialize($data));
        
        if ($encrypted_data === false) {
            return false;
        }
        
        return update_user_meta($user_id, '_encrypted_' . $field_key, $encrypted_data);
    }

    /**
     * Decrypt sensitive user data
     *
     * @param int $user_id User ID
     * @param string $field_key Field key
     * @return mixed|false
     */
    public static function decrypt_user_data($user_id, $field_key) {
        $encrypted_data = get_user_meta($user_id, '_encrypted_' . $field_key, true);
        
        if (empty($encrypted_data)) {
            return false;
        }
        
        $decrypted_data = self::decrypt($encrypted_data);
        
        if ($decrypted_data === false) {
            return false;
        }
        
        return maybe_unserialize($decrypted_data);
    }

    /**
     * Create secure hash for data integrity
     *
     * @param mixed $data Data to hash
     * @param string $salt Optional salt
     * @return string
     */
    public static function create_hash($data, $salt = '') {
        $serialized_data = is_string($data) ? $data : serialize($data);
        $key = self::get_encryption_key();
        
        return hash_hmac(self::HASH_ALGO, $serialized_data . $salt, $key);
    }

    /**
     * Verify data integrity
     *
     * @param mixed $data Original data
     * @param string $hash Hash to verify against
     * @param string $salt Optional salt used in original hash
     * @return bool
     */
    public static function verify_hash($data, $hash, $salt = '') {
        $calculated_hash = self::create_hash($data, $salt);
        return hash_equals($hash, $calculated_hash);
    }

    /**
     * Encrypt file contents
     *
     * @param string $file_path Path to file
     * @param string $output_path Optional output path for encrypted file
     * @return bool
     */
    public static function encrypt_file($file_path, $output_path = null) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        if ($content === false) {
            return false;
        }
        
        $encrypted_content = self::encrypt($content);
        if ($encrypted_content === false) {
            return false;
        }
        
        $output_file = $output_path ?: $file_path . '.encrypted';
        
        return file_put_contents($output_file, $encrypted_content) !== false;
    }

    /**
     * Decrypt file contents
     *
     * @param string $file_path Path to encrypted file
     * @param string $output_path Optional output path for decrypted file
     * @return bool
     */
    public static function decrypt_file($file_path, $output_path = null) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }
        
        $encrypted_content = file_get_contents($file_path);
        if ($encrypted_content === false) {
            return false;
        }
        
        $decrypted_content = self::decrypt($encrypted_content);
        if ($decrypted_content === false) {
            return false;
        }
        
        $output_file = $output_path ?: str_replace('.encrypted', '', $file_path);
        
        return file_put_contents($output_file, $decrypted_content) !== false;
    }

    /**
     * Generate API key
     *
     * @param string $prefix Optional prefix
     * @return string
     */
    public static function generate_api_key($prefix = 'wp_') {
        $random_part = self::generate_random_string(32);
        $timestamp = time();
        $hash = substr(hash('sha256', $random_part . $timestamp), 0, 16);
        
        return $prefix . $random_part . $hash;
    }

    /**
     * Create secure URL token
     *
     * @param array $data Data to include in token
     * @param int $expiry_time Expiry timestamp
     * @return string
     */
    public static function create_url_token($data, $expiry_time = null) {
        if ($expiry_time === null) {
            $expiry_time = time() + (24 * 60 * 60); // 24 hours
        }
        
        $token_data = array(
            'data' => $data,
            'expiry' => $expiry_time,
            'created' => time()
        );
        
        $serialized = serialize($token_data);
        $encrypted = self::encrypt($serialized);
        
        return base64_encode($encrypted);
    }

    /**
     * Verify and decode URL token
     *
     * @param string $token Token to verify
     * @return array|false Token data or false if invalid
     */
    public static function verify_url_token($token) {
        $encrypted = base64_decode($token);
        if ($encrypted === false) {
            return false;
        }
        
        $serialized = self::decrypt($encrypted);
        if ($serialized === false) {
            return false;
        }
        
        $token_data = unserialize($serialized);
        if (!is_array($token_data) || !isset($token_data['expiry'], $token_data['data'])) {
            return false;
        }
        
        // Check if token has expired
        if (time() > $token_data['expiry']) {
            return false;
        }
        
        return $token_data['data'];
    }

    /**
     * Secure compare strings (timing attack safe)
     *
     * @param string $string1 First string
     * @param string $string2 Second string
     * @return bool
     */
    public static function secure_compare($string1, $string2) {
        return hash_equals($string1, $string2);
    }

    /**
     * Sanitize and encrypt sensitive form data
     *
     * @param array $form_data Form data array
     * @param array $sensitive_fields Array of field names to encrypt
     * @return array
     */
    public static function sanitize_and_encrypt_form_data($form_data, $sensitive_fields = array()) {
        $sanitized_data = array();
        
        foreach ($form_data as $key => $value) {
            // Sanitize based on field type
            if (in_array($key, array('email', 'user_email'))) {
                $sanitized_value = sanitize_email($value);
            } elseif (in_array($key, array('url', 'website'))) {
                $sanitized_value = esc_url_raw($value);
            } elseif (is_string($value)) {
                $sanitized_value = sanitize_text_field($value);
            } else {
                $sanitized_value = $value;
            }
            
            // Encrypt sensitive fields
            if (in_array($key, $sensitive_fields)) {
                $sanitized_data[$key] = self::encrypt($sanitized_value);
            } else {
                $sanitized_data[$key] = $sanitized_value;
            }
        }
        
        return $sanitized_data;
    }

    /**
     * Get encryption status and info
     *
     * @return array
     */
    public static function get_encryption_info() {
        return array(
            'method' => self::ENCRYPTION_METHOD,
            'hash_algo' => self::HASH_ALGO,
            'openssl_available' => extension_loaded('openssl'),
            'key_length' => strlen(self::get_encryption_key()),
            'supported_methods' => openssl_get_cipher_methods()
        );
    }

    /**
     * Test encryption functionality
     *
     * @return bool
     */
    public static function test_encryption() {
        $test_data = 'WPMatch encryption test: ' . time();
        
        $encrypted = self::encrypt($test_data);
        if ($encrypted === false) {
            return false;
        }
        
        $decrypted = self::decrypt($encrypted);
        if ($decrypted === false) {
            return false;
        }
        
        return $test_data === $decrypted;
    }
}