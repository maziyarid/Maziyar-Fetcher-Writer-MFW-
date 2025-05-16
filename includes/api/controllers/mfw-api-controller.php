<?php
/**
 * API Controller Class
 * 
 * Handles REST API functionality for the framework.
 * Manages API endpoints, authentication, and response formatting.
 *
 * @package MFW
 * @subpackage Controllers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Api_Controller extends MFW_Controller {
    /**
     * API version
     *
     * @var string
     */
    protected $version = 'v1';

    /**
     * Initialize controller
     */
    protected function init() {
        $this->init_timestamp = '2025-05-14 07:25:25';
        $this->init_user = 'maziyarid';

        $this->add_middleware('api');
        $this->response->header('Content-Type', 'application/json');
    }

    /**
     * Format API response
     *
     * @param mixed $data Response data
     * @param string $message Response message
     * @param int $status HTTP status code
     * @return array Formatted response
     */
    protected function format_response($data = null, $message = '', $status = 200) {
        return [
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'version' => $this->version,
                'timestamp' => current_time('c'),
                'request_id' => $this->request->get_id()
            ]
        ];
    }

    /**
     * Get API information
     *
     * @return MFW_Response API info response
     */
    public function info() {
        $data = [
            'name' => 'Modern Framework for WordPress',
            'version' => MFW_VERSION,
            'api_version' => $this->version,
            'documentation' => mfw_url('docs/api'),
            'endpoints' => $this->get_endpoints()
        ];

        return $this->json(
            $this->format_response($data, 'API information retrieved successfully')
        );
    }

    /**
     * Authenticate user
     *
     * @return MFW_Response Authentication response
     */
    public function authenticate() {
        if (!$this->validate([
            'username' => 'required',
            'password' => 'required'
        ])) {
            return $this->json(
                $this->format_response(
                    ['errors' => $this->get_validation_errors()],
                    'Validation failed',
                    422
                ),
                422
            );
        }

        $user = wp_authenticate(
            $this->input('username'),
            $this->input('password')
        );

        if (is_wp_error($user)) {
            return $this->json(
                $this->format_response(
                    ['error' => $user->get_error_message()],
                    'Authentication failed',
                    401
                ),
                401
            );
        }

        $token = mfw_service('auth')->generate_token($user);

        return $this->json(
            $this->format_response(
                [
                    'token' => $token,
                    'user' => [
                        'id' => $user->ID,
                        'username' => $user->user_login,
                        'email' => $user->user_email,
                        'display_name' => $user->display_name,
                        'roles' => $user->roles
                    ]
                ],
                'Authentication successful'
            )
        );
    }

    /**
     * Get posts
     *
     * @return MFW_Response Posts response
     */
    public function get_posts() {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => $this->input('per_page', 10),
            'paged' => $this->input('page', 1),
            'orderby' => $this->input('orderby', 'date'),
            'order' => $this->input('order', 'DESC')
        ];

        if ($search = $this->input('search')) {
            $args['s'] = $search;
        }

        if ($category = $this->input('category')) {
            $args['category_name'] = $category;
        }

        $query = new WP_Query($args);
        $posts = array_map([$this, 'format_post'], $query->posts);

        return $this->json(
            $this->format_response(
                [
                    'posts' => $posts,
                    'pagination' => [
                        'total' => $query->found_posts,
                        'per_page' => $args['posts_per_page'],
                        'current_page' => $args['paged'],
                        'last_page' => ceil($query->found_posts / $args['posts_per_page'])
                    ]
                ],
                'Posts retrieved successfully'
            )
        );
    }

    /**
     * Get single post
     *
     * @param int $id Post ID
     * @return MFW_Response Post response
     */
    public function get_post($id) {
        $post = get_post($id);

        if (!$post) {
            return $this->json(
                $this->format_response(
                    null,
                    'Post not found',
                    404
                ),
                404
            );
        }

        return $this->json(
            $this->format_response(
                $this->format_post($post),
                'Post retrieved successfully'
            )
        );
    }

    /**
     * Create post
     *
     * @return MFW_Response Creation response
     */
    public function create_post() {
        if (!current_user_can('publish_posts')) {
            return $this->json(
                $this->format_response(
                    null,
                    'Permission denied',
                    403
                ),
                403
            );
        }

        if (!$this->validate([
            'title' => 'required',
            'content' => 'required'
        ])) {
            return $this->json(
                $this->format_response(
                    ['errors' => $this->get_validation_errors()],
                    'Validation failed',
                    422
                ),
                422
            );
        }

        $post_data = [
            'post_title' => $this->input('title'),
            'post_content' => $this->input('content'),
            'post_status' => $this->input('status', 'draft'),
            'post_author' => get_current_user_id()
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $this->json(
                $this->format_response(
                    ['error' => $post_id->get_error_message()],
                    'Failed to create post',
                    500
                ),
                500
            );
        }

        return $this->json(
            $this->format_response(
                $this->format_post(get_post($post_id)),
                'Post created successfully',
                201
            ),
            201
        );
    }

    /**
     * Update post
     *
     * @param int $id Post ID
     * @return MFW_Response Update response
     */
    public function update_post($id) {
        $post = get_post($id);

        if (!$post) {
            return $this->json(
                $this->format_response(
                    null,
                    'Post not found',
                    404
                ),
                404
            );
        }

        if (!current_user_can('edit_post', $id)) {
            return $this->json(
                $this->format_response(
                    null,
                    'Permission denied',
                    403
                ),
                403
            );
        }

        $post_data = [
            'ID' => $id,
            'post_title' => $this->input('title', $post->post_title),
            'post_content' => $this->input('content', $post->post_content),
            'post_status' => $this->input('status', $post->post_status)
        ];

        $updated = wp_update_post($post_data);

        if (is_wp_error($updated)) {
            return $this->json(
                $this->format_response(
                    ['error' => $updated->get_error_message()],
                    'Failed to update post',
                    500
                ),
                500
            );
        }

        return $this->json(
            $this->format_response(
                $this->format_post(get_post($id)),
                'Post updated successfully'
            )
        );
    }

    /**
     * Delete post
     *
     * @param int $id Post ID
     * @return MFW_Response Deletion response
     */
    public function delete_post($id) {
        if (!current_user_can('delete_post', $id)) {
            return $this->json(
                $this->format_response(
                    null,
                    'Permission denied',
                    403
                ),
                403
            );
        }

        $deleted = wp_delete_post($id, true);

        if (!$deleted) {
            return $this->json(
                $this->format_response(
                    null,
                    'Failed to delete post',
                    500
                ),
                500
            );
        }

        return $this->json(
            $this->format_response(
                null,
                'Post deleted successfully'
            )
        );
    }

    /**
     * Format post data
     *
     * @param WP_Post $post Post object
     * @return array Formatted post data
     */
    protected function format_post($post) {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => get_the_excerpt($post),
            'status' => $post->post_status,
            'slug' => $post->post_name,
            'author' => [
                'id' => $post->post_author,
                'name' => get_the_author_meta('display_name', $post->post_author)
            ],
            'categories' => wp_get_post_categories($post->ID, ['fields' => 'names']),
            'tags' => wp_get_post_tags($post->ID, ['fields' => 'names']),
            'created_at' => $post->post_date_gmt,
            'updated_at' => $post->post_modified_gmt,
            'meta' => get_post_meta($post->ID),
            'links' => [
                'self' => get_permalink($post),
                'api' => rest_url('mfw/v1/posts/' . $post->ID)
            ]
        ];
    }

    /**
     * Get available endpoints
     *
     * @return array API endpoints
     */
    protected function get_endpoints() {
        return [
            'GET /api/v1' => [
                'description' => 'Get API information',
                'auth_required' => false
            ],
            'POST /api/v1/auth' => [
                'description' => 'Authenticate user',
                'auth_required' => false
            ],
            'GET /api/v1/posts' => [
                'description' => 'Get posts',
                'auth_required' => false
            ],
            'GET /api/v1/posts/{id}' => [
                'description' => 'Get single post',
                'auth_required' => false
            ],
            'POST /api/v1/posts' => [
                'description' => 'Create post',
                'auth_required' => true
            ],
            'PUT /api/v1/posts/{id}' => [
                'description' => 'Update post',
                'auth_required' => true
            ],
            'DELETE /api/v1/posts/{id}' => [
                'description' => 'Delete post',
                'auth_required' => true
            ]
        ];
    }
}