<?php
/**
 * WPMatch Search Manager
 *
 * @package WPMatch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Advanced search management class for WPMatch
 */
class WPMatch_Search_Manager {

    /**
     * Instance of this class
     *
     * @var WPMatch_Search_Manager
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return WPMatch_Search_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize search manager
    }

    /**
     * Perform advanced user search
     *
     * @param array $criteria Search criteria
     * @param array $options Search options
     * @return array
     */
    public function search_users($criteria = array(), $options = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'order_by' => 'last_active',
            'order' => 'DESC',
            'include_photos' => true,
            'distance_radius' => null,
            'user_location' => null
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Generate cache key
        $cache_key = $this->generate_search_cache_key($criteria, $options);
        
        // Try to get from cache first
        $cached_results = WPMatch_Cache::get_search_results($cache_key);
        if ($cached_results !== false) {
            return $cached_results;
        }
        
        global $wpdb;
        
        $where_clauses = array();
        $join_clauses = array();
        $params = array();
        
        // Base query
        $sql = "SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered";
        
        if ($options['include_photos']) {
            $sql .= ", (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'profile_photo' LIMIT 1) as profile_photo";
        }
        
        $sql .= " FROM {$wpdb->users} u";
        
        // Join with usermeta for profile data
        $join_clauses[] = "LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id";
        
        // Age criteria
        if (!empty($criteria['age_min']) || !empty($criteria['age_max'])) {
            if (!empty($criteria['age_min'])) {
                $where_clauses[] = "u.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'birth_date' AND DATEDIFF(NOW(), STR_TO_DATE(meta_value, '%%Y-%%m-%%d')) / 365.25 >= %d)";
                $params[] = intval($criteria['age_min']);
            }
            if (!empty($criteria['age_max'])) {
                $where_clauses[] = "u.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'birth_date' AND DATEDIFF(NOW(), STR_TO_DATE(meta_value, '%%Y-%%m-%%d')) / 365.25 <= %d)";
                $params[] = intval($criteria['age_max']);
            }
        }
        
        // Gender criteria
        if (!empty($criteria['gender'])) {
            $where_clauses[] = "u.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'gender' AND meta_value = %s)";
            $params[] = sanitize_text_field($criteria['gender']);
        }
        
        // Location criteria
        if (!empty($criteria['location'])) {
            $where_clauses[] = "u.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'location' AND meta_value LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($criteria['location']) . '%';
        }
        
        // Distance-based search
        if (!empty($options['distance_radius']) && !empty($options['user_location'])) {
            $distance_clause = $this->build_distance_clause($options['user_location'], $options['distance_radius']);
            if ($distance_clause) {
                $where_clauses[] = $distance_clause['clause'];
                $params = array_merge($params, $distance_clause['params']);
            }
        }
        
        // Interests criteria
        if (!empty($criteria['interests'])) {
            $interests = is_array($criteria['interests']) ? $criteria['interests'] : array($criteria['interests']);
            $interest_placeholders = implode(',', array_fill(0, count($interests), '%s'));
            $where_clauses[] = "u.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'interests' AND meta_value IN ({$interest_placeholders}))";
            $params = array_merge($params, array_map('sanitize_text_field', $interests));
        }
        
        // Online status
        if (!empty($criteria['online_only'])) {
            $where_clauses[] = "u.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'last_activity' AND meta_value > %s)";
            $params[] = date('Y-m-d H:i:s', strtotime('-15 minutes'));
        }
        
        // Exclude blocked users
        if (!empty($criteria['exclude_blocked'])) {
            $current_user_id = get_current_user_id();
            $where_clauses[] = "u.ID NOT IN (SELECT blocked_user_id FROM {$wpdb->prefix}wpmatch_user_blocks WHERE user_id = %d)";
            $params[] = $current_user_id;
        }
        
        // Build complete query
        if (!empty($join_clauses)) {
            $sql .= ' ' . implode(' ', $join_clauses);
        }
        
        if (!empty($where_clauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Add ordering
        $sql .= $this->build_order_clause($options['order_by'], $options['order']);
        
        // Add limit
        $sql .= ' LIMIT %d OFFSET %d';
        $params[] = intval($options['limit']);
        $params[] = intval($options['offset']);
        
        // Prepare and execute query
        if (!empty($params)) {
            $prepared_sql = $wpdb->prepare($sql, $params);
        } else {
            $prepared_sql = $sql;
        }
        
        $results = $wpdb->get_results($prepared_sql, ARRAY_A);
        
        // Process results
        $processed_results = $this->process_search_results($results, $criteria, $options);
        
        // Cache results
        WPMatch_Cache::cache_search_results($cache_key, $processed_results);
        
        return $processed_results;
    }

    /**
     * Generate cache key for search
     *
     * @param array $criteria Search criteria
     * @param array $options Search options
     * @return string
     */
    private function generate_search_cache_key($criteria, $options) {
        return md5(serialize(array($criteria, $options)));
    }

    /**
     * Build distance clause for location-based search
     *
     * @param array $user_location User's location coordinates
     * @param int $radius Search radius in miles
     * @return array|false
     */
    private function build_distance_clause($user_location, $radius) {
        if (empty($user_location['lat']) || empty($user_location['lng'])) {
            return false;
        }
        
        global $wpdb;
        
        $clause = "u.ID IN (
            SELECT user_id FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'latitude' AND um2.meta_key = 'longitude'
            AND (
                3959 * acos(
                    cos(radians(%f)) * 
                    cos(radians(CAST(um1.meta_value AS DECIMAL(10,8)))) * 
                    cos(radians(CAST(um2.meta_value AS DECIMAL(11,8))) - radians(%f)) + 
                    sin(radians(%f)) * 
                    sin(radians(CAST(um1.meta_value AS DECIMAL(10,8))))
                )
            ) <= %d
        )";
        
        return array(
            'clause' => $clause,
            'params' => array(
                floatval($user_location['lat']),
                floatval($user_location['lng']),
                floatval($user_location['lat']),
                intval($radius)
            )
        );
    }

    /**
     * Build ORDER BY clause
     *
     * @param string $order_by Order by field
     * @param string $order Order direction
     * @return string
     */
    private function build_order_clause($order_by, $order) {
        $valid_orders = array('ASC', 'DESC');
        $order = in_array(strtoupper($order), $valid_orders) ? strtoupper($order) : 'DESC';
        
        switch ($order_by) {
            case 'last_active':
                return " ORDER BY (SELECT meta_value FROM {$GLOBALS['wpdb']->usermeta} WHERE user_id = u.ID AND meta_key = 'last_activity' LIMIT 1) {$order}";
            case 'registration_date':
                return " ORDER BY u.user_registered {$order}";
            case 'age':
                return " ORDER BY (SELECT meta_value FROM {$GLOBALS['wpdb']->usermeta} WHERE user_id = u.ID AND meta_key = 'birth_date' LIMIT 1) {$order}";
            case 'name':
                return " ORDER BY u.display_name {$order}";
            default:
                return " ORDER BY u.user_registered {$order}";
        }
    }

    /**
     * Process search results
     *
     * @param array $results Raw search results
     * @param array $criteria Search criteria
     * @param array $options Search options
     * @return array
     */
    private function process_search_results($results, $criteria, $options) {
        $processed = array();
        
        foreach ($results as $user) {
            $user_data = array(
                'ID' => $user['ID'],
                'display_name' => $user['display_name'],
                'user_registered' => $user['user_registered'],
                'profile_photo' => !empty($user['profile_photo']) ? $user['profile_photo'] : '',
                'age' => $this->calculate_age($user['ID']),
                'location' => get_user_meta($user['ID'], 'location', true),
                'last_activity' => get_user_meta($user['ID'], 'last_activity', true),
                'is_online' => $this->is_user_online($user['ID'])
            );
            
            // Calculate compatibility score if criteria provided
            if (!empty($criteria)) {
                $user_data['compatibility_score'] = $this->calculate_compatibility($user['ID'], $criteria);
            }
            
            $processed[] = $user_data;
        }
        
        // Sort by compatibility score if calculated
        if (!empty($criteria) && isset($processed[0]['compatibility_score'])) {
            usort($processed, function($a, $b) {
                return $b['compatibility_score'] - $a['compatibility_score'];
            });
        }
        
        return $processed;
    }

    /**
     * Calculate user age
     *
     * @param int $user_id User ID
     * @return int|null
     */
    private function calculate_age($user_id) {
        $birth_date = get_user_meta($user_id, 'birth_date', true);
        if (empty($birth_date)) {
            return null;
        }
        
        $birth = new DateTime($birth_date);
        $now = new DateTime();
        return $now->diff($birth)->y;
    }

    /**
     * Check if user is online
     *
     * @param int $user_id User ID
     * @return bool
     */
    private function is_user_online($user_id) {
        $last_activity = get_user_meta($user_id, 'last_activity', true);
        if (empty($last_activity)) {
            return false;
        }
        
        return strtotime($last_activity) > strtotime('-15 minutes');
    }

    /**
     * Calculate compatibility score
     *
     * @param int $user_id User ID
     * @param array $criteria Search criteria
     * @return int
     */
    private function calculate_compatibility($user_id, $criteria) {
        $score = 0;
        $max_score = 0;
        
        // Age compatibility (weight: 30)
        if (!empty($criteria['preferred_age_min']) || !empty($criteria['preferred_age_max'])) {
            $max_score += 30;
            $user_age = $this->calculate_age($user_id);
            if ($user_age) {
                $min_age = !empty($criteria['preferred_age_min']) ? $criteria['preferred_age_min'] : 18;
                $max_age = !empty($criteria['preferred_age_max']) ? $criteria['preferred_age_max'] : 100;
                
                if ($user_age >= $min_age && $user_age <= $max_age) {
                    $score += 30;
                }
            }
        }
        
        // Interest compatibility (weight: 40)
        if (!empty($criteria['interests'])) {
            $max_score += 40;
            $user_interests = get_user_meta($user_id, 'interests', true);
            if (!empty($user_interests)) {
                $user_interests = is_array($user_interests) ? $user_interests : array($user_interests);
                $search_interests = is_array($criteria['interests']) ? $criteria['interests'] : array($criteria['interests']);
                
                $common_interests = array_intersect($user_interests, $search_interests);
                $compatibility_ratio = count($common_interests) / count($search_interests);
                $score += intval($compatibility_ratio * 40);
            }
        }
        
        // Location compatibility (weight: 20)
        if (!empty($criteria['location'])) {
            $max_score += 20;
            $user_location = get_user_meta($user_id, 'location', true);
            if (!empty($user_location) && stripos($user_location, $criteria['location']) !== false) {
                $score += 20;
            }
        }
        
        // Activity compatibility (weight: 10)
        $max_score += 10;
        if ($this->is_user_online($user_id)) {
            $score += 10;
        } else {
            $last_activity = get_user_meta($user_id, 'last_activity', true);
            if (!empty($last_activity) && strtotime($last_activity) > strtotime('-7 days')) {
                $score += 5;
            }
        }
        
        return $max_score > 0 ? intval(($score / $max_score) * 100) : 0;
    }
}