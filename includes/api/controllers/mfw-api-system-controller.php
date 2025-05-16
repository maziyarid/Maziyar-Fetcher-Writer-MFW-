<?php
/**
 * API System Controller Class
 * 
 * Handles API endpoints for system operations.
 * Manages system status, diagnostics, and maintenance via API.
 *
 * @package MFW
 * @subpackage API
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_API_System_Controller extends MFW_API_Controller_Base {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 19:11:30';

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
    protected $rest_base = 'system';

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
        // Get system status
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/status',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_system_status'],
                    'permission_callback' => [$this, 'get_system_status_permissions_check']
                ]
            ]
        );

        // Run system diagnostic
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/diagnostic',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'run_diagnostic'],
                    'permission_callback' => [$this, 'run_diagnostic_permissions_check'],
                    'args' => $this->get_diagnostic_params()
                ]
            ]
        );

        // Perform maintenance
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/maintenance',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'perform_maintenance'],
                    'permission_callback' => [$this, 'perform_maintenance_permissions_check'],
                    'args' => $this->get_maintenance_params()
                ]
            ]
        );

        // Get system logs
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/logs',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_system_logs'],
                    'permission_callback' => [$this, 'get_system_logs_permissions_check'],
                    'args' => $this->get_logs_params()
                ]
            ]
        );

        // Clear system logs
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/logs',
            [
                [
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => [$this, 'clear_system_logs'],
                    'permission_callback' => [$this, 'clear_system_logs_permissions_check']
                ]
            ]
        );
    }

    /**
     * Get system status
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_system_status($request) {
        try {
            $status = [
                'version' => MFW_VERSION,
                'environment' => $this->get_environment_info(),
                'database' => $this->get_database_info(),
                'memory' => $this->get_memory_usage(),
                'plugins' => $this->get_active_plugins(),
                'theme' => $this->get_theme_info(),
                'server' => $this->get_server_info()
            ];

            return new WP_REST_Response([
                'success' => true,
                'data' => $status
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
     * Run system diagnostic
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function run_diagnostic($request) {
        try {
            $type = $request->get_param('type');
            $options = $request->get_param('options');

            $results = [];
            
            switch ($type) {
                case 'files':
                    $results = $this->check_file_permissions();
                    break;
                case 'database':
                    $results = $this->check_database_integrity();
                    break;
                case 'cron':
                    $results = $this->check_cron_health();
                    break;
                case 'full':
                    $results = $this->run_full_diagnostic();
                    break;
                default:
                    throw new Exception(__('Invalid diagnostic type.', 'mfw'));
            }

            // Log diagnostic run
            $this->log_diagnostic_run($type, $results);

            return new WP_REST_Response([
                'success' => true,
                'data' => $results
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
     * Perform maintenance
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function perform_maintenance($request) {
        try {
            $tasks = $request->get_param('tasks');
            $options = $request->get_param('options');

            $results = [
                'completed' => [],
                'failed' => []
            ];

            foreach ($tasks as $task) {
                try {
                    switch ($task) {
                        case 'clear_cache':
                            $this->clear_plugin_cache();
                            $results['completed'][] = $task;
                            break;

                        case 'optimize_tables':
                            $this->optimize_database_tables();
                            $results['completed'][] = $task;
                            break;

                        case 'cleanup_files':
                            $this->cleanup_temporary_files();
                            $results['completed'][] = $task;
                            break;

                        default:
                            throw new Exception(sprintf(__('Invalid maintenance task: %s', 'mfw'), $task));
                    }
                } catch (Exception $e) {
                    $results['failed'][] = [
                        'task' => $task,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log maintenance tasks
            $this->log_maintenance_tasks($tasks, $results);

            return new WP_REST_Response([
                'success' => true,
                'data' => $results
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
     * Get system logs
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_system_logs($request) {
        try {
            $params = [
                'type' => $request->get_param('type'),
                'start_date' => $request->get_param('start_date'),
                'end_date' => $request->get_param('end_date'),
                'level' => $request->get_param('level'),
                'limit' => $request->get_param('limit'),
                'page' => $request->get_param('page')
            ];

            $logs = $this->fetch_system_logs($params);

            return new WP_REST_Response([
                'success' => true,
                'data' => $logs['items'],
                'total' => $logs['total'],
                'pages' => $logs['pages']
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
     * Clear system logs
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function clear_system_logs($request) {
        try {
            global $wpdb;

            $tables = [
                $wpdb->prefix . 'mfw_error_log',
                $wpdb->prefix . 'mfw_activity_log',
                $wpdb->prefix . 'mfw_security_log'
            ];

            foreach ($tables as $table) {
                $wpdb->query("TRUNCATE TABLE $table");
            }

            // Log the clearing of logs
            $this->log_system_operation('clear_logs');

            return new WP_REST_Response([
                'success' => true,
                'message' => __('System logs cleared successfully.', 'mfw')
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
     * Get environment information
     *
     * @return array Environment information
     */
    private function get_environment_info() {
        return [
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->get_mysql_version(),
            'wordpress_version' => get_bloginfo('version'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ];
    }

    /**
     * Get MySQL version
     *
     * @return string MySQL version
     */
    private function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var('SELECT VERSION()');
    }

    /**
     * Log system operation
     *
     * @param string $operation Operation performed
     * @param array $details Operation details
     */
    private function log_system_operation($operation, $details = []) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_system_log',
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
                sprintf('Failed to log system operation: %s', $e->getMessage()),
                'system_controller',
                'error'
            );
        }
    }
}