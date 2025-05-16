<?php
/**
 * Loggable Trait
 * 
 * Implements logging functionality for classes.
 * Provides methods for logging messages with different severity levels and context.
 *
 * @package MFW
 * @subpackage Traits
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

trait MFW_Loggable {
    /**
     * Log levels
     */
    private $log_levels = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7
    ];

    /**
     * Minimum log level
     *
     * @var string
     */
    private $minimum_log_level = 'debug';

    /**
     * Last log timestamp
     *
     * @var string
     */
    private $last_log = '2025-05-14 06:28:59';

    /**
     * Last logger
     *
     * @var string
     */
    private $last_logger = 'maziyarid';

    /**
     * Initialize logger
     *
     * @param string $min_level Minimum log level
     * @return void
     */
    protected function init_logger($min_level = 'debug') {
        $this->minimum_log_level = $min_level;
    }

    /**
     * Log a message
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function log($message, $level = 'info', array $context = []) {
        if (!$this->is_level_enabled($level)) {
            return false;
        }

        try {
            $this->last_log = current_time('mysql');
            $this->last_logger = wp_get_current_user()->user_login;

            return $this->write_log([
                'message' => $this->interpolate($message, $context),
                'level' => $level,
                'context' => $context,
                'class' => get_class($this),
                'created_at' => $this->last_log,
                'created_by' => $this->last_logger
            ]);

        } catch (Exception $e) {
            error_log(sprintf('Failed to write log: %s', $e->getMessage()));
            return false;
        }
    }

    /**
     * Log an emergency message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function emergency($message, array $context = []) {
        return $this->log($message, 'emergency', $context);
    }

    /**
     * Log an alert message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function alert($message, array $context = []) {
        return $this->log($message, 'alert', $context);
    }

    /**
     * Log a critical message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function critical($message, array $context = []) {
        return $this->log($message, 'critical', $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function error($message, array $context = []) {
        return $this->log($message, 'error', $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function warning($message, array $context = []) {
        return $this->log($message, 'warning', $context);
    }

    /**
     * Log a notice message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function notice($message, array $context = []) {
        return $this->log($message, 'notice', $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function info($message, array $context = []) {
        return $this->log($message, 'info', $context);
    }

    /**
     * Log a debug message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    protected function debug($message, array $context = []) {
        return $this->log($message, 'debug', $context);
    }

    /**
     * Check if log level is enabled
     *
     * @param string $level Log level
     * @return bool Whether level is enabled
     */
    private function is_level_enabled($level) {
        return $this->log_levels[$level] <= $this->log_levels[$this->minimum_log_level];
    }

    /**
     * Write log to database
     *
     * @param array $log_entry Log entry data
     * @return bool Whether write was successful
     */
    private function write_log($log_entry) {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'mfw_log',
            [
                'message' => $log_entry['message'],
                'level' => $log_entry['level'],
                'context' => json_encode($log_entry['context']),
                'loggable_class' => $log_entry['class'],
                'created_at' => $log_entry['created_at'],
                'created_by' => $log_entry['created_by']
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Interpolate message with context
     *
     * @param string $message Message with placeholders
     * @param array $context Context values
     * @return string Interpolated message
     */
    private function interpolate($message, array $context = []) {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    /**
     * Get log entries
     *
     * @param array $filters Log filters
     * @param array $options Query options
     * @return array Log entries
     */
    public function get_logs(array $filters = [], array $options = []) {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}mfw_log WHERE loggable_class = %s";
        $params = [get_class($this)];

        if (!empty($filters['level'])) {
            $query .= " AND level = %s";
            $params[] = $filters['level'];
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND created_at >= %s";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND created_at <= %s";
            $params[] = $filters['date_to'];
        }

        $query .= " ORDER BY created_at DESC";

        if (!empty($options['limit'])) {
            $query .= " LIMIT %d";
            $params[] = $options['limit'];
        }

        if (!empty($options['offset'])) {
            $query .= " OFFSET %d";
            $params[] = $options['offset'];
        }

        return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
    }

    /**
     * Clear log entries
     *
     * @param array $filters Log filters
     * @return bool Whether clear was successful
     */
    public function clear_logs(array $filters = []) {
        global $wpdb;

        $query = "DELETE FROM {$wpdb->prefix}mfw_log WHERE loggable_class = %s";
        $params = [get_class($this)];

        if (!empty($filters['level'])) {
            $query .= " AND level = %s";
            $params[] = $filters['level'];
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND created_at >= %s";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND created_at <= %s";
            $params[] = $filters['date_to'];
        }

        return $wpdb->query($wpdb->prepare($query, $params)) !== false;
    }
}