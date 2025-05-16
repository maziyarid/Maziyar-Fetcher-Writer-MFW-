<?php
/**
 * Statistics Handler Class
 * 
 * Manages data collection, analysis, and reporting functionality.
 * Supports various types of statistics and metrics tracking.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Statistics_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:17:28';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Cache handler instance
     */
    private $cache;

    /**
     * Date formatter instance
     */
    private $formatter;

    /**
     * Initialize statistics handler
     */
    public function __construct() {
        $this->cache = new MFW_Cache_Handler();
        $this->formatter = new MFW_Data_Formatter();

        // Schedule cleanup tasks
        if (!wp_next_scheduled('mfw_cleanup_statistics')) {
            wp_schedule_event(time(), 'daily', 'mfw_cleanup_statistics');
        }

        // Add hooks for data collection
        add_action('mfw_record_event', [$this, 'record_event'], 10, 3);
        add_action('mfw_cleanup_statistics', [$this, 'cleanup_old_data']);
    }

    /**
     * Record statistical event
     *
     * @param string $type Event type
     * @param mixed $data Event data
     * @param array $meta Additional metadata
     * @return bool Success status
     */
    public function record_event($type, $data = null, $meta = []) {
        try {
            global $wpdb;

            // Prepare event data
            $event = [
                'event_type' => $type,
                'event_data' => is_array($data) || is_object($data) ? json_encode($data) : $data,
                'metadata' => json_encode($meta),
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_by' => $this->current_user,
                'created_at' => $this->current_time
            ];

            // Insert event
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_statistics_events',
                $event,
                ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            // Update aggregated statistics
            $this->update_aggregates($type, $data);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to record event: %s', $e->getMessage()),
                'statistics_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get statistics for a period
     *
     * @param string $metric Metric name
     * @param string $period Period type (day, week, month, year)
     * @param array $filters Optional filters
     * @return array Statistics data
     */
    public function get_statistics($metric, $period = 'day', $filters = []) {
        try {
            // Generate cache key
            $cache_key = $this->generate_cache_key($metric, $period, $filters);

            // Check cache
            $cached = $this->cache->get($cache_key, 'mfw_statistics');
            if ($cached !== false) {
                return $cached;
            }

            // Calculate date range
            $range = $this->calculate_date_range($period);

            // Get raw data
            $data = $this->get_raw_data($metric, $range['start'], $range['end'], $filters);

            // Process data based on metric type
            $processed = $this->process_metric_data($metric, $data, $period);

            // Cache results
            $this->cache->set($cache_key, $processed, 'mfw_statistics', 3600);

            return $processed;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get statistics: %s', $e->getMessage()),
                'statistics_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Generate statistics report
     *
     * @param array $metrics Metrics to include
     * @param string $period Report period
     * @param array $options Report options
     * @return array Report data
     */
    public function generate_report($metrics, $period = 'day', $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'format' => 'array',
                'compare_previous' => true,
                'include_charts' => true
            ]);

            $report = [
                'period' => $period,
                'generated_at' => $this->current_time,
                'metrics' => []
            ];

            foreach ($metrics as $metric) {
                // Get current period data
                $current = $this->get_statistics($metric, $period);

                // Get previous period data if requested
                $previous = [];
                if ($options['compare_previous']) {
                    $previous = $this->get_statistics($metric, $period, [
                        'date_range' => $this->get_previous_period($period)
                    ]);
                }

                $report['metrics'][$metric] = [
                    'current' => $current,
                    'previous' => $previous,
                    'change' => $this->calculate_change($current, $previous)
                ];

                // Generate chart data if requested
                if ($options['include_charts']) {
                    $report['metrics'][$metric]['chart'] = $this->generate_chart_data(
                        $metric,
                        $current,
                        $previous
                    );
                }
            }

            // Format report based on requested format
            return $this->format_report($report, $options['format']);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to generate report: %s', $e->getMessage()),
                'statistics_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Clean up old statistical data
     */
    public function cleanup_old_data() {
        try {
            global $wpdb;

            // Get retention period from settings
            $retention_days = get_option('mfw_statistics_retention_days', 90);
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

            // Delete old events
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}mfw_statistics_events
                WHERE created_at < %s",
                $cutoff_date
            ));

            // Delete old aggregates
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}mfw_statistics_aggregates
                WHERE date < %s",
                $cutoff_date
            ));

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to cleanup old data: %s', $e->getMessage()),
                'statistics_handler',
                'error'
            );
        }
    }

    /**
     * Update aggregated statistics
     *
     * @param string $type Event type
     * @param mixed $data Event data
     */
    private function update_aggregates($type, $data) {
        try {
            global $wpdb;

            $date = date('Y-m-d', strtotime($this->current_time));

            // Update daily aggregate
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}mfw_statistics_aggregates
                (date, metric, value, count)
                VALUES (%s, %s, %s, 1)
                ON DUPLICATE KEY UPDATE
                value = value + VALUES(value),
                count = count + 1",
                $date,
                $type,
                is_numeric($data) ? $data : 1
            ));

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update aggregates: %s', $e->getMessage()),
                'statistics_handler',
                'error'
            );
        }
    }

    /**
     * Calculate date range for period
     *
     * @param string $period Period type
     * @return array Start and end dates
     */
    private function calculate_date_range($period) {
        $end = strtotime($this->current_time);
        
        switch ($period) {
            case 'week':
                $start = strtotime('-7 days', $end);
                break;
            case 'month':
                $start = strtotime('-30 days', $end);
                break;
            case 'year':
                $start = strtotime('-365 days', $end);
                break;
            default:
                $start = strtotime('-24 hours', $end);
        }

        return [
            'start' => date('Y-m-d H:i:s', $start),
            'end' => date('Y-m-d H:i:s', $end)
        ];
    }

    /**
     * Get raw statistical data
     *
     * @param string $metric Metric name
     * @param string $start Start date
     * @param string $end End date
     * @param array $filters Data filters
     * @return array Raw data
     */
    private function get_raw_data($metric, $start, $end, $filters) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT date, metric, value, count
            FROM {$wpdb->prefix}mfw_statistics_aggregates
            WHERE metric = %s
            AND date BETWEEN %s AND %s",
            $metric,
            $start,
            $end
        );

        // Apply additional filters
        foreach ($filters as $key => $value) {
            if ($key === 'user_id') {
                $query .= $wpdb->prepare(" AND user_id = %d", $value);
            }
        }

        return $wpdb->get_results($query);
    }

    /**
     * Generate cache key
     *
     * @param string $metric Metric name
     * @param string $period Period type
     * @param array $filters Data filters
     * @return string Cache key
     */
    private function generate_cache_key($metric, $period, $filters) {
        return md5($metric . $period . serialize($filters));
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (isset($_SERVER[$header])) {
                foreach (explode(',', $_SERVER[$header]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return '0.0.0.0';
    }
}