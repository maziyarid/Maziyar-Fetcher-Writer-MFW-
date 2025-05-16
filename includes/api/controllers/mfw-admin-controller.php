<?php
/**
 * Admin Controller Class
 * 
 * Handles administrative functionality for the framework.
 * Manages settings, configurations, and system operations.
 *
 * @package MFW
 * @subpackage Controllers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Admin_Controller extends MFW_Controller {
    /**
     * Initialize controller
     */
    protected function init() {
        $this->add_middleware('auth');
        $this->add_middleware('admin');
    }

    /**
     * Display dashboard
     *
     * @return string Rendered view
     */
    public function dashboard() {
        $stats = [
            'users' => count_users(),
            'posts' => wp_count_posts(),
            'pages' => wp_count_posts('page'),
            'comments' => wp_count_comments()
        ];

        return $this->render('admin/dashboard', [
            'stats' => $stats,
            'recent_activity' => $this->get_recent_activity()
        ]);
    }

    /**
     * Display settings
     *
     * @return string|MFW_Response Rendered view or redirect
     */
    public function settings() {
        if ($this->is_post()) {
            if (!$this->validate([
                'site_name' => 'required',
                'admin_email' => 'required|email',
                'posts_per_page' => 'required|numeric'
            ])) {
                return $this->render('admin/settings', [
                    'errors' => $this->get_validation_errors(),
                    'settings' => $this->input()
                ]);
            }

            update_option('blogname', $this->input('site_name'));
            update_option('admin_email', $this->input('admin_email'));
            update_option('posts_per_page', $this->input('posts_per_page'));

            return $this->redirect(admin_url('admin.php?page=mfw-settings&updated=true'));
        }

        return $this->render('admin/settings', [
            'settings' => [
                'site_name' => get_option('blogname'),
                'admin_email' => get_option('admin_email'),
                'posts_per_page' => get_option('posts_per_page')
            ]
        ]);
    }

    /**
     * Handle AJAX requests
     *
     * @return MFW_Response JSON response
     */
    public function ajax() {
        if (!$this->is_ajax()) {
            return $this->json(['error' => 'Invalid request'], 400);
        }

        $action = $this->input('action');
        switch ($action) {
            case 'get_stats':
                return $this->json([
                    'users' => count_users(),
                    'posts' => wp_count_posts(),
                    'pages' => wp_count_posts('page'),
                    'comments' => wp_count_comments()
                ]);

            case 'clear_cache':
                mfw_service('cache')->clear();
                return $this->json(['message' => 'Cache cleared successfully']);

            default:
                return $this->json(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Get recent activity
     *
     * @param int $limit Number of items to return
     * @return array Recent activity
     */
    protected function get_recent_activity($limit = 10) {
        global $wpdb;

        $activity = [];

        // Get recent posts
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);

        foreach ($posts as $post) {
            $activity[] = [
                'type' => 'post',
                'title' => $post->post_title,
                'url' => get_permalink($post),
                'date' => $post->post_modified,
                'user' => get_userdata($post->post_author)->display_name
            ];
        }

        // Get recent comments
        $comments = get_comments([
            'number' => $limit,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ]);

        foreach ($comments as $comment) {
            $activity[] = [
                'type' => 'comment',
                'title' => wp_trim_words($comment->comment_content, 10),
                'url' => get_comment_link($comment),
                'date' => $comment->comment_date,
                'user' => $comment->comment_author
            ];
        }

        // Sort by date
        usort($activity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($activity, 0, $limit);
    }
}