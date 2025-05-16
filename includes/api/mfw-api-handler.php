<?php
/**
 * API Handler Class
 * 
 * Manages API routes, authentication, and requests.
 * Handles REST API functionality and endpoints.
 *
 * @package MFW
 * @subpackage API
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_API_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 19:03:29';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * API namespace
     */
    private $namespace = 'mfw/v1';

    /**
     * API routes
     */
    private $routes = [];

    /**
     * Initialize API handler
     */
    public function __construct() {
        // Register REST API routes
        add_action('rest_api_init', [$this, 'register_routes']);

        // Add authentication filters
        add_filter('rest_authentication_errors', [$this, 'authenticate_request']);

        // Add response filters
        add_filter('rest_pre_serve_request', [$this, 'pre_serve_request'], 10, 4);

        // Initialize routes
        $this->init_routes();
    }

    /**
     * Initialize API routes
     */
    private function init_routes() {
        $this->routes = [
            // Settings endpoints
            new MFW_API_Settings_Controller($this->namespace),
            
            // Analytics endpoints
            new MFW_API_Analytics_Controller($this->namespace),
            
            // Data endpoints
            new MFW_API_Data_Controller($this->namespace),
            
            // User endpoints
            new MFW_API_User_Controller($this->namespace),
            
            // System endpoints
            new MFW_API_System_Controller($this->namespace)
        ];
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        foreach ($this->routes as $route) {
            $route->register_routes();
        }
    }

    /**
     * Authenticate API request
     *
     * @param WP_Error|null|bool $result Error from another authentication handler,
     *                                    null if we should handle it, or another value if not
     * @return WP_Error|null|bool
     */
    public function authenticate_request($result) {
        // Pass through other authentication errors
        if (null !== $result) {
            return $result;
        }

        // Get the API key from headers
        $api_key = $this->get_api_key();

        // Skip our authentication if no API key provided
        if (!$api_key) {
            return null;
        }

        try {
            // Validate API key
            $this->validate_api_key($api_key);

            // Log successful authentication
            $this->log_authentication('success');

            return true;

        } catch (Exception $e) {
            // Log failed authentication
            $this->log_authentication('failure', $e->getMessage());

            return new WP_Error(
                'rest_authentication_error',
                $e->getMessage(),
                ['status' => 401]
            );
        }
    }

    /**
     * Pre-serve request handler
     *
     * @param bool $served Whether the request has already been served
     * @param WP_HTTP_Response $result Result to send to the client
     * @param WP_REST_Request $request Request used to generate the response
     * @param WP_REST_Server $server Server instance
     * @return bool Whether the request has been served
     */
    public function pre_serve_request($served, $result, $request, $server) {
        // Log API request
        $this->log_request($request, $result);

        // Add custom headers
        $this->add_response_headers($result);

        return $served;
    }

    /**
     * Get API key from request
     *
     * @return string|null API key or null if not found
     */
    private function get_api_key() {
        // Try to get from Authorization header
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }

        // Try to get from X-API-Key header
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }

        // Try to get from query parameter
        return isset($_GET['api_key']) ? $_GET['api_key'] : null;
    }

    /**
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @throws Exception If validation fails
     */
    private function validate_api_key($api_key) {
        // Get stored API keys
        $api_keys = get_option('mfw_api_keys', []);

        // Check if key exists and is valid
        if (!isset($api_keys[$api_key])) {
            throw new Exception(__('Invalid API key.', 'mfw'));
        }

        $key_data = $api_keys[$api_key];

        // Check if key is expired
        if (!empty($key_data['expires']) && strtotime($key_data['expires']) < time()) {
            throw new Exception(__('API key has expired.', 'mfw'));
        }

        // Check if key is enabled
        if (!empty($key_data['status']) && $key_data['status'] !== 'active') {
            throw new Exception(__('API key is inactive.', 'mfw'));
        }

        // Check rate limiting
        $this->check_rate_limit($api_key);
    }

    /**
     * Check rate limiting for API key
     *
     * @param string $api_key API key to check
     * @throws Exception If rate limit exceeded
     */
    private function check_rate_limit($api_key) {
        $rate_limits = get_option('mfw_api_rate_limits', []);
        
        if (!isset($rate_limits[$api_key])) {
            $rate_limits[$api_key] = [
                'count' => 0,
                'timestamp' => time()
            ];
        }

        // Reset count if window has passed
        if ((time() - $rate_limits[$api_key]['timestamp']) > 3600) {
            $rate_limits[$api_key] = [
                'count' => 1,
                'timestamp' => time()
            ];
        } else {
            $rate_limits[$api_key]['count']++;
        }

        // Check if limit exceeded
        if ($rate_limits[$api_key]['count'] > 1000) {
            throw new Exception(__('API rate limit exceeded.', 'mfw'));
        }

        // Update rate limits
        update_option('mfw_api_rate_limits', $rate_limits);
    }

    /**
     * Add custom response headers
     *
     * @param WP_HTTP_Response $response Response object
     */
    private function add_response_headers($response) {
        $headers = [
            'X-MFW-Version' => MFW_VERSION,
            'X-MFW-API-Version' => '1.0',
            'X-MFW-Request-ID' => uniqid('mfw-', true)
        ];

        foreach ($headers as $header => $value) {
            $response->header($header, $value);
        }
    }

    /**
     * Log API request
     *
     * @param WP_REST_Request $request Request object
     * @param WP_HTTP_Response $response Response object
     */
    private function log_request($request, $response) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_api_log',
                [
                    'endpoint' => $request->get_route(),
                    'method' => $request->get_method(),
                    'params' => json_encode($request->get_params()),
                    'status' => $response->get_status(),
                    'response_time' => $this->get_request_time(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log API request: %s', $e->getMessage()),
                'api_handler',
                'error'
            );
        }
    }

    /**
     * Log authentication attempt
     *
     * @param string $status Authentication status
     * @param string $message Error message if any
     */
    private function log_authentication($status, $message = '') {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_api_auth_log',
                [
                    'status' => $status,
                    'message' => $message,
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log API authentication: %s', $e->getMessage()),
                'api_handler',
                'error'
            );
        }
    }

    /**
     * Get request execution time
     *
     * @return float Request time in seconds
     */
    private function get_request_time() {
        if (defined('REQUEST_TIME_FLOAT')) {
            return microtime(true) - REQUEST_TIME_FLOAT;
        }
        return 0;
    }
}