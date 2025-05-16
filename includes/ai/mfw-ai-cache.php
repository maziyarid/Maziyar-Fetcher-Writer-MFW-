<?php
namespace MFW\AI;

class AICache {
    private $cache_dir;
    private $expiration;
    private $enabled;

    /**
     * Initialize cache system
     */
    public function __construct() {
        $this->enabled = get_option('mfw_cache_enabled', true);
        $this->expiration = get_option('mfw_cache_expiration', 3600); // 1 hour default
        
        $upload_dir = wp_upload_dir();
        $this->cache_dir = $upload_dir['basedir'] . '/mfw/cache';
        
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    /**
     * Set cache value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Optional. Time until expiration in seconds
     * @return bool Success status
     */
    public function set($key, $value, $expiration = null) {
        if (!$this->enabled) {
            return false;
        }

        $expiration = $expiration ?? $this->expiration;
        
        $cache_data = [
            'value' => $value,
            'expires' => time() + $expiration
        ];

        $file = $this->get_cache_file($key);
        return file_put_contents($file, serialize($cache_data)) !== false;
    }

    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }

        $file = $this->get_cache_file($key);
        
        if (!file_exists($file)) {
            return null;
        }

        $cache_data = unserialize(file_get_contents($file));
        
        if (!$cache_data || time() > $cache_data['expires']) {
            unlink($file);
            return null;
        }

        return $cache_data['value'];
    }

    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete($key) {
        $file = $this->get_cache_file($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * Flush all cache
     *
     * @return bool Success status
     */
    public function flush() {
        if (!is_dir($this->cache_dir)) {
            return true;
        }

        $files = glob($this->cache_dir . '/*');
        
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool Whether key exists and is valid
     */
    public function exists($key) {
        if (!$this->enabled) {
            return false;
        }

        $file = $this->get_cache_file($key);
        
        if (!file_exists($file)) {
            return false;
        }

        $cache_data = unserialize(file_get_contents($file));
        
        if (!$cache_data || time() > $cache_data['expires']) {
            unlink($file);
            return false;
        }

        return true;
    }

    /**
     * Get cache file path for key
     *
     * @param string $key Cache key
     * @return string File path
     */
    private function get_cache_file($key) {
        return $this->cache_dir . '/' . md5($key) . '.cache';
    }

    /**
     * Clean expired cache files
     *
     * @return bool Success status
     */
    public function clean() {
        if (!is_dir($this->cache_dir)) {
            return true;
        }

        $files = glob($this->cache_dir . '/*');
        
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $cache_data = unserialize(file_get_contents($file));
            
            if (!$cache_data || time() > $cache_data['expires']) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_stats() {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'expired_files' => 0,
            'enabled' => $this->enabled,
            'expiration' => $this->expiration
        ];

        if (!is_dir($this->cache_dir)) {
            return $stats;
        }

        $files = glob($this->cache_dir . '/*');
        
        if ($files === false) {
            return $stats;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $stats['total_files']++;
            $stats['total_size'] += filesize($file);

            $cache_data = unserialize(file_get_contents($file));
            
            if (!$cache_data || time() > $cache_data['expires']) {
                $stats['expired_files']++;
            }
        }

        return $stats;
    }
}