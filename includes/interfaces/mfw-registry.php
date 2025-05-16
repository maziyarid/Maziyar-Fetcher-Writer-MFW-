<?php
/**
 * Registry Interface
 * 
 * Defines the contract for registry operations.
 * Ensures consistent registration and management of framework components.
 *
 * @package MFW
 * @subpackage Interfaces
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

interface MFW_Registry_Interface {
    /**
     * Register an item
     *
     * @param string $id Item identifier
     * @param mixed $item Item to register
     * @param array $args Additional arguments
     * @return bool Whether registration was successful
     */
    public function register($id, $item, array $args = []);

    /**
     * Unregister an item
     *
     * @param string $id Item identifier
     * @return bool Whether unregistration was successful
     */
    public function unregister($id);

    /**
     * Get registered item
     *
     * @param string $id Item identifier
     * @return mixed|null Registered item or null if not found
     */
    public function get($id);

    /**
     * Check if item is registered
     *
     * @param string $id Item identifier
     * @return bool Whether item is registered
     */
    public function has($id);

    /**
     * Get all registered items
     *
     * @return array All registered items
     */
    public function all();

    /**
     * Get registered items count
     *
     * @return int Count of registered items
     */
    public function count();

    /**
     * Get registered items by property
     *
     * @param string $property Property name
     * @param mixed $value Property value
     * @return array Matching items
     */
    public function get_by($property, $value);

    /**
     * Filter registered items
     *
     * @param callable $callback Filter callback
     * @return array Filtered items
     */
    public function filter(callable $callback);

    /**
     * Sort registered items
     *
     * @param callable $callback Sort callback
     * @return array Sorted items
     */
    public function sort(callable $callback);

    /**
     * Clear all registered items
     *
     * @return bool Whether clear was successful
     */
    public function clear();

    /**
     * Get registry metadata
     *
     * @param string $key Metadata key
     * @return mixed Metadata value
     */
    public function get_metadata($key);

    /**
     * Set registry metadata
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return bool Whether set was successful
     */
    public function set_metadata($key, $value);

    /**
     * Get registry statistics
     *
     * @return array Registry statistics
     */
    public function get_stats();

    /**
     * Lock registry for writing
     *
     * @return bool Whether lock was acquired
     */
    public function lock();

    /**
     * Unlock registry
     *
     * @return bool Whether unlock was successful
     */
    public function unlock();

    /**
     * Check if registry is locked
     *
     * @return bool Whether registry is locked
     */
    public function is_locked();

    /**
     * Execute a callback within a locked context
     *
     * @param callable $callback Callback to execute
     * @return mixed Callback result
     */
    public function with_lock(callable $callback);

    /**
     * Register multiple items
     *
     * @param array $items Items to register
     * @return array Registration results
     */
    public function register_many(array $items);

    /**
     * Unregister multiple items
     *
     * @param array $ids Item identifiers
     * @return array Unregistration results
     */
    public function unregister_many(array $ids);

    /**
     * Export registry state
     *
     * @return array Registry state
     */
    public function export();

    /**
     * Import registry state
     *
     * @param array $state Registry state
     * @return bool Whether import was successful
     */
    public function import(array $state);

    /**
     * Add registry event listener
     *
     * @param string $event Event name
     * @param callable $callback Event callback
     * @return bool Whether listener was added
     */
    public function on($event, callable $callback);

    /**
     * Remove registry event listener
     *
     * @param string $event Event name
     * @param callable $callback Event callback
     * @return bool Whether listener was removed
     */
    public function off($event, callable $callback);

    /**
     * Get registry events
     *
     * @return array Registered events
     */
    public function get_events();
}