<?php
/**
 * AJAX Handler Class
 *
 * Handles all AJAX requests for the plugin.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Ajax_Handler {
    /**
     * Initialize AJAX hooks
     */
    public static function init() {
        // Admin AJAX actions
        add_action('wp_ajax_mfw_test_api_connection', [__CLASS__, 'test_api_connection']);
        add_action('wp_ajax_mfw_fetch_content', [__CLASS__, 'handle_content_fetch']);
        add_action('wp_ajax_mfw_generate_image', [__CLASS__, 'handle_image_generation']);
        add_action('wp_ajax_mfw_preview_content', [__CLASS__, 'preview_generated_content']);
        add_action('wp_ajax_mfw_save_source', [__CLASS__, 'save_content_source']);
        add_action('wp_ajax_mfw_delete_source', [__CLASS__, 'delete_content_source']);
        add_action('wp_ajax_mfw_get_analytics', [__CLASS__, 'get_analytics_data']);
    }

    /**
     * Test API connection
     */
    public static function test_api_connection() {
        // Verify nonce
        if (!check_ajax_referer('mfw_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'mfw')]);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mfw')]);
        }

        $api_type = isset($_POST['api_type']) ? sanitize_text_field($_POST['api_type']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_type) || empty($api_key)) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'mfw')]);
        }

        try {
            switch ($api_type) {
                case 'gemini':
                    $result = MFW_Gemini_Service::test_connection($api_key);
                    break;
                case 'deepseek':
                    $result = MFW_Deepseek_Service::test_connection($api_key);
                    break;
                default:
                    throw new Exception(__('Invalid API type.', 'mfw'));
            }

            wp_send_json_success([
                'message' => __('API connection successful!', 'mfw'),
                'data' => $result
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle content fetch request
     */
    public static function handle_content_fetch() {
        // Verify nonce
        if (!check_ajax_referer('mfw_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'mfw')]);
        }

        // Verify user capabilities
        if (!current_user_can('mfw_run_fetch')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mfw')]);
        }

        $source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
        
        if (!$source_id) {
            wp_send_json_error(['message' => __('Invalid source ID.', 'mfw')]);
        }

        try {
            // Get source details
            $source = get_post($source_id);
            if (!$source || $source->post_type !== MFW_SOURCE_CPT) {
                throw new Exception(__('Invalid content source.', 'mfw'));
            }

            // Get source meta
            $source_type = get_post_meta($source_id, '_mfw_source_type', true);
            $source_config = get_post_meta($source_id, '_mfw_source_config', true);

            // Initialize appropriate fetcher
            $fetcher = self::get_fetcher_instance($source_type, $source_config);
            
            // Start fetch process
            $result = $fetcher->fetch();

            // Process fetched content
            $processor = new MFW_Content_Processor();
            $processed_items = $processor->process_items($result['items'], $source_config);

            // Create posts
            $created_posts = [];
            foreach ($processed_items as $item) {
                $post_id = self::create_post($item, $source_config);
                if ($post_id) {
                    $created_posts[] = $post_id;
                }
            }

            // Update analytics
            self::update_fetch_analytics($source_id, count($result['items']), count($created_posts));

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully processed %d items and created %d posts.', 'mfw'),
                    count($result['items']),
                    count($created_posts)
                ),
                'data' => [
                    'processed' => count($result['items']),
                    'created' => count($created_posts),
                    'posts' => $created_posts
                ]
            ]);

        } catch (Exception $e) {
            // Log error
            MFW_Error_Logger::log($e->getMessage(), 'content_fetch');
            
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle image generation request
     */
    public static function handle_image_generation() {
        // Verify nonce
        if (!check_ajax_referer('mfw_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'mfw')]);
        }

        // Verify user capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mfw')]);
        }

        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (empty($prompt)) {
            wp_send_json_error(['message' => __('Image prompt is required.', 'mfw')]);
        }

        try {
            // Get configured AI service
            $settings = get_option(MFW_SETTINGS_OPTION);
            $service = isset($settings['ai_service']) ? $settings['ai_service'] : MFW_AI_GEMINI;

            // Initialize image processor
            $image_processor = new MFW_Image_Processor();

            // Generate image
            $result = $image_processor->generate_image($prompt, $service);

            if ($result['success']) {
                // If post ID is provided, attach the image
                if ($post_id) {
                    $attachment_id = $image_processor->attach_image_to_post(
                        $result['image_path'],
                        $post_id,
                        $prompt
                    );
                    
                    $result['attachment_id'] = $attachment_id;
                }

                wp_send_json_success($result);
            } else {
                throw new Exception($result['message']);
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), 'image_generation');
            
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Preview generated content
     */
    public static function preview_generated_content() {
        // Verify nonce
        if (!check_ajax_referer('mfw_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'mfw')]);
        }

        // Verify user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mfw')]);
        }

        $original_content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $source_lang = isset($_POST['source_lang']) ? sanitize_text_field($_POST['source_lang']) : '';
        $target_lang = isset($_POST['target_lang']) ? sanitize_text_field($_POST['target_lang']) : '';

        if (empty($original_content)) {
            wp_send_json_error(['message' => __('Content is required.', 'mfw')]);
        }

        try {
            $processor = new MFW_Content_Processor();
            
            $processed_content = $processor->process_content(
                $original_content,
                [
                    'source_language' => $source_lang,
                    'target_language' => $target_lang
                ]
            );

            wp_send_json_success([
                'content' => $processed_content,
                'word_count' => str_word_count(strip_tags($processed_content))
            ]);

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), 'content_preview');
            
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Save content source
     */
    public static function save_content_source() {
        // Implementation for saving content source
        // Similar security checks and error handling as above
    }

    /**
     * Delete content source
     */
    public static function delete_content_source() {
        // Implementation for deleting content source
        // Similar security checks and error handling as above
    }

    /**
     * Get analytics data
     */
    public static function get_analytics_data() {
        // Implementation for fetching analytics data
        // Similar security checks and error handling as above
    }

    /**
     * Get appropriate fetcher instance
     *
     * @param string $source_type Source type
     * @param array $config Source configuration
     * @return MFW_Abstract_Fetcher
     * @throws Exception
     */
    private static function get_fetcher_instance($source_type, $config) {
        switch ($source_type) {
            case MFW_FETCH_GOOGLE_NEWS:
                return new MFW_Google_News_Fetcher($config);
            case MFW_FETCH_RSS:
                return new MFW_RSS_Fetcher($config);
            case MFW_FETCH_YOUTUBE:
                return new MFW_Youtube_Fetcher($config);
            case MFW_FETCH_AMAZON:
                return new MFW_Amazon_Fetcher($config);
            case MFW_FETCH_EBAY:
                return new MFW_Ebay_Fetcher($config);
            default:
                throw new Exception(__('Invalid source type.', 'mfw'));
        }
    }

    /**
     * Create WordPress post from processed content
     *
     * @param array $item Processed content item
     * @param array $source_config Source configuration
     * @return int|false Post ID or false on failure
     */
    private static function create_post($item, $source_config) {
        $post_data = [
            'post_title'    => $item['title'],
            'post_content'  => $item['content'],
            'post_status'   => $source_config['post_status'] ?? 'draft',
            'post_type'     => $source_config['post_type'] ?? 'post',
            'post_author'   => $source_config['author_id'] ?? get_current_user_id(),
            'post_category' => isset($source_config['categories']) ? array_map('intval', $source_config['categories']) : [],
        ];

        // Insert post
        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Set tags
            if (!empty($item['tags'])) {
                wp_set_post_tags($post_id, $item['tags']);
            }

            // Store source metadata
            update_post_meta($post_id, '_mfw_original_url', $item['source_url']);
            update_post_meta($post_id, '_mfw_fetch_date', current_time('mysql'));
            
            // Handle SEO metadata if available
            if (!empty($item['seo_meta'])) {
                self::update_seo_meta($post_id, $item['seo_meta']);
            }

            return $post_id;
        }

        return false;
    }

    /**
     * Update SEO metadata for post
     *
     * @param int $post_id Post ID
     * @param array $seo_meta SEO metadata
     */
    private static function update_seo_meta($post_id, $seo_meta) {
        // Yoast SEO integration
        if (defined('WPSEO_VERSION')) {
            if (isset($seo_meta['title'])) {
                update_post_meta($post_id, '_yoast_wpseo_title', $seo_meta['title']);
            }
            if (isset($seo_meta['description'])) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_meta['description']);
            }
            if (isset($seo_meta['focus_keyword'])) {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $seo_meta['focus_keyword']);
            }
        }

        // Rank Math integration
        if (defined('RANK_MATH_VERSION')) {
            if (isset($seo_meta['title'])) {
                update_post_meta($post_id, 'rank_math_title', $seo_meta['title']);
            }
            if (isset($seo_meta['description'])) {
                update_post_meta($post_id, 'rank_math_description', $seo_meta['description']);
            }
            if (isset($seo_meta['focus_keyword'])) {
                update_post_meta($post_id, 'rank_math_focus_keyword', $seo_meta['focus_keyword']);
            }
        }
    }

    /**
     * Update fetch analytics
     *
     * @param int $source_id Source ID
     * @param int $items_found Number of items found
     * @param int $items_created Number of items created
     */
    private static function update_fetch_analytics($source_id, $items_found, $items_created) {
        $analytics = get_option(MFW_ANALYTICS_OPTION, []);
        
        $analytics['total_fetches'] = ($analytics['total_fetches'] ?? 0) + 1;
        $analytics['total_posts_generated'] = ($analytics['total_posts_generated'] ?? 0) + $items_created;
        $analytics['last_fetch_date'] = current_time('timestamp');
        
        // Update source-specific stats
        $source_stats = get_post_meta($source_id, '_mfw_source_stats', true) ?: [];
        $source_stats['total_fetches'] = ($source_stats['total_fetches'] ?? 0) + 1;
        $source_stats['total_items_found'] = ($source_stats['total_items_found'] ?? 0) + $items_found;
        $source_stats['total_posts_created'] = ($source_stats['total_posts_created'] ?? 0) + $items_created;
        $source_stats['last_fetch'] = current_time('timestamp');
        
        update_option(MFW_ANALYTICS_OPTION, $analytics);
        update_post_meta($source_id, '_mfw_source_stats', $source_stats);
    }
}