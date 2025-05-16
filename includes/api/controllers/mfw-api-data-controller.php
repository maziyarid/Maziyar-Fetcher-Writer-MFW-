<?php
/**
 * API Data Controller Class
 * 
 * Handles API endpoints for data management.
 * Manages CRUD operations for plugin data via API.
 *
 * @package MFW
 * @subpackage API
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_API_Data_Controller extends MFW_API_Controller_Base {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 19:08:30';

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
    protected $rest_base = 'data';

    /**
     * Data handler
     */
    private $data_handler;

    /**
     * Initialize controller
     *
     * @param string $namespace API namespace
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
        $this->data_handler = new MFW_Data_Handler();
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Get collection
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<type>[\w-]+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_items'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                    'args' => $this->get_collection_params()
                ]
            ]
        );

        // Get single item
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<type>[\w-]+)/(?P<id>\d+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_item'],
                    'permission_callback' => [$this, 'get_item_permissions_check'],
                    'args' => $this->get_item_params()
                ]
            ]
        );

        // Create item
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<type>[\w-]+)',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'create_item'],
                    'permission_callback' => [$this, 'create_item_permissions_check'],
                    'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)
                ]
            ]
        );

        // Update item
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<type>[\w-]+)/(?P<id>\d+)',
            [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'update_item'],
                    'permission_callback' => [$this, 'update_item_permissions_check'],
                    'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)
                ]
            ]
        );

        // Delete item
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<type>[\w-]+)/(?P<id>\d+)',
            [
                [
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => [$this, 'delete_item'],
                    'permission_callback' => [$this, 'delete_item_permissions_check'],
                    'args' => [
                        'force' => [
                            'type' => 'boolean',
                            'default' => false,
                            'description' => __('Whether to bypass trash and force deletion.', 'mfw')
                        ]
                    ]
                ]
            ]
        );

        // Batch operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<type>[\w-]+)/batch',
            [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'batch_operations'],
                    'permission_callback' => [$this, 'batch_operations_permissions_check'],
                    'args' => $this->get_batch_params()
                ]
            ]
        );
    }

    /**
     * Get collection of items
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_items($request) {
        try {
            $type = $request->get_param('type');
            $params = [
                'page' => $request->get_param('page'),
                'per_page' => $request->get_param('per_page'),
                'search' => $request->get_param('search'),
                'orderby' => $request->get_param('orderby'),
                'order' => $request->get_param('order'),
                'filters' => $request->get_param('filters')
            ];

            $result = $this->data_handler->get_items($type, $params);

            return new WP_REST_Response([
                'success' => true,
                'data' => $result['items'],
                'total' => $result['total'],
                'pages' => $result['pages']
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
     * Get single item
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_item($request) {
        try {
            $type = $request->get_param('type');
            $id = $request->get_param('id');

            $item = $this->data_handler->get_item($type, $id);

            if (!$item) {
                return new WP_Error(
                    'mfw_not_found',
                    __('Item not found.', 'mfw'),
                    ['status' => 404]
                );
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => $item
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
     * Create item
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function create_item($request) {
        try {
            $type = $request->get_param('type');
            $data = $request->get_param('data');

            $result = $this->data_handler->create_item($type, $data);

            // Log item creation
            $this->log_data_operation('create', $type, $result['id']);

            return new WP_REST_Response([
                'success' => true,
                'data' => $result,
                'message' => __('Item created successfully.', 'mfw')
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
     * Update item
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function update_item($request) {
        try {
            $type = $request->get_param('type');
            $id = $request->get_param('id');
            $data = $request->get_param('data');

            $result = $this->data_handler->update_item($type, $id, $data);

            if (!$result) {
                return new WP_Error(
                    'mfw_update_failed',
                    __('Failed to update item.', 'mfw'),
                    ['status' => 500]
                );
            }

            // Log item update
            $this->log_data_operation('update', $type, $id);

            return new WP_REST_Response([
                'success' => true,
                'data' => $result,
                'message' => __('Item updated successfully.', 'mfw')
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
     * Delete item
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function delete_item($request) {
        try {
            $type = $request->get_param('type');
            $id = $request->get_param('id');
            $force = $request->get_param('force');

            $result = $this->data_handler->delete_item($type, $id, $force);

            if (!$result) {
                return new WP_Error(
                    'mfw_delete_failed',
                    __('Failed to delete item.', 'mfw'),
                    ['status' => 500]
                );
            }

            // Log item deletion
            $this->log_data_operation('delete', $type, $id);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Item deleted successfully.', 'mfw')
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
     * Batch operations
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function batch_operations($request) {
        try {
            $type = $request->get_param('type');
            $operations = $request->get_param('operations');

            $results = [
                'created' => [],
                'updated' => [],
                'deleted' => [],
                'failed' => []
            ];

            foreach ($operations as $operation) {
                try {
                    switch ($operation['method']) {
                        case 'POST':
                            $result = $this->data_handler->create_item($type, $operation['data']);
                            $results['created'][] = $result['id'];
                            break;

                        case 'PUT':
                            $result = $this->data_handler->update_item($type, $operation['id'], $operation['data']);
                            $results['updated'][] = $operation['id'];
                            break;

                        case 'DELETE':
                            $result = $this->data_handler->delete_item($type, $operation['id'], $operation['force'] ?? false);
                            $results['deleted'][] = $operation['id'];
                            break;
                    }
                } catch (Exception $e) {
                    $results['failed'][] = [
                        'operation' => $operation,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log batch operation
            $this->log_batch_operation($type, $results);

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
     * Log data operation
     *
     * @param string $operation Operation type
     * @param string $type Data type
     * @param int $id Item ID
     */
    private function log_data_operation($operation, $type, $id) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_data_log',
                [
                    'operation' => $operation,
                    'data_type' => $type,
                    'item_id' => $id,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log data operation: %s', $e->getMessage()),
                'data_controller',
                'error'
            );
        }
    }

    /**
     * Log batch operation
     *
     * @param string $type Data type
     * @param array $results Operation results
     */
    private function log_batch_operation($type, $results) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_data_log',
                [
                    'operation' => 'batch',
                    'data_type' => $type,
                    'details' => json_encode($results),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log batch operation: %s', $e->getMessage()),
                'data_controller',
                'error'
            );
        }
    }
}