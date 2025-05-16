<?php
/**
 * API Settings Controller Class
 * 
 * Handles API endpoints for settings management.
 * Manages configuration and preferences via the API.
 *
 * @package MFW
 * @subpackage API
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_API_Settings_Controller extends MFW_API_Controller_Base {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 19:04:34';

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
    protected $rest_base = 'settings';

    /**
     * Initialize controller
     *
     * @param string $namespace API namespace
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Get all settings
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_settings'],
                    'permission_callback' => [$this, 'get_settings_permissions_check'],
                    'args' => $this->get_collection_params()
                ]
            ]
        );

        // Get single setting
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<key>[\w-]+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_setting'],
                    'permission_callback' => [$this, 'get_setting_permissions_check'],
                    'args' => [
                        'key' => [
                            'required' => true,
                            'type' => 'string',
                            'description' => __('Setting key to retrieve.', 'mfw'),
                            'validate_callback' => [$this, 'validate_setting_key']
                        ]
                    ]
                ]
            ]
        );

        // Update settings
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'update_settings'],
                    'permission_callback' => [$this, 'update_settings_permissions_check'],
                    'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)
                ]
            ]
        );

        // Delete setting
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<key>[\w-]+)',
            [
                [
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => [$this, 'delete_setting'],
                    'permission_callback' => [$this, 'delete_setting_permissions_check'],
                    'args' => [
                        'key' => [
                            'required' => true,
                            'type' => 'string',
                            'description' => __('Setting key to delete.', 'mfw'),
                            'validate_callback' => [$this, 'validate_setting_key']
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * Get settings
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_settings($request) {
        try {
            // Get filtered settings
            $settings = $this->get_filtered_settings($request);

            return new WP_REST_Response([
                'success' => true,
                'data' => $settings
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
     * Get single setting
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_setting($request) {
        try {
            $key = $request->get_param('key');
            $value = get_option("mfw_{$key}");

            if (false === $value) {
                return new WP_Error(
                    'mfw_setting_not_found',
                    __('Setting not found.', 'mfw'),
                    ['status' => 404]
                );
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $value
                ]
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
     * Update settings
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function update_settings($request) {
        try {
            $settings = $request->get_param('settings');
            $updated = [];
            $failed = [];

            foreach ($settings as $key => $value) {
                if ($this->validate_setting_key($key) && $this->validate_setting_value($value)) {
                    if (update_option("mfw_{$key}", $value)) {
                        $updated[] = $key;
                    } else {
                        $failed[] = $key;
                    }
                }
            }

            // Log settings update
            $this->log_settings_update($updated);

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'updated' => $updated,
                    'failed' => $failed
                ]
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
     * Delete setting
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function delete_setting($request) {
        try {
            $key = $request->get_param('key');

            if (!delete_option("mfw_{$key}")) {
                return new WP_Error(
                    'mfw_delete_failed',
                    __('Failed to delete setting.', 'mfw'),
                    ['status' => 500]
                );
            }

            // Log setting deletion
            $this->log_setting_deletion($key);

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'message' => __('Setting deleted successfully.', 'mfw')
                ]
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
     * Check get settings permissions
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if permitted, WP_Error if not
     */
    public function get_settings_permissions_check($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'mfw_rest_forbidden',
                __('Sorry, you are not allowed to view settings.', 'mfw'),
                ['status' => rest_authorization_required_code()]
            );
        }
        return true;
    }

    /**
     * Get filtered settings
     *
     * @param WP_REST_Request $request Request object
     * @return array Filtered settings
     */
    private function get_filtered_settings($request) {
        global $wpdb;

        $query = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'mfw_%'";
        $results = $wpdb->get_results($query);

        $settings = [];
        foreach ($results as $result) {
            $key = str_replace('mfw_', '', $result->option_name);
            $settings[$key] = maybe_unserialize($result->option_value);
        }

        return $settings;
    }

    /**
     * Validate setting key
     *
     * @param string $key Setting key
     * @return bool Whether key is valid
     */
    private function validate_setting_key($key) {
        return (bool) preg_match('/^[\w-]+$/', $key);
    }

    /**
     * Validate setting value
     *
     * @param mixed $value Setting value
     * @return bool Whether value is valid
     */
    private function validate_setting_value($value) {
        // Add validation logic based on setting type
        return true;
    }

    /**
     * Log settings update
     *
     * @param array $updated Updated settings
     */
    private function log_settings_update($updated) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_settings_log',
                [
                    'action' => 'update',
                    'settings' => json_encode($updated),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log settings update: %s', $e->getMessage()),
                'settings_controller',
                'error'
            );
        }
    }

    /**
     * Log setting deletion
     *
     * @param string $key Deleted setting key
     */
    private function log_setting_deletion($key) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_settings_log',
                [
                    'action' => 'delete',
                    'settings' => json_encode(['key' => $key]),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log setting deletion: %s', $e->getMessage()),
                'settings_controller',
                'error'
            );
        }
    }
}