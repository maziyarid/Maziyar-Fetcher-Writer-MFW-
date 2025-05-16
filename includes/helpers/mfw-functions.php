<?php
/**
 * Framework Helper Functions
 * 
 * Collection of global helper functions for the framework.
 * Provides utility functions for common tasks.
 *
 * @package MFW
 * @subpackage Helpers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mfw')) {
    /**
     * Get framework instance
     *
     * @return MFW Framework instance
     */
    function mfw() {
        return MFW::get_instance();
    }
}

if (!function_exists('mfw_service')) {
    /**
     * Get framework service
     *
     * @param string $name Service name
     * @return mixed Service instance
     */
    function mfw_service($name) {
        return mfw()->services->get($name);
    }
}

if (!function_exists('mfw_config')) {
    /**
     * Get/set configuration value
     *
     * @param string|array $key Configuration key or array of key-value pairs
     * @param mixed $value Configuration value
     * @return mixed Configuration value or success status
     */
    function mfw_config($key = null, $value = null) {
        if (is_null($key)) {
            return mfw()->get_config();
        }

        if (is_array($key)) {
            return mfw()->set_config($key);
        }

        if (!is_null($value)) {
            return mfw()->set_config($key, $value);
        }

        return mfw()->get_config($key);
    }
}

if (!function_exists('mfw_event')) {
    /**
     * Dispatch framework event
     *
     * @param string $event Event name
     * @param array $payload Event payload
     * @return mixed Event result
     */
    function mfw_event($event, array $payload = []) {
        return mfw_service('events')->dispatch($event, $payload);
    }
}

if (!function_exists('mfw_cache')) {
    /**
     * Get/set cached value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return mixed Cached value or success status
     */
    function mfw_cache($key, $value = null, $ttl = null) {
        $cache = mfw_service('cache');

        if (is_null($value)) {
            return $cache->get($key);
        }

        return $cache->set($key, $value, $ttl);
    }
}

if (!function_exists('mfw_log')) {
    /**
     * Write log message
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    function mfw_log($message, $level = 'info', array $context = []) {
        return mfw_service('logger')->log($message, $level, $context);
    }
}

if (!function_exists('mfw_db')) {
    /**
     * Get database instance
     *
     * @return MFW_Database Database instance
     */
    function mfw_db() {
        return mfw_service('database');
    }
}

if (!function_exists('mfw_path')) {
    /**
     * Get framework path
     *
     * @param string $path Path to append
     * @return string Framework path
     */
    function mfw_path($path = '') {
        return MFW_PATH . ltrim($path, '/');
    }
}

if (!function_exists('mfw_url')) {
    /**
     * Get framework URL
     *
     * @param string $path Path to append
     * @return string Framework URL
     */
    function mfw_url($path = '') {
        return MFW_URL . ltrim($path, '/');
    }
}

if (!function_exists('mfw_view')) {
    /**
     * Render framework view
     *
     * @param string $view View name
     * @param array $data View data
     * @return string Rendered view
     */
    function mfw_view($view, array $data = []) {
        return mfw_service('view')->render($view, $data);
    }
}

if (!function_exists('mfw_array_get')) {
    /**
     * Get array value using dot notation
     *
     * @param array $array Array to search
     * @param string $key Key in dot notation
     * @param mixed $default Default value
     * @return mixed Array value
     */
    function mfw_array_get($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }

        if (is_null($key)) {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('mfw_array_set')) {
    /**
     * Set array value using dot notation
     *
     * @param array &$array Array to modify
     * @param string $key Key in dot notation
     * @param mixed $value Value to set
     * @return void
     */
    function mfw_array_set(&$array, $key, $value) {
        if (is_null($key)) {
            return;
        }

        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }
}

if (!function_exists('mfw_str_slug')) {
    /**
     * Generate URL friendly slug
     *
     * @param string $string String to slugify
     * @param string $separator Word separator
     * @return string Slugified string
     */
    function mfw_str_slug($string, $separator = '-') {
        $string = preg_replace('/[^\p{L}\p{Nd}]+/u', $separator, $string);
        $string = preg_replace('/[' . preg_quote($separator, '/') . ']+/', $separator, $string);
        return trim($string, $separator);
    }
}

if (!function_exists('mfw_str_random')) {
    /**
     * Generate random string
     *
     * @param int $length String length
     * @return string Random string
     */
    function mfw_str_random($length = 16) {
        $string = '';
        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytes = random_bytes($size);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }
        return $string;
    }
}

if (!function_exists('mfw_is_ajax')) {
    /**
     * Check if request is AJAX
     *
     * @return bool Whether request is AJAX
     */
    function mfw_is_ajax() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}

if (!function_exists('mfw_is_api')) {
    /**
     * Check if request is API
     *
     * @return bool Whether request is API
     */
    function mfw_is_api() {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
}

if (!function_exists('mfw_current_url')) {
    /**
     * Get current URL
     *
     * @param bool $query Include query string
     * @return string Current URL
     */
    function mfw_current_url($query = true) {
        $url = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $url .= 's';
        }
        $url .= '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        if (!$query) {
            $url = strtok($url, '?');
        }
        
        return $url;
    }
}

if (!function_exists('mfw_asset')) {
    /**
     * Get asset URL
     *
     * @param string $path Asset path
     * @return string Asset URL
     */
    function mfw_asset($path) {
        return mfw_url('assets/' . ltrim($path, '/'));
    }
}