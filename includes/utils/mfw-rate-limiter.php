<?php
/**
 * Rate Limiter Class
 *
 * Manages API and action rate limiting functionality.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Rate_Limiter {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:46:20';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Default limits
     */
    private $default_limits = [
        'api_requests' => [
            'requests' => 100,
            'period' => 3600 // 1 hour
        ],
        'content_generation' => [
            'requests' => 50,
            'period' => 3600 // 1 hour
        ],
        'image_generation' => [
            'requests' => 25,
            'period' => 3600 // 1 hour
        ],
        'batch_processing' => [
            'requests' => 10,
            'period' => 3600 // 1 hour
        ]
    ];

    /**
     * Check rate limit
     *
     * @param string $type Limit type
     * @param string $identifier User or API key identifier
     * @return bool True if within limits
     */
    public function check_limit($type, $identifier) {
        try {
            global $wpdb;

            // Get limit settings
            $limits = $this->get_limits($type);
            if (!$limits) {
                return true; // No limits defined
            }

            // Get current count
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mfw_rate_limit_log
                    WHERE type = %s
                    AND identifier = %s
                    AND created_at >= %s",
                    $type,
                    $identifier,
                    date('Y-m-d H:i:s', strtotime($this->current_time) - $limits['period'])
                )
            );

            return $count < $limits['requests'];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Rate limit check failed: %s', $e->getMessage()),
                'rate_limiter',
                'error'
            );
            return false;
        }
    }

    /**
     * Log rate limited action
     *
     * @param string $type Limit type
     * @param string $identifier User or API key identifier
     * @param array $data Additional data
     * @return bool Success status
     */
    public function log_action($type, $identifier, $data = []) {
        try {
            global $wpdb;

            // Insert log entry
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_rate_limit_log',
                [
                    'type' => $type,
                    'identifier' => $identifier,
                    'data' => json_encode($data),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            // Update usage statistics
            $this->update_usage_stats($type, $identifier);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log rate limited action: %s', $e->getMessage()),
                'rate_limiter',
                'error'
            );
            return false;
        }
    }

    /**
     * Get remaining limit
     *
     * @param string $type Limit type
     * @param string $identifier User or API key identifier
     * @return array|false Limit information or false on failure
     */
    public function get_remaining_limit($type, $identifier) {
        try {
            global $wpdb;

            // Get limit settings
            $limits = $this->get_limits($type);
            if (!$limits) {
                return false;
            }

            // Get current count
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mfw_rate_limit_log
                    WHERE type = %s
                    AND identifier = %s
                    AND created_at >= %s",
                    $type,
                    $identifier,
                    date('Y-m-d H:i:s', strtotime($this->current_time) - $limits['period'])
                )
            );

            // Calculate reset time
            $last_action = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT created_at FROM {$wpdb->prefix}mfw_rate_limit_log
                    WHERE type = %s
                    AND identifier = %s
                    ORDER BY created_at ASC
                    LIMIT 1",
                    $type,
                    $identifier
                )
            );

            $reset_time = $last_action ? 
                         strtotime($last_action) + $limits['period'] : 
                         strtotime($this->current_time) + $limits['period'];

            return [
                'limit' => $limits['requests'],
                'remaining' => max(0, $limits['requests'] - $count),
                'reset' => date('Y-m-d H:i:s', $reset_time),
                'period' => $limits['period']
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get remaining limit: %s', $e->getMessage()),
                'rate_limiter',
                'error'
            );
            return false;
        }
    }

    /**
     * Update rate limits
     *
     * @param array $limits New limits configuration
     * @return bool Success status
     */
    public function update_limits($limits) {
        try {
            // Validate limits
            foreach ($limits as $type => $config) {
                if (!isset($config['requests']) || !isset($config['period'])) {
                    throw new Exception(sprintf(
                        __('Invalid limit configuration for type: %s', 'mfw'),
                        $type
                    ));
                }
            }

            // Update settings
            $settings_handler = new MFW_Settings_Handler();
            return $settings_handler->update_setting('rate_limits', $limits);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update rate limits: %s', $e->getMessage()),
                'rate_limiter',
                'error'
            );
            return false;
        }
    }

    /**
     * Get usage statistics
     *
     * @param string $type Limit type
     * @param string $identifier User or API key identifier
     * @param array $params Query parameters
     * @return array Usage statistics
     */
    public function get_usage_stats($type = '', $identifier = '', $params = []) {
        try {
            global $wpdb;

            $params = wp_parse_args($params, [
                'start_date' => date('Y-m-d', strtotime('-7 days', strtotime($this->current_time))),
                'end_date' => date('Y-m-d', strtotime($this->current_time)),
                'group_by' => 'day'
            ]);

            // Build query
            $query = "SELECT 
                        DATE(created_at) as date,
                        type,
                        identifier,
                        COUNT(*) as count
                     FROM {$wpdb->prefix}mfw_rate_limit_log
                     WHERE created_at BETWEEN %s AND %s";
            
            $query_params = [
                $params['start_date'] . ' 00:00:00',
                $params['end_date'] . ' 23:59:59'
            ];

            if (!empty($type)) {
                $query .= " AND type = %s";
                $query_params[] = $type;
            }

            if (!empty($identifier)) {
                $query .= " AND identifier = %s";
                $query_params[] = $identifier;
            }

            // Group by clause
            switch ($params['group_by']) {
                case 'hour':
                    $query .= " GROUP BY DATE(created_at), HOUR(created_at), type, identifier";
                    break;
                case 'week':
                    $query .= " GROUP BY YEARWEEK(created_at), type, identifier";
                    break;
                case 'month':
                    $query .= " GROUP BY YEAR(created_at), MONTH(created_at), type, identifier";
                    break;
                default:
                    $query .= " GROUP BY DATE(created_at), type, identifier";
            }

            return $wpdb->get_results(
                $wpdb->prepare($query, $query_params),
                ARRAY_A
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get usage statistics: %s', $e->getMessage()),
                'rate_limiter',
                'error'
            );
            return [];
        }
    }

    /**
     * Get rate limits
     *
     * @param string $type Limit type
     * @return array|false Limit configuration or false if not found
     */
    private function get_limits($type) {
        // Get configured limits
        $settings_handler = new MFW_Settings_Handler();
        $limits = $settings_handler->get_setting('rate_limits');

        // Fall back to defaults if not configured
        if (!$limits) {
            $limits = $this->default_limits;
        }

        return isset($limits[$type]) ? $limits[$type] : false;
    }

    /**
     * Update usage statistics
     *
     * @param string $type Limit type
     * @param string $identifier User or API key identifier
     */
    private function update_usage_stats($type, $identifier) {
        try {
            global $wpdb;

            $date = date('Y-m-d', strtotime($this->current_time));

            // Get current stats
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mfw_rate_limit_stats
                WHERE stats_date = %s AND type = %s AND identifier = %s",
                $date,
                $type,
                $identifier
            ));

            if ($stats) {
                // Update existing stats
                $wpdb->update(
                    $wpdb->prefix . 'mfw_rate_limit_stats',
                    [
                        'request_count' => $stats->request_count + 1,
                        'updated_at' => $this->current_time
                    ],
                    [
                        'stats_date' => $date,
                        'type' => $type,
                        'identifier' => $identifier
                    ]
                );
            } else {
                // Insert new stats
                $wpdb->insert(
                    $wpdb->prefix . 'mfw_rate_limit_stats',
                    [
                        'stats_date' => $date,
                        'type' => $type,
                        'identifier' => $identifier,
                        'request_count' => 1,
                        'created_at' => $this->current_time,
                        'updated_at' => $this->current_time
                    ]
                );
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update usage statistics: %s', $e->getMessage()),
                'rate_limiter',
                'error'
            );
        }
    }
}