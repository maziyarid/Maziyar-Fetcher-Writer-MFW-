<?php
/**
 * Integration Abstract Class
 * 
 * Provides base functionality for third-party integrations.
 * Handles API connections, authentication, and data sync.
 *
 * @package MFW
 * @subpackage Abstracts
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Abstract_Integration extends MFW_Abstract_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:25:44';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Integration configuration
     */
    protected $config = [
        'name' => '',
        'description' => '',
        'version' => '1.0.0',
        'api_version' => '',
        'auth_type' => 'oauth2', // oauth2, api_key, basic
        'requires_ssl' => true,
        'endpoints' => []
    ];

    /**
     * Integration credentials
     */
    protected $credentials = [];

    /**
     * API client instance
     */
    protected $client;

    /**
     * API rate limits
     */
    protected $rate_limits = [
        'limit' => 0,
        'remaining' => 0,
        'reset' => 0
    ];

    /**
     * Initialize integration
     */
    protected function init() {
        // Set integration configuration
        $this->setup();

        // Load credentials
        $this->load_credentials();

        // Initialize API client
        if ($this->validate_credentials()) {
            $this->initialize_client();
        }
    }

    /**
     * Setup integration configuration
     * Must be implemented by child classes
     */
    abstract protected function setup();

    /**
     * Initialize API client
     * Must be implemented by child classes
     */
    abstract protected function initialize_client();

    /**
     * Get integration status
     *
     * @return string Integration status
     */
    public function get_status() {
        if (!$this->validate_credentials()) {
            return 'not_configured';
        }

        if (!$this->check_connection()) {
            return 'connection_error';
        }

        return 'connected';
    }

    /**
     * Get integration config
     *
     * @param string $key Config key
     * @param mixed $default Default value
     * @return mixed Config value
     */
    public function get_config($key = null, $default = null) {
        if ($key === null) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $method HTTP method
     * @return array|WP_Error API response or error
     */
    protected function request($endpoint, $params = [], $method = 'GET') {
        try {
            // Check rate limits
            if (!$this->check_rate_limits()) {
                throw new Exception(__('API rate limit exceeded.', 'mfw'));
            }

            // Validate endpoint
            if (!isset($this->config['endpoints'][$endpoint])) {
                throw new Exception(__('Invalid API endpoint.', 'mfw'));
            }

            // Make request
            $response = $this->make_request($endpoint, $params, $method);

            // Update rate limits
            $this->update_rate_limits($response);

            // Log request
            $this->log_request($endpoint, $params, $method, $response);

            return $this->parse_response($response);

        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error', [
                'endpoint' => $endpoint,
                'params' => $params,
                'method' => $method
            ]);
            return new WP_Error('api_error', $e->getMessage());
        }
    }

    /**
     * Load integration credentials
     */
    protected function load_credentials() {
        $this->credentials = get_option('mfw_integration_' . $this->id . '_credentials', []);
    }

    /**
     * Save integration credentials
     *
     * @param array $credentials Credentials data
     * @return bool Whether save was successful
     */
    protected function save_credentials($credentials) {
        try {
            // Encrypt sensitive data
            $encrypted = $this->encrypt_credentials($credentials);

            $result = update_option(
                'mfw_integration_' . $this->id . '_credentials',
                $encrypted
            );

            if ($result) {
                $this->credentials = $credentials;
                // Log credentials update
                $this->log_credentials_update();
            }

            return $result;

        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Validate integration credentials
     *
     * @return bool Whether credentials are valid
     */
    protected function validate_credentials() {
        if (empty($this->credentials)) {
            return false;
        }

        switch ($this->config['auth_type']) {
            case 'oauth2':
                return !empty($this->credentials['access_token']);

            case 'api_key':
                return !empty($this->credentials['api_key']);

            case 'basic':
                return !empty($this->credentials['username']) && 
                       !empty($this->credentials['password']);

            default:
                return false;
        }
    }

    /**
     * Check API connection
     *
     * @return bool Whether connection is successful
     */
    protected function check_connection() {
        try {
            if ($this->config['requires_ssl'] && !is_ssl()) {
                throw new Exception(__('SSL is required for this integration.', 'mfw'));
            }

            // Make test request
            $response = $this->request('test', [], 'GET');
            return !is_wp_error($response);

        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Check API rate limits
     *
     * @return bool Whether within rate limits
     */
    protected function check_rate_limits() {
        if ($this->rate_limits['limit'] === 0) {
            return true;
        }

        if ($this->rate_limits['reset'] < time()) {
            $this->rate_limits['remaining'] = $this->rate_limits['limit'];
            return true;
        }

        return $this->rate_limits['remaining'] > 0;
    }

    /**
     * Update API rate limits
     *
     * @param array $response API response
     */
    protected function update_rate_limits($response) {
        if (isset($response['headers'])) {
            $headers = $response['headers'];

            $this->rate_limits = [
                'limit' => (int)($headers['x-ratelimit-limit'] ?? 0),
                'remaining' => (int)($headers['x-ratelimit-remaining'] ?? 0),
                'reset' => (int)($headers['x-ratelimit-reset'] ?? 0)
            ];
        }
    }

    /**
     * Encrypt sensitive data
     *
     * @param array $data Data to encrypt
     * @return array Encrypted data
     */
    protected function encrypt_credentials($data) {
        foreach ($data as $key => $value) {
            if (in_array($key, ['access_token', 'refresh_token', 'api_key', 'password'])) {
                $data[$key] = MFW()->encryption->encrypt($value);
            }
        }
        return $data;
    }

    /**
     * Log API request
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $method HTTP method
     * @param array $response API response
     */
    protected function log_request($endpoint, $params, $method, $response) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_integration_log',
                [
                    'integration_id' => $this->id,
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'params' => json_encode($params),
                    'response_code' => $response['response']['code'] ?? 0,
                    'response_message' => $response['response']['message'] ?? '',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            $this->log(
                sprintf('Failed to log API request: %s', $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * Log credentials update
     */
    protected function log_credentials_update() {
        $this->log(
            'Integration credentials updated',
            'info',
            ['integration' => $this->config['name']]
        );
    }
}