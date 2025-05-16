<?php
/**
 * Search Handler Class
 * 
 * Manages advanced search functionality with filters, sorting,
 * and relevance scoring. Supports custom post types and taxonomies.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Search_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:08:20';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Search settings
     */
    private $settings;

    /**
     * Search filters
     */
    private $filters = [];

    /**
     * Search cache
     */
    private $cache;

    /**
     * Initialize search handler
     */
    public function __construct() {
        // Load settings
        $this->settings = get_option('mfw_search_settings', []);
        $this->cache = new MFW_Cache_Handler();

        // Add search filters
        add_filter('posts_search', [$this, 'modify_search_query'], 10, 2);
        add_filter('posts_join', [$this, 'modify_search_join'], 10, 2);
        add_filter('posts_where', [$this, 'modify_search_where'], 10, 2);
        add_filter('posts_orderby', [$this, 'modify_search_orderby'], 10, 2);

        // Register AJAX handlers
        add_action('wp_ajax_mfw_ajax_search', [$this, 'handle_ajax_search']);
        add_action('wp_ajax_nopriv_mfw_ajax_search', [$this, 'handle_ajax_search']);
    }

    /**
     * Perform search
     *
     * @param string $query Search query
     * @param array $args Search arguments
     * @return array Search results
     */
    public function search($query, $args = []) {
        try {
            // Parse arguments
            $args = wp_parse_args($args, [
                'post_type' => 'any',
                'posts_per_page' => 10,
                'paged' => 1,
                'orderby' => 'relevance',
                'order' => 'DESC',
                'filters' => [],
                'cache' => true,
                'cache_ttl' => 3600
            ]);

            // Generate cache key
            $cache_key = $this->generate_cache_key($query, $args);

            // Check cache
            if ($args['cache']) {
                $cached = $this->cache->get($cache_key, 'mfw_search');
                if ($cached !== false) {
                    return $cached;
                }
            }

            // Set active filters
            $this->filters = $args['filters'];

            // Prepare search query
            $search_args = [
                's' => $query,
                'post_type' => $args['post_type'],
                'posts_per_page' => $args['posts_per_page'],
                'paged' => $args['paged'],
                'orderby' => $args['orderby'],
                'order' => $args['order'],
                'suppress_filters' => false
            ];

            // Perform search
            $results = new WP_Query($search_args);

            // Format results
            $formatted = $this->format_results($results);

            // Cache results
            if ($args['cache']) {
                $this->cache->set($cache_key, $formatted, 'mfw_search', $args['cache_ttl']);
            }

            // Log search
            $this->log_search($query, $args, $formatted['total']);

            return $formatted;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Search failed: %s', $e->getMessage()),
                'search_handler',
                'error'
            );
            return [
                'items' => [],
                'total' => 0,
                'pages' => 0
            ];
        }
    }

    /**
     * Handle AJAX search request
     */
    public function handle_ajax_search() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_ajax_search', 'nonce');

            // Get search parameters
            $query = sanitize_text_field($_POST['query'] ?? '');
            $args = isset($_POST['args']) ? (array)$_POST['args'] : [];

            // Perform search
            $results = $this->search($query, $args);

            wp_send_json_success($results);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Modify search query
     *
     * @param string $search Search SQL
     * @param WP_Query $wp_query Query object
     * @return string Modified search SQL
     */
    public function modify_search_query($search, $wp_query) {
        if (!$wp_query->is_search() || !$wp_query->get('s')) {
            return $search;
        }

        global $wpdb;

        $search_terms = $this->parse_search_terms($wp_query->get('s'));
        $search_parts = [];

        foreach ($search_terms as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';
            $search_parts[] = $wpdb->prepare(
                "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s)",
                $like,
                $like,
                $like
            );
        }

        if (!empty($search_parts)) {
            $search = ' AND (' . implode(' AND ', $search_parts) . ')';
        }

        return $search;
    }

    /**
     * Modify search join
     *
     * @param string $join Join SQL
     * @param WP_Query $wp_query Query object
     * @return string Modified join SQL
     */
    public function modify_search_join($join, $wp_query) {
        if (!$wp_query->is_search() || empty($this->filters)) {
            return $join;
        }

        global $wpdb;

        // Add joins for taxonomy filters
        foreach ($this->filters as $taxonomy => $terms) {
            if (!empty($terms) && taxonomy_exists($taxonomy)) {
                $tax_index = sanitize_key($taxonomy);
                $join .= " LEFT JOIN {$wpdb->term_relationships} AS tr_{$tax_index}
                          ON ({$wpdb->posts}.ID = tr_{$tax_index}.object_id)
                          LEFT JOIN {$wpdb->term_taxonomy} AS tt_{$tax_index}
                          ON (tr_{$tax_index}.term_taxonomy_id = tt_{$tax_index}.term_taxonomy_id
                          AND tt_{$tax_index}.taxonomy = '{$taxonomy}')";
            }
        }

        return $join;
    }

    /**
     * Modify search where
     *
     * @param string $where Where SQL
     * @param WP_Query $wp_query Query object
     * @return string Modified where SQL
     */
    public function modify_search_where($where, $wp_query) {
        if (!$wp_query->is_search() || empty($this->filters)) {
            return $where;
        }

        global $wpdb;

        // Add conditions for taxonomy filters
        foreach ($this->filters as $taxonomy => $terms) {
            if (!empty($terms) && taxonomy_exists($taxonomy)) {
                $tax_index = sanitize_key($taxonomy);
                $term_ids = array_map('intval', $terms);
                $where .= " AND tt_{$tax_index}.term_id IN (" . implode(',', $term_ids) . ")";
            }
        }

        return $where;
    }

    /**
     * Modify search orderby
     *
     * @param string $orderby Orderby SQL
     * @param WP_Query $wp_query Query object
     * @return string Modified orderby SQL
     */
    public function modify_search_orderby($orderby, $wp_query) {
        if (!$wp_query->is_search() || $wp_query->get('orderby') !== 'relevance') {
            return $orderby;
        }

        // Calculate relevance score
        $search_terms = $this->parse_search_terms($wp_query->get('s'));
        $relevance_parts = [];

        global $wpdb;

        foreach ($search_terms as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';
            $relevance_parts[] = $wpdb->prepare(
                "IF({$wpdb->posts}.post_title LIKE %s, 10, 0) +
                 IF({$wpdb->posts}.post_excerpt LIKE %s, 5, 0) +
                 IF({$wpdb->posts}.post_content LIKE %s, 1, 0)",
                $like,
                $like,
                $like
            );
        }

        $orderby = '(' . implode(' + ', $relevance_parts) . ') DESC';

        return $orderby;
    }

    /**
     * Format search results
     *
     * @param WP_Query $results Search results
     * @return array Formatted results
     */
    private function format_results($results) {
        $items = [];

        foreach ($results->posts as $post) {
            $items[] = [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'excerpt' => get_the_excerpt($post),
                'url' => get_permalink($post),
                'post_type' => $post->post_type,
                'date' => get_the_date('c', $post),
                'thumbnail' => get_the_post_thumbnail_url($post, 'thumbnail'),
                'author' => [
                    'id' => $post->post_author,
                    'name' => get_the_author_meta('display_name', $post->post_author),
                    'avatar' => get_avatar_url($post->post_author)
                ],
                'taxonomies' => $this->get_post_taxonomies($post)
            ];
        }

        return [
            'items' => $items,
            'total' => $results->found_posts,
            'pages' => $results->max_num_pages
        ];
    }

    /**
     * Parse search terms
     *
     * @param string $query Search query
     * @return array Search terms
     */
    private function parse_search_terms($query) {
        $terms = [];

        // Extract quoted phrases
        if (preg_match_all('/"([^"]+)"/', $query, $matches)) {
            $terms = $matches[1];
            $query = preg_replace('/"[^"]+"/', '', $query);
        }

        // Add remaining words
        $words = preg_split('/[\s,]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $terms = array_merge($terms, $words);

        // Remove short terms if configured
        if (!empty($this->settings['min_word_length'])) {
            $terms = array_filter($terms, function($term) {
                return mb_strlen($term) >= $this->settings['min_word_length'];
            });
        }

        return array_unique($terms);
    }

    /**
     * Get post taxonomies
     *
     * @param WP_Post $post Post object
     * @return array Taxonomies
     */
    private function get_post_taxonomies($post) {
        $taxonomies = [];
        $post_taxonomies = get_object_taxonomies($post->post_type, 'objects');

        foreach ($post_taxonomies as $tax_name => $tax) {
            $terms = wp_get_post_terms($post->ID, $tax_name);
            if (!is_wp_error($terms)) {
                $taxonomies[$tax_name] = array_map(function($term) {
                    return [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug
                    ];
                }, $terms);
            }
        }

        return $taxonomies;
    }

    /**
     * Generate cache key
     *
     * @param string $query Search query
     * @param array $args Search arguments
     * @return string Cache key
     */
    private function generate_cache_key($query, $args) {
        return md5($query . serialize($args));
    }

    /**
     * Log search
     *
     * @param string $query Search query
     * @param array $args Search arguments
     * @param int $results_count Results count
     */
    private function log_search($query, $args, $results_count) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_search_log',
                [
                    'query' => $query,
                    'parameters' => json_encode($args),
                    'results_count' => $results_count,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log search: %s', $e->getMessage()),
                'search_handler',
                'error'
            );
        }
    }
}