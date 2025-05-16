<?php
/**
 * Registry Class
 * 
 * Implements a central registry for framework components and services.
 * Provides registration, retrieval, and management of framework resources.
 *
 * @package MFW
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Registry implements MFW_Registry_Interface {
    use MFW_Observable;
    use MFW_Configurable;
    use MFW_Loggable;

    /**
     * Registry items
     *
     * @var array
     */
    private $items = [];

    /**
     * Registry metadata
     *
     * @var array
     */
    private $metadata = [];

    /**
     * Write lock status
     *
     * @var bool
     */
    private $locked = false;

    /**
     * Initialize registry
     */
    public function __construct() {
        $this->init_timestamp = '2025-05-14 06:32:39';
        $this->init_user = 'maziyarid';

        $this->info('Registry initialized');
    }

    /**
     * Register an item
     *
     * @param string $id Item identifier
     * @param mixed $item Item to register
     * @param array $args Additional arguments
     * @return bool Whether registration was successful
     */
    public function register($id, $item, array $args = []) {
        if ($this->locked) {
            $this->warning('Registry is locked', ['id' => $id]);
            return false;
        }

        if ($this->has($id)) {
            $this->warning('Item already registered', ['id' => $id]);
            return false;
        }

        $this->items[$id] = [
            'item' => $item,
            'args' => $args,
            'registered_at' => current_time('mysql'),
            'registered_by' => wp_get_current_user()->user_login
        ];

        $this->notify('item.registered', [
            'id' => $id,
            'item' => $item,
            'args' => $args
        ]);

        $this->debug('Item registered', ['id' => $id]);
        return true;
    }

    /**
     * Unregister an item
     *
     * @param string $id Item identifier
     * @return bool Whether unregistration was successful
     */
    public function unregister($id) {
        if ($this->locked) {
            $this->warning('Registry is locked', ['id' => $id]);
            return false;
        }

        if (!$this->has($id)) {
            return false;
        }

        $item = $this->items[$id];
        unset($this->items[$id]);

        $this->notify('item.unregistered', [
            'id' => $id,
            'item' => $item
        ]);

        $this->debug('Item unregistered', ['id' => $id]);
        return true;
    }

    /**
     * Get registered item
     *
     * @param string $id Item identifier
     * @return mixed|null Registered item or null if not found
     */
    public function get($id) {
        if (!$this->has($id)) {
            return null;
        }

        return $this->items[$id]['item'];
    }

    /**
     * Check if item is registered
     *
     * @param string $id Item identifier
     * @return bool Whether item is registered
     */
    public function has($id) {
        return isset($this->items[$id]);
    }

    /**
     * Get all registered items
     *
     * @return array All registered items
     */
    public function all() {
        return array_map(function($item) {
            return $item['item'];
        }, $this->items);
    }

    /**
     * Get registered items count
     *
     * @return int Count of registered items
     */
    public function count() {
        return count($this->items);
    }

    /**
     * Get registered items by property
     *
     * @param string $property Property name
     * @param mixed $value Property value
     * @return array Matching items
     */
    public function get_by($property, $value) {
        $results = [];

        foreach ($this->items as $id => $data) {
            if (isset($data['args'][$property]) && $data['args'][$property] === $value) {
                $results[$id] = $data['item'];
            }
        }

        return $results;
    }

    /**
     * Filter registered items
     *
     * @param callable $callback Filter callback
     * @return array Filtered items
     */
    public function filter(callable $callback) {
        $results = [];

        foreach ($this->items as $id => $data) {
            if ($callback($data['item'], $id, $data['args'])) {
                $results[$id] = $data['item'];
            }
        }

        return $results;
    }

    /**
     * Sort registered items
     *
     * @param callable $callback Sort callback
     * @return array Sorted items
     */
    public function sort(callable $callback) {
        $items = $this->all();
        uasort($items, $callback);
        return $items;
    }

    /**
     * Clear all registered items
     *
     * @return bool Whether clear was successful
     */
    public function clear() {
        if ($this->locked) {
            $this->warning('Registry is locked');
            return false;
        }

        $this->items = [];
        $this->notify('registry.cleared');
        
        $this->debug('Registry cleared');
        return true;
    }

    /**
     * Get registry metadata
     *
     * @param string $key Metadata key
     * @return mixed Metadata value
     */
    public function get_metadata($key) {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Set registry metadata
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return bool Whether set was successful
     */
    public function set_metadata($key, $value) {
        $this->metadata[$key] = $value;
        return true;
    }

    /**
     * Get registry statistics
     *
     * @return array Registry statistics
     */
    public function get_stats() {
        return [
            'total_items' => $this->count(),
            'registration_dates' => array_column($this->items, 'registered_at'),
            'registrants' => array_column($this->items, 'registered_by'),
            'metadata' => $this->metadata
        ];
    }

    /**
     * Lock registry for writing
     *
     * @return bool Whether lock was acquired
     */
    public function lock() {
        $this->locked = true;
        $this->notify('registry.locked');
        return true;
    }

    /**
     * Unlock registry
     *
     * @return bool Whether unlock was successful
     */
    public function unlock() {
        $this->locked = false;
        $this->notify('registry.unlocked');
        return true;
    }

    /**
     * Check if registry is locked
     *
     * @return bool Whether registry is locked
     */
    public function is_locked() {
        return $this->locked;
    }

    /**
     * Execute a callback within a locked context
     *
     * @param callable $callback Callback to execute
     * @return mixed Callback result
     */
    public function with_lock(callable $callback) {
        $this->lock();
        try {
            $result = $callback($this);
        } finally {
            $this->unlock();
        }
        return $result;
    }

    /**
     * Register multiple items
     *
     * @param array $items Items to register
     * @return array Registration results
     */
    public function register_many(array $items) {
        $results = [];
        foreach ($items as $id => $item) {
            $args = [];
            if (is_array($item) && isset($item['item'])) {
                $args = $item['args'] ?? [];
                $item = $item['item'];
            }
            $results[$id] = $this->register($id, $item, $args);
        }
        return $results;
    }

    /**
     * Unregister multiple items
     *
     * @param array $ids Item identifiers
     * @return array Unregistration results
     */
    public function unregister_many(array $ids) {
        $results = [];
        foreach ($ids as $id) {
            $results[$id] = $this->unregister($id);
        }
        return $results;
    }

    /**
     * Export registry state
     *
     * @return array Registry state
     */
    public function export() {
        return [
            'items' => $this->items,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Import registry state
     *
     * @param array $state Registry state
     * @return bool Whether import was successful
     */
    public function import(array $state) {
        if ($this->locked) {
            $this->warning('Registry is locked');
            return false;
        }

        $this->items = $state['items'] ?? [];
        $this->metadata = $state['metadata'] ?? [];

        $this->notify('registry.imported');
        return true;
    }
}