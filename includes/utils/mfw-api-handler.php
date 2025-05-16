<?php
/**
 * API Handler Class
 * 
 * Manages API interactions and REST endpoints.
 * Handles authentication, rate limiting, and response formatting.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_API_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:40:48';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * API namespace
     */
    private $namespace = 'mfw/v1';

    /**
     * Rate limiter
     */
    private $rate_limiter;

    /**
     * Initialize API handler
     */
    public function __construct() {
        // Initialize rate limiter
        $this->rate_limiter = new MFW_Rate_Limiter();

        // Register REST routes
        add_action('rest_api_init', [$this, 'register_routes']);

        // Add API security headers
        add_action('rest_api_init', [$this, 'add_security_headers']);

        // Add authentication filters
        add_filter('rest_authentication_errors', [$this, 'authenticate_request']);
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        // Settings endpoints
        register_rest_route($this->namespace, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_settings_permission']
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_settings_permission']
            ]
        ]);

        // Data endpoints
        register_rest_route($this->namespace, '/data/(?P<type>[a-zA-Z0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_data'],
                'permission_callback' => [$this, 'check_data_permission']
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_data'],
                'permission_callback' => [$this, 'check_data_permission']
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_data'],
                'permission_callback' => [$this, 'check_data_permission']
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_data'],
                'permission_callback' => [$this, 'check_data_permission']
            ]
        ]);

        // Analytics endpoints
        register_rest_route($this->namespace, '/analytics/(?P<metric>[a-zA-Z0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_analytics'],
                'permission_callback' => [$this, 'check_analytics_permission']
            ]
        ]);
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    /**
     * Authenticate API request
     *
     * @param WP_Error|null|bool $result Authentication result
     * @return WP_Error|null|bool Modified result
     */
    public function authenticate_request($result) {
        // Skip authentication for non-MFW endpoints
        if (!$this->is_mfw_endpoint()) {
            return $result;
        }

        try {
            // Check API key
            $api_key = $this->get_api_key_from_request();
            if (!$this->validate_api_key($api_key)) {
                return new WP_Error(
                    'rest_auth_invalid_key',
                    __('Invalid API key.', 'mfw'),
                    ['status' => 401]
                );
            }

            // Check rate limit
            if (!$this->rate_limiter->check_limit($api_key)) {
                return new WP_Error(
                    'rest_auth_rate_limit',
                    __('Rate limit exceeded.', 'mfw'),
                    ['status' => 429]
                );
            }

            return $result;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('API authentication failed: %s', $e->getMessage()),
                'api_handler',
                'error'
            );

            return new WP_Error(
                'rest_auth_error',
                __('Authentication failed.', 'mfw'),
                ['status' => 401]
            );
        }
    }

    /**
     * Get settings endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_settings($request) {
        try {
            $settings = get_option('mfw_settings', []);
            return new WP_REST_Response($settings, 200);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Get settings failed: %s', $e->getMessage()),
                'api_handler',
                'error'
            );

            return new WP_Error(
                'rest_get_settings_error',
                __('Failed to get settings.', 'mfw'),
                ['status' => 500]
            );
        }
    }

    /**
     * Update settings endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function update_settings($request) {
        try {
            $settings = $request->get_json_params();
            
            // Validate settings
            $validator = new MFW_Validation_Handler();
            $valid = $validator->validate($settings, [
                'setting_key' => 'required|string',
                'setting_value' => 'required'
            ]);

            if ($valid !== true) {
                return new WP_Error(
                    'rest_invalid_settings',
                    __('Invalid settings data.', 'mfw'),
                    ['status' => 400]
                );
            }

            // Update settings
            update_option('mfw_settings', $settings);

            // Log update
            $this->log_api_operation('update_settings', $settings);

            return new WP_REST_Response([
                'message' => __('Settings updated successfully.', 'mfw')
            ], 200);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Update settings failed: %s', $e->getMessage()),
                'api_handler',
                'error'
            );

            return new WP_Error(
                'rest_update_settings_error',
                __('Failed to update settings.', 'mfw'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get data endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_data($request) {
        try {
            $type = $request->get_param('type');
            $params = $request->get_query_params();

            // Get data handler
            $handler = $this->get_data_handler($type);
            if (!$handler) {
                return new WP_Error(
                    'rest_invalid_data_type',
                    __('Invalid data type.', 'mfw'),
                    ['status' => 400]
                );
            }

            // Get data
            $data = $handler->get_data($params);

            return new WP_REST_Response($data, 200);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Get data failed: %s', $e->getMessage()),
                'api_handler',
                'error'
            );

            return new WP_Error(
                'rest_get_data_error',
                __('Failed to get data.', 'mfw'),
                ['status' => 500]
            );
        }
    }

    /**
     * Check if current endpoint is MFW endpoint
     *
     * @return bool Is MFW endpoint
     */
    private function is_mfw_endpoint() {
        $current_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        return strpos($current_route, $this->namespace) === 1;
    }

    /**
     * Get API key from request
     *
     * @return string|null API key
     */
    private function get_api_key_from_request() {
        // Check header
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if ($api_key) {
            return $api_key;
        }

        // Check query parameter
        return $_GET['api_key'] ?? null;
    }

    /**
     * Validate API key
     *
     * @param string|null $api_key API key
     * @return bool Is valid
     */
    private function validate_api_key($api_key) {
        if (!$api_key) {
            return false;
        }

        $valid_keys = get_option('mfw_api_keys', []);
        return isset($valid_keys[$api_key]) && $valid_keys[$api_key]['active'];
    }

    /**
     * Log API operation
     *
     * @param string $operation Operation type
     * @param array $details Operation details
     */
    private function log_api_operation($operation, $details = []) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_api_log',
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
                sprintf('Failed to log API operation: %s', $e->getMessage()),
                'api_handler',
                'error'
            );
        }
    }

    /**
     * Check settings permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool Has permission
     */
    public function check_settings_permission($request) {
        return current_user_can('manage_options');
    }

    /**
     * Check data permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool Has permission
     */
    public function check_data_permission($request) {
        return current_user_can('mfw_manage_data');
    }

    /**
     * Check analytics permission
     *
     * @param WP_REST_Request $request Request object
     * @return bool Has permission
     */
    public function check_analytics_permission($request) {
        return current_user_can('mfw_view_reports');
    }
}