<?php
/**
 * Ajax Controller Class
 * 
 * Handles AJAX requests and responses for the framework.
 * Manages asynchronous operations and JSON responses.
 *
 * @package MFW
 * @subpackage Controllers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Ajax_Controller extends MFW_Controller {
    /**
     * Initialize controller
     */
    protected function init() {
        $this->init_timestamp = '2025-05-14 07:33:41';
        $this->init_user = 'maziyarid';

        if (!$this->is_ajax()) {
            wp_die('Invalid request');
        }

        $this->add_middleware('ajax');
        $this->response->header('Content-Type', 'application/json');
    }

    /**
     * Format AJAX response
     *
     * @param mixed $data Response data
     * @param string $message Response message
     * @param bool $success Whether request was successful
     * @return array Formatted response
     */
    protected function format_response($data = null, $message = '', $success = true) {
        return [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => current_time('c'),
            'request_id' => $this->request->get_id()
        ];
    }

    /**
     * Handle post voting
     *
     * @return MFW_Response JSON response
     */
    public function vote() {
        if (!is_user_logged_in()) {
            return $this->json(
                $this->format_response(
                    null,
                    'Login required to vote',
                    false
                ),
                403
            );
        }

        if (!$this->validate([
            'post_id' => 'required|numeric',
            'vote' => 'required|in:up,down'
        ])) {
            return $this->json(
                $this->format_response(
                    ['errors' => $this->get_validation_errors()],
                    'Invalid vote data',
                    false
                ),
                422
            );
        }

        $post_id = $this->input('post_id');
        $vote = $this->input('vote');
        $user_id = get_current_user_id();

        // Check if post exists
        if (!get_post($post_id)) {
            return $this->json(
                $this->format_response(
                    null,
                    'Post not found',
                    false
                ),
                404
            );
        }

        // Get existing votes
        $votes = get_post_meta($post_id, '_mfw_votes', true) ?: [];
        $user_vote = isset($votes[$user_id]) ? $votes[$user_id] : null;

        // Handle vote
        if ($user_vote === $vote) {
            // Remove vote if same
            unset($votes[$user_id]);
        } else {
            // Set new vote
            $votes[$user_id] = $vote;
        }

        // Update votes
        update_post_meta($post_id, '_mfw_votes', $votes);

        // Calculate totals
        $totals = [
            'up' => count(array_filter($votes, fn($v) => $v === 'up')),
            'down' => count(array_filter($votes, fn($v) => $v === 'down'))
        ];

        return $this->json(
            $this->format_response(
                [
                    'post_id' => $post_id,
                    'user_vote' => $votes[$user_id] ?? null,
                    'totals' => $totals
                ],
                'Vote recorded successfully'
            )
        );
    }

    /**
     * Handle post comments
     *
     * @return MFW_Response JSON response
     */
    public function comment() {
        if (!is_user_logged_in()) {
            return $this->json(
                $this->format_response(
                    null,
                    'Login required to comment',
                    false
                ),
                403
            );
        }

        if (!$this->validate([
            'post_id' => 'required|numeric',
            'content' => 'required|min:3'
        ])) {
            return $this->json(
                $this->format_response(
                    ['errors' => $this->get_validation_errors()],
                    'Invalid comment data',
                    false
                ),
                422
            );
        }

        $post_id = $this->input('post_id');
        $content = $this->input('content');
        $user = wp_get_current_user();

        // Check if post exists and comments are open
        $post = get_post($post_id);
        if (!$post || !comments_open($post_id)) {
            return $this->json(
                $this->format_response(
                    null,
                    'Comments are closed for this post',
                    false
                ),
                403
            );
        }

        // Create comment
        $comment_data = [
            'comment_post_ID' => $post_id,
            'comment_content' => $content,
            'user_id' => $user->ID,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_author_url' => $user->user_url,
            'comment_type' => 'comment'
        ];

        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            return $this->json(
                $this->format_response(
                    null,
                    'Failed to create comment',
                    false
                ),
                500
            );
        }

        $comment = get_comment($comment_id);

        return $this->json(
            $this->format_response(
                [
                    'comment' => $this->format_comment($comment),
                    'count' => get_comments_number($post_id)
                ],
                'Comment added successfully'
            )
        );
    }

    /**
     * Handle post bookmarks
     *
     * @return MFW_Response JSON response
     */
    public function bookmark() {
        if (!is_user_logged_in()) {
            return $this->json(
                $this->format_response(
                    null,
                    'Login required to bookmark',
                    false
                ),
                403
            );
        }

        if (!$this->validate([
            'post_id' => 'required|numeric'
        ])) {
            return $this->json(
                $this->format_response(
                    ['errors' => $this->get_validation_errors()],
                    'Invalid bookmark data',
                    false
                ),
                422
            );
        }

        $post_id = $this->input('post_id');
        $user_id = get_current_user_id();

        // Check if post exists
        if (!get_post($post_id)) {
            return $this->json(
                $this->format_response(
                    null,
                    'Post not found',
                    false
                ),
                404
            );
        }

        // Get user bookmarks
        $bookmarks = get_user_meta($user_id, '_mfw_bookmarks', true) ?: [];

        // Toggle bookmark
        $index = array_search($post_id, $bookmarks);
        if ($index !== false) {
            unset($bookmarks[$index]);
            $message = 'Bookmark removed successfully';
        } else {
            $bookmarks[] = $post_id;
            $message = 'Bookmark added successfully';
        }

        // Update bookmarks
        update_user_meta($user_id, '_mfw_bookmarks', array_values($bookmarks));

        return $this->json(
            $this->format_response(
                [
                    'post_id' => $post_id,
                    'bookmarked' => $index === false,
                    'count' => count($bookmarks)
                ],
                $message
            )
        );
    }

    /**
     * Handle search suggestions
     *
     * @return MFW_Response JSON response
     */
    public function search_suggest() {
        if (!$this->validate([
            'query' => 'required|min:2'
        ])) {
            return $this->json(
                $this->format_response(
                    [],
                    'Invalid search query',
                    false
                ),
                422
            );
        }

        $query = $this->input('query');
        $limit = min(10, $this->input('limit', 5));

        // Search posts
        $posts = get_posts([
            'posts_per_page' => $limit,
            's' => $query,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);

        // Format suggestions
        $suggestions = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post),
                'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 10),
                'date' => get_the_date('c', $post)
            ];
        }, $posts);

        return $this->json(
            $this->format_response(
                [
                    'query' => $query,
                    'suggestions' => $suggestions
                ],
                'Search suggestions retrieved successfully'
            )
        );
    }

    /**
     * Format comment data
     *
     * @param WP_Comment $comment Comment object
     * @return array Formatted comment data
     */
    protected function format_comment($comment) {
        return [
            'id' => $comment->comment_ID,
            'content' => $comment->comment_content,
            'author' => [
                'id' => $comment->user_id,
                'name' => $comment->comment_author,
                'avatar' => get_avatar_url($comment->comment_author_email)
            ],
            'date' => [
                'raw' => $comment->comment_date,
                'relative' => human_time_diff(
                    strtotime($comment->comment_date_gmt),
                    current_time('timestamp', true)
                ) . ' ago'
            ],
            'permalink' => get_comment_link($comment)
        ];
    }
}