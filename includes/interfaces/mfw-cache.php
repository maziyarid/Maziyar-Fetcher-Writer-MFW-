<?php
/**
 * Cache Interface
 * 
 * Defines the contract for cache operations.
 * Ensures consistent caching behavior across different implementations.
 *
 * @package MFW
 * @subpackage Interfaces
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

interface MFW_Cache_Interface {
    /**
     * Get cached item
     *
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed Cached value
     */
    public function get($key, $default = null);

    /**
     * Set cached item
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Whether operation was successful
     */
    public function set($key, $value, $ttl = null);

    /**
     * Delete cached item
     *
     * @param string $key Cache key
     * @return bool Whether operation was successful
     */
    public function delete($key);

    /**
     * Check if cached item exists
     *
     * @param string $key Cache key
     * @return bool Whether item exists
     */
    public function has($key);

    /**
     * Clear all cached items
     *
     * @return bool Whether operation was successful
     */
    public function clear();

    /**
     * Get multiple cached items
     *
     * @param array $keys Cache keys
     * @return array Cached values
     */
    public function get_many(array $keys);

    /**
     * Set multiple cached items
     *
     * @param array $values Values to cache
     * @param int $ttl Time to live in seconds
     * @return bool Whether operation was successful
     */
    public function set_many(array $values, $ttl = null);

    /**
     * Delete multiple cached items
     *
     * @param array $keys Cache keys
     * @return bool Whether operation was successful
     */
    public function delete_many(array $keys);

    /**
     * Increment numeric item
     *
     * @param string $key Cache key
     * @param int $offset Increment by
     * @return int|bool New value or false on failure
     */
    public function increment($key, $offset = 1);

    /**
     * Decrement numeric item
     *
     * @param string $key Cache key
     * @param int $offset Decrement by
     * @return int|bool New value or false on failure
     */
    public function decrement($key, $offset = 1);
}