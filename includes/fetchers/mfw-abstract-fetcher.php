<?php
/**
 * Abstract Fetcher Class
 *
 * Base class for all content fetchers.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Abstract_Fetcher {
    /**
     * Fetcher configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Current timestamp
     *
     * @var string
     */
    protected $current_time = '2025-05-13 17:03:10';

    /**
     * Current user
     *
     * @var string
     */
    protected $current_user = 'maziyarid';

    /**
     * Constructor
     *
     * @param array $config Fetcher configuration
     */
    public function __construct($config = []) {
        $this->config = wp_parse_args($config, $this->get_default_config());
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    protected function get_default_config() {
        return [
            'items_per_fetch' => 10,
            'source_language' => MFW_DEFAULT_SOURCE_LANG,
            'target_language' => MFW_DEFAULT_TARGET_LANG,
            'extract_full_text' => true,
            'filter_duplicates' => true,
            'min_content_length' => 100,
            'max_content_length' => 5000,
            'filter_keywords' => [],
            'excluded_keywords' => [],
            'categories' => [],
            'tags' => [],
            'post_status' => 'draft',
            'post_type' => 'post',
            'author_id' => 1
        ];
    }

    /**
     * Fetch content
     *
     * @return array Fetch results
     */
    abstract public function fetch();

    /**
     * Validate fetched item
     *
     * @param array $item Item to validate
     * @return bool Whether the item is valid
     */
    protected function validate_item($item) {
        // Check required fields
        if (empty($item['title']) || empty($item['content']) || empty($item['source_url'])) {
            return false;
        }

        // Check content length
        $content_length = str_word_count(strip_tags($item['content']));
        if ($content_length < $this->config['min_content_length'] || 
            $content_length > $this->config['max_content_length']) {
            return false;
        }

        // Check for duplicates if enabled
        if ($this->config['filter_duplicates'] && $this->is_duplicate($item)) {
            return false;
        }

        // Check keywords
        if (!empty($this->config['filter_keywords']) && 
            !$this->matches_keywords($item, $this->config['filter_keywords'])) {
            return false;
        }

        // Check excluded keywords
        if (!empty($this->config['excluded_keywords']) && 
            $this->matches_keywords($item, $this->config['excluded_keywords'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if item is a duplicate
     *
     * @param array $item Item to check
     * @return bool Whether the item is a duplicate
     */
    protected function is_duplicate($item) {
        global $wpdb;

        // Check by source URL
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mfw_original_url' 
            AND meta_value = %s 
            LIMIT 1",
            $item['source_url']
        ));

        if ($duplicate) {
            return true;
        }

        // Check by title similarity
        $similar_titles = $wpdb->get_col($wpdb->prepare(
            "SELECT post_title FROM {$wpdb->posts} 
            WHERE post_type = %s 
            AND post_status IN ('publish', 'draft', 'pending') 
            AND post_date >= DATE_SUB(%s, INTERVAL 30 DAY)",
            $this->config['post_type'],
            $this->current_time
        ));

        foreach ($similar_titles as $title) {
            if (similar_text($title, $item['title']) > 80) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if item matches keywords
     *
     * @param array $item Item to check
     * @param array $keywords Keywords to match
     * @return bool Whether the item matches keywords
     */
    protected function matches_keywords($item, $keywords) {
        $content = strtolower($item['title'] . ' ' . strip_tags($item['content']));
        
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            if (strpos($content, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract full text from URL
     *
     * @param string $url URL to extract from
     * @return string|false Extracted text or false on failure
     */
    protected function extract_full_text($url) {
        if (!$this->config['extract_full_text']) {
            return false;
        }

        try {
            // Initialize full text extractor
            $extractor = new MFW_Full_Text_Extractor();
            return $extractor->extract($url);
        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Full text extraction failed for URL %s: %s', $url, $e->getMessage()),
                'full_text_extraction'
            );
            return false;
        }
    }

    /**
     * Clean HTML content
     *
     * @param string $content Content to clean
     * @return string Cleaned content
     */
    protected function clean_content($content) {
        // Remove script and style tags
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);

        // Remove HTML comments
        $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Convert relative URLs to absolute
        if (!empty($this->config['base_url'])) {
            $content = $this->make_urls_absolute($content, $this->config['base_url']);
        }

        return $content;
    }

    /**
     * Make URLs absolute
     *
     * @param string $content Content containing URLs
     * @param string $base_url Base URL
     * @return string Content with absolute URLs
     */
    protected function make_urls_absolute($content, $base_url) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Fix links
        $links = $dom->getElementsByTagName('a');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href) {
                $absolute = $this->make_url_absolute($href, $base_url);
                $link->setAttribute('href', $absolute);
            }
        }

        // Fix images
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $image) {
            $src = $image->getAttribute('src');
            if ($src) {
                $absolute = $this->make_url_absolute($src, $base_url);
                $image->setAttribute('src', $absolute);
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Make single URL absolute
     *
     * @param string $url URL to make absolute
     * @param string $base_url Base URL
     * @return string Absolute URL
     */
    protected function make_url_absolute($url, $base_url) {
        // Already absolute
        if (parse_url($url, PHP_URL_SCHEME) !== null) {
            return $url;
        }

        // Remove anchor
        $url = preg_replace('/#.*$/', '', $url);

        if (strpos($url, '//') === 0) {
            // Protocol-relative URL
            return 'https:' . $url;
        }

        if ($url[0] === '/') {
            // Absolute path
            $parts = parse_url($base_url);
            return $parts['scheme'] . '://' . $parts['host'] . $url;
        }

        // Relative path
        return rtrim($base_url, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Log fetch attempt
     *
     * @param int $source_id Source ID
     * @param string $status Status of the fetch
     * @param int $items_found Number of items found
     * @param int $items_processed Number of items processed
     * @param string|null $error_message Error message if any
     */
    protected function log_fetch($source_id, $status, $items_found, $items_processed, $error_message = null) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mfw_fetch_logs',
            [
                'source_id' => $source_id,
                'source_type' => get_post_meta($source_id, '_mfw_source_type', true),
                'fetch_time' => $this->current_time,
                'items_found' => $items_found,
                'items_processed' => $items_processed,
                'status' => $status,
                'error_message' => $error_message
            ],
            ['%d', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }
}