<?php
/**
 * Data Interface
 * 
 * Defines the contract for data manipulation.
 * Ensures consistent data access patterns across the framework.
 *
 * @package MFW
 * @subpackage Interfaces
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

interface MFW_Data_Interface {
    /**
     * Get data value
     *
     * @param string $key Data key
     * @param mixed $default Default value
     * @return mixed Data value
     */
    public function get($key, $default = null);

    /**
     * Set data value
     *
     * @param string $key Data key
     * @param mixed $value Data value
     * @return bool Whether operation was successful
     */
    public function set($key, $value);

    /**
     * Check if data exists
     *
     * @param string $key Data key
     * @return bool Whether data exists
     */
    public function has($key);

    /**
     * Remove data
     *
     * @param string $key Data key
     * @return bool Whether operation was successful
     */
    public function remove($key);

    /**
     * Get all data
     *
     * @return array All data
     */
    public function all();

    /**
     * Set multiple data values
     *
     * @param array $data Data array
     * @return bool Whether operation was successful
     */
    public function set_many(array $data);

    /**
     * Remove multiple data values
     *
     * @param array $keys Data keys
     * @return bool Whether operation was successful
     */
    public function remove_many(array $keys);

    /**
     * Clear all data
     *
     * @return bool Whether operation was successful
     */
    public function clear();
}