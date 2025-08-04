<?php
/**
 * WPMatch Cache Manager
 *
 * @package WPMatch
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache management class for WPMatch
 */
class WPMatch_Cache {

    /**
     * Cache group for WPMatch
     */
    const CACHE_GROUP = 'wpmatch';

    /**
     * Default cache expiration (1 hour)
     */
    const DEFAULT_EXPIRATION = 3600;

    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed|false
     */
    public static function get($key, $group = self::CACHE_GROUP) {
        return wp_cache_get($key, $group);
    }

    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time in seconds
     * @param string $group Cache group
     * @return bool
     */
    public static function set($key, $data, $expiration = self::DEFAULT_EXPIRATION, $group = self::CACHE_GROUP) {
        return wp_cache_set($key, $data, $group, $expiration);
    }

    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool
     */
    public static function delete($key, $group = self::CACHE_GROUP) {
        return wp_cache_delete($key, $group);
    }

    /**
     * Flush all WPMatch cache
     *
     * @return bool
     */
    public static function flush() {
        return wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Get or set cached data with callback
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate data if not cached
     * @param int $expiration Expiration time in seconds
     * @param string $group Cache group
     * @return mixed
     */
    public static function remember($key, $callback, $expiration = self::DEFAULT_EXPIRATION, $group = self::CACHE_GROUP) {
        $data = self::get($key, $group);
        
        if ($data === false) {
            $data = call_user_func($callback);
            self::set($key, $data, $expiration, $group);
        }
        
        return $data;
    }

    /**
     * Cache user profile data
     *
     * @param int $user_id User ID
     * @param array $profile_data Profile data
     * @return bool
     */
    public static function cache_user_profile($user_id, $profile_data) {
        return self::set("user_profile_{$user_id}", $profile_data, self::DEFAULT_EXPIRATION);
    }

    /**
     * Get cached user profile
     *
     * @param int $user_id User ID
     * @return array|false
     */
    public static function get_user_profile($user_id) {
        return self::get("user_profile_{$user_id}");
    }

    /**
     * Clear user profile cache
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function clear_user_profile($user_id) {
        return self::delete("user_profile_{$user_id}");
    }

    /**
     * Cache search results
     *
     * @param string $search_hash Hash of search parameters
     * @param array $results Search results
     * @param int $expiration Expiration time
     * @return bool
     */
    public static function cache_search_results($search_hash, $results, $expiration = 1800) {
        return self::set("search_results_{$search_hash}", $results, $expiration);
    }

    /**
     * Get cached search results
     *
     * @param string $search_hash Hash of search parameters
     * @return array|false
     */
    public static function get_search_results($search_hash) {
        return self::get("search_results_{$search_hash}");
    }
}