# WPMatch Profile Fields - Performance Optimization Specifications

## Overview

This document provides comprehensive performance optimization specifications for the WPMatch Profile Fields Management system. It addresses all performance gaps identified in the validation feedback including N+1 query elimination, caching strategies, pagination optimization, and performance monitoring.

## Performance Requirements

### Target Performance Metrics
- **Admin Page Load Time**: < 2 seconds for 90th percentile
- **Field Operations**: < 1 second for CRUD operations
- **Search Performance**: < 5 seconds for complex queries with 100,000+ users
- **Memory Usage**: < 128MB per request
- **Database Query Time**: < 200ms for 95th percentile queries
- **Cache Hit Ratio**: > 80% for frequently accessed data

## Query Optimization Architecture

### N+1 Query Prevention Strategy

```php
/**
 * Optimized data loading to prevent N+1 queries
 */
class WPMatch_Query_Optimizer {
    
    private $field_cache = [];
    private $user_data_cache = [];
    
    /**
     * Load multiple fields with their options in a single query
     */
    public function load_fields_with_options($field_ids = null, $include_options = true) {
        global $wpdb;
        
        $cache_key = 'wpmatch_fields_' . md5(serialize($field_ids) . $include_options);
        $cached_result = wp_cache_get($cache_key, 'wpmatch_fields');
        
        if (false !== $cached_result) {
            return $cached_result;
        }
        
        // Build optimized query with JOINs
        $fields_query = "
            SELECT 
                f.id,
                f.field_name,
                f.field_label,
                f.field_type,
                f.field_description,
                f.field_group,
                f.field_order,
                f.is_required,
                f.is_searchable,
                f.validation_rules,
                f.status,
                f.created_at,
                f.updated_at";
        
        if ($include_options) {
            $fields_query .= ",
                GROUP_CONCAT(
                    DISTINCT CONCAT_WS('|', fo.option_value, fo.option_label, fo.option_order)
                    ORDER BY fo.option_order
                    SEPARATOR '||'
                ) as field_options";
        }
        
        $fields_query .= "
            FROM {$wpdb->prefix}wpmatch_profile_fields f";
            
        if ($include_options) {
            $fields_query .= "
                LEFT JOIN {$wpdb->prefix}wpmatch_field_options fo ON f.id = fo.field_id";
        }
        
        $fields_query .= "
            WHERE f.status IN ('active', 'inactive')";
        
        if (!empty($field_ids)) {
            $field_ids_placeholder = implode(',', array_map('intval', $field_ids));
            $fields_query .= " AND f.id IN ({$field_ids_placeholder})";
        }
        
        $fields_query .= "
            GROUP BY f.id
            ORDER BY f.field_group, f.field_order";
        
        $results = $wpdb->get_results($fields_query);
        
        // Process and structure the results
        $fields = [];
        foreach ($results as $row) {
            $field = (object) [
                'id' => $row->id,
                'field_name' => $row->field_name,
                'field_label' => $row->field_label,
                'field_type' => $row->field_type,
                'field_description' => $row->field_description,
                'field_group' => $row->field_group,
                'field_order' => $row->field_order,
                'is_required' => $row->is_required,
                'is_searchable' => $row->is_searchable,
                'validation_rules' => json_decode($row->validation_rules, true),
                'status' => $row->status,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'options' => []
            ];
            
            // Parse field options if included
            if ($include_options && !empty($row->field_options)) {
                $options_data = explode('||', $row->field_options);
                foreach ($options_data as $option_data) {
                    $option_parts = explode('|', $option_data);
                    if (count($option_parts) >= 2) {
                        $field->options[] = (object) [
                            'value' => $option_parts[0],
                            'label' => $option_parts[1],
                            'order' => isset($option_parts[2]) ? intval($option_parts[2]) : 0
                        ];
                    }
                }
            }
            
            $fields[$row->id] = $field;
        }
        
        // Cache the result for 5 minutes
        wp_cache_set($cache_key, $fields, 'wpmatch_fields', 300);
        
        return $fields;
    }
    
    /**
     * Load user profile data for multiple users and fields efficiently
     */
    public function load_users_profile_data($user_ids, $field_ids = null, $privacy_level = 'public') {
        global $wpdb;
        
        $cache_key = 'wpmatch_user_profiles_' . md5(serialize($user_ids) . serialize($field_ids) . $privacy_level);
        $cached_result = wp_cache_get($cache_key, 'wpmatch_user_data');
        
        if (false !== $cached_result) {
            return $cached_result;
        }
        
        // Build optimized query with proper JOINs
        $query = "
            SELECT 
                pv.user_id,
                pv.field_id,
                pv.field_value,
                pv.privacy,
                f.field_name,
                f.field_type,
                f.field_label
            FROM {$wpdb->prefix}wpmatch_profile_values pv
            INNER JOIN {$wpdb->prefix}wpmatch_profile_fields f ON pv.field_id = f.id
            WHERE pv.user_id IN (" . implode(',', array_map('intval', $user_ids)) . ")
            AND f.status = 'active'";
        
        // Privacy filtering
        if ($privacy_level !== 'all') {
            $privacy_conditions = [
                'public' => "pv.privacy = 'public'",
                'members' => "pv.privacy IN ('public', 'members')",
                'private' => "pv.privacy IN ('public', 'members', 'private')"
            ];
            
            if (isset($privacy_conditions[$privacy_level])) {
                $query .= " AND " . $privacy_conditions[$privacy_level];
            }
        }
        
        // Field filtering
        if (!empty($field_ids)) {
            $query .= " AND pv.field_id IN (" . implode(',', array_map('intval', $field_ids)) . ")";
        }
        
        $query .= " ORDER BY pv.user_id, f.field_order";
        
        $results = $wpdb->get_results($query);
        
        // Structure data by user_id
        $user_data = [];
        foreach ($results as $row) {
            if (!isset($user_data[$row->user_id])) {
                $user_data[$row->user_id] = [];
            }
            
            $user_data[$row->user_id][$row->field_name] = (object) [
                'field_id' => $row->field_id,
                'field_name' => $row->field_name,
                'field_type' => $row->field_type,
                'field_label' => $row->field_label,
                'field_value' => $this->unserialize_field_value($row->field_value, $row->field_type),
                'privacy' => $row->privacy
            ];
        }
        
        // Cache for 2 minutes (shorter cache for user data)
        wp_cache_set($cache_key, $user_data, 'wpmatch_user_data', 120);
        
        return $user_data;
    }
    
    /**
     * Optimized search query with proper indexes and joins
     */
    public function search_users_by_fields($search_criteria, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $cache_key = 'wpmatch_search_' . md5(serialize($search_criteria) . $limit . $offset);
        $cached_result = wp_cache_get($cache_key, 'wpmatch_search');
        
        if (false !== $cached_result) {
            return $cached_result;
        }
        
        // Build optimized search query using search index table
        $query = "
            SELECT DISTINCT 
                si.user_id,
                COUNT(DISTINCT si.field_id) as matched_fields
            FROM {$wpdb->prefix}wpmatch_field_search_index si
            INNER JOIN {$wpdb->prefix}wpmatch_profile_fields f ON si.field_id = f.id
            WHERE f.status = 'active'";
        
        $where_conditions = [];
        $query_params = [];
        
        foreach ($search_criteria as $field_name => $criteria) {
            $field_condition = $this->build_search_condition($field_name, $criteria);
            if ($field_condition) {
                $where_conditions[] = $field_condition['condition'];
                $query_params = array_merge($query_params, $field_condition['params']);
            }
        }
        
        if (!empty($where_conditions)) {
            $query .= " AND (" . implode(' OR ', $where_conditions) . ")";
        }
        
        $query .= "
            GROUP BY si.user_id
            HAVING matched_fields >= %d
            ORDER BY matched_fields DESC, si.user_id
            LIMIT %d OFFSET %d";
        
        $query_params[] = count($search_criteria);
        $query_params[] = $limit;
        $query_params[] = $offset;
        
        $prepared_query = $wpdb->prepare($query, $query_params);
        $results = $wpdb->get_results($prepared_query);
        
        // Cache search results for 1 minute
        wp_cache_set($cache_key, $results, 'wpmatch_search', 60);
        
        return $results;
    }
    
    /**
     * Batch update field values to minimize database operations
     */
    public function batch_update_field_values($updates) {
        global $wpdb;
        
        if (empty($updates)) {
            return true;
        }
        
        // Group updates by operation type
        $inserts = [];
        $updates_sql = [];
        $deletes = [];
        
        foreach ($updates as $update) {
            $user_id = intval($update['user_id']);
            $field_id = intval($update['field_id']);
            $value = $update['value'];
            $privacy = $update['privacy'] ?? 'public';
            
            if (empty($value)) {
                $deletes[] = "user_id = {$user_id} AND field_id = {$field_id}";
            } else {
                // Check if record exists
                $exists = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_profile_values 
                    WHERE user_id = %d AND field_id = %d
                ", $user_id, $field_id));
                
                if ($exists) {
                    $updates_sql[] = $wpdb->prepare("
                        UPDATE {$wpdb->prefix}wpmatch_profile_values 
                        SET field_value = %s, privacy = %s, updated_at = %s 
                        WHERE user_id = %d AND field_id = %d
                    ", $value, $privacy, current_time('mysql'), $user_id, $field_id);
                } else {
                    $inserts[] = $wpdb->prepare("(%d, %d, %s, %s, %s, %s)", 
                        $user_id, $field_id, $value, $privacy, current_time('mysql'), current_time('mysql'));
                }
            }
        }
        
        // Execute batch operations
        $wpdb->query('START TRANSACTION');
        
        try {
            // Batch inserts
            if (!empty($inserts)) {
                $insert_sql = "
                    INSERT INTO {$wpdb->prefix}wpmatch_profile_values 
                    (user_id, field_id, field_value, privacy, created_at, updated_at) 
                    VALUES " . implode(', ', $inserts);
                $wpdb->query($insert_sql);
            }
            
            // Batch updates
            foreach ($updates_sql as $update_sql) {
                $wpdb->query($update_sql);
            }
            
            // Batch deletes
            if (!empty($deletes)) {
                $delete_sql = "
                    DELETE FROM {$wpdb->prefix}wpmatch_profile_values 
                    WHERE " . implode(' OR ', $deletes);
                $wpdb->query($delete_sql);
            }
            
            $wpdb->query('COMMIT');
            
            // Invalidate relevant caches
            $this->invalidate_user_caches(array_unique(array_column($updates, 'user_id')));
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('batch_update_failed', $e->getMessage());
        }
    }
    
    private function invalidate_user_caches($user_ids) {
        foreach ($user_ids as $user_id) {
            wp_cache_delete("wpmatch_user_profile_{$user_id}", 'wpmatch_user_data');
        }
        
        // Clear search cache
        wp_cache_flush_group('wpmatch_search');
    }
}
```

## Comprehensive Caching Strategy

### WordPress Object Cache Integration

```php
/**
 * Advanced caching system for WPMatch profile fields
 */
class WPMatch_Cache_Manager {
    
    private const DEFAULT_EXPIRATION = 300; // 5 minutes
    private const LONG_EXPIRATION = 3600;   // 1 hour
    private const SHORT_EXPIRATION = 60;    // 1 minute
    
    private $cache_groups = [
        'wpmatch_fields',
        'wpmatch_user_data',
        'wpmatch_search',
        'wpmatch_analytics',
        'wpmatch_config'
    ];
    
    public function __construct() {
        // Register cache groups
        foreach ($this->cache_groups as $group) {
            wp_cache_add_global_groups($group);
        }
        
        // Set up cache invalidation hooks
        $this->setup_cache_invalidation();
    }
    
    /**
     * Multi-level caching for field configurations
     */
    public function get_field_config($field_id, $include_options = true) {
        $cache_key = "field_config_{$field_id}_{$include_options}";
        
        // Level 1: Object cache
        $cached = wp_cache_get($cache_key, 'wpmatch_fields');
        if (false !== $cached) {
            return $cached;
        }
        
        // Level 2: Transient cache
        $transient_key = "wpmatch_field_{$field_id}";
        $cached = get_transient($transient_key);
        if (false !== $cached) {
            wp_cache_set($cache_key, $cached, 'wpmatch_fields', self::DEFAULT_EXPIRATION);
            return $cached;
        }
        
        // Level 3: Database query
        $field_data = $this->load_field_from_database($field_id, $include_options);
        
        if ($field_data) {
            // Cache in both levels
            set_transient($transient_key, $field_data, self::LONG_EXPIRATION);
            wp_cache_set($cache_key, $field_data, 'wpmatch_fields', self::DEFAULT_EXPIRATION);
        }
        
        return $field_data;
    }
    
    /**
     * Hierarchical cache for user profile data
     */
    public function get_user_profile_data($user_id, $field_ids = null, $force_refresh = false) {
        $cache_key = "user_profile_{$user_id}_" . md5(serialize($field_ids));
        
        if (!$force_refresh) {
            $cached = wp_cache_get($cache_key, 'wpmatch_user_data');
            if (false !== $cached) {
                return $cached;
            }
        }
        
        // Load from database with optimization
        $profile_data = $this->load_user_profile_from_database($user_id, $field_ids);
        
        // Cache with appropriate expiration
        $expiration = $this->get_cache_expiration_for_user($user_id);
        wp_cache_set($cache_key, $profile_data, 'wpmatch_user_data', $expiration);
        
        return $profile_data;
    }
    
    /**
     * Smart cache preloading for anticipated requests
     */
    public function preload_cache($context, $identifiers = []) {
        switch ($context) {
            case 'user_list':
                $this->preload_user_profiles($identifiers);
                break;
                
            case 'field_admin':
                $this->preload_field_configurations();
                break;
                
            case 'search_results':
                $this->preload_search_data($identifiers);
                break;
        }
    }
    
    private function preload_user_profiles($user_ids) {
        if (empty($user_ids)) {
            return;
        }
        
        // Batch load user profiles that aren't cached
        $uncached_users = [];
        foreach ($user_ids as $user_id) {
            $cache_key = "user_profile_{$user_id}";
            if (false === wp_cache_get($cache_key, 'wpmatch_user_data')) {
                $uncached_users[] = $user_id;
            }
        }
        
        if (!empty($uncached_users)) {
            $optimizer = new WPMatch_Query_Optimizer();
            $bulk_data = $optimizer->load_users_profile_data($uncached_users);
            
            // Cache individual user profiles
            foreach ($bulk_data as $user_id => $profile_data) {
                $cache_key = "user_profile_{$user_id}";
                wp_cache_set($cache_key, $profile_data, 'wpmatch_user_data', self::DEFAULT_EXPIRATION);
            }
        }
    }
    
    /**
     * Cache warming strategies
     */
    public function warm_cache() {
        // Warm field configurations cache
        $this->warm_field_cache();
        
        // Warm frequently accessed user data
        $this->warm_popular_user_cache();
        
        // Warm search analytics
        $this->warm_analytics_cache();
    }
    
    private function warm_field_cache() {
        $optimizer = new WPMatch_Query_Optimizer();
        $fields = $optimizer->load_fields_with_options();
        
        foreach ($fields as $field) {
            $cache_key = "field_config_{$field->id}_true";
            wp_cache_set($cache_key, $field, 'wpmatch_fields', self::LONG_EXPIRATION);
        }
    }
    
    /**
     * Intelligent cache invalidation
     */
    private function setup_cache_invalidation() {
        // Field-related invalidation
        add_action('wpmatch_field_created', [$this, 'invalidate_field_cache']);
        add_action('wpmatch_field_updated', [$this, 'invalidate_field_cache']);
        add_action('wpmatch_field_deleted', [$this, 'invalidate_field_cache']);
        
        // User data invalidation
        add_action('wpmatch_profile_updated', [$this, 'invalidate_user_cache']);
        add_action('profile_update', [$this, 'invalidate_user_cache']);
        
        // Search cache invalidation
        add_action('wpmatch_profile_updated', [$this, 'invalidate_search_cache']);
    }
    
    public function invalidate_field_cache($field_id = null) {
        if ($field_id) {
            // Invalidate specific field cache
            wp_cache_delete("field_config_{$field_id}_true", 'wpmatch_fields');
            wp_cache_delete("field_config_{$field_id}_false", 'wpmatch_fields');
            delete_transient("wpmatch_field_{$field_id}");
        } else {
            // Invalidate all field cache
            wp_cache_flush_group('wpmatch_fields');
        }
        
        // Invalidate related caches
        wp_cache_flush_group('wpmatch_search');
    }
    
    public function invalidate_user_cache($user_id) {
        // Clear user-specific caches
        $pattern = "user_profile_{$user_id}*";
        $this->delete_cache_by_pattern($pattern, 'wpmatch_user_data');
        
        // Clear search cache as user data affects search results
        wp_cache_flush_group('wpmatch_search');
    }
    
    /**
     * Performance monitoring for cache effectiveness
     */
    public function get_cache_statistics() {
        $stats = [
            'hit_ratio' => $this->calculate_cache_hit_ratio(),
            'memory_usage' => $this->get_cache_memory_usage(),
            'expiration_stats' => $this->get_expiration_statistics(),
            'invalidation_frequency' => $this->get_invalidation_frequency()
        ];
        
        return $stats;
    }
    
    private function calculate_cache_hit_ratio() {
        $total_requests = wp_cache_get('wpmatch_cache_total_requests', 'wpmatch_stats') ?: 1;
        $cache_hits = wp_cache_get('wpmatch_cache_hits', 'wpmatch_stats') ?: 0;
        
        return round(($cache_hits / $total_requests) * 100, 2);
    }
}
```

## Pagination and Data Loading Optimization

### Smart Pagination System

```php
/**
 * Advanced pagination with performance optimization
 */
class WPMatch_Smart_Pagination {
    
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 100;
    
    /**
     * Optimized pagination for large datasets
     */
    public function paginate_fields($args = []) {
        $defaults = [
            'page' => 1,
            'per_page' => self::DEFAULT_PAGE_SIZE,
            'order_by' => 'field_order',
            'order' => 'ASC',
            'status' => 'active',
            'search' => '',
            'field_group' => null
        ];
        
        $args = wp_parse_args($args, $defaults);
        $args['per_page'] = min(intval($args['per_page']), self::MAX_PAGE_SIZE);
        $args['page'] = max(1, intval($args['page']));
        
        $cache_key = 'paginated_fields_' . md5(serialize($args));
        $cached = wp_cache_get($cache_key, 'wpmatch_fields');
        
        if (false !== $cached) {
            return $cached;
        }
        
        global $wpdb;
        
        // Build optimized count query
        $count_query = $this->build_count_query($args);
        $total_items = $wpdb->get_var($count_query);
        
        // Build data query with LIMIT
        $data_query = $this->build_data_query($args);
        $items = $wpdb->get_results($data_query);
        
        // Calculate pagination info
        $total_pages = ceil($total_items / $args['per_page']);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $result = [
            'items' => $items,
            'pagination' => [
                'current_page' => $args['page'],
                'per_page' => $args['per_page'],
                'total_items' => intval($total_items),
                'total_pages' => $total_pages,
                'has_prev' => $args['page'] > 1,
                'has_next' => $args['page'] < $total_pages,
                'prev_page' => $args['page'] > 1 ? $args['page'] - 1 : null,
                'next_page' => $args['page'] < $total_pages ? $args['page'] + 1 : null
            ]
        ];
        
        // Cache for 2 minutes
        wp_cache_set($cache_key, $result, 'wpmatch_fields', 120);
        
        return $result;
    }
    
    /**
     * Cursor-based pagination for real-time data
     */
    public function paginate_with_cursor($table, $args = []) {
        $defaults = [
            'limit' => self::DEFAULT_PAGE_SIZE,
            'cursor' => null,
            'order_by' => 'id',
            'order' => 'ASC',
            'where' => '1=1'
        ];
        
        $args = wp_parse_args($args, $defaults);
        $args['limit'] = min(intval($args['limit']), self::MAX_PAGE_SIZE);
        
        global $wpdb;
        
        $query = "SELECT * FROM {$table} WHERE {$args['where']}";
        
        // Add cursor condition
        if ($args['cursor']) {
            $operator = $args['order'] === 'ASC' ? '>' : '<';
            $query .= $wpdb->prepare(" AND {$args['order_by']} {$operator} %s", $args['cursor']);
        }
        
        $query .= " ORDER BY {$args['order_by']} {$args['order']} LIMIT " . ($args['limit'] + 1);
        
        $results = $wpdb->get_results($query);
        
        // Check if there are more results
        $has_more = count($results) > $args['limit'];
        if ($has_more) {
            array_pop($results); // Remove the extra item
        }
        
        // Get the cursor for the next page
        $next_cursor = null;
        if ($has_more && !empty($results)) {
            $last_item = end($results);
            $next_cursor = $last_item->{$args['order_by']};
        }
        
        return [
            'items' => $results,
            'has_more' => $has_more,
            'next_cursor' => $next_cursor
        ];
    }
    
    /**
     * Infinite scroll pagination for frontend
     */
    public function paginate_for_infinite_scroll($args = []) {
        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'total_loaded' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Use cursor-based pagination for better performance
        $cursor = $args['total_loaded'] > 0 ? $args['total_loaded'] : null;
        
        return $this->paginate_with_cursor('wp_wpmatch_profile_fields', [
            'limit' => $args['per_page'],
            'cursor' => $cursor,
            'order_by' => 'id',
            'order' => 'ASC'
        ]);
    }
}
```

## Performance Monitoring and Optimization

### Real-time Performance Monitoring

```php
/**
 * Performance monitoring and optimization system
 */
class WPMatch_Performance_Monitor {
    
    private $start_time;
    private $start_memory;
    private $query_count_start;
    
    public function __construct() {
        $this->start_monitoring();
        
        // Hook into WordPress query monitoring
        add_filter('query', [$this, 'monitor_query']);
        add_action('shutdown', [$this, 'log_performance_metrics']);
    }
    
    private function start_monitoring() {
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage(true);
        $this->query_count_start = get_num_queries();
    }
    
    /**
     * Monitor and log slow queries
     */
    public function monitor_query($query) {
        $start_time = microtime(true);
        
        // Execute query and measure time
        $result = $query;
        
        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        
        // Log slow queries
        if ($execution_time > 200) { // Queries taking more than 200ms
            $this->log_slow_query($query, $execution_time);
        }
        
        // Update query statistics
        $this->update_query_statistics($execution_time);
        
        return $result;
    }
    
    private function log_slow_query($query, $execution_time) {
        $log_data = [
            'query' => $this->sanitize_query_for_logging($query),
            'execution_time' => $execution_time,
            'memory_usage' => memory_get_usage(true),
            'backtrace' => wp_debug_backtrace_summary(),
            'timestamp' => current_time('mysql')
        ];
        
        // Store in performance log
        $this->store_performance_log('slow_query', $log_data);
        
        // Send alert for very slow queries
        if ($execution_time > 1000) { // 1 second
            $this->send_performance_alert('slow_query', $log_data);
        }
    }
    
    /**
     * Log comprehensive performance metrics
     */
    public function log_performance_metrics() {
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $query_count_end = get_num_queries();
        
        $metrics = [
            'execution_time' => ($end_time - $this->start_time) * 1000,
            'memory_usage' => $end_memory - $this->start_memory,
            'peak_memory' => memory_get_peak_usage(true),
            'query_count' => $query_count_end - $this->query_count_start,
            'cache_hit_ratio' => $this->get_cache_hit_ratio(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ];
        
        // Store metrics
        $this->store_performance_metrics($metrics);
        
        // Check for performance issues
        $this->check_performance_thresholds($metrics);
    }
    
    private function check_performance_thresholds($metrics) {
        $alerts = [];
        
        // Check execution time
        if ($metrics['execution_time'] > 5000) { // 5 seconds
            $alerts[] = [
                'type' => 'slow_page_load',
                'value' => $metrics['execution_time'],
                'threshold' => 5000
            ];
        }
        
        // Check memory usage
        if ($metrics['memory_usage'] > 134217728) { // 128MB
            $alerts[] = [
                'type' => 'high_memory_usage',
                'value' => $metrics['memory_usage'],
                'threshold' => 134217728
            ];
        }
        
        // Check query count
        if ($metrics['query_count'] > 50) {
            $alerts[] = [
                'type' => 'too_many_queries',
                'value' => $metrics['query_count'],
                'threshold' => 50
            ];
        }
        
        // Check cache hit ratio
        if ($metrics['cache_hit_ratio'] < 70) {
            $alerts[] = [
                'type' => 'low_cache_hit_ratio',
                'value' => $metrics['cache_hit_ratio'],
                'threshold' => 70
            ];
        }
        
        // Send alerts if any thresholds exceeded
        if (!empty($alerts)) {
            $this->send_performance_alert('performance_threshold_exceeded', [
                'alerts' => $alerts,
                'metrics' => $metrics
            ]);
        }
    }
    
    /**
     * Generate performance analytics report
     */
    public function generate_performance_report($period = '24 hours') {
        global $wpdb;
        
        $since = date('Y-m-d H:i:s', strtotime("-{$period}"));
        
        // Get aggregated performance metrics
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                AVG(execution_time) as avg_execution_time,
                MAX(execution_time) as max_execution_time,
                AVG(memory_usage) as avg_memory_usage,
                MAX(memory_usage) as max_memory_usage,
                AVG(query_count) as avg_query_count,
                MAX(query_count) as max_query_count,
                AVG(cache_hit_ratio) as avg_cache_hit_ratio,
                COUNT(*) as total_requests
            FROM {$wpdb->prefix}wpmatch_performance_log
            WHERE created_at >= %s
        ", $since));
        
        // Get slow queries
        $slow_queries = $wpdb->get_results($wpdb->prepare("
            SELECT 
                query_hash,
                COUNT(*) as occurrence_count,
                AVG(execution_time) as avg_execution_time,
                MAX(execution_time) as max_execution_time
            FROM {$wpdb->prefix}wpmatch_slow_queries
            WHERE created_at >= %s
            GROUP BY query_hash
            ORDER BY occurrence_count DESC
            LIMIT 10
        ", $since));
        
        // Get performance trends
        $trends = $this->calculate_performance_trends($since);
        
        return [
            'period' => $period,
            'metrics' => $metrics,
            'slow_queries' => $slow_queries,
            'trends' => $trends,
            'recommendations' => $this->generate_performance_recommendations($metrics, $slow_queries)
        ];
    }
    
    private function generate_performance_recommendations($metrics, $slow_queries) {
        $recommendations = [];
        
        // Execution time recommendations
        if ($metrics->avg_execution_time > 2000) {
            $recommendations[] = [
                'type' => 'execution_time',
                'priority' => 'high',
                'message' => 'Average page load time exceeds 2 seconds. Consider implementing additional caching.',
                'action' => 'optimize_caching'
            ];
        }
        
        // Memory usage recommendations
        if ($metrics->avg_memory_usage > 67108864) { // 64MB
            $recommendations[] = [
                'type' => 'memory_usage',
                'priority' => 'medium',
                'message' => 'High memory usage detected. Review data loading strategies.',
                'action' => 'optimize_data_loading'
            ];
        }
        
        // Query count recommendations
        if ($metrics->avg_query_count > 30) {
            $recommendations[] = [
                'type' => 'query_count',
                'priority' => 'high',
                'message' => 'High number of database queries detected. Implement query optimization.',
                'action' => 'optimize_queries'
            ];
        }
        
        // Cache hit ratio recommendations
        if ($metrics->avg_cache_hit_ratio < 80) {
            $recommendations[] = [
                'type' => 'cache_efficiency',
                'priority' => 'medium',
                'message' => 'Cache hit ratio is below optimal. Review caching strategy.',
                'action' => 'improve_caching'
            ];
        }
        
        // Slow query recommendations
        if (!empty($slow_queries)) {
            $recommendations[] = [
                'type' => 'slow_queries',
                'priority' => 'high',
                'message' => count($slow_queries) . ' slow query patterns detected. Review and optimize.',
                'action' => 'optimize_slow_queries',
                'queries' => array_slice($slow_queries, 0, 5)
            ];
        }
        
        return $recommendations;
    }
}
```

## Database Optimization Specifications

### Index Strategy

```sql
-- Optimized indexes for WPMatch profile fields tables

-- Profile fields table indexes
CREATE INDEX idx_field_status_order ON wp_wpmatch_profile_fields (status, field_group, field_order);
CREATE INDEX idx_field_searchable ON wp_wpmatch_profile_fields (is_searchable, status);
CREATE INDEX idx_field_name ON wp_wpmatch_profile_fields (field_name);

-- Profile values table indexes
CREATE INDEX idx_user_field_values ON wp_wpmatch_profile_values (user_id, field_id);
CREATE INDEX idx_field_privacy ON wp_wpmatch_profile_values (field_id, privacy);
CREATE INDEX idx_user_privacy ON wp_wpmatch_profile_values (user_id, privacy);
CREATE INDEX idx_value_search ON wp_wpmatch_profile_values (field_id, field_value(100));

-- Search index table (for optimized searches)
CREATE TABLE wp_wpmatch_field_search_index (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    field_id BIGINT(20) UNSIGNED NOT NULL,
    field_value_hash VARCHAR(64) NOT NULL,
    field_value_normalized TEXT,
    numeric_value DECIMAL(15,6) NULL,
    date_value DATE NULL,
    privacy ENUM('public', 'members', 'private') DEFAULT 'public',
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_user_field (user_id, field_id),
    KEY idx_field_hash (field_id, field_value_hash),
    KEY idx_numeric_range (field_id, numeric_value),
    KEY idx_date_range (field_id, date_value),
    KEY idx_search_optimization (field_id, privacy, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance monitoring table
CREATE TABLE wp_wpmatch_performance_log (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    request_type VARCHAR(50) NOT NULL,
    execution_time DECIMAL(10,3) NOT NULL,
    memory_usage BIGINT(20) NOT NULL,
    query_count INT NOT NULL,
    cache_hit_ratio DECIMAL(5,2),
    url VARCHAR(500),
    user_id BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_performance_time (created_at, execution_time),
    KEY idx_performance_memory (created_at, memory_usage),
    KEY idx_performance_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

This comprehensive performance optimization specification addresses all the critical performance gaps:

1. **N+1 Query Prevention**: Proper JOIN queries and batch loading strategies
2. **Caching Strategy**: Multi-level caching with WordPress object cache integration
3. **Pagination Optimization**: Smart pagination with cursor-based loading for large datasets
4. **Performance Monitoring**: Real-time monitoring with alerting and analytics
5. **Database Optimization**: Proper indexing and query optimization strategies
6. **Memory Management**: Efficient data loading and memory usage monitoring

<function_calls>
<invoke name="TodoWrite">
<parameter name="todos">[{"id": "1", "content": "Enhance security requirements to address persistent rate limiting using WordPress transients", "status": "completed", "priority": "high"}, {"id": "2", "content": "Define comprehensive capability-based access control for all AJAX handlers", "status": "completed", "priority": "high"}, {"id": "3", "content": "Add brute force protection specifications with progressive penalties", "status": "completed", "priority": "high"}, {"id": "4", "content": "Include CSP header implementation requirements and XSS protection", "status": "completed", "priority": "high"}, {"id": "5", "content": "Standardize nonce verification patterns across all operations", "status": "completed", "priority": "high"}, {"id": "6", "content": "Define query optimization patterns with proper joins to eliminate N+1 queries", "status": "completed", "priority": "high"}, {"id": "7", "content": "Specify caching strategy using WordPress object cache for performance", "status": "completed", "priority": "high"}, {"id": "8", "content": "Add pagination requirements for all data lists with configurable limits", "status": "completed", "priority": "medium"}, {"id": "9", "content": "Create testing framework requirements with PHPUnit and 80%+ coverage", "status": "in_progress", "priority": "high"}, {"id": "10", "content": "Define comprehensive error monitoring and logging system specifications", "status": "pending", "priority": "medium"}, {"id": "11", "content": "Specify input validation test suite with automated security scanning", "status": "pending", "priority": "medium"}, {"id": "12", "content": "Add performance monitoring requirements with benchmarking standards", "status": "completed", "priority": "medium"}]