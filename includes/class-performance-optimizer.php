<?php
/**
 * Performance Optimization for WPMatch
 *
 * @package WPMatch
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPMatch Performance Optimizer class
 * 
 * Handles database optimization, caching strategies, and query optimization
 * to ensure optimal performance even with large datasets.
 */
class WPMatch_Performance_Optimizer {

    /**
     * Cache group for field data
     */
    const CACHE_GROUP = 'wpmatch_fields';

    /**
     * Cache expiration times
     */
    const CACHE_EXPIRY = array(
        'field_config' => 3600,        // 1 hour
        'field_list' => 1800,          // 30 minutes
        'field_groups' => 3600,        // 1 hour
        'field_statistics' => 300,     // 5 minutes
        'user_fields' => 1800,         // 30 minutes
        'field_validation' => 3600     // 1 hour
    );

    /**
     * Database instance
     *
     * @var WPMatch_Database
     */
    private static $database;

    /**
     * Initialize performance optimizations
     */
    public static function init() {
        // Hook into WordPress init
        add_action('init', array(__CLASS__, 'setup_optimizations'), 5);
        
        // Database optimization hooks
        add_action('wpmatch_field_created', array(__CLASS__, 'clear_field_caches'));
        add_action('wpmatch_field_updated', array(__CLASS__, 'clear_field_caches'));
        add_action('wpmatch_field_deleted', array(__CLASS__, 'clear_field_caches'));
        add_action('wpmatch_fields_reordered', array(__CLASS__, 'clear_field_caches'));
        
        // Cache management hooks
        add_action('wp_cache_flush', array(__CLASS__, 'flush_custom_caches'));
        add_action('wpmatch_clear_caches', array(__CLASS__, 'clear_all_caches'));
        
        // Query optimization
        add_filter('posts_clauses', array(__CLASS__, 'optimize_profile_queries'), 10, 2);
        add_action('pre_get_posts', array(__CLASS__, 'optimize_post_queries'));
        
        // Memory optimization
        add_action('wp_footer', array(__CLASS__, 'cleanup_memory'));
        add_action('admin_footer', array(__CLASS__, 'cleanup_memory'));
        
        // Scheduled cache cleanup
        if (!wp_next_scheduled('wpmatch_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wpmatch_cache_cleanup');
        }
        add_action('wpmatch_cache_cleanup', array(__CLASS__, 'scheduled_cache_cleanup'));
    }

    /**
     * Setup performance optimizations
     */
    public static function setup_optimizations() {
        global $wpdb;
        
        // Initialize database reference
        if (function_exists('wpmatch_plugin')) {
            self::$database = wpmatch_plugin()->database;
        }
        
        // Add database indexes if not exist
        self::add_database_indexes();
        
        // Enable query cache for MySQL
        self::optimize_mysql_settings();
        
        // Setup object caching
        self::setup_object_caching();
    }

    /**
     * Add database indexes for optimal performance
     */
    private static function add_database_indexes() {
        global $wpdb;
        
        if (!self::$database) {
            return;
        }

        $profile_fields_table = self::$database->get_table_name('profile_fields');
        $profile_values_table = self::$database->get_table_name('profile_values');
        
        // Define indexes to add
        $indexes = array(
            $profile_fields_table => array(
                'idx_field_status' => 'status',
                'idx_field_type' => 'field_type',
                'idx_field_group' => 'field_group',
                'idx_field_order' => 'field_order',
                'idx_field_searchable' => 'is_searchable',
                'idx_field_public' => 'is_public',
                'idx_field_name_unique' => 'field_name',
                'idx_field_composite' => array('field_group', 'field_order', 'status'),
                'idx_field_active_searchable' => array('status', 'is_searchable'),
                'idx_field_created_date' => 'created_at'
            ),
            $profile_values_table => array(
                'idx_user_field' => array('user_id', 'field_id'),
                'idx_field_value' => 'field_id',
                'idx_user_values' => 'user_id',
                'idx_value_search' => array('field_id', 'field_value(255)'),
                'idx_updated_date' => 'updated_at',
                'idx_composite_lookup' => array('field_id', 'user_id', 'updated_at')
            )
        );

        foreach ($indexes as $table => $table_indexes) {
            foreach ($table_indexes as $index_name => $columns) {
                self::add_index_if_not_exists($table, $index_name, $columns);
            }
        }
    }

    /**
     * Add index if it doesn't exist
     *
     * @param string       $table      Table name
     * @param string       $index_name Index name
     * @param string|array $columns    Column(s) to index
     */
    private static function add_index_if_not_exists($table, $index_name, $columns) {
        global $wpdb;
        
        // Check if index exists
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`");
        $index_exists = false;
        
        foreach ($existing_indexes as $index) {
            if ($index->Key_name === $index_name) {
                $index_exists = true;
                break;
            }
        }
        
        if (!$index_exists) {
            $column_spec = is_array($columns) ? implode(', ', $columns) : $columns;
            $index_type = ($index_name === 'idx_field_name_unique') ? 'UNIQUE' : '';
            
            $sql = "ALTER TABLE `{$table}` ADD {$index_type} INDEX `{$index_name}` ({$column_spec})";
            
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                error_log("WPMatch: Failed to create index {$index_name} on table {$table}: " . $wpdb->last_error);
            } else {
                error_log("WPMatch: Successfully created index {$index_name} on table {$table}");
            }
        }
    }

    /**
     * Optimize MySQL settings for better performance
     */
    private static function optimize_mysql_settings() {
        global $wpdb;
        
        // Only set session variables that are allowed and with proper values
        $session_variables = array(
            'tmp_table_size' => 67108864, // 64MB in bytes
            'max_heap_table_size' => 67108864, // 64MB in bytes
            'join_buffer_size' => 2097152, // 2MB in bytes
            'sort_buffer_size' => 2097152, // 2MB in bytes
        );
        
        foreach ($session_variables as $variable => $value) {
            // Validate variable name to prevent SQL injection
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $variable)) {
                continue;
            }
            
            // Check if we can set this variable
            $current_value = $wpdb->get_var($wpdb->prepare("SHOW VARIABLES LIKE %s", $variable));
            if ($current_value !== null) {
                // Variable names cannot be parameterized in prepare, but we validated it above
                $result = $wpdb->query("SET SESSION `{$variable}` = " . intval($value));
                if ($result === false) {
                    // Log error but don't break execution
                    error_log("WPMatch: Failed to set MySQL variable {$variable} to {$value}");
                }
            }
        }
        
        // Note: query_cache_type and innodb_buffer_pool_size are global variables
        // and cannot be set per session, so we skip them to avoid errors
    }

    /**
     * Setup object caching with fall-back strategies
     */
    private static function setup_object_caching() {
        // Use Redis if available
        if (class_exists('Redis') && defined('WP_REDIS_HOST')) {
            add_action('init', array(__CLASS__, 'setup_redis_caching'));
        }
        // Use Memcached if available
        elseif (class_exists('Memcached') && defined('WP_CACHE_KEY_SALT')) {
            add_action('init', array(__CLASS__, 'setup_memcached_caching'));
        }
        // Fall back to file-based caching
        else {
            add_action('init', array(__CLASS__, 'setup_file_caching'));
        }
    }

    /**
     * Setup Redis caching
     */
    public static function setup_redis_caching() {
        if (!class_exists('Redis')) {
            return;
        }

        try {
            $redis = new Redis();
            $redis->connect(WP_REDIS_HOST, WP_REDIS_PORT ?? 6379);
            
            if (defined('WP_REDIS_PASSWORD') && WP_REDIS_PASSWORD) {
                $redis->auth(WP_REDIS_PASSWORD);
            }
            
            // Test connection
            $redis->ping();
            
            // Store Redis instance for later use
            wp_cache_add_global_groups(array(self::CACHE_GROUP));
            
        } catch (Exception $e) {
            error_log('WPMatch: Redis connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Setup Memcached caching
     */
    public static function setup_memcached_caching() {
        if (!class_exists('Memcached')) {
            return;
        }

        try {
            $memcached = new Memcached();
            $memcached->addServer('localhost', 11211);
            
            // Test connection
            $memcached->set('test_key', 'test_value', 10);
            
            wp_cache_add_global_groups(array(self::CACHE_GROUP));
            
        } catch (Exception $e) {
            error_log('WPMatch: Memcached connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Setup file-based caching
     */
    public static function setup_file_caching() {
        $cache_dir = WP_CONTENT_DIR . '/cache/wpmatch/';
        
        if (!is_dir($cache_dir)) {
            wp_mkdir_p($cache_dir);
            
            // Add .htaccess to protect cache directory
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($cache_dir . '.htaccess', $htaccess_content);
        }
    }

    /**
     * Get cached field configuration
     *
     * @param int $field_id Field ID
     * @return object|false Field object or false if not cached
     */
    public static function get_cached_field($field_id) {
        $cache_key = "field_config_{$field_id}";
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }

    /**
     * Cache field configuration
     *
     * @param int    $field_id Field ID
     * @param object $field    Field object
     */
    public static function cache_field($field_id, $field) {
        $cache_key = "field_config_{$field_id}";
        wp_cache_set($cache_key, $field, self::CACHE_GROUP, self::CACHE_EXPIRY['field_config']);
    }

    /**
     * Get cached field list
     *
     * @param array $args Query arguments
     * @return array|false Field list or false if not cached
     */
    public static function get_cached_field_list($args = array()) {
        $cache_key = "field_list_" . md5(serialize($args));
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }

    /**
     * Cache field list
     *
     * @param array $args   Query arguments
     * @param array $fields Field list
     */
    public static function cache_field_list($args, $fields) {
        $cache_key = "field_list_" . md5(serialize($args));
        wp_cache_set($cache_key, $fields, self::CACHE_GROUP, self::CACHE_EXPIRY['field_list']);
    }

    /**
     * Get cached field groups
     *
     * @return array|false Field groups or false if not cached
     */
    public static function get_cached_field_groups() {
        $cache_key = "field_groups";
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }

    /**
     * Cache field groups
     *
     * @param array $groups Field groups
     */
    public static function cache_field_groups($groups) {
        $cache_key = "field_groups";
        wp_cache_set($cache_key, $groups, self::CACHE_GROUP, self::CACHE_EXPIRY['field_groups']);
    }

    /**
     * Get cached user field values
     *
     * @param int $user_id User ID
     * @return array|false User field values or false if not cached
     */
    public static function get_cached_user_fields($user_id) {
        $cache_key = "user_fields_{$user_id}";
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }

    /**
     * Cache user field values
     *
     * @param int   $user_id User ID
     * @param array $fields  User field values
     */
    public static function cache_user_fields($user_id, $fields) {
        $cache_key = "user_fields_{$user_id}";
        wp_cache_set($cache_key, $fields, self::CACHE_GROUP, self::CACHE_EXPIRY['user_fields']);
    }

    /**
     * Clear field-related caches
     *
     * @param int $field_id Optional specific field ID
     */
    public static function clear_field_caches($field_id = null) {
        // Clear field list caches
        wp_cache_flush_group(self::CACHE_GROUP);
        
        // Clear specific field cache if provided
        if ($field_id) {
            $cache_key = "field_config_{$field_id}";
            wp_cache_delete($cache_key, self::CACHE_GROUP);
        }
        
        // Clear field groups cache
        wp_cache_delete("field_groups", self::CACHE_GROUP);
        
        // Clear field statistics cache
        wp_cache_delete("field_statistics", self::CACHE_GROUP);
    }

    /**
     * Clear user field caches
     *
     * @param int $user_id User ID
     */
    public static function clear_user_field_caches($user_id) {
        $cache_key = "user_fields_{$user_id}";
        wp_cache_delete($cache_key, self::CACHE_GROUP);
    }

    /**
     * Clear all custom caches
     */
    public static function clear_all_caches() {
        wp_cache_flush_group(self::CACHE_GROUP);
        
        // Clear file cache if using file-based caching
        $cache_dir = WP_CONTENT_DIR . '/cache/wpmatch/';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'cache') {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Flush custom caches when WordPress cache is flushed
     */
    public static function flush_custom_caches() {
        self::clear_all_caches();
    }

    /**
     * Optimize profile queries
     *
     * @param array    $clauses Query clauses
     * @param WP_Query $query   WordPress query object
     * @return array Modified clauses
     */
    public static function optimize_profile_queries($clauses, $query) {
        global $wpdb;
        
        // Only optimize our profile queries
        if (!$query->get('wpmatch_profile_query')) {
            return $clauses;
        }

        // Add proper joins for field values
        $profile_values_table = self::$database ? self::$database->get_table_name('profile_values') : null;
        
        if ($profile_values_table) {
            // Use LEFT JOIN instead of subqueries for better performance
            $clauses['join'] .= " LEFT JOIN {$profile_values_table} pv ON pv.user_id = {$wpdb->users}.ID";
            
            // Add indexes hints for MySQL optimizer
            $clauses['join'] .= " USE INDEX (idx_user_values)";
        }

        return $clauses;
    }

    /**
     * Optimize post queries
     *
     * @param WP_Query $query WordPress query object
     */
    public static function optimize_post_queries($query) {
        // Don't modify admin queries
        if (is_admin()) {
            return;
        }

        // Optimize profile-related queries
        if ($query->get('post_type') === 'user_profile') {
            // Limit posts per page to prevent memory issues
            if (!$query->get('posts_per_page')) {
                $query->set('posts_per_page', 20);
            }
            
            // Don't load post content for list views
            if ($query->get('profile_list_view')) {
                $query->set('fields', 'ids');
            }
        }
    }

    /**
     * Optimized field retrieval with caching
     *
     * @param array $args Query arguments
     * @return array Field list
     */
    public static function get_optimized_fields($args = array()) {
        // Try to get from cache first
        $cached_fields = self::get_cached_field_list($args);
        if ($cached_fields !== false) {
            return $cached_fields;
        }

        // If not cached, use optimized query
        global $wpdb;
        
        if (!self::$database) {
            return array();
        }

        $table_name = self::$database->get_table_name('profile_fields');
        
        // Build optimized query with proper indexes
        $where_clauses = array();
        $where_values = array();

        // Use indexed columns in WHERE clause
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }

        if (!empty($args['field_type'])) {
            $where_clauses[] = "field_type = %s";
            $where_values[] = $args['field_type'];
        }

        if (!empty($args['field_group'])) {
            $where_clauses[] = "field_group = %s";
            $where_values[] = $args['field_group'];
        }

        if (!empty($args['is_searchable'])) {
            $where_clauses[] = "is_searchable = 1";
        }

        if (!empty($args['is_public'])) {
            $where_clauses[] = "is_public = 1";
        }

        // Build WHERE clause
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // Use covering index for better performance
        $fields = $args['fields'] ?? '*';
        $orderby = $args['orderby'] ?? 'field_order';
        $order = strtoupper($args['order'] ?? 'ASC');
        
        // Ensure we're using an indexed column for ordering
        if (!in_array($orderby, array('field_order', 'created_at', 'id'))) {
            $orderby = 'field_order';
        }

        // Add query hints for MySQL optimizer
        $sql = "SELECT {$fields} FROM {$table_name} USE INDEX (idx_field_composite) {$where_sql} ORDER BY {$orderby} {$order}";
        
        if (!empty($args['limit'])) {
            $sql .= $wpdb->prepare(" LIMIT %d", $args['limit']);
            
            if (!empty($args['offset'])) {
                $sql .= $wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        $fields = $wpdb->get_results($sql);
        
        // Cache the results
        self::cache_field_list($args, $fields);
        
        return $fields ?: array();
    }

    /**
     * Optimized user field values retrieval
     *
     * @param int   $user_id   User ID
     * @param array $field_ids Optional specific field IDs
     * @return array User field values
     */
    public static function get_optimized_user_fields($user_id, $field_ids = array()) {
        // Try cache first
        if (empty($field_ids)) {
            $cached_values = self::get_cached_user_fields($user_id);
            if ($cached_values !== false) {
                return $cached_values;
            }
        }

        global $wpdb;
        
        if (!self::$database) {
            return array();
        }

        $values_table = self::$database->get_table_name('profile_values');
        $fields_table = self::$database->get_table_name('profile_fields');
        
        // Use JOIN with indexes for better performance
        $sql = "
            SELECT pv.field_id, pv.field_value, pf.field_name, pf.field_type, pf.field_label
            FROM {$values_table} pv
            INNER JOIN {$fields_table} pf ON pv.field_id = pf.id
            WHERE pv.user_id = %d AND pf.status = 'active'
        ";
        
        $params = array($user_id);
        
        if (!empty($field_ids)) {
            $placeholders = implode(',', array_fill(0, count($field_ids), '%d'));
            $sql .= " AND pv.field_id IN ({$placeholders})";
            $params = array_merge($params, $field_ids);
        }
        
        $sql .= " ORDER BY pf.field_order ASC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Organize results
        $user_fields = array();
        foreach ($results as $result) {
            $user_fields[$result->field_name] = array(
                'field_id' => $result->field_id,
                'field_value' => $result->field_value,
                'field_type' => $result->field_type,
                'field_label' => $result->field_label
            );
        }
        
        // Cache if getting all fields
        if (empty($field_ids)) {
            self::cache_user_fields($user_id, $user_fields);
        }
        
        return $user_fields;
    }

    /**
     * Cleanup memory usage
     */
    public static function cleanup_memory() {
        // Clear object cache if function exists and cache is getting too large
        if (function_exists('wp_cache_get_stats')) {
            $cache_stats = wp_cache_get_stats();
            if (is_array($cache_stats) && isset($cache_stats['bytes']) && $cache_stats['bytes'] > 50 * 1024 * 1024) { // 50MB
                wp_cache_flush_group(self::CACHE_GROUP);
            }
        } else {
            // Alternative approach - check memory usage and clear cache periodically
            $memory_usage = memory_get_usage(true);
            if ($memory_usage > 50 * 1024 * 1024) { // 50MB
                wp_cache_flush_group(self::CACHE_GROUP);
            }
        }
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Scheduled cache cleanup
     */
    public static function scheduled_cache_cleanup() {
        // Clear expired caches
        self::clear_all_caches();
        
        // Clean up temporary files
        $temp_dir = WP_CONTENT_DIR . '/cache/wpmatch/temp/';
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            $one_day_ago = time() - DAY_IN_SECONDS;
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $one_day_ago) {
                    unlink($file);
                }
            }
        }
        
        // Optimize database tables
        self::optimize_database_tables();
    }

    /**
     * Optimize database tables
     */
    private static function optimize_database_tables() {
        global $wpdb;
        
        if (!self::$database) {
            return;
        }

        $tables = array(
            self::$database->get_table_name('profile_fields'),
            self::$database->get_table_name('profile_values'),
            self::$database->get_table_name('field_history')
        );

        foreach ($tables as $table) {
            // Only optimize if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                $wpdb->query("OPTIMIZE TABLE {$table}");
            }
        }
    }

    /**
     * Get performance statistics
     *
     * @return array Performance statistics
     */
    public static function get_performance_stats() {
        global $wpdb;
        
        $stats = array(
            'total_queries' => $wpdb->num_queries,
            'cache_hits' => function_exists('wp_cache_get_stats') ? wp_cache_get_stats() : array(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        );
        
        // Get database-specific stats
        if (self::$database) {
            $profile_fields_table = self::$database->get_table_name('profile_fields');
            $profile_values_table = self::$database->get_table_name('profile_values');
            
            $stats['field_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$profile_fields_table}");
            $stats['field_values_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$profile_values_table}");
            
            // Table sizes
            $table_stats = $wpdb->get_results("
                SELECT table_name, table_rows, data_length, index_length
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name IN ('{$profile_fields_table}', '{$profile_values_table}')
            ");
            
            $stats['table_stats'] = $table_stats;
        }
        
        return $stats;
    }

    /**
     * Display performance debug information
     */
    public static function display_debug_info() {
        if (!current_user_can('manage_options') || !WP_DEBUG) {
            return;
        }

        $stats = self::get_performance_stats();
        
        echo '<!-- WPMatch Performance Debug -->';
        echo '<div style="position: fixed; bottom: 10px; right: 10px; background: #000; color: #fff; padding: 10px; font-size: 12px; z-index: 9999;">';
        echo '<strong>WPMatch Performance:</strong><br>';
        echo 'Queries: ' . $stats['total_queries'] . '<br>';
        echo 'Memory: ' . size_format($stats['memory_usage']) . '<br>';
        echo 'Time: ' . round($stats['execution_time'], 4) . 's<br>';
        echo 'Fields: ' . ($stats['field_count'] ?? 'N/A') . '<br>';
        echo '</div>';
    }
}

// Initialize performance optimizations
WPMatch_Performance_Optimizer::init();

// Add debug info in footer if debugging is enabled
if (WP_DEBUG) {
    add_action('wp_footer', array('WPMatch_Performance_Optimizer', 'display_debug_info'));
    add_action('admin_footer', array('WPMatch_Performance_Optimizer', 'display_debug_info'));
}