<?php
/**
 * Batch Processor Class
 *
 * Handles batch processing of content generation and API requests.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Batch_Processor {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:43:59';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Batch statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_PAUSED = 'paused';

    /**
     * Default batch size
     */
    const DEFAULT_BATCH_SIZE = 50;

    /**
     * Create batch
     *
     * @param array $items Batch items
     * @param array $options Batch options
     * @return int|false Batch ID or false on failure
     */
    public function create_batch($items, $options = []) {
        try {
            global $wpdb;

            // Parse options
            $options = wp_parse_args($options, [
                'name' => '',
                'description' => '',
                'type' => 'content',
                'priority' => 10,
                'batch_size' => self::DEFAULT_BATCH_SIZE,
                'max_retries' => 3,
                'notify_on_complete' => true
            ]);

            // Start transaction
            $wpdb->query('START TRANSACTION');

            try {
                // Insert batch record
                $inserted = $wpdb->insert(
                    $wpdb->prefix . 'mfw_batches',
                    [
                        'name' => $options['name'],
                        'description' => $options['description'],
                        'type' => $options['type'],
                        'total_items' => count($items),
                        'processed_items' => 0,
                        'failed_items' => 0,
                        'status' => self::STATUS_PENDING,
                        'options' => json_encode($options),
                        'created_by' => $this->current_user,
                        'created_at' => $this->current_time,
                        'updated_at' => $this->current_time
                    ],
                    [
                        '%s', '%s', '%s', '%d', '%d', '%d', 
                        '%s', '%s', '%s', '%s', '%s'
                    ]
                );

                if (!$inserted) {
                    throw new Exception($wpdb->last_error);
                }

                $batch_id = $wpdb->insert_id;

                // Queue items
                $queue_handler = new MFW_Queue_Handler();
                foreach ($items as $index => $item) {
                    $queue_handler->add_to_queue(
                        $options['type'],
                        $item,
                        [
                            'priority' => $options['priority'],
                            'max_attempts' => $options['max_retries'],
                            'batch_id' => $batch_id
                        ]
                    );
                }

                // Schedule batch processing
                $this->schedule_batch_processing($batch_id);

                // Commit transaction
                $wpdb->query('COMMIT');

                return $batch_id;

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to create batch: %s', $e->getMessage()),
                'batch_processor',
                'error'
            );
            return false;
        }
    }

    /**
     * Process batch
     *
     * @param int $batch_id Batch ID
     * @return bool Success status
     */
    public function process_batch($batch_id) {
        try {
            global $wpdb;

            // Get batch
            $batch = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}mfw_batches WHERE id = %d",
                    $batch_id
                )
            );

            if (!$batch) {
                throw new Exception(__('Batch not found.', 'mfw'));
            }

            // Check if batch can be processed
            if ($batch->status !== self::STATUS_PENDING && $batch->status !== self::STATUS_PROCESSING) {
                return false;
            }

            // Update status to processing
            $this->update_batch_status($batch_id, self::STATUS_PROCESSING);

            // Get batch options
            $options = json_decode($batch->options, true);
            $batch_size = $options['batch_size'] ?? self::DEFAULT_BATCH_SIZE;

            // Get pending items
            $pending_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}mfw_queue
                    WHERE batch_id = %d AND status = %s
                    ORDER BY priority DESC, id ASC
                    LIMIT %d",
                    $batch_id,
                    MFW_Queue_Handler::STATUS_PENDING,
                    $batch_size
                )
            );

            if (empty($pending_items)) {
                // Check if all items are completed
                $total_completed = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}mfw_queue
                        WHERE batch_id = %d AND status = %s",
                        $batch_id,
                        MFW_Queue_Handler::STATUS_COMPLETED
                    )
                );

                if ($total_completed == $batch->total_items) {
                    $this->complete_batch($batch_id);
                } else {
                    // Some items failed
                    $this->fail_batch($batch_id);
                }

                return true;
            }

            // Process items
            $queue_handler = new MFW_Queue_Handler();
            foreach ($pending_items as $item) {
                $queue_handler->process_item($item->id);
            }

            // Update batch progress
            $this->update_batch_progress($batch_id);

            // Schedule next batch if needed
            $this->schedule_batch_processing($batch_id);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process batch: %s', $e->getMessage()),
                'batch_processor',
                'error'
            );
            return false;
        }
    }

    /**
     * Update batch status
     *
     * @param int $batch_id Batch ID
     * @param string $status New status
     * @param string $message Status message
     * @return bool Success status
     */
    public function update_batch_status($batch_id, $status, $message = '') {
        try {
            global $wpdb;

            $updated = $wpdb->update(
                $wpdb->prefix . 'mfw_batches',
                [
                    'status' => $status,
                    'status_message' => $message,
                    'updated_at' => $this->current_time
                ],
                ['id' => $batch_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            return (bool)$updated;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update batch status: %s', $e->getMessage()),
                'batch_processor',
                'error'
            );
            return false;
        }
    }

    /**
     * Update batch progress
     *
     * @param int $batch_id Batch ID
     * @return bool Success status
     */
    private function update_batch_progress($batch_id) {
        try {
            global $wpdb;

            // Get counts
            $processed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mfw_queue
                    WHERE batch_id = %d AND status IN (%s, %s)",
                    $batch_id,
                    MFW_Queue_Handler::STATUS_COMPLETED,
                    MFW_Queue_Handler::STATUS_FAILED
                )
            );

            $failed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mfw_queue
                    WHERE batch_id = %d AND status = %s",
                    $batch_id,
                    MFW_Queue_Handler::STATUS_FAILED
                )
            );

            // Update batch
            return (bool)$wpdb->update(
                $wpdb->prefix . 'mfw_batches',
                [
                    'processed_items' => $processed,
                    'failed_items' => $failed,
                    'updated_at' => $this->current_time
                ],
                ['id' => $batch_id],
                ['%d', '%d', '%s'],
                ['%d']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update batch progress: %s', $e->getMessage()),
                'batch_processor',
                'error'
            );
            return false;
        }
    }

    /**
     * Complete batch
     *
     * @param int $batch_id Batch ID
     * @return bool Success status
     */
    private function complete_batch($batch_id) {
        try {
            // Update status
            $this->update_batch_status(
                $batch_id,
                self::STATUS_COMPLETED,
                __('All items processed successfully.', 'mfw')
            );

            // Send notification if enabled
            $batch = $this->get_batch($batch_id);
            if ($batch) {
                $options = json_decode($batch->options, true);
                if (!empty($options['notify_on_complete'])) {
                    $this->send_completion_notification($batch);
                }
            }

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to complete batch: %s', $e->getMessage()),
                'batch_processor',
                'error'
            );
            return false;
        }
    }

    /**
     * Schedule batch processing
     *
     * @param int $batch_id Batch ID
     * @param int $delay Delay in seconds
     * @return bool Success status
     */
    private function schedule_batch_processing($batch_id, $delay = 60) {
        return wp_schedule_single_event(
            time() + $delay,
            'mfw_process_batch',
            [$batch_id]
        );
    }
}

// Add batch processing hook
add_action('mfw_process_batch', ['MFW_Batch_Processor', 'process_batch']);