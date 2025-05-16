<?php
/**
 * Array Helper Class
 * 
 * Provides utility methods for array manipulation.
 * Handles common array operations with additional functionality.
 *
 * @package MFW
 * @subpackage Helpers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Array_Helper {
    /**
     * Last operation timestamp
     *
     * @var string
     */
    private static $last_operation = '2025-05-14 07:17:08';

    /**
     * Last operator
     *
     * @var string
     */
    private static $last_operator = 'maziyarid';

    /**
     * Get array value using dot notation
     *
     * @param array $array Array to search
     * @param string|int|null $key Key to search for
     * @param mixed $default Default value if key not found
     * @return mixed Value found or default
     */
    public static function get($array, $key = null, $default = null) {
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

    /**
     * Set array value using dot notation
     *
     * @param array &$array Array to modify
     * @param string $key Key to set
     * @param mixed $value Value to set
     * @return array Modified array
     */
    public static function set(&$array, $key, $value) {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        self::$last_operation = current_time('mysql');
        self::$last_operator = wp_get_current_user()->user_login;

        return $array;
    }

    /**
     * Check if array has key using dot notation
     *
     * @param array $array Array to check
     * @param string $key Key to check for
     * @return bool Whether key exists
     */
    public static function has($array, $key) {
        if (empty($array) || is_null($key)) {
            return false;
        }

        if (array_key_exists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Remove array value using dot notation
     *
     * @param array &$array Array to modify
     * @param string $key Key to remove
     * @return void
     */
    public static function forget(&$array, $key) {
        $keys = explode('.', $key);
        $last = array_pop($keys);
        $current = &$array;

        foreach ($keys as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
            $current = &$current[$segment];
        }

        unset($current[$last]);

        self::$last_operation = current_time('mysql');
        self::$last_operator = wp_get_current_user()->user_login;
    }

    /**
     * Filter array by callback
     *
     * @param array $array Array to filter
     * @param callable $callback Filter callback
     * @return array Filtered array
     */
    public static function filter($array, callable $callback) {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Map array using callback
     *
     * @param array $array Array to map
     * @param callable $callback Map callback
     * @return array Mapped array
     */
    public static function map($array, callable $callback) {
        return array_map($callback, $array);
    }

    /**
     * Pluck column from array of arrays/objects
     *
     * @param array $array Array to pluck from
     * @param string $value Value key to pluck
     * @param string|null $key Key to use as index
     * @return array Plucked values
     */
    public static function pluck($array, $value, $key = null) {
        $results = [];

        foreach ($array as $item) {
            $itemValue = is_object($item) ? $item->{$value} : $item[$value];

            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_object($item) ? $item->{$key} : $item[$key];
                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Group array by key
     *
     * @param array $array Array to group
     * @param string $key Key to group by
     * @return array Grouped array
     */
    public static function group_by($array, $key) {
        $results = [];

        foreach ($array as $item) {
            $value = is_object($item) ? $item->{$key} : $item[$key];
            $results[$value][] = $item;
        }

        return $results;
    }

    /**
     * Sort array by key
     *
     * @param array $array Array to sort
     * @param string $key Key to sort by
     * @param string $direction Sort direction (asc|desc)
     * @return array Sorted array
     */
    public static function sort_by($array, $key, $direction = 'asc') {
        usort($array, function($a, $b) use ($key, $direction) {
            $a = is_object($a) ? $a->{$key} : $a[$key];
            $b = is_object($b) ? $b->{$key} : $b[$key];

            if ($a === $b) {
                return 0;
            }

            if ($direction === 'desc') {
                return $a < $b ? 1 : -1;
            }

            return $a > $b ? 1 : -1;
        });

        return $array;
    }

    /**
     * Convert array to object
     *
     * @param array $array Array to convert
     * @return object Converted object
     */
    public static function to_object($array) {
        return json_decode(json_encode($array));
    }

    /**
     * Convert array to JSON
     *
     * @param array $array Array to convert
     * @param int $options JSON encode options
     * @return string JSON string
     */
    public static function to_json($array, $options = 0) {
        return json_encode($array, $options);
    }

    /**
     * Merge arrays recursively
     *
     * @param array ...$arrays Arrays to merge
     * @return array Merged array
     */
    public static function merge_recursive(...$arrays) {
        $result = array_shift($arrays);

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                    $result[$key] = self::merge_recursive($result[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get array except specified keys
     *
     * @param array $array Array to process
     * @param array $keys Keys to exclude
     * @return array Filtered array
     */
    public static function except($array, $keys) {
        return array_diff_key($array, array_flip((array) $keys));
    }

    /**
     * Get array only specified keys
     *
     * @param array $array Array to process
     * @param array $keys Keys to include
     * @return array Filtered array
     */
    public static function only($array, $keys) {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * Get first element from array
     *
     * @param array $array Array to process
     * @param callable|null $callback Filter callback
     * @param mixed $default Default value if not found
     * @return mixed First element or default
     */
    public static function first($array, callable $callback = null, $default = null) {
        if (is_null($callback)) {
            return empty($array) ? $default : reset($array);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get last element from array
     *
     * @param array $array Array to process
     * @param callable|null $callback Filter callback
     * @param mixed $default Default value if not found
     * @return mixed Last element or default
     */
    public static function last($array, callable $callback = null, $default = null) {
        if (is_null($callback)) {
            return empty($array) ? $default : end($array);
        }

        return self::first(array_reverse($array, true), $callback, $default);
    }
}