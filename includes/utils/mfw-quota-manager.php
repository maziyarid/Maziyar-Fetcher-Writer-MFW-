<?php
/**
 * Quota Manager Class
 *
 * Manages user quotas and usage limits for AI services.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Quota_Manager {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:24:04';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Default quotas by user role
     *
     * @var array
     */
    private $default_quotas = [
        'administrator' => [
            'text_tokens' => 1000000,
            'image_generations' => 1000,
            'api_calls' => 10000
        ],
        'editor' => [
            'text_tokens' => 500000,
            'image_generations' => 500,
            'api_calls' => 5000
        ],
        'author' => [
            'text_tokens' => 250000,
            'image_generations' => 250,
            'api_calls' => 2500
        ],
        'contributor' => [
            'text_tokens' => 100000,
            'image_generations' => 100,
            'api_calls' => 1000
        ],
        'subscriber' => [
            'text_tokens' => 50000,
            'image_generations' => 50,
            'api_calls' => 500
        ]
    ];

    /**
     * Quota periods
     */
    const PERIOD_DAILY = 'daily';
    const PERIOD_WEEKLY = 'weekly';
    const PERIOD_MONTHLY = 'monthly';

    /**
     * Check quota availability
     *
     * @param string $quota_type Quota type
     * @param string $user User ID or login
     * @param string $period Quota period
     * @return array Quota status
     */
    public function check_quota($quota_type, $user = '', $period = self::PERIOD_MONTHLY) {
        if (empty($user)) {
            $user = $this->current_user;
        }

        // Get user's quota limit
        $limit = $this->get_user_quota_limit($user, $quota_type);

        // Get current usage
        $usage = $this->get_usage($user, $quota_type, $period);

        // Calculate remaining quota
        $remaining = $limit - $usage;

        return [
            'limit' => $limit,
            'used' => $usage,
            'remaining' => max(0, $remaining),
            'percentage_used' => $limit > 0 ? round(($usage / $limit) * 100, 2) : 100
        ];
    }

    /**
     * Record quota usage
     *
     * @param string $quota_type Quota type
     * @param int $amount Usage amount
     * @param string $user User ID or login
     * @return bool Success status
     */
    public function record_usage($quota_type, $amount = 1, $user = '') {
        try {
            global $wpdb;

            if (empty($user)) {
                $user = $this->current_user;
            }

            // Check if quota is available
            $quota_status = $this->check_quota($quota_type, $user);
            if ($quota_status['remaining'] < $amount) {
                throw new Exception(__('Quota limit exceeded.', 'mfw'));
            }

            // Record usage
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_quota_usage',
                [
                    'user' => $user,
                    'quota_type' => $quota_type,
                    'amount' => $amount,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%d', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            // Update user statistics
            $this->update_user_usage_stats($user, $quota_type, $amount);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to record quota usage: %s', $e->getMessage()),
                'quota_manager',
                'error'
            );
            return false;
        }
    }

    /**
     * Get user's quota usage
     *
     * @param string $user User ID or login
     * @param string $quota_type Quota type
     * @param string $period Quota period
     * @return int Usage amount
     */
    public function get_usage($user, $quota_type, $period = self::PERIOD_MONTHLY) {
        global $wpdb;

        $date_from = $this->get_period_start_date($period);

        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0)
            FROM {$wpdb->prefix}mfw_quota_usage
            WHERE user = %s
            AND quota_type = %s
            AND created_at >= %s",
            $user,
            $quota_type,
            $date_from
        ));
    }

    /**
     * Get user's quota limit
     *
     * @param string $user User ID or login
     * @param string $quota_type Quota type
     * @return int Quota limit
     */
    private function get_user_quota_limit($user, $quota_type) {
        // Get custom quota if set
        $custom_quota = $this->get_custom_quota($user, $quota_type);
        if ($custom_quota !== null) {
            return $custom_quota;
        }

        // Get user's role
        $user_obj = get_user_by('login', $user);
        if (!$user_obj) {
            return 0;
        }

        $role = reset($user_obj->roles);

        // Return quota based on role
        return $this->default_quotas[$role][$quota_type] ?? 0;
    }

    /**
     * Get custom quota for user
     *
     * @param string $user User ID or login
     * @param string $quota_type Quota type
     * @return int|null Custom quota or null if not set
     */
    private function get_custom_quota($user, $quota_type) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT quota_limit
            FROM {$wpdb->prefix}mfw_custom_quotas
            WHERE user = %s
            AND quota_type = %s
            AND (expiry_date IS NULL OR expiry_date > %s)",
            $user,
            $quota_type,
            $this->current_time
        ));
    }

    /**
     * Set custom quota for user
     *
     * @param string $user User ID or login
     * @param string $quota_type Quota type
     * @param int $limit Quota limit
     * @param string $expiry_date Optional expiry date
     * @return bool Success status
     */
    public function set_custom_quota($user, $quota_type, $limit, $expiry_date = null) {
        try {
            global $wpdb;

            $data = [
                'user' => $user,
                'quota_type' => $quota_type,
                'quota_limit' => $limit,
                'expiry_date' => $expiry_date,
                'created_by' => $this->current_user,
                'created_at' => $this->current_time
            ];

            $format = ['%s', '%s', '%d', '%s', '%s', '%s'];

            // Check if custom quota exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}mfw_custom_quotas
                WHERE user = %s AND quota_type = %s",
                $user,
                $quota_type
            ));

            if ($exists) {
                unset($data['created_by'], $data['created_at']);
                $data['updated_by'] = $this->current_user;
                $data['updated_at'] = $this->current_time;

                $updated = $wpdb->update(
                    $wpdb->prefix . 'mfw_custom_quotas',
                    $data,
                    [
                        'user' => $user,
                        'quota_type' => $quota_type
                    ],
                    $format,
                    ['%s', '%s']
                );
            } else {
                $updated = $wpdb->insert(
                    $wpdb->prefix . 'mfw_custom_quotas',
                    $data,
                    $format
                );
            }

            return (bool)$updated;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to set custom quota: %s', $e->getMessage()),
                'quota_manager',
                'error'
            );
            return false;
        }
    }

    /**
     * Update user usage statistics
     *
     * @param string $user User ID or login
     * @param string $quota_type Quota type
     * @param int $amount Usage amount
     */
    private function update_user_usage_stats($user, $quota_type, $amount) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mfw_user_usage_stats
            WHERE user = %s AND quota_type = %s",
            $user,
            $quota_type
        ));

        if ($stats) {
            $wpdb->update(
                $wpdb->prefix . 'mfw_user_usage_stats',
                [
                    'total_usage' => $stats->total_usage + $amount,
                    'last_usage' => $amount,
                    'last_used_at' => $this->current_time,
                    'updated_at' => $this->current_time
                ],
                [
                    'user' => $user,
                    'quota_type' => $quota_type
                ],
                ['%d', '%d', '%s', '%s'],
                ['%s', '%s']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'mfw_user_usage_stats',
                [
                    'user' => $user,
                    'quota_type' => $quota_type,
                    'total_usage' => $amount,
                    'last_usage' => $amount,
                    'last_used_at' => $this->current_time,
                    'created_at' => $this->current_time,
                    'updated_at' => $this->current_time
                ],
                ['%s', '%s', '%d', '%d', '%s', '%s', '%s']
            );
        }
    }

    /**
     * Get period start date
     *
     * @param string $period Quota period
     * @return string Start date
     */
    private function get_period_start_date($period) {
        switch ($period) {
            case self::PERIOD_DAILY:
                return date('Y-m-d 00:00:00', strtotime($this->current_time));
            case self::PERIOD_WEEKLY:
                return date('Y-m-d 00:00:00', strtotime('monday this week', strtotime($this->current_time)));
            case self::PERIOD_MONTHLY:
                return date('Y-m-01 00:00:00', strtotime($this->current_time));
            default:
                return date('Y-m-01 00:00:00', strtotime($this->current_time));
        }
    }
}