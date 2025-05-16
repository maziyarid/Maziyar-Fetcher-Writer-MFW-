<?php
/**
 * API Analytics Controller Class
 * 
 * Handles API endpoints for analytics data.
 * Manages analytics data retrieval and reporting via API.
 *
 * @package MFW
 * @subpackage API
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_API_Analytics_Controller extends MFW_API_Controller_Base {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 19:07:21';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Route namespace
     */
    protected $namespace;

    /**
     * Route base
     */
    protected $rest_base = 'analytics';

    /**
     * Analytics handler
     */
    private $analytics;

    /**
     * Initialize controller
     *
     * @param string $namespace API namespace
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
        $this->analytics = new MFW_Analytics_Handler();
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Get analytics overview
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/overview',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_overview'],
                    'permission_callback' => [$this, 'get_analytics_permissions_check'],
                    'args' => $this->get_overview_params()
                ]
            ]
        );

        // Get specific metric
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/metric/(?P<metric>[\w-]+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_metric'],
                    'permission_callback' => [$this, 'get_analytics_permissions_check'],
                    'args' => $this->get_metric_params()
                ]
            ]
        );

        // Get events data
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/events',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_events'],
                    'permission_callback' => [$this, 'get_analytics_permissions_check'],
                    'args' => $this->get_events_params()
                ]
            ]
        );

        // Track event
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/track',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'track_event'],
                    'permission_callback' => [$this, 'track_event_permissions_check'],
                    'args' => $this->get_tracking_params()
                ]
            ]
        );
    }

    /**
     * Get analytics overview
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_overview($request) {
        try {
            $params = [
                'start_date' => $request->get_param('start_date'),
                'end_date' => $request->get_param('end_date'),
                'metrics' => $request->get_param('metrics')
            ];

            $data = $this->analytics->get_overview($params);

            return new WP_REST_Response([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (Exception $e) {
            return new WP_Error(
                'mfw_api_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get specific metric data
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_metric($request) {
        try {
            $metric = $request->get_param('metric');
            $params = [
                'start_date' => $request->get_param('start_date'),
                'end_date' => $request->get_param('end_date'),
                'dimension' => $request->get_param('dimension'),
                'limit' => $request->get_param('limit')
            ];

            $data = $this->analytics->get_metric($metric, $params);

            return new WP_REST_Response([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (Exception $e) {
            return new WP_Error(
                'mfw_api_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get events data
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_events($request) {
        try {
            $params = [
                'start_date' => $request->get_param('start_date'),
                'end_date' => $request->get_param('end_date'),
                'event_type' => $request->get_param('event_type'),
                'limit' => $request->get_param('limit'),
                'page' => $request->get_param('page')
            ];

            $data = $this->analytics->get_events($params);

            return new WP_REST_Response([
                'success' => true,
                'data' => $data['items'],
                'total' => $data['total'],
                'pages' => $data['pages']
            ], 200);

        } catch (Exception $e) {
            return new WP_Error(
                'mfw_api_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Track event
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function track_event($request) {
        try {
            $event_data = [
                'event' => $request->get_param('event'),
                'category' => $request->get_param('category'),
                'action' => $request->get_param('action'),
                'label' => $request->get_param('label'),
                'value' => $request->get_param('value'),
                'metadata' => $request->get_param('metadata')
            ];

            $result = $this->analytics->track_event($event_data);

            // Log event tracking
            $this->log_event_tracking($event_data);

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'event_id' => $result['event_id'],
                    'message' => __('Event tracked successfully.', 'mfw')
                ]
            ], 201);

        } catch (Exception $e) {
            return new WP_Error(
                'mfw_api_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get overview parameters
     *
     * @return array Parameter configuration
     */
    private function get_overview_params() {
        return [
            'start_date' => [
                'required' => true,
                'type' => 'string',
                'format' => 'date',
                'description' => __('Start date for analytics data.', 'mfw')
            ],
            'end_date' => [
                'required' => true,
                'type' => 'string',
                'format' => 'date',
                'description' => __('End date for analytics data.', 'mfw')
            ],
            'metrics' => [
                'required' => false,
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['pageviews', 'visitors', 'bounce_rate', 'avg_duration']
                ],
                'description' => __('Metrics to include in overview.', 'mfw')
            ]
        ];
    }

    /**
     * Get metric parameters
     *
     * @return array Parameter configuration
     */
    private function get_metric_params() {
        return [
            'metric' => [
                'required' => true,
                'type' => 'string',
                'enum' => ['pageviews', 'visitors', 'bounce_rate', 'avg_duration'],
                'description' => __('Metric to retrieve.', 'mfw')
            ],
            'start_date' => [
                'required' => true,
                'type' => 'string',
                'format' => 'date',
                'description' => __('Start date for metric data.', 'mfw')
            ],
            'end_date' => [
                'required' => true,
                'type' => 'string',
                'format' => 'date',
                'description' => __('End date for metric data.', 'mfw')
            ],
            'dimension' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['date', 'page', 'source', 'device'],
                'description' => __('Dimension to group metric by.', 'mfw')
            ],
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 1000,
                'default' => 100,
                'description' => __('Maximum number of results to return.', 'mfw')
            ]
        ];
    }

    /**
     * Get events parameters
     *
     * @return array Parameter configuration
     */
    private function get_events_params() {
        return [
            'start_date' => [
                'required' => true,
                'type' => 'string',
                'format' => 'date',
                'description' => __('Start date for events data.', 'mfw')
            ],
            'end_date' => [
                'required' => true,
                'type' => 'string',
                'format' => 'date',
                'description' => __('End date for events data.', 'mfw')
            ],
            'event_type' => [
                'required' => false,
                'type' => 'string',
                'description' => __('Type of events to retrieve.', 'mfw')
            ],
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 100,
                'default' => 20,
                'description' => __('Number of events per page.', 'mfw')
            ],
            'page' => [
                'required' => false,
                'type' => 'integer',
                'minimum' => 1,
                'default' => 1,
                'description' => __('Page number for pagination.', 'mfw')
            ]
        ];
    }

    /**
     * Get tracking parameters
     *
     * @return array Parameter configuration
     */
    private function get_tracking_params() {
        return [
            'event' => [
                'required' => true,
                'type' => 'string',
                'description' => __('Event name.', 'mfw')
            ],
            'category' => [
                'required' => true,
                'type' => 'string',
                'description' => __('Event category.', 'mfw')
            ],
            'action' => [
                'required' => true,
                'type' => 'string',
                'description' => __('Event action.', 'mfw')
            ],
            'label' => [
                'required' => false,
                'type' => 'string',
                'description' => __('Event label.', 'mfw')
            ],
            'value' => [
                'required' => false,
                'type' => 'number',
                'description' => __('Event value.', 'mfw')
            ],
            'metadata' => [
                'required' => false,
                'type' => 'object',
                'description' => __('Additional event metadata.', 'mfw')
            ]
        ];
    }

    /**
     * Log event tracking
     *
     * @param array $event_data Event data
     */
    private function log_event_tracking($event_data) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_analytics_log',
                [
                    'action' => 'track_event',
                    'data' => json_encode($event_data),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log event tracking: %s', $e->getMessage()),
                'analytics_controller',
                'error'
            );
        }
    }
}