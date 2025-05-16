<?php
/**
 * Content Processor Class
 *
 * Handles content processing, rewriting, and enhancement using AI services.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Content_Processor {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:09:44';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings;

    /**
     * AI service instance
     *
     * @var MFW_Gemini_Service|MFW_Deepseek_Service
     */
    private $ai_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option(MFW_SETTINGS_OPTION, []);
        $this->initialize_ai_service();
    }

    /**
     * Initialize AI service
     */
    private function initialize_ai_service() {
        $service_type = $this->settings['default_ai_service'] ?? MFW_AI_GEMINI;
        
        switch ($service_type) {
            case MFW_AI_DEEPSEEK:
                $this->ai_service = new MFW_Deepseek_Service();
                break;
            default:
                $this->ai_service = new MFW_Gemini_Service();
                break;
        }
    }

    /**
     * Process multiple content items
     *
     * @param array $items Content items to process
     * @param array $config Processing configuration
     * @return array Processed items
     */
    public function process_items($items, $config = []) {
        $processed_items = [];

        foreach ($items as $item) {
            try {
                $processed_item = $this->process_item($item, $config);
                if ($processed_item) {
                    $processed_items[] = $processed_item;
                }
            } catch (Exception $e) {
                MFW_Error_Logger::log(
                    sprintf('Failed to process item: %s', $e->getMessage()),
                    'content_processor'
                );
                continue;
            }
        }

        return $processed_items;
    }

    /**
     * Process single content item
     *
     * @param array $item Content item
     * @param array $config Processing configuration
     * @return array|false Processed item or false on failure
     */
    public function process_item($item, $config = []) {
        try {
            // Merge config with defaults
            $config = wp_parse_args($config, [
                'source_language' => $this->settings['default_source_language'] ?? MFW_DEFAULT_SOURCE_LANG,
                'target_language' => $this->settings['default_target_language'] ?? MFW_DEFAULT_TARGET_LANG,
                'content_length' => $this->settings['default_content_length'] ?? MFW_DEFAULT_CONTENT_LENGTH,
                'maintain_keywords' => true,
                'enhance_seo' => true,
                'generate_images' => $this->settings['auto_generate_image'] ?? false
            ]);

            // Process title
            $title = $this->process_title($item['title'], $config);

            // Process content
            $content = $this->process_content($item['content'], $config);

            // Generate meta description
            $meta_description = '';
            if ($config['enhance_seo']) {
                $meta_description = $this->generate_meta_description($content);
            }

            // Generate image if needed
            $image_url = $item['image_url'] ?? '';
            if ($config['generate_images'] && empty($image_url)) {
                $image_url = $this->generate_featured_image($title, $content);
            }

            // Extract or generate keywords/tags
            $tags = $this->extract_keywords($content, $item['tags'] ?? []);

            // Prepare processed item
            $processed_item = [
                'title' => $title,
                'content' => $content,
                'source_url' => $item['source_url'],
                'publish_date' => $item['publish_date'] ?? $this->current_time,
                'author' => $item['author'] ?? $this->current_user,
                'image_url' => $image_url,
                'tags' => $tags,
                'seo_meta' => [
                    'title' => $this->generate_seo_title($title),
                    'description' => $meta_description,
                    'focus_keyword' => $tags[0] ?? ''
                ]
            ];

            // Add schema markup if enabled
            if ($config['enhance_seo']) {
                $processed_item['schema_markup'] = $this->generate_schema_markup($processed_item);
            }

            return $processed_item;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process item: %s', $e->getMessage()),
                'content_processor'
            );
            return false;
        }
    }

    /**
     * Process title using AI
     *
     * @param string $title Original title
     * @param array $config Processing configuration
     * @return string Processed title
     */
    public function process_title($title, $config) {
        $prompt = sprintf(
            'Rewrite the following title in %s, maintaining its meaning but making it more engaging and SEO-friendly: "%s"',
            $config['target_language'],
            $title
        );

        try {
            $response = $this->ai_service->generate_text($prompt);
            return !empty($response) ? $response : $title;
        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process title: %s', $e->getMessage()),
                'content_processor'
            );
            return $title;
        }
    }

    /**
     * Process content using AI
     *
     * @param string $content Original content
     * @param array $config Processing configuration
     * @return string Processed content
     */
    public function process_content($content, $config) {
        // Clean content first
        $content = $this->clean_content($content);

        // Translate if needed
        if ($config['source_language'] !== $config['target_language']) {
            $content = $this->translate_content($content, $config);
        }

        // Generate enhanced content
        $prompt = $this->build_content_prompt($content, $config);

        try {
            $response = $this->ai_service->generate_text($prompt);
            if (!empty($response)) {
                $content = $response;
            }
        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to enhance content: %s', $e->getMessage()),
                'content_processor'
            );
        }

        // Post-process content
        $content = $this->post_process_content($content);

        return $content;
    }

    /**
     * Clean content
     *
     * @param string $content Content to clean
     * @return string Cleaned content
     */
    private function clean_content($content) {
        // Remove script and style tags
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);

        // Remove HTML comments
        $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);

        // Convert special characters
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return $content;
    }

    /**
     * Translate content
     *
     * @param string $content Content to translate
     * @param array $config Translation configuration
     * @return string Translated content
     */
    private function translate_content($content, $config) {
        $prompt = sprintf(
            'Translate the following text from %s to %s, maintaining its meaning and style: %s',
            $config['source_language'],
            $config['target_language'],
            $content
        );

        try {
            $response = $this->ai_service->generate_text($prompt);
            return !empty($response) ? $response : $content;
        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Translation failed: %s', $e->getMessage()),
                'content_processor'
            );
            return $content;
        }
    }

    /**
     * Build content enhancement prompt
     *
     * @param string $content Original content
     * @param array $config Configuration
     * @return string AI prompt
     */
    private function build_content_prompt($content, $config) {
        return sprintf(
            'Rewrite and enhance the following content in %s. ' .
            'Make it engaging, informative, and approximately %d words long. ' .
            'Maintain key information and facts, but improve readability and flow: %s',
            $config['target_language'],
            $config['content_length'],
            $content
        );
    }

    /**
     * Post-process content
     *
     * @param string $content Content to process
     * @return string Processed content
     */
    private function post_process_content($content) {
        // Add paragraph tags if needed
        if (strpos($content, '<p>') === false) {
            $content = wpautop($content);
        }

        // Add heading tags for structure
        $content = $this->add_content_structure($content);

        return $content;
    }

    /**
     * Add content structure (headings)
     *
     * @param string $content Content to structure
     * @return string Structured content
     */
    private function add_content_structure($content) {
        // Implementation for adding headings and structure
        return $content;
    }

    /**
     * Generate meta description
     *
     * @param string $content Content to summarize
     * @return string Meta description
     */
    private function generate_meta_description($content) {
        $prompt = sprintf(
            'Generate a compelling meta description (maximum 160 characters) for the following content: %s',
            wp_strip_all_tags($content)
        );

        try {
            $response = $this->ai_service->generate_text($prompt);
            return substr($response, 0, 160);
        } catch (Exception $e) {
            return substr(wp_strip_all_tags($content), 0, 160);
        }
    }

    /**
     * Generate SEO title
     *
     * @param string $title Original title
     * @return string SEO optimized title
     */
    private function generate_seo_title($title) {
        // Keep original title if it's already good
        if (strlen($title) <= 60) {
            return $title;
        }

        // Try to generate a shorter version
        $prompt = sprintf(
            'Generate a shorter, SEO-friendly title (maximum 60 characters) that maintains the meaning of: %s',
            $title
        );

        try {
            $response = $this->ai_service->generate_text($prompt);
            return !empty($response) ? $response : substr($title, 0, 60);
        } catch (Exception $e) {
            return substr($title, 0, 60);
        }
    }

    /**
     * Extract keywords from content
     *
     * @param string $content Content to analyze
     * @param array $existing_tags Existing tags
     * @return array Keywords/tags
     */
    private function extract_keywords($content, $existing_tags = []) {
        $prompt = sprintf(
            'Extract 5-7 relevant keywords or phrases from the following content, suitable for use as tags: %s',
            wp_strip_all_tags($content)
        );

        try {
            $response = $this->ai_service->generate_text($prompt);
            $keywords = array_map('trim', explode(',', $response));
            return array_unique(array_merge($keywords, $existing_tags));
        } catch (Exception $e) {
            return $existing_tags;
        }
    }

    /**
     * Generate schema markup
     *
     * @param array $item Processed item data
     * @return array Schema markup
     */
    private function generate_schema_markup($item) {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $item['title'],
            'description' => $item['seo_meta']['description'],
            'image' => $item['image_url'],
            'datePublished' => $item['publish_date'],
            'dateModified' => $this->current_time,
            'author' => [
                '@type' => 'Person',
                'name' => $item['author']
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url()
                ]
            ]
        ];
    }
}