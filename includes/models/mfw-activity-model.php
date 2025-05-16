<?php
/**
 * Activity Model Class
 * 
 * Handles activity logging and retrieval.
 * Manages user and system activity tracking.
 *
 * @package MFW
 * @subpackage Models
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Activity_Model extends MFW_Model_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:19:30';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Activity types configuration
     */
    protected $types = [
        'user' => [
            'login',
            'logout',
            'register',
            'password_change',
            'profile_update'
        ],
        'content' => [
            'create',
            'update',
            'delete',
            'publish',
            'unpublish'
        ],
        'system' => [
            'settings_update',
            'backup',
            'restore',
            'maintenance',
            'error'
        ],
        'security' => [
            'login_attempt',
            'password_reset',
            'api_access',
            'permission_change'
        ]
    ];

    /**
     * Initialize model
     */
    protected function init() {
        $this->table = 'mfw_activity_log';
        
        $this->fields = [
            'type' => [
                'type' => 'string',
                'length' => 50
            ],
            'subtype' => [
                'type' => 'string',
                'length' => 50
            ],
            'action' => [
                'type' => 'string',
                'length' => 50
            ],
            'user_id' => [
                'type' => 'integer'
            ],
            'object_type' => [
                'type' => 'string',
                'length' => 50
            ],
            'object_id' => [
                'type' => 'integer'
            ],
            'details' => [
                'type' => 'json'
            ],
            'ip_address' => [
                'type' => 'string',
                'length' => 45
            ],
            'user_agent' => [
                'type' => 'string'
            ],
            'severity' => [
                'type' => 'string',
                'length' => 20,
                'default' => 'info'
            ]
        ];

        $this->required = ['type', 'action'];

        $this->validations = [
            'type' => [
                'in' => array_keys($this->types)
            ],
            'severity' => [
                'in' => ['debug', 'info', 'warning', 'error', 'critical']
            ]
        ];
    }

    /**
     * Log activity
     *
     * @param array $data Activity data
     * @return bool Whether logging was successful
     */
    public function log($data) {
        try {
            // Validate activity type
            if (!isset($this->types[$data['type']])) {
                throw new Exception(__('Invalid activity type.', 'mfw'));
            }

            // Prepare activity data
            $activity = [
                'type' => $data['type'],
                'subtype' => $data['subtype'] ?? '',
                'action' => $data['action'],
                'user_id' => get_current_user_id(),
                'object_type' => $data['object_type'] ?? '',
                'object_id' => $data['object_id'] ?? null,
                'details' => isset($data['details']) ? json_encode($data['details']) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'severity' => $data['severity'] ?? 'info',
                'created_by' => $this->current_user,
                'created_at' => $this->current_time
            ];

            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . $this->table,
                $activity,
                [
                    '%s', '%s', '%s', '%d', '%s', '%d', '%s', 
                    '%s', '%s', '%s', '%s', '%s'
                ]
            );

            if ($result === false) {
                throw new Exception(__('Failed to log activity.', 'mfw'));
            }

            // Trigger activity logged action
            do_action('mfw_activity_logged', $activity);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Get activities
     *
     * @param array $params Query parameters
     * @return array Activities data
     */
    public function get_activities($params = []) {
        try {
            $defaults = [
                'type' => '',
                'subtype' => '',
                'action' => '',
                'user_id' => 0,
                'object_type' => '',
                'object_id' => 0,
                'severity' => '',
                'start_date' => '',
                'end_date' => '',
                'orderby' => 'created_at',
                'order' => 'DESC',
                'per_page' => 20,
                'page' => 1
            ];

            $params = wp_parse_args($params, $defaults);
            $query = $this->build_activities_query($params);
            
            global $wpdb;
            $total = $wpdb->get_var($query['count']);
            $items = $wpdb->get_results($query['select'], ARRAY_A);

            // Format activities data
            $activities = array_map([$this, 'format_activity'], $items);

            return [
                'items' => $activities,
                'total' => (int)$total,
                'pages' => ceil($total / $params['per_page'])
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Get user activity
     *
     * @param int $user_id User ID
     * @param array $params Query parameters
     * @return array User activity data
     */
    public function get_user_activity($user_id, $params = []) {
        $params['user_id'] = $user_id;
        return $this->get_activities($params);
    }

    /**
     * Get activity summary
     *
     * @param array $params Query parameters
     * @return array Activity summary
     */
    public function get_summary($params = []) {
        try {
            global $wpdb;

            $where = $this->build_where_clause($params);
            
            // Get counts by type
            $type_counts = $wpdb->get_results(
                "SELECT type, COUNT(*) as count 
                FROM {$wpdb->prefix}{$this->table} 
                WHERE {$where} 
                GROUP BY type",
                ARRAY_A
            );

            // Get counts by severity
            $severity_counts = $wpdb->get_results(
                "SELECT severity, COUNT(*) as count 
                FROM {$wpdb->prefix}{$this->table} 
                WHERE {$where} 
                GROUP BY severity",
                ARRAY_A
            );

            // Get user statistics
            $user_stats = $wpdb->get_row(
                "SELECT 
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM {$wpdb->prefix}{$this->table} 
                WHERE {$where}",
                ARRAY_A
            );

            return [
                'by_type' => array_column($type_counts, 'count', 'type'),
                'by_severity' => array_column($severity_counts, 'count', 'severity'),
                'user_stats' => $user_stats
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Build activities query
     *
     * @param array $params Query parameters
     * @return array Select and count queries
     */
    protected function build_activities_query($params) {
        global $wpdb;

        $where = $this->build_where_clause($params);
        $limit = $this->build_limit_clause($params);
        $order = $this->build_order_clause($params);

        return [
            'select' => "SELECT * FROM {$wpdb->prefix}{$this->table} WHERE {$where} {$order} {$limit}",
            'count' => "SELECT COUNT(*) FROM {$wpdb->prefix}{$this->table} WHERE {$where}"
        ];
    }

    /**
     * Build where clause
     *
     * @param array $params Query parameters
     * @return string WHERE clause
     */
    protected function build_where_clause($params) {
        $where = ['1=1'];
        
        if (!empty($params['type'])) {
            $where[] = "type = '" . esc_sql($params['type']) . "'";
        }
        if (!empty($params['subtype'])) {
            $where[] = "subtype = '" . esc_sql($params['subtype']) . "'";
        }
        if (!empty($params['action'])) {
            $where[] = "action = '" . esc_sql($params['action']) . "'";
        }
        if (!empty($params['user_id'])) {
            $where[] = "user_id = " . (int)$params['user_id'];
        }
        if (!empty($params['object_type'])) {
            $where[] = "object_type = '" . esc_sql($params['object_type']) . "'";
        }
        if (!empty($params['object_id'])) {
            $where[] = "object_id = " . (int)$params['object_id'];
        }
        if (!empty($params['severity'])) {
            $where[] = "severity = '" . esc_sql($params['severity']) . "'";
        }
        if (!empty($params['start_date'])) {
            $where[] = "created_at >= '" . esc_sql($params['start_date']) . "'";
        }
        if (!empty($params['end_date'])) {
            $where[] = "created_at <= '" . esc_sql($params['end_date']) . "'";
        }

        return implode(' AND ', $where);
    }

    /**
     * Format activity data
     *
     * @param array $activity Raw activity data
     * @return array Formatted activity data
     */
    protected function format_activity($activity) {
        // Decode JSON details
        if (!empty($activity['details'])) {
            $activity['details'] = json_decode($activity['details'], true);
        }

        // Add user data if available
        if (!empty($activity['user_id'])) {
            $user = get_userdata($activity['user_id']);
            if ($user) {
                $activity['user'] = [
                    'id' => $user->ID,
                    'login' => $user->user_login,
                    'display_name' => $user->display_name
                ];
            }
        }

        // Format timestamps
        $activity['created_at'] = mysql2date('c', $activity['created_at']);

        return $activity;
    }
}