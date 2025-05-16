<?php
/**
 * Performance Monitor Class
 *
 * Monitors and tracks plugin performance metrics and resource usage.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Performance_Monitor {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:41:43';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Performance metrics
     */
    private $metrics = [];

    /**
     * Timer start points
     */
    private $timers = [];

    /**
     * Memory usage snapshots
     */
    private $memory_snapshots = [];

    /**
     * Start monitoring performance
     *
     * @param string $context Monitoring context
     * @return void
     */
    public function start_monitoring($context = 'general') {
        try {
            $this->metrics[$context] = [
                'start_time' => microtime(true),
                'start_memory' => memory_get_usage(),
                'peak_memory' => memory_get_peak_usage(),
                'queries' => [],
                'timers' => [],
                'errors' => []
            ];

            // Start query monitoring
            add_filter('query', [$this, 'log_query']);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to start performance monitoring: %s', $e->getMessage()),
                'performance_monitor',
                'error'
            );
        }
    }

    /**
     * Stop monitoring and get results
     *
     * @param string $context Monitoring context
     * @return array Performance metrics
     */
    public function stop_monitoring($context = 'general') {
        try {
            if (!isset($this->metrics[$context])) {
                throw new Exception(__('No active monitoring for this context.', 'mfw'));
            }

            // Remove query monitoring
            remove_filter('query', [$this, 'log_query']);

            // Calculate metrics
            $end_time = microtime(true);
            $end_memory = memory_get_usage();

            $metrics = $this->metrics[$context];
            $metrics['end_time'] = $end_time;
            $metrics['end_memory'] = $end_memory;
            $metrics['duration'] = $end_time - $metrics['start_time'];
            $metrics['memory_usage'] = $end_memory - $metrics['start_memory'];
            $metrics['peak_memory_usage'] = memory_get_peak_usage() - $metrics['peak_memory'];

            // Store metrics
            $this->store_metrics($context, $metrics);

            // Clear context metrics
            unset($this->metrics[$context]);

            return $metrics;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to stop performance monitoring: %s', $e->getMessage()),
                'performance_monitor',
                'error'
            );
            return [];
        }
    }

    /**
     * Start timer
     *
     * @param string $name Timer name
     * @param string $context Monitoring context
     * @return void
     */
    public function start_timer($name, $context = 'general') {
        try {
            if (!isset($this->timers[$context])) {
                $this->timers[$context] = [];
            }

            $this->timers[$context][$name] = [
                'start' => microtime(true),
                'memory_start' => memory_get_usage()
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to start timer: %s', $e->getMessage()),
                'performance_monitor',
                'error'
            );
        }
    }

    /**
     * Stop timer
     *
     * @param string $name Timer name
     * @param string $context Monitoring context
     * @return array|false Timer results or false on failure
     */
    public function stop_timer($name, $context = 'general') {
        try {
            if (!isset($this->timers[$context][$name])) {
                throw new Exception(__('Timer not found.', 'mfw'));
            }

            $timer = $this->timers[$context][$name];
            $end_time = microtime(true);
            $end_memory = memory_get_usage();

            $results = [
                'duration' => $end_time - $timer['start'],
                'memory_usage' => $end_memory - $timer['memory_start']
            ];

            // Store timer results
            if (isset($this->metrics[$context])) {
                $this->metrics[$context]['timers'][$name] = $results;
            }

            unset($this->timers[$context][$name]);

            return $results;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to stop timer: %s', $e->getMessage()),
                'performance_monitor',
                'error'
            );
            return false;
        }
    }

    /**
     * Take memory snapshot
     *
     * @param string $label Snapshot label
     * @param string $context Monitoring context
     * @return array Memory usage data
     */
    public function take_memory_snapshot($label, $context = 'general') {
        try {
            $snapshot = [
                'label' => $label,
                'time' => $this->current_time,
                'memory_usage' => memory_get_usage(),
                'peak_memory' => memory_get_peak_usage(),
                'memory_limit' => ini_get('memory_limit')
            ];

            if (!isset($this->memory_snapshots[$context])) {
                $this->memory_snapshots[$context] = [];
            }

            $this->memory_snapshots[$context][] = $snapshot;

            return $snapshot;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to take memory snapshot: %s', $e->getMessage()),
                'performance_monitor',
                'error'
            );
            return [];
        }
    }

    /**
     * Log database query
     *
     * @param string $query SQL query
     * @return string Original query
     */
    public function log_query($query) {
        try {
            foreach ($this->metrics as $context => &$metrics) {
                $metrics['queries'][] = [
                    'query' => $query,
                    'time' => microtime(true)
                ];
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log query: %s', $e->getMessage()),
                'performance_monitor',
                'error'
            );
        }

        return $query;
    }

    /**
     * Store performance metrics
     *
     * @param string $context Monitoring context
     * @param array $metrics Performance metrics
     * @return bool Success status
     */
    private function store_metrics($context, $metrics) {
        try {
            global $wpdb;

            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_performance_metrics',
                [
                    'context' => $context,
                    'duration' => $metrics['duration'],
                    'memory_usage' => $metrics['memory_usage'],
                    'peak_memory_usage' => $metrics['peak_memory_usage'],
                    'query_count' => count($metrics['queries']),
                    'metrics_data' => json_encode([
                        'timers' => $metrics['timers'],
                        'queries' => $metrics['queries'],
                        'memory_snapshots' => $this->memory_snapshots[$context] ?? []
                    ]),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                [
                    '%s', '%f', '%d', '%d', '%d', '%s', '%s', '%s'
                ]
            );

            return (bool)$inserted;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to store performance metrics: %s', $e->getMessage()),
                'performance_monitor',
                'error'
            );
            return false;
        }
    }

    /**
     * Get performance metrics
     *
     * @param array $params Query parameters
     * @return array Performance metrics
     */
    public function get_metrics($params = []) {
        try {
            global $wpdb;

            $params = wp_parse_args($params, [
                'context' => '',
                'start_date' => date('Y-m-d', strtotime('-7 days', strtotime($this->current_time))),
                'end_date' => date('Y-m-d', strtotime($this->current_time)),
                'limit' => 100
            ]);

            $query = "SELECT * FROM {$wpdb->prefix}mfw_performance_metrics WHERE 1=1";
            $query_params = [];

            if (!empty($params['context'])) {
                $query .= " AND context = %s";
                $query_params[] = $params['context'];
            }

            $query .= " AND created_at BETWEEN %s AND %s";
            $query_params[] = $params['start_date'] . ' 00:00:00';
            $query_params[] = $params['end_date'] . ' 23:59:59';

            $query .= " ORDER BY created_at DESC LIMIT %d";
            $query_params[] = $params['limit'];

            return $wpdb->get_results(
                $wpdb->prepare($query, $query_params),
                ARRAY_A
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get performance metrics: %s', $e->getMessage()),
                'performance_monitor',
                'error'
            );
            return [];
        }
    }
}