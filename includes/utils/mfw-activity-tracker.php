<?php
/**
 * User Activity Tracker Class
 *
 * Tracks and logs user interactions with the plugin.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Activity_Tracker {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:22:35';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Activity types
     */
    const TYPE_CONTENT_GENERATION = 'content_generation';
    const TYPE_IMAGE_GENERATION = 'image_generation';
    const TYPE_API_REQUEST = 'api_request';
    const TYPE_SETTINGS_CHANGE = 'settings_change';
    const TYPE_TEMPLATE_CHANGE = 'template_change';
    const TYPE_ERROR = 'error';

    /**
     * Track activity
     *
     * @param string $type Activity type
     * @param string $action Action performed
     * @param array $details Activity details
     * @return bool Success status
     */
    public function track($type, $action, $details = []) {
        try {
            global $wpdb;

            // Prepare metadata
            $metadata = array_merge($details, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'referer' => $_SERVER['HTTP_REFERER'] ?? ''
            ]);

            // Insert activity record
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_activity_log',
                [
                    'type' => $type,
                    'action' => $action,
                    'user' => $this->current_user,
                    'metadata' => json_encode($metadata),
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            // Update user statistics
            $this->update_user_stats($type, $action);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to track activity: %s', $e->getMessage()),
                'activity_tracker',
                'error'
            );
            return false;
        }
    }

    /**
     * Get user activity
     *
     * @param string $user User ID or login
     * @param array $filters Activity filters
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array Activity records
     */
    public function get_user_activity($user = '', $filters = [], $limit = 50, $offset = 0) {
        global $wpdb;

        if (empty($user)) {
            $user = $this->current_user;
        }

        // Build query
        $query = "SELECT * FROM {$wpdb->prefix}mfw_activity_log WHERE user = %s";
        $params = [$user];

        // Apply filters
        if (!empty($filters['type'])) {
            $query .= " AND type = %s";
            $params[] = $filters['type'];
        }

        if (!empty($filters['action'])) {
            $query .= " AND action = %s";
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND created_at >= %s";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND created_at <= %s";
            $params[] = $filters['date_to'];
        }

        // Add ordering and limits
        $query .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        // Execute query
        return $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );
    }

    /**
     * Get activity statistics
     *
     * @param string $type Activity type
     * @param string $interval Time interval (day, week, month)
     * @return array Activity statistics
     */
    public function get_activity_stats($type = '', $interval = 'day') {
        global $wpdb;

        // Calculate date range
        $date_from = date('Y-m-d H:i:s', strtotime("-1 {$interval}", strtotime($this->current_time)));

        // Build query
        $query = "SELECT 
            type,
            action,
            COUNT(*) as count,
            MIN(created_at) as first_occurrence,
            MAX(created_at) as last_occurrence
        FROM {$wpdb->prefix}mfw_activity_log
        WHERE created_at >= %s";
        $params = [$date_from];

        if (!empty($type)) {
            $query .= " AND type = %s";
            $params[] = $type;
        }

        $query .= " GROUP BY type, action";

        // Execute query
        return $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );
    }

    /**
     * Update user statistics
     *
     * @param string $type Activity type
     * @param string $action Action performed
     */
    private function update_user_stats($type, $action) {
        global $wpdb;

        // Get current stats
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mfw_user_stats 
            WHERE user = %s AND type = %s",
            $this->current_user,
            $type
        ));

        if ($stats) {
            // Update existing stats
            $wpdb->update(
                $wpdb->prefix . 'mfw_user_stats',
                [
                    'total_actions' => $stats->total_actions + 1,
                    'last_action' => $action,
                    'last_activity' => $this->current_time,
                    'updated_at' => $this->current_time
                ],
                [
                    'user' => $this->current_user,
                    'type' => $type
                ],
                ['%d', '%s', '%s', '%s'],
                ['%s', '%s']
            );
        } else {
            // Insert new stats
            $wpdb->insert(
                $wpdb->prefix . 'mfw_user_stats',
                [
                    'user' => $this->current_user,
                    'type' => $type,
                    'total_actions' => 1,
                    'last_action' => $action,
                    'last_activity' => $this->current_time,
                    'created_at' => $this->current_time,
                    'updated_at' => $this->current_time
                ],
                ['%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );
        }
    }

    /**
     * Get user statistics
     *
     * @param string $user User ID or login
     * @return array User statistics
     */
    public function get_user_stats($user = '') {
        global $wpdb;

        if (empty($user)) {
            $user = $this->current_user;
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mfw_user_stats 
            WHERE user = %s",
            $user
        ), ARRAY_A);
    }

    /**
     * Clean up old activity logs
     *
     * @param int $days Days to keep
     * @return bool Success status
     */
    public function cleanup_logs($days = 30) {
        try {
            global $wpdb;

            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($this->current_time)));

            // Delete old activity logs
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}mfw_activity_log 
                WHERE created_at < %s",
                $cutoff_date
            ));

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to cleanup activity logs: %s', $e->getMessage()),
                'activity_tracker',
                'error'
            );
            return false;
        }
    }

    /**
     * Get recent activity summary
     *
     * @param int $hours Hours to look back
     * @return array Activity summary
     */
    public function get_recent_activity_summary($hours = 24) {
        global $wpdb;

        $date_from = date('Y-m-d H:i:s', strtotime("-{$hours} hours", strtotime($this->current_time)));

        $summary = [
            'total_activities' => 0,
            'unique_users' => 0,
            'by_type' => [],
            'by_hour' => []
        ];

        // Get total activities and unique users
        $overall = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT user) as users
            FROM {$wpdb->prefix}mfw_activity_log
            WHERE created_at >= %s",
            $date_from
        ));

        $summary['total_activities'] = (int)$overall->total;
        $summary['unique_users'] = (int)$overall->users;

        // Get activities by type
        $by_type = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                type,
                COUNT(*) as count
            FROM {$wpdb->prefix}mfw_activity_log
            WHERE created_at >= %s
            GROUP BY type",
            $date_from
        ));

        foreach ($by_type as $row) {
            $summary['by_type'][$row->type] = (int)$row->count;
        }

        // Get activities by hour
        $by_hour = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                COUNT(*) as count
            FROM {$wpdb->prefix}mfw_activity_log
            WHERE created_at >= %s
            GROUP BY hour
            ORDER BY hour ASC",
            $date_from
        ));

        foreach ($by_hour as $row) {
            $summary['by_hour'][$row->hour] = (int)$row->count;
        }

        return $summary;
    }
}

// Add cleanup schedule
add_action('mfw_cleanup_activity_logs', ['MFW_Activity_Tracker', 'cleanup_logs']);
if (!wp_next_scheduled('mfw_cleanup_activity_logs')) {
    wp_schedule_event(time(), 'daily', 'mfw_cleanup_activity_logs');
}