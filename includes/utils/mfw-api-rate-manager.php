<?php
/**
 * API Rate Manager Class
 *
 * Manages external API quotas, throttling, and usage optimization.
 * Different from Rate Limiter which handles internal rate limiting.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_API_Rate_Manager {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:48:18';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * API providers and their quota configurations
     */
    private $api_configs = [
        'openai' => [
            'tokens_per_minute' => 150000,
            'requests_per_minute' => 500,
            'max_retry_attempts' => 3,
            'retry_delay' => 5, // seconds
            'cost_per_token' => 0.000002 // USD
        ],
        'stability' => [
            'requests_per_minute' => 50,
            'credits_per_request' => 1,
            'max_retry_attempts' => 3,
            'retry_delay' => 10
        ],
        'google' => [
            'queries_per_second' => 10,
            'daily_quota' => 10000,
            'max_retry_attempts' => 2,
            'retry_delay' => 2
        ]
    ];

    /**
     * Check API availability
     *
     * @param string $provider API provider name
     * @param array $request_data Request details
     * @return bool|array False if quota exceeded, or array with quota info
     */
    public function check_availability($provider, $request_data = []) {
        try {
            if (!isset($this->api_configs[$provider])) {
                throw new Exception(sprintf(
                    __('Unknown API provider: %s', 'mfw'),
                    $provider
                ));
            }

            // Get current usage
            $usage = $this->get_current_usage($provider);
            $config = $this->api_configs[$provider];

            // Check different quota types
            switch ($provider) {
                case 'openai':
                    return $this->check_openai_quota($usage, $config, $request_data);
                
                case 'stability':
                    return $this->check_stability_quota($usage, $config);
                
                case 'google':
                    return $this->check_google_quota($usage, $config);
                
                default:
                    return $this->check_generic_quota($usage, $config);
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('API availability check failed: %s', $e->getMessage()),
                'api_rate_manager',
                'error'
            );
            return false;
        }
    }

    /**
     * Log API request
     *
     * @param string $provider API provider
     * @param array $request_data Request details
     * @param array $response_data Response details
     * @return bool Success status
     */
    public function log_request($provider, $request_data, $response_data) {
        try {
            global $wpdb;

            // Calculate cost if applicable
            $cost = $this->calculate_request_cost($provider, $request_data, $response_data);

            // Insert log entry
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_api_requests',
                [
                    'provider' => $provider,
                    'request_data' => json_encode($request_data),
                    'response_data' => json_encode($response_data),
                    'cost' => $cost,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%f', '%s', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            // Update usage statistics
            $this->update_usage_stats($provider, $request_data, $cost);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log API request: %s', $e->getMessage()),
                'api_rate_manager',
                'error'
            );
            return false;
        }
    }

    /**
     * Get current usage statistics
     *
     * @param string $provider API provider
     * @return array Usage statistics
     */
    private function get_current_usage($provider) {
        global $wpdb;

        $minute_ago = date('Y-m-d H:i:s', strtotime($this->current_time) - 60);
        $today = date('Y-m-d', strtotime($this->current_time));

        return [
            'requests_last_minute' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}mfw_api_requests
                WHERE provider = %s AND created_at >= %s",
                $provider,
                $minute_ago
            )),
            'requests_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}mfw_api_requests
                WHERE provider = %s AND DATE(created_at) = %s",
                $provider,
                $today
            )),
            'total_cost_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(cost) FROM {$wpdb->prefix}mfw_api_requests
                WHERE provider = %s AND DATE(created_at) = %s",
                $provider,
                $today
            ))
        ];
    }

    /**
     * Check OpenAI specific quota
     */
    private function check_openai_quota($usage, $config, $request_data) {
        // Check token limit
        $estimated_tokens = $this->estimate_tokens($request_data);
        if ($estimated_tokens > $config['tokens_per_minute']) {
            return false;
        }

        // Check request limit
        if ($usage['requests_last_minute'] >= $config['requests_per_minute']) {
            return false;
        }

        return [
            'available' => true,
            'remaining_tokens' => $config['tokens_per_minute'] - $estimated_tokens,
            'remaining_requests' => $config['requests_per_minute'] - $usage['requests_last_minute'],
            'estimated_cost' => $estimated_tokens * $config['cost_per_token']
        ];
    }

    /**
     * Estimate tokens for OpenAI request
     */
    private function estimate_tokens($request_data) {
        // Basic estimation: ~4 characters per token
        $text = '';
        if (isset($request_data['prompt'])) {
            $text .= $request_data['prompt'];
        }
        if (isset($request_data['messages'])) {
            foreach ($request_data['messages'] as $message) {
                $text .= $message['content'];
            }
        }
        return ceil(strlen($text) / 4);
    }

    /**
     * Calculate request cost
     */
    private function calculate_request_cost($provider, $request_data, $response_data) {
        switch ($provider) {
            case 'openai':
                return isset($response_data['usage']['total_tokens']) ?
                       $response_data['usage']['total_tokens'] * $this->api_configs['openai']['cost_per_token'] :
                       0;
            
            case 'stability':
                return isset($response_data['credits_used']) ?
                       $response_data['credits_used'] :
                       $this->api_configs['stability']['credits_per_request'];
            
            default:
                return 0;
        }
    }

    /**
     * Update usage statistics
     */
    private function update_usage_stats($provider, $request_data, $cost) {
        global $wpdb;

        $date = date('Y-m-d', strtotime($this->current_time));

        // Get current stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mfw_api_usage_stats
            WHERE stats_date = %s AND provider = %s",
            $date,
            $provider
        ));

        if ($stats) {
            // Update existing stats
            $wpdb->update(
                $wpdb->prefix . 'mfw_api_usage_stats',
                [
                    'request_count' => $stats->request_count + 1,
                    'total_cost' => $stats->total_cost + $cost,
                    'updated_at' => $this->current_time
                ],
                [
                    'stats_date' => $date,
                    'provider' => $provider
                ]
            );
        } else {
            // Insert new stats
            $wpdb->insert(
                $wpdb->prefix . 'mfw_api_usage_stats',
                [
                    'stats_date' => $date,
                    'provider' => $provider,
                    'request_count' => 1,
                    'total_cost' => $cost,
                    'created_at' => $this->current_time,
                    'updated_at' => $this->current_time
                ]
            );
        }
    }
}