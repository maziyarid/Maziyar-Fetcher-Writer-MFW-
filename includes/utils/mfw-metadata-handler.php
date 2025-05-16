<?php
/**
 * Metadata Handler Class
 *
 * Manages metadata for generated content and API responses.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Metadata_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:25:22';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Metadata types
     */
    const TYPE_CONTENT = 'content';
    const TYPE_IMAGE = 'image';
    const TYPE_API_RESPONSE = 'api_response';
    const TYPE_USER_PREFERENCE = 'user_preference';

    /**
     * Add metadata
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     * @param array $metadata Metadata array
     * @return bool Success status
     */
    public function add_metadata($object_id, $type, $metadata) {
        try {
            global $wpdb;

            // Validate metadata
            if (!is_array($metadata) || empty($metadata)) {
                throw new Exception(__('Invalid metadata format.', 'mfw'));
            }

            // Insert metadata record
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_metadata',
                [
                    'object_id' => $object_id,
                    'type' => $type,
                    'metadata' => json_encode($metadata),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time,
                    'updated_at' => $this->current_time
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            // Update cache
            $this->update_metadata_cache($object_id, $type, $metadata);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to add metadata: %s', $e->getMessage()),
                'metadata_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Update metadata
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     * @param array $metadata New metadata array
     * @return bool Success status
     */
    public function update_metadata($object_id, $type, $metadata) {
        try {
            global $wpdb;

            // Validate metadata
            if (!is_array($metadata) || empty($metadata)) {
                throw new Exception(__('Invalid metadata format.', 'mfw'));
            }

            // Update metadata record
            $updated = $wpdb->update(
                $wpdb->prefix . 'mfw_metadata',
                [
                    'metadata' => json_encode($metadata),
                    'updated_by' => $this->current_user,
                    'updated_at' => $this->current_time
                ],
                [
                    'object_id' => $object_id,
                    'type' => $type
                ],
                ['%s', '%s', '%s'],
                ['%d', '%s']
            );

            if ($updated === false) {
                throw new Exception($wpdb->last_error);
            }

            // Update cache
            $this->update_metadata_cache($object_id, $type, $metadata);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update metadata: %s', $e->getMessage()),
                'metadata_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get metadata
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     * @param string $key Optional specific metadata key
     * @return mixed Metadata value(s)
     */
    public function get_metadata($object_id, $type, $key = '') {
        // Try to get from cache first
        $metadata = $this->get_metadata_from_cache($object_id, $type);

        if ($metadata === false) {
            // Get from database
            $metadata = $this->get_metadata_from_db($object_id, $type);

            // Cache the result
            if ($metadata !== false) {
                $this->update_metadata_cache($object_id, $type, $metadata);
            }
        }

        if ($metadata === false) {
            return null;
        }

        // Return specific key if requested
        if (!empty($key)) {
            return $metadata[$key] ?? null;
        }

        return $metadata;
    }

    /**
     * Delete metadata
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     * @return bool Success status
     */
    public function delete_metadata($object_id, $type) {
        try {
            global $wpdb;

            // Delete metadata record
            $deleted = $wpdb->delete(
                $wpdb->prefix . 'mfw_metadata',
                [
                    'object_id' => $object_id,
                    'type' => $type
                ],
                ['%d', '%s']
            );

            // Clear cache
            $this->delete_metadata_cache($object_id, $type);

            return $deleted !== false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to delete metadata: %s', $e->getMessage()),
                'metadata_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get metadata from database
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     * @return array|false Metadata array or false if not found
     */
    private function get_metadata_from_db($object_id, $type) {
        global $wpdb;

        $metadata = $wpdb->get_var($wpdb->prepare(
            "SELECT metadata FROM {$wpdb->prefix}mfw_metadata
            WHERE object_id = %d AND type = %s",
            $object_id,
            $type
        ));

        return $metadata ? json_decode($metadata, true) : false;
    }

    /**
     * Get metadata from cache
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     * @return array|false Metadata array or false if not found
     */
    private function get_metadata_from_cache($object_id, $type) {
        $cache_key = $this->get_cache_key($object_id, $type);
        return wp_cache_get($cache_key, 'mfw_metadata');
    }

    /**
     * Update metadata cache
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     * @param array $metadata Metadata array
     */
    private function update_metadata_cache($object_id, $type, $metadata) {
        $cache_key = $this->get_cache_key($object_id, $type);
        wp_cache_set($cache_key, $metadata, 'mfw_metadata');
    }

    /**
     * Delete metadata cache
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     */
    private function delete_metadata_cache($object_id, $type) {
        $cache_key = $this->get_cache_key($object_id, $type);
        wp_cache_delete($cache_key, 'mfw_metadata');
    }

    /**
     * Get cache key
     *
     * @param int $object_id Object ID
     * @param string $type Metadata type
     * @return string Cache key
     */
    private function get_cache_key($object_id, $type) {
        return "mfw_metadata_{$type}_{$object_id}";
    }

    /**
     * Search metadata
     *
     * @param array $criteria Search criteria
     * @param array $options Search options
     * @return array Search results
     */
    public function search_metadata($criteria = [], $options = []) {
        global $wpdb;

        $query = "SELECT object_id, type, metadata, created_at, created_by 
                 FROM {$wpdb->prefix}mfw_metadata WHERE 1=1";
        $params = [];

        // Apply criteria
        if (!empty($criteria['type'])) {
            $query .= " AND type = %s";
            $params[] = $criteria['type'];
        }

        if (!empty($criteria['created_by'])) {
            $query .= " AND created_by = %s";
            $params[] = $criteria['created_by'];
        }

        if (!empty($criteria['date_from'])) {
            $query .= " AND created_at >= %s";
            $params[] = $criteria['date_from'];
        }

        if (!empty($criteria['date_to'])) {
            $query .= " AND created_at <= %s";
            $params[] = $criteria['date_to'];
        }

        // Apply metadata field search
        if (!empty($criteria['metadata_field']) && !empty($criteria['metadata_value'])) {
            $query .= " AND metadata LIKE %s";
            $params[] = '%' . $wpdb->esc_like(sprintf('"%s":"%s"', 
                $criteria['metadata_field'], 
                $criteria['metadata_value']
            )) . '%';
        }

        // Apply ordering
        $order = $options['order'] ?? 'DESC';
        $orderby = $options['orderby'] ?? 'created_at';
        $query .= " ORDER BY {$orderby} {$order}";

        // Apply limits
        if (!empty($options['limit'])) {
            $query .= " LIMIT %d";
            $params[] = $options['limit'];

            if (!empty($options['offset'])) {
                $query .= " OFFSET %d";
                $params[] = $options['offset'];
            }
        }

        // Execute query
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        // Process results
        foreach ($results as &$result) {
            $result['metadata'] = json_decode($result['metadata'], true);
        }

        return $results;
    }
}