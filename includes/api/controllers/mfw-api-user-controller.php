<?php
/**
 * API User Controller Class
 * 
 * Handles API endpoints for user management.
 * Manages user-related operations via API.
 *
 * @package MFW
 * @subpackage API
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_API_User_Controller extends MFW_API_Controller_Base {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 19:09:53';

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
    protected $rest_base = 'users';

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
        // Get users
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_users'],
                    'permission_callback' => [$this, 'get_users_permissions_check'],
                    'args' => $this->get_collection_params()
                ]
            ]
        );

        // Get user
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_user'],
                    'permission_callback' => [$this, 'get_user_permissions_check'],
                    'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => __('User ID.', 'mfw')
                    ]
                ]
            ]
        ]);

        // Get user preferences
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/preferences',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_user_preferences'],
                    'permission_callback' => [$this, 'get_user_preferences_permissions_check']
                ]
            ]
        );

        // Update user preferences
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/preferences',
            [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'update_user_preferences'],
                    'permission_callback' => [$this, 'update_user_preferences_permissions_check'],
                    'args' => $this->get_preferences_params()
                ]
            ]
        );

        // Get user activity
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/activity',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_user_activity'],
                    'permission_callback' => [$this, 'get_user_activity_permissions_check'],
                    'args' => $this->get_activity_params()
                ]
            ]
        );

        // Get user permissions
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)/permissions',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_user_permissions'],
                    'permission_callback' => [$this, 'get_user_permissions_check']
                ]
            ]
        );
    }

    /**
     * Get users
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_users($request) {
        try {
            $params = [
                'role' => $request->get_param('role'),
                'search' => $request->get_param('search'),
                'orderby' => $request->get_param('orderby'),
                'order' => $request->get_param('order'),
                'per_page' => $request->get_param('per_page'),
                'page' => $request->get_param('page')
            ];

            $users_query = new WP_User_Query($params);
            $users = array_map([$this, 'prepare_user_data'], $users_query->get_results());

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'total' => $users_query->get_total(),
                    'pages' => ceil($users_query->get_total() / $params['per_page'])
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
     * Get single user
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_user($request) {
        try {
            $user = get_user_by('id', $request->get_param('id'));

            if (!$user) {
                return new WP_Error(
                    'mfw_user_not_found',
                    __('User not found.', 'mfw'),
                    ['status' => 404]
                );
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => $this->prepare_user_data($user)
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
     * Get user preferences
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_user_preferences($request) {
        try {
            $user_id = $request->get_param('id');
            $preferences = get_user_meta($user_id, 'mfw_preferences', true);

            if (!$preferences) {
                $preferences = $this->get_default_preferences();
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => $preferences
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
     * Update user preferences
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function update_user_preferences($request) {
        try {
            $user_id = $request->get_param('id');
            $preferences = $request->get_param('preferences');

            // Merge with existing preferences
            $current_preferences = get_user_meta($user_id, 'mfw_preferences', true) ?: [];
            $updated_preferences = wp_parse_args($preferences, $current_preferences);

            // Update preferences
            update_user_meta($user_id, 'mfw_preferences', $updated_preferences);

            // Log preference update
            $this->log_preference_update($user_id, $preferences);

            return new WP_REST_Response([
                'success' => true,
                'data' => $updated_preferences,
                'message' => __('Preferences updated successfully.', 'mfw')
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
     * Get user activity
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_user_activity($request) {
        try {
            $user_id = $request->get_param('id');
            $params = [
                'start_date' => $request->get_param('start_date'),
                'end_date' => $request->get_param('end_date'),
                'type' => $request->get_param('type'),
                'per_page' => $request->get_param('per_page'),
                'page' => $request->get_param('page')
            ];

            $activity = $this->get_user_activity_data($user_id, $params);

            return new WP_REST_Response([
                'success' => true,
                'data' => $activity
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
     * Get user permissions
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_user_permissions($request) {
        try {
            $user_id = $request->get_param('id');
            $user = get_user_by('id', $user_id);

            if (!$user) {
                return new WP_Error(
                    'mfw_user_not_found',
                    __('User not found.', 'mfw'),
                    ['status' => 404]
                );
            }

            $permissions = $this->get_user_capabilities($user);

            return new WP_REST_Response([
                'success' => true,
                'data' => $permissions
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
     * Prepare user data
     *
     * @param WP_User $user User object
     * @return array Prepared user data
     */
    private function prepare_user_data($user) {
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles,
            'registered' => $user->user_registered,
            'last_login' => get_user_meta($user->ID, 'last_login', true),
            'meta' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            ]
        ];
    }

    /**
     * Get default user preferences
     *
     * @return array Default preferences
     */
    private function get_default_preferences() {
        return [
            'notifications' => [
                'email' => true,
                'browser' => true
            ],
            'display' => [
                'theme' => 'light',
                'language' => 'en_US'
            ],
            'dashboard' => [
                'widgets' => ['overview', 'activity', 'stats']
            ]
        ];
    }

    /**
     * Log preference update
     *
     * @param int $user_id User ID
     * @param array $preferences Updated preferences
     */
    private function log_preference_update($user_id, $preferences) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_user_log',
                [
                    'user_id' => $user_id,
                    'action' => 'update_preferences',
                    'data' => json_encode($preferences),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log preference update: %s', $e->getMessage()),
                'user_controller',
                'error'
            );
        }
    }
}