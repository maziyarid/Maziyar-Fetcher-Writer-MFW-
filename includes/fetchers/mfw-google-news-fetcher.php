<?php
/**
 * Google News Fetcher Class
 *
 * Handles fetching content from Google News.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Google_News_Fetcher extends MFW_Abstract_Fetcher {
    /**
     * Google News API endpoint
     *
     * @var string
     */
    private $api_endpoint = 'https://news.google.com/rss/search';

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array_merge(parent::get_default_config(), [
            'query' => '',
            'country' => 'US',
            'language' => 'en',
            'sort_by' => 'date', // 'date', 'relevance'
            'time_range' => '7d', // '1h', '1d', '7d', '1m', '1y'
        ]);
    }

    /**
     * Fetch content from Google News
     *
     * @return array Fetch results
     */
    public function fetch() {
        if (empty($this->config['query'])) {
            throw new Exception(__('Search query is required for Google News fetching.', 'mfw'));
        }

        try {
            // Build request URL
            $url = add_query_arg([
                'q' => urlencode($this->config['query']),
                'hl' => $this->config['language'],
                'gl' => $this->config['country'],
                'ceid' => $this->config['country'] . ':' . $this->config['language']
            ], $this->api_endpoint);

            // Fetch RSS feed
            $response = wp_remote_get($url, [
                'timeout' => 15,
                'user-agent' => 'Mozilla/5.0 (compatible; MaziyarFetcherWriter/1.0; +http://example.com)'
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                throw new Exception(__('Empty response from Google News.', 'mfw'));
            }

            // Parse RSS feed
            libxml_use_internal_errors(true);
            $rss = simplexml_load_string($body);
            if (!$rss) {
                throw new Exception(__('Invalid RSS feed from Google News.', 'mfw'));
            }

            $items = [];
            $processed_count = 0;

            foreach ($rss->channel->item as $item) {
                if (count($items) >= $this->config['items_per_fetch']) {
                    break;
                }

                // Process item
                $processed_item = $this->process_item($item);
                if ($processed_item && $this->validate_item($processed_item)) {
                    $items[] = $processed_item;
                    $processed_count++;
                }
            }

            // Log successful fetch
            $this->log_fetch(
                $this->config['source_id'],
                'success',
                count($rss->channel->item),
                $processed_count
            );

            return [
                'success' => true,
                'items' => $items,
                'total_found' => count($rss->channel->item),
                'processed' => $processed_count
            ];

        } catch (Exception $e) {
            // Log failed fetch
            $this->log_fetch(
                $this->config['source_id'],
                'error',
                0,
                0,
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Process single RSS item
     *
     * @param SimpleXMLElement $item RSS item
     * @return array|false Processed item or false on failure
     */
    private function process_item($item) {
        try {
            $title = (string)$item->title;
            $link = (string)$item->link;
            $pubDate = strtotime((string)$item->pubDate);
            
            // Skip old items based on time range
            if (!$this->is_within_time_range($pubDate)) {
                return false;
            }

            // Extract full content if enabled
            $content = $this->extract_full_text($link);
            if (!$content) {
                $content = (string)$item->description;
            }

            // Clean content
            $content = $this->clean_content($content);

            return [
                'title' => $title,
                'content' => $content,
                'source_url' => $link,
                'publish_date' => date('Y-m-d H:i:s', $pubDate),
                'author' => (string)$item->author ?: __('Google News', 'mfw'),
                'categories' => $this->extract_categories($item),
                'image_url' => $this->extract_image($content),
                'language' => $this->detect_language($title . ' ' . $content)
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process Google News item: %s', $e->getMessage()),
                'google_news'
            );
            return false;
        }
    }

    /**
     * Check if date is within configured time range
     *
     * @param int $timestamp Timestamp to check
     * @return bool Whether timestamp is within range
     */
    private function is_within_time_range($timestamp) {
        $current = strtotime($this->current_time);
        
        switch ($this->config['time_range']) {
            case '1h':
                return ($current - $timestamp) <= 3600;
            case '1d':
                return ($current - $timestamp) <= 86400;
            case '7d':
                return ($current - $timestamp) <= 604800;
            case '1m':
                return ($current - $timestamp) <= 2592000;
            case '1y':
                return ($current - $timestamp) <= 31536000;
            default:
                return true;
        }
    }

    /**
     * Extract categories from item
     *
     * @param SimpleXMLElement $item RSS item
     * @return array Categories
     */
    private function extract_categories($item) {
        $categories = [];
        
        if ($item->category) {
            foreach ($item->category as $category) {
                $categories[] = (string)$category;
            }
        }

        return array_unique(array_merge($categories, $this->config['categories']));
    }

    /**
     * Extract main image from content
     *
     * @param string $content HTML content
     * @return string|null Image URL or null if not found
     */
    private function extract_image($content) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $image) {
            $src = $image->getAttribute('src');
            if ($src && filter_var($src, FILTER_VALIDATE_URL)) {
                return $src;
            }
        }

        return null;
    }

    /**
     * Detect content language
     *
     * @param string $text Text to analyze
     * @return string Language code
     */
    private function detect_language($text) {
        // Use configured language as fallback
        return $this->config['language'];
    }
}