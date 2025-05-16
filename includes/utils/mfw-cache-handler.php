<?php
/**
 * Cache Handler Class
 * 
 * Manages caching functionality and optimization.
 * Supports multiple cache storage methods and advanced cache management.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Cache_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:36:31';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Cache settings
     */
    private $settings;

    /**
     * Cache storage handler
     */
    private $storage;

    /**
     * Cache groups
     */
    private $groups = [];

    /**
     * Initialize cache handler
     */
    public function __construct() {
        // Load settings
        $this->settings = get_option('mfw_cache_settings', [
            'enabled' => true,
            'storage' => 'transient', // transient, file, redis, memcached
            'expiration' => 3600,
            'prefix' => 'mfw_',
            'compression' => false
        ]);

        // Initialize storage handler
        $this->init_storage();

        // Add cache cleanup hooks
        add_action('mfw_daily_cleanup', [$this, 'cleanup_expired']);
        
        // Add cache invalidation hooks
        add_action('switch_theme', [$this, 'flush_all']);
        add_action('wp_update_nav_menu', [$this, 'flush_group'], 10, 1);
        add_action('activated_plugin', [$this, 'flush_all']);
        add_action('deactivated_plugin', [$this, 'flush_all']);
    }

    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed Cached value or false if not found
     */
    public function get($key, $group = 'default') {
        if (!$this->settings['enabled']) {
            return false;
        }

        try {
            $cache_key = $this->build_key($key, $group);
            $value = $this->storage->get($cache_key);

            if ($value === false) {
                return false;
            }

            // Handle compression
            if ($this->settings['compression'] && is_string($value)) {
                $value = $this->decompress($value);
            }

            return maybe_unserialize($value);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Cache get failed: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Set cached value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param string $group Cache group
     * @param int $expiration Expiration time in seconds
     * @return bool Success status
     */
    public function set($key, $value, $group = 'default', $expiration = null) {
        if (!$this->settings['enabled']) {
            return false;
        }

        try {
            $cache_key = $this->build_key($key, $group);
            $expiration = $expiration ?? $this->settings['expiration'];
            
            // Prepare value for storage
            $value = maybe_serialize($value);

            // Handle compression
            if ($this->settings['compression'] && is_string($value)) {
                $value = $this->compress($value);
            }

            $success = $this->storage->set($cache_key, $value, $expiration);

            if ($success) {
                // Track group membership
                $this->groups[$group][$key] = true;
            }

            return $success;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Cache set failed: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success status
     */
    public function delete($key, $group = 'default') {
        try {
            $cache_key = $this->build_key($key, $group);
            $success = $this->storage->delete($cache_key);

            if ($success) {
                // Remove from group tracking
                unset($this->groups[$group][$key]);
            }

            return $success;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Cache delete failed: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Delete all items in a cache group
     *
     * @param string $group Cache group
     * @return bool Success status
     */
    public function flush_group($group) {
        try {
            if (!isset($this->groups[$group])) {
                return true;
            }

            $success = true;
            foreach (array_keys($this->groups[$group]) as $key) {
                if (!$this->delete($key, $group)) {
                    $success = false;
                }
            }

            return $success;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Cache group flush failed: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Delete all cached items
     *
     * @return bool Success status
     */
    public function flush_all() {
        try {
            $success = $this->storage->flush();

            if ($success) {
                // Reset group tracking
                $this->groups = [];

                // Log flush
                $this->log_cache_flush();
            }

            return $success;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Cache flush failed: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Clean up expired cache items
     */
    public function cleanup_expired() {
        try {
            $cleaned = $this->storage->cleanup();

            // Log cleanup
            if ($cleaned > 0) {
                $this->log_cache_cleanup($cleaned);
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Cache cleanup failed: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Cache stats
     */
    public function get_stats() {
        try {
            return $this->storage->get_stats();

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get cache stats: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Initialize cache storage handler
     */
    private function init_storage() {
        switch ($this->settings['storage']) {
            case 'file':
                $this->storage = new MFW_File_Cache_Storage();
                break;

            case 'redis':
                if (class_exists('Redis')) {
                    $this->storage = new MFW_Redis_Cache_Storage();
                }
                break;

            case 'memcached':
                if (class_exists('Memcached')) {
                    $this->storage = new MFW_Memcached_Cache_Storage();
                }
                break;

            case 'transient':
            default:
                $this->storage = new MFW_Transient_Cache_Storage();
                break;
        }
    }

    /**
     * Build cache key
     *
     * @param string $key Original key
     * @param string $group Cache group
     * @return string Complete cache key
     */
    private function build_key($key, $group) {
        return $this->settings['prefix'] . $group . '_' . md5($key);
    }

    /**
     * Compress data
     *
     * @param string $data Data to compress
     * @return string Compressed data
     */
    private function compress($data) {
        return gzcompress($data);
    }

    /**
     * Decompress data
     *
     * @param string $data Data to decompress
     * @return string Decompressed data
     */
    private function decompress($data) {
        return gzuncompress($data);
    }

    /**
     * Log cache flush
     */
    private function log_cache_flush() {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_cache_log',
                [
                    'action' => 'flush',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log cache flush: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
        }
    }

    /**
     * Log cache cleanup
     *
     * @param int $count Number of items cleaned
     */
    private function log_cache_cleanup($count) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_cache_log',
                [
                    'action' => 'cleanup',
                    'details' => sprintf('Cleaned %d items', $count),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log cache cleanup: %s', $e->getMessage()),
                'cache_handler',
                'error'
            );
        }
    }
}