<?php
/**
 * Queue Handler Class
 *
 * Manages background processing queues for content generation and API requests.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Queue_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:42:58';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Queue statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Queue types
     */
    const TYPE_CONTENT = 'content';
    const TYPE_IMAGE = 'image';
    const TYPE_API = 'api';
    const TYPE_BATCH = 'batch';

    /**
     * Add item to queue
     *
     * @param string $type Queue type
     * @param array $data Item data
     * @param array $options Queue options
     * @return int|false Queue item ID or false on failure
     */
    public function add_to_queue($type, $data, $options = []) {
        try {
            global $wpdb;

            // Validate queue type
            if (!in_array($type, [self::TYPE_CONTENT, self::TYPE_IMAGE, self::TYPE_API, self::TYPE_BATCH])) {
                throw new Exception(__('Invalid queue type.', 'mfw'));
            }

            // Parse options
            $options = wp_parse_args($options, [
                'priority' => 10,
                'scheduled_time' => null,
                'expiry_time' => null,
                'max_attempts' => 3,
                'batch_id' => null
            ]);

            // Insert queue item
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_queue',
                [
                    'type' => $type,
                    'data' => json_encode($data),
                    'status' => self::STATUS_PENDING,
                    'priority' => $options['priority'],
                    'scheduled_time' => $options['scheduled_time'],
                    'expiry_time' => $options['expiry_time'],
                    'max_attempts' => $options['max_attempts'],
                    'attempts' => 0,
                    'batch_id' => $options['batch_id'],
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                [
                    '%s', '%s', '%s', '%d', '%s', '%s', 
                    '%d', '%d', '%s', '%s', '%s'
                ]
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            $queue_id = $wpdb->insert_id;

            // Schedule processing if immediate
            if (empty($options['scheduled_time'])) {
                $this->schedule_processing($queue_id);
            }

            return $queue_id;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to add item to queue: %s', $e->getMessage()),
                'queue_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Process queue item
     *
     * @param int $queue_id Queue item ID
     * @return bool Success status
     */
    public function process_item($queue_id) {
        try {
            global $wpdb;

            // Get queue item
            $item = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}mfw_queue WHERE id = %d",
                    $queue_id
                )
            );

            if (!$item) {
                throw new Exception(__('Queue item not found.', 'mfw'));
            }

            // Check if item can be processed
            if ($item->status !== self::STATUS_PENDING) {
                return false;
            }

            // Check expiry
            if ($item->expiry_time && strtotime($item->expiry_time) < strtotime($this->current_time)) {
                $this->update_item_status($queue_id, self::STATUS_CANCELLED, 'Item expired');
                return false;
            }

            // Update status to processing
            $this->update_item_status($queue_id, self::STATUS_PROCESSING);

            // Process based on type
            $result = false;
            switch ($item->type) {
                case self::TYPE_CONTENT:
                    $result = $this->process_content_item($item);
                    break;
                case self::TYPE_IMAGE:
                    $result = $this->process_image_item($item);
                    break;
                case self::TYPE_API:
                    $result = $this->process_api_item($item);
                    break;
                case self::TYPE_BATCH:
                    $result = $this->process_batch_item($item);
                    break;
            }

            // Update status based on result
            if ($result) {
                $this->update_item_status($queue_id, self::STATUS_COMPLETED);
            } else {
                // Increment attempts
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}mfw_queue
                    SET attempts = attempts + 1
                    WHERE id = %d",
                    $queue_id
                ));

                // Check max attempts
                $item->attempts++;
                if ($item->attempts >= $item->max_attempts) {
                    $this->update_item_status($queue_id, self::STATUS_FAILED, 'Max attempts reached');
                } else {
                    // Reset to pending for retry
                    $this->update_item_status($queue_id, self::STATUS_PENDING);
                    $this->schedule_processing($queue_id, $item->attempts * 300); // Exponential backoff
                }
            }

            return $result;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process queue item: %s', $e->getMessage()),
                'queue_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Update queue item status
     *
     * @param int $queue_id Queue item ID
     * @param string $status New status
     * @param string $message Status message
     * @return bool Success status
     */
    public function update_item_status($queue_id, $status, $message = '') {
        try {
            global $wpdb;

            $updated = $wpdb->update(
                $wpdb->prefix . 'mfw_queue',
                [
                    'status' => $status,
                    'status_message' => $message,
                    'updated_at' => $this->current_time
                ],
                ['id' => $queue_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            return (bool)$updated;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update queue item status: %s', $e->getMessage()),
                'queue_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Process content generation item
     *
     * @param object $item Queue item
     * @return bool Success status
     */
    private function process_content_item($item) {
        try {
            $data = json_decode($item->data, true);
            
            // Get content generator
            $content_generator = new MFW_Content_Generator();
            
            // Generate content
            $result = $content_generator->generate_content($data);
            
            return !empty($result);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process content item: %s', $e->getMessage()),
                'queue_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Process image generation item
     *
     * @param object $item Queue item
     * @return bool Success status
     */
    private function process_image_item($item) {
        try {
            $data = json_decode($item->data, true);
            
            // Get image generator
            $image_generator = new MFW_Image_Generator();
            
            // Generate image
            $result = $image_generator->generate_image($data);
            
            return !empty($result);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process image item: %s', $e->getMessage()),
                'queue_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Process API request item
     *
     * @param object $item Queue item
     * @return bool Success status
     */
    private function process_api_item($item) {
        try {
            $data = json_decode($item->data, true);
            
            // Get API handler
            $api_handler = new MFW_API_Handler();
            
            // Make API request
            $response = $api_handler->make_request(
                $data['endpoint'],
                $data['method'] ?? 'GET',
                $data['params'] ?? []
            );
            
            return !is_wp_error($response);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process API item: %s', $e->getMessage()),
                'queue_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Schedule queue item processing
     *
     * @param int $queue_id Queue item ID
     * @param int $delay Delay in seconds
     * @return bool Success status
     */
    private function schedule_processing($queue_id, $delay = 0) {
        $timestamp = time() + $delay;
        
        return wp_schedule_single_event(
            $timestamp,
            'mfw_process_queue_item',
            [$queue_id]
        );
    }
}

// Add queue processing hook
add_action('mfw_process_queue_item', ['MFW_Queue_Handler', 'process_item']);