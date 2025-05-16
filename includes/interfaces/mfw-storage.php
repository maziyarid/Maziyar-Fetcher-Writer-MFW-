<?php
/**
 * Storage Interface
 * 
 * Defines the contract for storage operations.
 * Ensures consistent data persistence across different storage backends.
 *
 * @package MFW
 * @subpackage Interfaces
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

interface MFW_Storage_Interface {
    /**
     * Initialize storage
     *
     * @param array $config Storage configuration
     * @return bool Whether initialization was successful
     */
    public function initialize(array $config = []);

    /**
     * Read data from storage
     *
     * @param string $key Storage key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Stored value
     */
    public function read($key, $default = null);

    /**
     * Write data to storage
     *
     * @param string $key Storage key
     * @param mixed $value Value to store
     * @param array $options Storage options
     * @return bool Whether write was successful
     */
    public function write($key, $value, array $options = []);

    /**
     * Delete data from storage
     *
     * @param string $key Storage key
     * @return bool Whether deletion was successful
     */
    public function delete($key);

    /**
     * Check if key exists in storage
     *
     * @param string $key Storage key
     * @return bool Whether key exists
     */
    public function exists($key);

    /**
     * Read multiple keys from storage
     *
     * @param array $keys Storage keys
     * @return array Key-value pairs of stored data
     */
    public function read_many(array $keys);

    /**
     * Write multiple key-value pairs to storage
     *
     * @param array $data Key-value pairs to store
     * @param array $options Storage options
     * @return array Write results
     */
    public function write_many(array $data, array $options = []);

    /**
     * Delete multiple keys from storage
     *
     * @param array $keys Storage keys to delete
     * @return array Deletion results
     */
    public function delete_many(array $keys);

    /**
     * List all keys in storage
     *
     * @param string $prefix Key prefix to filter by
     * @return array Storage keys
     */
    public function list_keys($prefix = '');

    /**
     * Clear all data from storage
     *
     * @return bool Whether clear was successful
     */
    public function clear();

    /**
     * Get storage information
     *
     * @return array Storage information
     */
    public function info();

    /**
     * Begin a storage transaction
     *
     * @return bool Whether transaction was started
     */
    public function begin_transaction();

    /**
     * Commit a storage transaction
     *
     * @return bool Whether transaction was committed
     */
    public function commit_transaction();

    /**
     * Rollback a storage transaction
     *
     * @return bool Whether transaction was rolled back
     */
    public function rollback_transaction();

    /**
     * Lock storage for writing
     *
     * @param string $key Lock key
     * @param int $timeout Lock timeout in seconds
     * @return bool Whether lock was acquired
     */
    public function acquire_lock($key, $timeout = 30);

    /**
     * Release storage lock
     *
     * @param string $key Lock key
     * @return bool Whether lock was released
     */
    public function release_lock($key);

    /**
     * Get storage size
     *
     * @return int Storage size in bytes
     */
    public function get_size();

    /**
     * Get storage capacity
     *
     * @return int|null Storage capacity in bytes (null if unlimited)
     */
    public function get_capacity();

    /**
     * Check if storage is full
     *
     * @return bool Whether storage is full
     */
    public function is_full();

    /**
     * Optimize storage
     *
     * @return bool Whether optimization was successful
     */
    public function optimize();

    /**
     * Backup storage
     *
     * @param string $destination Backup destination
     * @return bool Whether backup was successful
     */
    public function backup($destination);

    /**
     * Restore storage from backup
     *
     * @param string $source Backup source
     * @return bool Whether restore was successful
     */
    public function restore($source);

    /**
     * Get storage events
     *
     * @return array Storage events
     */
    public function get_events();

    /**
     * Add storage event listener
     *
     * @param string $event Event name
     * @param callable $callback Event callback
     * @return bool Whether listener was added
     */
    public function on($event, callable $callback);

    /**
     * Remove storage event listener
     *
     * @param string $event Event name
     * @param callable $callback Event callback
     * @return bool Whether listener was removed
     */
    public function off($event, callable $callback);
}