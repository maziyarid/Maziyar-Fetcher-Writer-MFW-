<?php
/**
 * Task Scheduler Class
 *
 * Manages scheduled tasks and recurring operations.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Task_Scheduler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:29:03';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Task status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Task priority levels
     */
    const PRIORITY_HIGH = 1;
    const PRIORITY_NORMAL = 5;
    const PRIORITY_LOW = 10;

    /**
     * Schedule task
     *
     * @param string $task_name Task name
     * @param array $task_data Task data
     * @param array $schedule Schedule parameters
     * @return int|false Task ID or false on failure
     */
    public function schedule_task($task_name, $task_data = [], $schedule = []) {
        try {
            global $wpdb;

            // Validate schedule parameters
            $schedule = wp_parse_args($schedule, [
                'start_time' => $this->current_time,
                'interval' => null,
                'priority' => self::PRIORITY_NORMAL,
                'max_attempts' => 3
            ]);

            // Insert task
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_scheduled_tasks',
                [
                    'task_name' => $task_name,
                    'task_data' => json_encode($task_data),
                    'status' => self::STATUS_PENDING,
                    'priority' => $schedule['priority'],
                    'schedule_time' => $schedule['start_time'],
                    'interval' => $schedule['interval'],
                    'max_attempts' => $schedule['max_attempts'],
                    'attempts' => 0,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time,
                    'updated_at' => $this->current_time
                ],
                [
                    '%s', '%s', '%s', '%d', '%s', '%s', 
                    '%d', '%d', '%s', '%s', '%s'
                ]
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            $task_id = $wpdb->insert_id;

            // Log task creation
            $this->log_task_action($task_id, 'created');

            return $task_id;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to schedule task: %s', $e->getMessage()),
                'task_scheduler',
                'error'
            );
            return false;
        }
    }

    /**
     * Execute pending tasks
     *
     * @param int $limit Maximum number of tasks to process
     * @return array Execution results
     */
    public function execute_pending_tasks($limit = 10) {
        global $wpdb;

        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            // Get pending tasks
            $tasks = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mfw_scheduled_tasks
                WHERE status = %s
                AND schedule_time <= %s
                AND attempts < max_attempts
                ORDER BY priority ASC, schedule_time ASC
                LIMIT %d",
                self::STATUS_PENDING,
                $this->current_time,
                $limit
            ));

            foreach ($tasks as $task) {
                $results['processed']++;

                try {
                    // Update task status to running
                    $this->update_task_status($task->id, self::STATUS_RUNNING);

                    // Execute task
                    $task_result = $this->execute_task($task);

                    if ($task_result['success']) {
                        $this->update_task_status($task->id, self::STATUS_COMPLETED);
                        $results['successful']++;
                    } else {
                        $this->handle_task_failure($task, $task_result['error']);
                        $results['failed']++;
                        $results['errors'][] = sprintf(
                            'Task %d failed: %s',
                            $task->id,
                            $task_result['error']
                        );
                    }

                    // Schedule next run if task is recurring
                    if ($task->interval) {
                        $this->schedule_next_run($task);
                    }

                } catch (Exception $e) {
                    $this->handle_task_failure($task, $e->getMessage());
                    $results['failed']++;
                    $results['errors'][] = sprintf(
                        'Task %d failed: %s',
                        $task->id,
                        $e->getMessage()
                    );
                }
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Task execution failed: %s', $e->getMessage()),
                'task_scheduler',
                'error'
            );
        }

        return $results;
    }

    /**
     * Execute individual task
     *
     * @param object $task Task object
     * @return array Execution result
     */
    private function execute_task($task) {
        try {
            $task_data = json_decode($task->task_data, true);

            // Execute task based on task name
            switch ($task->task_name) {
                case 'generate_content':
                    $result = $this->execute_content_generation($task_data);
                    break;
                case 'process_images':
                    $result = $this->execute_image_processing($task_data);
                    break;
                case 'sync_data':
                    $result = $this->execute_data_sync($task_data);
                    break;
                case 'cleanup':
                    $result = $this->execute_cleanup($task_data);
                    break;
                default:
                    // Allow external task handling through filters
                    $result = apply_filters(
                        'mfw_execute_task',
                        ['success' => false, 'error' => 'Unknown task type'],
                        $task
                    );
            }

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle task failure
     *
     * @param object $task Task object
     * @param string $error Error message
     */
    private function handle_task_failure($task, $error) {
        global $wpdb;

        $attempts = $task->attempts + 1;
        $status = $attempts >= $task->max_attempts ? self::STATUS_FAILED : self::STATUS_PENDING;

        $wpdb->update(
            $wpdb->prefix . 'mfw_scheduled_tasks',
            [
                'status' => $status,
                'attempts' => $attempts,
                'last_error' => $error,
                'updated_at' => $this->current_time
            ],
            ['id' => $task->id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );

        // Log failure
        $this->log_task_action($task->id, 'failed', $error);
    }

    /**
     * Schedule next run for recurring task
     *
     * @param object $task Task object
     */
    private function schedule_next_run($task) {
        $next_run = date('Y-m-d H:i:s', strtotime($task->schedule_time . ' +' . $task->interval));

        $this->schedule_task(
            $task->task_name,
            json_decode($task->task_data, true),
            [
                'start_time' => $next_run,
                'interval' => $task->interval,
                'priority' => $task->priority,
                'max_attempts' => $task->max_attempts
            ]
        );
    }

    /**
     * Update task status
     *
     * @param int $task_id Task ID
     * @param string $status New status
     * @return bool Success status
     */
    public function update_task_status($task_id, $status) {
        global $wpdb;

        $updated = $wpdb->update(
            $wpdb->prefix . 'mfw_scheduled_tasks',
            [
                'status' => $status,
                'updated_at' => $this->current_time
            ],
            ['id' => $task_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated) {
            $this->log_task_action($task_id, 'status_changed', $status);
        }

        return (bool)$updated;
    }

    /**
     * Log task action
     *
     * @param int $task_id Task ID
     * @param string $action Action performed
     * @param string $message Additional message
     */
    private function log_task_action($task_id, $action, $message = '') {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mfw_task_log',
            [
                'task_id' => $task_id,
                'action' => $action,
                'message' => $message,
                'performed_by' => $this->current_user,
                'performed_at' => $this->current_time
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }
}

// Add task execution schedule
add_action('mfw_execute_scheduled_tasks', ['MFW_Task_Scheduler', 'execute_pending_tasks']);
if (!wp_next_scheduled('mfw_execute_scheduled_tasks')) {
    wp_schedule_event(time(), 'every_minute', 'mfw_execute_scheduled_tasks');
}