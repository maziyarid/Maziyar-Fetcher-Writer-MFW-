<?php
/**
 * Error Logger Class
 *
 * Manages error logging and monitoring for the plugin.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Error_Logger {
    /**
     * Current timestamp
     */
    private static $current_time = '2025-05-13 17:37:18';

    /**
     * Current user
     */
    private static $current_user = 'maziyarid';

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Log contexts
     */
    const CONTEXT_GENERAL = 'general';
    const CONTEXT_API = 'api';
    const CONTEXT_DATABASE = 'database';
    const CONTEXT_SECURITY = 'security';
    const CONTEXT_PERFORMANCE = 'performance';

    /**
     * Log message
     *
     * @param string $message Log message
     * @param string $context Log context
     * @param string $level Log level
     * @param array $additional_data Additional data to log
     * @return bool Success status
     */
    public static function log($message, $context = self::CONTEXT_GENERAL, $level = self::LEVEL_ERROR, $additional_data = []) {
        try {
            global $wpdb;

            // Validate log level
            if (!in_array($level, [self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
                $level = self::LEVEL_ERROR;
            }

            // Validate context
            if (!in_array($context, [self::CONTEXT_GENERAL, self::CONTEXT_API, self::CONTEXT_DATABASE, self::CONTEXT_SECURITY, self::CONTEXT_PERFORMANCE])) {
                $context = self::CONTEXT_GENERAL;
            }

            // Prepare log data
            $log_data = [
                'message' => $message,
                'context' => $context,
                'level' => $level,
                'additional_data' => $additional_data,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => self::get_client_ip(),
                'request_url' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? ''
            ];

            // Insert log entry
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_error_log',
                [
                    'message' => $message,
                    'context' => $context,
                    'level' => $level,
                    'log_data' => json_encode($log_data),
                    'created_by' => self::$current_user,
                    'created_at' => self::$current_time
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            // Update error statistics
            self::update_error_stats($context, $level);

            // Check if notification should be sent
            if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_ERROR) {
                self::notify_error($message, $context, $level, $log_data);
            }

            return true;

        } catch (Exception $e) {
            // If logging fails, write to WordPress debug log
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                error_log(sprintf('MFW Error Logger failed: %s', $e->getMessage()));
            }
            return false;
        }
    }

    /**
     * Get error logs
     *
     * @param array $params Query parameters
     * @return array Error logs
     */
    public static function get_logs($params = []) {
        try {
            global $wpdb;

            $params = wp_parse_args($params, [
                'context' => '',
                'level' => '',
                'start_date' => date('Y-m-d', strtotime('-7 days', strtotime(self::$current_time))),
                'end_date' => date('Y-m-d', strtotime(self::$current_time)),
                'search' => '',
                'order' => 'DESC',
                'limit' => 100,
                'offset' => 0
            ]);

            // Build query
            $query = "SELECT * FROM {$wpdb->prefix}mfw_error_log WHERE 1=1";
            $query_params = [];

            if (!empty($params['context'])) {
                $query .= " AND context = %s";
                $query_params[] = $params['context'];
            }

            if (!empty($params['level'])) {
                $query .= " AND level = %s";
                $query_params[] = $params['level'];
            }

            if (!empty($params['search'])) {
                $query .= " AND (message LIKE %s OR log_data LIKE %s)";
                $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
                $query_params[] = $search_term;
                $query_params[] = $search_term;
            }

            $query .= " AND created_at BETWEEN %s AND %s";
            $query_params[] = $params['start_date'] . ' 00:00:00';
            $query_params[] = $params['end_date'] . ' 23:59:59';

            // Add ordering
            $query .= " ORDER BY created_at " . ($params['order'] === 'ASC' ? 'ASC' : 'DESC');

            // Add limit and offset
            $query .= " LIMIT %d OFFSET %d";
            $query_params[] = $params['limit'];
            $query_params[] = $params['offset'];

            // Execute query
            return $wpdb->get_results(
                $wpdb->prepare($query, $query_params),
                ARRAY_A
            );

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Update error statistics
     *
     * @param string $context Error context
     * @param string $level Error level
     */
    private static function update_error_stats($context, $level) {
        global $wpdb;

        $date = date('Y-m-d', strtotime(self::$current_time));

        // Get current stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mfw_error_stats
            WHERE stats_date = %s AND context = %s",
            $date,
            $context
        ));

        if ($stats) {
            // Update existing stats
            $stats_data = json_decode($stats->stats_data, true);
            $stats_data[$level] = ($stats_data[$level] ?? 0) + 1;

            $wpdb->update(
                $wpdb->prefix . 'mfw_error_stats',
                [
                    'stats_data' => json_encode($stats_data),
                    'updated_at' => self::$current_time
                ],
                [
                    'stats_date' => $date,
                    'context' => $context
                ]
            );
        } else {
            // Create new stats
            $stats_data = [$level => 1];
            
            $wpdb->insert(
                $wpdb->prefix . 'mfw_error_stats',
                [
                    'stats_date' => $date,
                    'context' => $context,
                    'stats_data' => json_encode($stats_data),
                    'created_at' => self::$current_time,
                    'updated_at' => self::$current_time
                ]
            );
        }
    }

    /**
     * Send error notification
     *
     * @param string $message Error message
     * @param string $context Error context
     * @param string $level Error level
     * @param array $log_data Log data
     */
    private static function notify_error($message, $context, $level, $log_data) {
        // Get notification settings
        $settings_handler = new MFW_Settings_Handler();
        $notification_settings = $settings_handler->get_setting('notifications', 'notify_on_error');

        if ($notification_settings) {
            $notification_email = $settings_handler->get_setting('notifications', 'notification_email');
            
            if (!empty($notification_email)) {
                $subject = sprintf(
                    '[%s] %s Error: %s',
                    get_bloginfo('name'),
                    ucfirst($level),
                    substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
                );

                $body = sprintf(
                    "Error Details:\n\nMessage: %s\nContext: %s\nLevel: %s\nTime: %s\nUser: %s\n\nAdditional Data:\n%s",
                    $message,
                    $context,
                    $level,
                    self::$current_time,
                    self::$current_user,
                    print_r($log_data, true)
                );

                wp_mail($notification_email, $subject, $body);
            }
        }
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }

        return '';
    }
}