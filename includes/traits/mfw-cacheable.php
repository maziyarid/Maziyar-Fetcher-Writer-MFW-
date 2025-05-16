<?php
/**
 * Cacheable Trait
 * 
 * Implements caching functionality for classes.
 * Provides methods for storing and retrieving cached data with TTL support.
 *
 * @package MFW
 * @subpackage Traits
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

trait MFW_Cacheable {
    /**
     * Cache prefix
     *
     * @var string
     */
    private $cache_prefix = 'mfw_';

    /**
     * Default TTL in seconds
     *
     * @var int
     */
    private $default_ttl = 3600;

    /**
     * Cache hits counter
     *
     * @var int
     */
    private $cache_hits = 0;

    /**
     * Cache misses counter
     *
     * @var int
     */
    private $cache_misses = 0;

    /**
     * Last cache operation timestamp
     *
     * @var string
     */
    private $last_cache_operation = '2025-05-14 06:28:01';

    /**
     * Last cache operator
     *
     * @var string
     */
    private $last_cache_operator = 'maziyarid';

    /**
     * Initialize cache
     *
     * @param string $prefix Cache prefix
     * @param int $ttl Default TTL
     * @return void
     */
    protected function init_cache($prefix = '', $ttl = null) {
        if ($prefix) {
            $this->cache_prefix = $prefix;
        }
        if ($ttl !== null) {
            $this->default_ttl = $ttl;
        }
    }

    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value
     */
    protected function get_cache($key) {
        $full_key = $this->get_full_key($key);
        $cached = wp_cache_get($full_key);

        if ($cached === false) {
            $this->cache_misses++;
            $this->log_cache_operation('miss', $key);
            return null;
        }

        $this->cache_hits++;
        $this->log_cache_operation('hit', $key);
        return $cached;
    }

    /**
     * Set cached value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Whether operation was successful
     */
    protected function set_cache($key, $value, $ttl = null) {
        $full_key = $this->get_full_key($key);
        $ttl = $ttl ?? $this->default_ttl;

        $result = wp_cache_set($full_key, $value, '', $ttl);
        $this->log_cache_operation('set', $key);

        return $result;
    }

    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @return bool Whether operation was successful
     */
    protected function delete_cache($key) {
        $full_key = $this->get_full_key($key);
        $result = wp_cache_delete($full_key);
        $this->log_cache_operation('delete', $key);

        return $result;
    }

    /**
     * Clear all cached values for this instance
     *
     * @return bool Whether operation was successful
     */
    protected function clear_cache() {
        global $wp_object_cache;

        if (!$wp_object_cache) {
            return false;
        }

        $cache_keys = $this->get_cache_keys();
        foreach ($cache_keys as $key) {
            $this->delete_cache($key);
        }

        $this->log_cache_operation('clear', '*');
        return true;
    }

    /**
     * Get full cache key
     *
     * @param string $key Cache key
     * @return string Full cache key
     */
    private function get_full_key($key) {
        return $this->cache_prefix . get_class($this) . '_' . $key;
    }

    /**
     * Get all cache keys for this instance
     *
     * @return array Cache keys
     */
    private function get_cache_keys() {
        global $wp_object_cache;

        if (!$wp_object_cache || !property_exists($wp_object_cache, 'cache')) {
            return [];
        }

        $prefix = $this->cache_prefix . get_class($this) . '_';
        $keys = [];

        foreach ($wp_object_cache->cache as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $keys[] = substr($key, strlen($prefix));
            }
        }

        return $keys;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        return [
            'hits' => $this->cache_hits,
            'misses' => $this->cache_misses,
            'hit_ratio' => $this->calculate_hit_ratio(),
            'keys' => count($this->get_cache_keys()),
            'last_operation' => $this->last_cache_operation,
            'last_operator' => $this->last_cache_operator
        ];
    }

    /**
     * Calculate cache hit ratio
     *
     * @return float Hit ratio
     */
    private function calculate_hit_ratio() {
        $total = $this->cache_hits + $this->cache_misses;
        if ($total === 0) {
            return 0.0;
        }
        return round($this->cache_hits / $total, 4);
    }

    /**
     * Log cache operation
     *
     * @param string $operation Operation type
     * @param string $key Cache key
     */
    private function log_cache_operation($operation, $key) {
        try {
            global $wpdb;

            $this->last_cache_operation = current_time('mysql');
            $this->last_cache_operator = wp_get_current_user()->user_login;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_cache_log',
                [
                    'cacheable_class' => get_class($this),
                    'operation' => $operation,
                    'cache_key' => $key,
                    'created_at' => $this->last_cache_operation,
                    'created_by' => $this->last_cache_operator
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            error_log(sprintf('Failed to log cache operation: %s', $e->getMessage()));
        }
    }
}