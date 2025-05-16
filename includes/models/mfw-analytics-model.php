<?php
/**
 * Analytics Model Class
 * 
 * Handles analytics data collection and reporting.
 * Manages tracking, metrics, and reporting functionality.
 *
 * @package MFW
 * @subpackage Models
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Analytics_Model extends MFW_Model_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:18:12';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Metrics configuration
     */
    protected $metrics = [
        'pageviews' => [
            'table' => 'mfw_pageviews',
            'aggregates' => ['count', 'unique']
        ],
        'events' => [
            'table' => 'mfw_events',
            'aggregates' => ['count', 'sum', 'average']
        ],
        'conversions' => [
            'table' => 'mfw_conversions',
            'aggregates' => ['count', 'rate']
        ],
        'performance' => [
            'table' => 'mfw_performance',
            'aggregates' => ['average', 'min', 'max']
        ]
    ];

    /**
     * Initialize model
     */
    protected function init() {
        $this->table = 'mfw_analytics';
        
        $this->fields = [
            'metric' => [
                'type' => 'string',
                'length' => 50
            ],
            'dimension' => [
                'type' => 'string',
                'length' => 50
            ],
            'value' => [
                'type' => 'float'
            ],
            'date' => [
                'type' => 'date'
            ]
        ];

        $this->required = ['metric', 'value', 'date'];

        $this->validations = [
            'metric' => [
                'in' => array_keys($this->metrics)
            ],
            'value' => [
                'numeric' => true
            ]
        ];
    }

    /**
     * Track pageview
     *
     * @param array $data Pageview data
     * @return bool Whether tracking was successful
     */
    public function track_pageview($data) {
        try {
            global $wpdb;

            $result = $wpdb->insert(
                $wpdb->prefix . 'mfw_pageviews',
                [
                    'url' => $data['url'],
                    'title' => $data['title'],
                    'referrer' => $data['referrer'] ?? '',
                    'user_id' => get_current_user_id(),
                    'session_id' => $data['session_id'],
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );

            if ($result === false) {
                throw new Exception(__('Failed to track pageview.', 'mfw'));
            }

            // Track performance data if available
            if (isset($data['performance'])) {
                $this->track_performance($data['performance']);
            }

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Track event
     *
     * @param array $data Event data
     * @return bool Whether tracking was successful
     */
    public function track_event($data) {
        try {
            global $wpdb;

            $result = $wpdb->insert(
                $wpdb->prefix . 'mfw_events',
                [
                    'category' => $data['category'],
                    'action' => $data['action'],
                    'label' => $data['label'] ?? '',
                    'value' => $data['value'] ?? null,
                    'user_id' => get_current_user_id(),
                    'session_id' => $data['session_id'],
                    'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s']
            );

            if ($result === false) {
                throw new Exception(__('Failed to track event.', 'mfw'));
            }

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Get analytics data
     *
     * @param array $params Query parameters
     * @return array Analytics data
     */
    public function get_data($params) {
        try {
            $defaults = [
                'metric' => 'pageviews',
                'dimension' => 'date',
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d'),
                'filters' => [],
                'sort' => 'date',
                'order' => 'desc',
                'limit' => 1000
            ];

            $params = wp_parse_args($params, $defaults);

            // Validate metric
            if (!isset($this->metrics[$params['metric']])) {
                throw new Exception(__('Invalid metric.', 'mfw'));
            }

            $table = $this->metrics[$params['metric']]['table'];
            $query = $this->build_analytics_query($table, $params);
            
            global $wpdb;
            $results = $wpdb->get_results($query, ARRAY_A);

            return [
                'data' => $results,
                'total' => $this->get_total_count($table, $params),
                'metadata' => $this->get_query_metadata($params)
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Get analytics report
     *
     * @param string $report_type Report type
     * @param array $params Report parameters
     * @return array Report data
     */
    public function get_report($report_type, $params = []) {
        try {
            switch ($report_type) {
                case 'overview':
                    return $this->generate_overview_report($params);

                case 'traffic':
                    return $this->generate_traffic_report($params);

                case 'events':
                    return $this->generate_events_report($params);

                case 'conversions':
                    return $this->generate_conversions_report($params);

                default:
                    throw new Exception(__('Invalid report type.', 'mfw'));
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Build analytics query
     *
     * @param string $table Table name
     * @param array $params Query parameters
     * @return string SQL query
     */
    protected function build_analytics_query($table, $params) {
        global $wpdb;
        
        $select = $this->build_select_clause($params);
        $where = $this->build_where_clause($params);
        $group_by = $this->build_group_by_clause($params);
        $order_by = $this->build_order_by_clause($params);
        $limit = $this->build_limit_clause($params);

        return "SELECT {$select} 
                FROM {$wpdb->prefix}{$table} 
                WHERE {$where} 
                {$group_by} 
                {$order_by} 
                {$limit}";
    }

    /**
     * Track performance data
     *
     * @param array $data Performance data
     */
    protected function track_performance($data) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_performance',
                [
                    'page_load_time' => $data['page_load_time'],
                    'dom_load_time' => $data['dom_load_time'],
                    'first_paint' => $data['first_paint'],
                    'first_contentful_paint' => $data['first_contentful_paint'],
                    'url' => $data['url'],
                    'user_id' => get_current_user_id(),
                    'created_at' => $this->current_time
                ],
                ['%f', '%f', '%f', '%f', '%s', '%d', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
        }
    }

    /**
     * Generate overview report
     *
     * @param array $params Report parameters
     * @return array Report data
     */
    protected function generate_overview_report($params) {
        return [
            'pageviews' => $this->get_data(array_merge($params, ['metric' => 'pageviews'])),
            'events' => $this->get_data(array_merge($params, ['metric' => 'events'])),
            'conversions' => $this->get_data(array_merge($params, ['metric' => 'conversions'])),
            'performance' => $this->get_data(array_merge($params, ['metric' => 'performance']))
        ];
    }

    /**
     * Build select clause
     *
     * @param array $params Query parameters
     * @return string SELECT clause
     */
    protected function build_select_clause($params) {
        $select = [];

        if ($params['dimension']) {
            $select[] = $params['dimension'];
        }

        foreach ($this->metrics[$params['metric']]['aggregates'] as $aggregate) {
            switch ($aggregate) {
                case 'count':
                    $select[] = 'COUNT(*) as count';
                    break;
                case 'unique':
                    $select[] = 'COUNT(DISTINCT user_id) as unique_count';
                    break;
                case 'sum':
                    $select[] = 'SUM(value) as total';
                    break;
                case 'average':
                    $select[] = 'AVG(value) as average';
                    break;
                case 'min':
                    $select[] = 'MIN(value) as minimum';
                    break;
                case 'max':
                    $select[] = 'MAX(value) as maximum';
                    break;
            }
        }

        return implode(', ', $select);
    }

    /**
     * Build where clause
     *
     * @param array $params Query parameters
     * @return string WHERE clause
     */
    protected function build_where_clause($params) {
        global $wpdb;

        $where = ["created_at BETWEEN %s AND %s"];
        $where_values = [
            $params['start_date'] . ' 00:00:00',
            $params['end_date'] . ' 23:59:59'
        ];

        if (!empty($params['filters'])) {
            foreach ($params['filters'] as $field => $value) {
                $where[] = $wpdb->prepare("$field = %s", $value);
            }
        }

        return $wpdb->prepare(implode(' AND ', $where), ...$where_values);
    }
}