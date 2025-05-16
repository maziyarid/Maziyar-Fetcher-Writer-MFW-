<?php
/**
 * Frontend Controller Class
 * 
 * Handles frontend functionality for the framework.
 * Manages page rendering, templates, and public-facing features.
 *
 * @package MFW
 * @subpackage Controllers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Frontend_Controller extends MFW_Controller {
    /**
     * Page cache duration
     *
     * @var int
     */
    protected $cache_duration = 3600; // 1 hour

    /**
     * Initialize controller
     */
    protected function init() {
        $this->init_timestamp = '2025-05-14 07:26:34';
        $this->init_user = 'maziyarid';

        $this->add_middleware('throttle:60,1'); // 60 requests per minute
    }

    /**
     * Display homepage
     *
     * @return string Rendered view
     */
    public function home() {
        $cache_key = 'mfw_home_' . md5(serialize($this->request->all()));
        
        if ($cached = mfw_cache($cache_key)) {
            return $cached;
        }

        $data = [
            'featured_posts' => $this->get_featured_posts(),
            'recent_posts' => $this->get_recent_posts(),
            'categories' => $this->get_categories(),
            'popular_tags' => $this->get_popular_tags()
        ];

        $view = $this->render('frontend/home', $data);
        mfw_cache($cache_key, $view, $this->cache_duration);

        return $view;
    }

    /**
     * Display single post
     *
     * @param int $id Post ID
     * @return string|MFW_Response Rendered view or redirect
     */
    public function single($id) {
        $post = get_post($id);

        if (!$post || $post->post_status !== 'publish') {
            return $this->redirect(home_url(), 404);
        }

        // Track post view
        $this->track_post_view($post->ID);

        // Get related posts
        $related_posts = $this->get_related_posts($post);

        // Get comments if enabled
        $comments = comments_open($post->ID) ? $this->get_post_comments($post->ID) : [];

        return $this->render('frontend/single', [
            'post' => $post,
            'author' => get_userdata($post->post_author),
            'categories' => get_the_category($post->ID),
            'tags' => get_the_tags($post->ID),
            'related_posts' => $related_posts,
            'comments' => $comments,
            'comment_form' => $this->get_comment_form($post->ID)
        ]);
    }

    /**
     * Display archive page
     *
     * @return string Rendered view
     */
    public function archive() {
        $type = $this->input('type', 'date');
        $value = $this->input('value');
        $page = max(1, $this->input('page', 1));
        $per_page = min(100, $this->input('per_page', 10));

        $args = [
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        switch ($type) {
            case 'category':
                $args['category_name'] = $value;
                $title = single_cat_title('', false);
                break;
            
            case 'tag':
                $args['tag'] = $value;
                $title = single_tag_title('', false);
                break;
            
            case 'author':
                $args['author_name'] = $value;
                $title = get_the_author();
                break;
            
            default: // date
                if ($value) {
                    $date = explode('-', $value);
                    $args['year'] = $date[0];
                    if (isset($date[1])) $args['monthnum'] = $date[1];
                    if (isset($date[2])) $args['day'] = $date[2];
                }
                $title = get_the_archive_title();
        }

        $query = new WP_Query($args);

        return $this->render('frontend/archive', [
            'title' => $title,
            'type' => $type,
            'value' => $value,
            'posts' => $query->posts,
            'pagination' => [
                'total' => $query->found_posts,
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil($query->found_posts / $per_page)
            ]
        ]);
    }

    /**
     * Handle search
     *
     * @return string Rendered view
     */
    public function search() {
        $query = $this->input('q');
        $page = max(1, $this->input('page', 1));
        $per_page = min(100, $this->input('per_page', 10));

        if (empty($query)) {
            return $this->render('frontend/search', [
                'query' => '',
                'posts' => [],
                'pagination' => null
            ]);
        }

        $args = [
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => $query
        ];

        $search = new WP_Query($args);

        return $this->render('frontend/search', [
            'query' => $query,
            'posts' => $search->posts,
            'pagination' => [
                'total' => $search->found_posts,
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil($search->found_posts / $per_page)
            ]
        ]);
    }

    /**
     * Handle contact form
     *
     * @return MFW_Response Response
     */
    public function contact() {
        if (!$this->is_post()) {
            return $this->render('frontend/contact');
        }

        if (!$this->validate([
            'name' => 'required',
            'email' => 'required|email',
            'subject' => 'required',
            'message' => 'required|min:10'
        ])) {
            return $this->render('frontend/contact', [
                'errors' => $this->get_validation_errors(),
                'old' => $this->request->all()
            ]);
        }

        $sent = wp_mail(
            get_option('admin_email'),
            sprintf('[Contact] %s', $this->input('subject')),
            $this->input('message'),
            [
                'From: ' . $this->input('name') . ' <' . $this->input('email') . '>',
                'Reply-To: ' . $this->input('email')
            ]
        );

        if (!$sent) {
            return $this->render('frontend/contact', [
                'error' => 'Failed to send message. Please try again later.',
                'old' => $this->request->all()
            ]);
        }

        return $this->render('frontend/contact', [
            'success' => 'Message sent successfully!'
        ]);
    }

    /**
     * Get featured posts
     *
     * @param int $limit Number of posts
     * @return array Featured posts
     */
    protected function get_featured_posts($limit = 5) {
        return get_posts([
            'posts_per_page' => $limit,
            'meta_key' => '_mfw_featured',
            'meta_value' => '1'
        ]);
    }

    /**
     * Get recent posts
     *
     * @param int $limit Number of posts
     * @return array Recent posts
     */
    protected function get_recent_posts($limit = 10) {
        return get_posts([
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
    }

    /**
     * Get related posts
     *
     * @param WP_Post $post Post object
     * @param int $limit Number of posts
     * @return array Related posts
     */
    protected function get_related_posts($post, $limit = 3) {
        $categories = wp_get_post_categories($post->ID, ['fields' => 'ids']);
        $tags = wp_get_post_tags($post->ID, ['fields' => 'ids']);

        return get_posts([
            'posts_per_page' => $limit,
            'post__not_in' => [$post->ID],
            'tax_query' => [
                'relation' => 'OR',
                [
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $categories
                ],
                [
                    'taxonomy' => 'post_tag',
                    'field' => 'term_id',
                    'terms' => $tags
                ]
            ]
        ]);
    }

    /**
     * Track post view
     *
     * @param int $post_id Post ID
     */
    protected function track_post_view($post_id) {
        $views = (int)get_post_meta($post_id, '_mfw_views', true);
        update_post_meta($post_id, '_mfw_views', $views + 1);
    }

    /**
     * Get post comments
     *
     * @param int $post_id Post ID
     * @return array Comments
     */
    protected function get_post_comments($post_id) {
        return get_comments([
            'post_id' => $post_id,
            'status' => 'approve',
            'order' => 'ASC'
        ]);
    }

    /**
     * Get comment form
     *
     * @param int $post_id Post ID
     * @return string Comment form HTML
     */
    protected function get_comment_form($post_id) {
        ob_start();
        comment_form([], $post_id);
        return ob_get_clean();
    }

    /**
     * Get categories
     *
     * @param array $args Query arguments
     * @return array Categories
     */
    protected function get_categories($args = []) {
        $defaults = [
            'orderby' => 'name',
            'order' => 'ASC',
            'hide_empty' => true
        ];

        return get_categories(array_merge($defaults, $args));
    }

    /**
     * Get popular tags
     *
     * @param int $limit Number of tags
     * @return array Tags
     */
    protected function get_popular_tags($limit = 10) {
        return get_terms([
            'taxonomy' => 'post_tag',
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => $limit,
            'hide_empty' => true
        ]);
    }
}