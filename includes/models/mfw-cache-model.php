<?php
/**
 * Cache Model Class
 * 
 * Handles data caching operations.
 * Manages cache storage, retrieval, and invalidation.
 *
 * @package MFW
 * @subpackage Models
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Cache_Model extends MFW_Model_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:21:09';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Cache groups configuration
     */
    protected $groups = [
        'default' => [
            'lifetime' => 3600,
            'auto_clean' => true
        ],
        'transient' => [
            'lifetime' => 86400,
            'auto_clean' => true
        ],
        'persistent' => [
            'lifetime' => 0,
            'auto_clean' => false
        ]
    ];

    /**
     * Initialize model
     */
    protected function init() {
        $this->table = 'mfw_cache';
        
        $this->fields = [
            'key' => [
                'type' => 'string',
                'length' => 255
            ],
            'value' => [
                'type' => 'longtext'
            ],
            'group' => [
                'type' => 'string',
                'length' => 50,
                'default' => 'default'
            ],
            'expiry' => [
                'type' => 'datetime'
            ],
            'tags' => [
                'type' => 'json'
            ]
        ];

        $this->required = ['key'];

        $this->validations = [
            'key' => [
                'length' => ['min' => 1, 'max' => 255],
                'unique' => true
            ],
            'group' => [
                'in' => array_keys($this->groups)
            ]
        ];
    }

    /**
     * Get cached item
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed|false Cached data or false if not found
     */
    public function get($key, $group = 'default') {
        try {
            global $wpdb;

            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}{$this->table} 
                    WHERE `key` = %s AND `group` = %s 
                    AND (expiry IS NULL OR expiry > %s)",
                    $key,
                    $group,
                    $this->current_time
                ),
                ARRAY_A
            );

            if (!$result) {
                return false;
            }

            // Log cache hit
            $this->log_cache_operation('hit', $key, $group);

            return $this->unserialize_value($result['value']);

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Set cached item
     *
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param string $group Cache group
     * @param array $tags Cache tags
     * @return bool Whether operation was successful
     */
    public function set($key, $value, $group = 'default', $tags = []) {
        try {
            global $wpdb;

            // Calculate expiry time
            $lifetime = $this->groups[$group]['lifetime'];
            $expiry = $lifetime > 0 ? date('Y-m-d H:i:s', time() + $lifetime) : null;

            $data = [
                'key' => $key,
                'value' => $this->serialize_value($value),
                'group' => $group,
                'expiry' => $expiry,
                'tags' => !empty($tags) ? json_encode($tags) : null,
                'created_by' => $this->current_user,
                'created_at' => $this->current_time,
                'updated_by' => $this->current_user,
                'updated_at' => $this->current_time
            ];

            // Check if key exists
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}{$this->table} WHERE `key` = %s AND `group` = %s",
                    $key,
                    $group
                )
            );

            if ($existing) {
                unset($data['created_by'], $data['created_at']);
                $result = $wpdb->update(
                    $wpdb->prefix . $this->table,
                    $data,
                    ['id' => $existing],
                    array_fill(0, count($data), '%s'),
                    ['%d']
                );
            } else {
                $result = $wpdb->insert(
                    $wpdb->prefix . $this->table,
                    $data,
                    array_fill(0, count($data), '%s')
                );
            }

            if ($result === false) {
                throw new Exception(__('Failed to set cache item.', 'mfw'));
            }

            // Log cache operation
            $this->log_cache_operation('set', $key, $group);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Delete cached item
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Whether deletion was successful
     */
    public function delete($key, $group = 'default') {
        try {
            global $wpdb;

            $result = $wpdb->delete(
                $wpdb->prefix . $this->table,
                [
                    'key' => $key,
                    'group' => $group
                ],
                ['%s', '%s']
            );

            if ($result === false) {
                throw new Exception(__('Failed to delete cache item.', 'mfw'));
            }

            // Log cache operation
            $this->log_cache_operation('delete', $key, $group);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Clear cache by group or tags
     *
     * @param string $group Cache group
     * @param array $tags Cache tags
     * @return bool Whether operation was successful
     */
    public function clear($group = null, $tags = []) {
        try {
            global $wpdb;

            $where = [];
            $values = [];

            if ($group) {
                $where[] = '`group` = %s';
                $values[] = $group;
            }

            if (!empty($tags)) {
                $tag_conditions = [];
                foreach ($tags as $tag) {
                    $tag_conditions[] = 'tags LIKE %s';
                    $values[] = '%' . $wpdb->esc_like($tag) . '%';
                }
                $where[] = '(' . implode(' OR ', $tag_conditions) . ')';
            }

            $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $query = $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}{$this->table} {$where_clause}",
                $values
            );

            $result = $wpdb->query($query);

            if ($result === false) {
                throw new Exception(__('Failed to clear cache.', 'mfw'));
            }

            // Log cache operation
            $this->log_cache_operation('clear', '', $group);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Clean expired cache items
     *
     * @return bool Whether cleanup was successful
     */
    public function clean() {
        try {
            global $wpdb;

            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}{$this->table} 
                    WHERE expiry IS NOT NULL AND expiry <= %s",
                    $this->current_time
                )
            );

            if ($result === false) {
                throw new Exception(__('Failed to clean cache.', 'mfw'));
            }

            // Log cache operation
            $this->log_cache_operation('clean');

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Serialize value for storage
     *
     * @param mixed $value Value to serialize
     * @return string Serialized value
     */
    protected function serialize_value($value) {
        if (is_numeric($value)) {
            return (string)$value;
        }
        return maybe_serialize($value);
    }

    /**
     * Unserialize stored value
     *
     * @param string $value Stored value
     * @return mixed Unserialized value
     */
    protected function unserialize_value($value) {
        if (is_numeric($value)) {
            return $value + 0;
        }
        return maybe_unserialize($value);
    }

    /**
     * Log cache operation
     *
     * @param string $operation Operation type
     * @param string $key Cache key
     * @param string $group Cache group
     */
    protected function log_cache_operation($operation, $key = '', $group = '') {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_cache_log',
                [
                    'operation' => $operation,
                    'cache_key' => $key,
                    'cache_group' => $group,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log cache operation: %s', $e->getMessage()),
                get_class($this),
                'error'
            );
        }
    }
}