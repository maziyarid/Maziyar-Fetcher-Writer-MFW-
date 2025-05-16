<?php
/**
 * Logging Handler Class
 * 
 * Manages system logging, error tracking, and debugging functionality.
 * Supports multiple log levels, formats, and storage methods.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Logging_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:23:43';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Logging settings
     */
    private $settings;

    /**
     * Initialize logging handler
     */
    public function __construct() {
        $this->settings = get_option('mfw_logging_settings', []);

        // Add hooks for error handling
        if (!empty($this->settings['catch_php_errors'])) {
            set_error_handler([$this, 'handle_php_error']);
            set_exception_handler([$this, 'handle_exception']);
        }

        // Schedule log cleanup
        if (!wp_next_scheduled('mfw_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'mfw_cleanup_logs');
        }
        add_action('mfw_cleanup_logs', [$this, 'cleanup_old_logs']);
    }

    /**
     * Log message
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param string $context Log context
     * @param array $data Additional data
     * @return bool Success status
     */
    public function log($message, $level = self::LEVEL_INFO, $context = '', $data = []) {
        try {
            // Check if logging is enabled for this level
            if (!$this->is_level_enabled($level)) {
                return false;
            }

            global $wpdb;

            // Prepare log data
            $log_data = [
                'message' => $message,
                'level' => $level,
                'context' => $context,
                'data' => json_encode($data),
                'url' => $this->get_current_url(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_by' => $this->current_user,
                'created_at' => $this->current_time
            ];

            // Insert log entry
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_logs',
                $log_data,
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            // Write to file if configured
            if (!empty($this->settings['file_logging'])) {
                $this->write_to_file($log_data);
            }

            // Send alert for critical errors
            if ($level === self::LEVEL_CRITICAL) {
                $this->send_alert($log_data);
            }

            return true;

        } catch (Exception $e) {
            // Fallback to error log
            error_log(sprintf(
                '[MFW] Logging failed: %s. Original message: %s',
                $e->getMessage(),
                $message
            ));
            return false;
        }
    }

    /**
     * Get logs
     *
     * @param array $args Query arguments
     * @return array Log entries
     */
    public function get_logs($args = []) {
        try {
            global $wpdb;

            // Parse arguments
            $args = wp_parse_args($args, [
                'level' => '',
                'context' => '',
                'search' => '',
                'date_from' => '',
                'date_to' => '',
                'limit' => 100,
                'offset' => 0,
                'order' => 'DESC'
            ]);

            // Build query
            $query = "SELECT * FROM {$wpdb->prefix}mfw_logs WHERE 1=1";
            $params = [];

            if ($args['level']) {
                $query .= " AND level = %s";
                $params[] = $args['level'];
            }

            if ($args['context']) {
                $query .= " AND context = %s";
                $params[] = $args['context'];
            }

            if ($args['search']) {
                $query .= " AND (message LIKE %s OR data LIKE %s)";
                $search = '%' . $wpdb->esc_like($args['search']) . '%';
                $params[] = $search;
                $params[] = $search;
            }

            if ($args['date_from']) {
                $query .= " AND created_at >= %s";
                $params[] = $args['date_from'];
            }

            if ($args['date_to']) {
                $query .= " AND created_at <= %s";
                $params[] = $args['date_to'];
            }

            // Add order and limit
            $query .= " ORDER BY created_at " . $args['order'];
            $query .= " LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];

            // Execute query
            $logs = $wpdb->get_results(
                $wpdb->prepare($query, $params)
            );

            // Format logs
            return array_map([$this, 'format_log'], $logs);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get logs: %s', $e->getMessage()),
                'logging_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Handle PHP error
     *
     * @param int $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool Whether to continue with PHP error handling
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Map PHP error level to log level
        $levels = [
            E_ERROR => self::LEVEL_ERROR,
            E_WARNING => self::LEVEL_WARNING,
            E_PARSE => self::LEVEL_ERROR,
            E_NOTICE => self::LEVEL_INFO,
            E_CORE_ERROR => self::LEVEL_CRITICAL,
            E_CORE_WARNING => self::LEVEL_WARNING,
            E_COMPILE_ERROR => self::LEVEL_CRITICAL,
            E_COMPILE_WARNING => self::LEVEL_WARNING,
            E_USER_ERROR => self::LEVEL_ERROR,
            E_USER_WARNING => self::LEVEL_WARNING,
            E_USER_NOTICE => self::LEVEL_INFO,
            E_STRICT => self::LEVEL_INFO,
            E_RECOVERABLE_ERROR => self::LEVEL_ERROR,
            E_DEPRECATED => self::LEVEL_INFO,
            E_USER_DEPRECATED => self::LEVEL_INFO
        ];

        $level = $levels[$errno] ?? self::LEVEL_ERROR;

        $this->log(
            $errstr,
            $level,
            'php_error',
            [
                'file' => $errfile,
                'line' => $errline,
                'error_number' => $errno
            ]
        );

        // Let PHP handle the error as well
        return false;
    }

    /**
     * Handle uncaught exception
     *
     * @param Throwable $exception Exception object
     */
    public function handle_exception($exception) {
        $this->log(
            $exception->getMessage(),
            self::LEVEL_ERROR,
            'uncaught_exception',
            [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]
        );
    }

    /**
     * Clean up old logs
     */
    public function cleanup_old_logs() {
        try {
            global $wpdb;

            // Get retention period from settings
            $retention_days = !empty($this->settings['retention_days']) ? 
                            $this->settings['retention_days'] : 30;

            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

            // Delete old logs
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}mfw_logs
                WHERE created_at < %s",
                $cutoff_date
            ));

            // Archive to file if configured
            if (!empty($this->settings['archive_logs'])) {
                $this->archive_logs($cutoff_date);
            }

        } catch (Exception $e) {
            error_log(sprintf(
                '[MFW] Failed to cleanup logs: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Format log entry
     *
     * @param object $log Log entry
     * @return array Formatted log
     */
    private function format_log($log) {
        return [
            'id' => $log->id,
            'message' => $log->message,
            'level' => $log->level,
            'context' => $log->context,
            'data' => json_decode($log->data, true),
            'url' => $log->url,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_by' => $log->created_by,
            'created_at' => $log->created_at,
            'relative_time' => human_time_diff(
                strtotime($log->created_at),
                strtotime($this->current_time)
            ) . ' ago'
        ];
    }

    /**
     * Write log to file
     *
     * @param array $log_data Log data
     */
    private function write_to_file($log_data) {
        $log_dir = WP_CONTENT_DIR . '/mfw-logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $file = $log_dir . '/' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] %s: %s | Context: %s | Data: %s\n",
            $log_data['created_at'],
            strtoupper($log_data['level']),
            $log_data['message'],
            $log_data['context'],
            $log_data['data']
        );

        file_put_contents($file, $entry, FILE_APPEND);
    }

    /**
     * Send alert for critical error
     *
     * @param array $log_data Log data
     */
    private function send_alert($log_data) {
        if (empty($this->settings['alert_email'])) {
            return;
        }

        $email_handler = new MFW_Email_Handler();
        $email_handler->send(
            $this->settings['alert_email'],
            sprintf(__('Critical Error Alert: %s', 'mfw'), $log_data['message']),
            [
                'template' => 'error_alert',
                'variables' => [
                    'log' => $this->format_log((object)$log_data)
                ]
            ]
        );
    }

    /**
     * Check if logging is enabled for level
     *
     * @param string $level Log level
     * @return bool Whether logging is enabled
     */
    private function is_level_enabled($level) {
        if (empty($this->settings['enabled_levels'])) {
            return true;
        }

        return in_array($level, $this->settings['enabled_levels']);
    }

    /**
     * Get current URL
     *
     * @return string Current URL
     */
    private function get_current_url() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
               "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
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