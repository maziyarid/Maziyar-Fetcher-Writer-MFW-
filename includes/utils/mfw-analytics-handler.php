<?php
/**
 * Analytics Handler Class
 * 
 * Manages analytics tracking and reporting.
 * Handles data collection, processing, and visualization.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Analytics_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:47:42';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Analytics settings
     */
    private $settings;

    /**
     * Database handler
     */
    private $db;

    /**
     * Initialize analytics handler
     */
    public function __construct() {
        // Load settings
        $this->settings = get_option('mfw_analytics_settings', [
            'enable_tracking' => true,
            'track_users' => true,
            'track_events' => true,
            'track_performance' => true,
            'anonymize_ips' => true,
            'retention_period' => 90, // days
            'sampling_rate' => 100 // percentage
        ]);

        // Initialize database handler
        $this->db = new MFW_Database_Handler();

        // Add tracking hooks
        if ($this->settings['enable_tracking']) {
            add_action('wp_head', [$this, 'add_tracking_code']);
            add_action('wp_ajax_mfw_track_event', [$this, 'track_event']);
            add_action('wp_ajax_nopriv_mfw_track_event', [$this, 'track_event']);
        }

        // Add maintenance hooks
        add_action('mfw_daily_maintenance', [$this, 'cleanup_old_data']);
    }

    /**
     * Add tracking code to page header
     */
    public function add_tracking_code() {
        if (!$this->should_track()) {
            return;
        }

        // Generate tracking code
        $tracking_code = $this->generate_tracking_code();

        // Output tracking code
        echo "<!-- MFW Analytics Tracking Code -->\n";
        echo "<script>\n";
        echo $tracking_code;
        echo "</script>\n";
    }

    /**
     * Track custom event
     */
    public function track_event() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_analytics', 'nonce');

            // Get event data
            $event = isset($_POST['event']) ? sanitize_text_field($_POST['event']) : '';
            $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : [];

            if (empty($event)) {
                wp_send_json_error('Invalid event data');
            }

            // Record event
            $this->record_event($event, $data);

            wp_send_json_success();

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get analytics report
     *
     * @param string $metric Metric to report
     * @param array $params Report parameters
     * @return array Report data
     */
    public function get_report($metric, $params = []) {
        try {
            // Parse parameters
            $defaults = [
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d'),
                'group_by' => 'day',
                'limit' => 1000
            ];
            $params = wp_parse_args($params, $defaults);

            // Get report data
            $method = "get_{$metric}_report";
            if (method_exists($this, $method)) {
                return $this->$method($params);
            }

            throw new Exception('Invalid metric requested.');

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Analytics report failed: %s', $e->getMessage()),
                'analytics_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Cleanup old analytics data
     */
    public function cleanup_old_data() {
        try {
            $retention_date = date('Y-m-d', strtotime("-{$this->settings['retention_period']} days"));

            // Clean up various analytics tables
            $tables = [
                'pageviews',
                'events',
                'visitors',
                'performance'
            ];

            foreach ($tables as $table) {
                $this->db->delete(
                    "mfw_analytics_{$table}",
                    ['created_at' => ['<', $retention_date]]
                );
            }

            // Log cleanup
            $this->log_analytics_operation('cleanup');

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Analytics cleanup failed: %s', $e->getMessage()),
                'analytics_handler',
                'error'
            );
        }
    }

    /**
     * Record pageview
     *
     * @param array $data Pageview data
     */
    public function record_pageview($data) {
        try {
            // Prepare pageview data
            $pageview = [
                'url' => $data['url'],
                'title' => $data['title'],
                'referrer' => $data['referrer'],
                'visitor_id' => $this->get_visitor_id(),
                'user_id' => get_current_user_id(),
                'session_id' => $this->get_session_id(),
                'ip_address' => $this->get_anonymized_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => $this->current_time
            ];

            // Save pageview
            $this->db->insert('mfw_analytics_pageviews', $pageview);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to record pageview: %s', $e->getMessage()),
                'analytics_handler',
                'error'
            );
        }
    }

    /**
     * Record event
     *
     * @param string $event Event name
     * @param array $data Event data
     */
    private function record_event($event, $data) {
        try {
            // Prepare event data
            $event_data = [
                'event' => $event,
                'data' => json_encode($data),
                'visitor_id' => $this->get_visitor_id(),
                'user_id' => get_current_user_id(),
                'session_id' => $this->get_session_id(),
                'created_at' => $this->current_time
            ];

            // Save event
            $this->db->insert('mfw_analytics_events', $event_data);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to record event: %s', $e->getMessage()),
                'analytics_handler',
                'error'
            );
        }
    }

    /**
     * Get pageviews report
     *
     * @param array $params Report parameters
     * @return array Report data
     */
    private function get_pageviews_report($params) {
        $query = [
            'select' => [
                'DATE(created_at) as date',
                'COUNT(*) as views',
                'COUNT(DISTINCT visitor_id) as visitors'
            ],
            'from' => 'mfw_analytics_pageviews',
            'where' => [
                'created_at >= ?' => $params['start_date'],
                'created_at <= ?' => $params['end_date']
            ],
            'group_by' => 'DATE(created_at)',
            'order_by' => 'date ASC'
        ];

        return $this->db->get_results($query);
    }

    /**
     * Generate tracking code
     *
     * @return string JavaScript tracking code
     */
    private function generate_tracking_code() {
        $code = "
            window.MFWAnalytics = {
                endpoint: '" . admin_url('admin-ajax.php') . "',
                nonce: '" . wp_create_nonce('mfw_analytics') . "',
                
                track: function(event, data) {
                    data = data || {};
                    
                    fetch(this.endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'mfw_track_event',
                            nonce: this.nonce,
                            event: event,
                            data: JSON.stringify(data)
                        })
                    });
                }
            };
        ";

        return $code;
    }

    /**
     * Check if should track current request
     *
     * @return bool Should track
     */
    private function should_track() {
        // Check if tracking is enabled
        if (!$this->settings['enable_tracking']) {
            return false;
        }

        // Check sampling rate
        if ($this->settings['sampling_rate'] < 100) {
            if (mt_rand(1, 100) > $this->settings['sampling_rate']) {
                return false;
            }
        }

        // Don't track admin users unless specifically enabled
        if (is_admin() && !$this->settings['track_admin']) {
            return false;
        }

        return true;
    }

    /**
     * Get visitor ID
     *
     * @return string Visitor ID
     */
    private function get_visitor_id() {
        if (!isset($_COOKIE['mfw_visitor_id'])) {
            $visitor_id = wp_generate_uuid4();
            setcookie('mfw_visitor_id', $visitor_id, time() + YEAR_IN_SECONDS, '/');
            return $visitor_id;
        }

        return $_COOKIE['mfw_visitor_id'];
    }

    /**
     * Get session ID
     *
     * @return string Session ID
     */
    private function get_session_id() {
        if (!isset($_COOKIE['mfw_session_id'])) {
            $session_id = wp_generate_uuid4();
            setcookie('mfw_session_id', $session_id, time() + HOUR_IN_SECONDS, '/');
            return $session_id;
        }

        return $_COOKIE['mfw_session_id'];
    }

    /**
     * Get anonymized IP address
     *
     * @return string Anonymized IP
     */
    private function get_anonymized_ip() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if ($this->settings['anonymize_ips']) {
            // Anonymize IPv4
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return preg_replace('/\.\d+$/', '.0', $ip);
            }
            
            // Anonymize IPv6
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return substr($ip, 0, strrpos($ip, ':')) . ':0000';
            }
        }

        return $ip;
    }

    /**
     * Log analytics operation
     *
     * @param string $operation Operation type
     * @param array $details Operation details
     */
    private function log_analytics_operation($operation, $details = []) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_analytics_log',
                [
                    'operation' => $operation,
                    'details' => json_encode($details),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log analytics operation: %s', $e->getMessage()),
                'analytics_handler',
                'error'
            );
        }
    }
}