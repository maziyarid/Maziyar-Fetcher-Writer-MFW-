<?php
/**
 * RSS Fetcher Class
 *
 * Handles fetching content from RSS feeds.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_RSS_Fetcher extends MFW_Abstract_Fetcher {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:05:02';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array_merge(parent::get_default_config(), [
            'feed_url' => '',
            'fetch_full_content' => true,
            'respect_ttl' => true,
            'update_interval' => 3600, // 1 hour in seconds
            'timeout' => 15,
            'user_agent' => 'MaziyarFetcherWriter/1.0',
            'verify_ssl' => true
        ]);
    }

    /**
     * Fetch content from RSS feed
     *
     * @return array Fetch results
     */
    public function fetch() {
        if (empty($this->config['feed_url'])) {
            throw new Exception(__('RSS feed URL is required.', 'mfw'));
        }

        try {
            // Check if feed needs updating
            if ($this->config['respect_ttl'] && !$this->should_update_feed()) {
                return [
                    'success' => true,
                    'items' => [],
                    'total_found' => 0,
                    'processed' => 0,
                    'message' => __('Feed is still within TTL period.', 'mfw')
                ];
            }

            // Fetch feed
            $response = wp_remote_get($this->config['feed_url'], [
                'timeout' => $this->config['timeout'],
                'user-agent' => $this->config['user_agent'],
                'sslverify' => $this->config['verify_ssl']
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                throw new Exception(__('Empty response from RSS feed.', 'mfw'));
            }

            // Parse feed
            libxml_use_internal_errors(true);
            $feed = simplexml_load_string($body);
            if (!$feed) {
                throw new Exception(__('Invalid RSS feed format.', 'mfw'));
            }

            // Determine feed type (RSS or Atom)
            $feed_type = $this->determine_feed_type($feed);
            
            // Process items
            $items = [];
            $processed_count = 0;
            $total_items = $this->get_feed_item_count($feed, $feed_type);

            foreach ($this->get_feed_items($feed, $feed_type) as $item) {
                if (count($items) >= $this->config['items_per_fetch']) {
                    break;
                }

                $processed_item = $this->process_item($item, $feed_type);
                if ($processed_item && $this->validate_item($processed_item)) {
                    $items[] = $processed_item;
                    $processed_count++;
                }
            }

            // Update feed metadata
            $this->update_feed_metadata($feed, $feed_type);

            // Log successful fetch
            $this->log_fetch(
                $this->config['source_id'],
                'success',
                $total_items,
                $processed_count
            );

            return [
                'success' => true,
                'items' => $items,
                'total_found' => $total_items,
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
     * Determine feed type
     *
     * @param SimpleXMLElement $feed Feed XML
     * @return string Feed type ('rss' or 'atom')
     */
    private function determine_feed_type($feed) {
        if ($feed->channel) {
            return 'rss';
        } elseif ($feed->entry) {
            return 'atom';
        }
        throw new Exception(__('Unknown feed format.', 'mfw'));
    }

    /**
     * Get feed items
     *
     * @param SimpleXMLElement $feed Feed XML
     * @param string $feed_type Feed type
     * @return array Feed items
     */
    private function get_feed_items($feed, $feed_type) {
        if ($feed_type === 'rss') {
            return $feed->channel->item;
        }
        return $feed->entry;
    }

    /**
     * Get total number of items in feed
     *
     * @param SimpleXMLElement $feed Feed XML
     * @param string $feed_type Feed type
     * @return int Number of items
     */
    private function get_feed_item_count($feed, $feed_type) {
        if ($feed_type === 'rss') {
            return count($feed->channel->item);
        }
        return count($feed->entry);
    }

    /**
     * Process single feed item
     *
     * @param SimpleXMLElement $item Feed item
     * @param string $feed_type Feed type
     * @return array|false Processed item or false on failure
     */
    private function process_item($item, $feed_type) {
        try {
            if ($feed_type === 'rss') {
                return $this->process_rss_item($item);
            }
            return $this->process_atom_item($item);
        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process feed item: %s', $e->getMessage()),
                'rss'
            );
            return false;
        }
    }

    /**
     * Process RSS item
     *
     * @param SimpleXMLElement $item RSS item
     * @return array Processed item
     */
    private function process_rss_item($item) {
        $title = (string)$item->title;
        $link = (string)$item->link;
        $guid = (string)$item->guid;
        $pubDate = strtotime((string)$item->pubDate);

        // Get content
        $content = '';
        if ($this->config['fetch_full_content']) {
            $content = $this->extract_full_text($link);
        }
        
        if (!$content) {
            $content = (string)($item->children('content', true)->encoded ?? 
                      $item->description ?? 
                      $item->children('excerpt', true)->encoded ?? '');
        }

        // Clean content
        $content = $this->clean_content($content);

        return [
            'title' => $title,
            'content' => $content,
            'source_url' => $link,
            'guid' => $guid ?: $link,
            'publish_date' => date('Y-m-d H:i:s', $pubDate),
            'author' => (string)($item->children('dc', true)->creator ?? $item->author ?? ''),
            'categories' => $this->extract_rss_categories($item),
            'image_url' => $this->extract_image($content),
            'language' => $this->detect_language($title . ' ' . $content)
        ];
    }

    /**
     * Process Atom item
     *
     * @param SimpleXMLElement $item Atom entry
     * @return array Processed item
     */
    private function process_atom_item($item) {
        $title = (string)$item->title;
        $link = '';
        
        foreach ($item->link as $l) {
            if ((string)$l['rel'] === 'alternate' || !$l['rel']) {
                $link = (string)$l['href'];
                break;
            }
        }

        $pubDate = strtotime((string)$item->published ?? (string)$item->updated);

        // Get content
        $content = '';
        if ($this->config['fetch_full_content']) {
            $content = $this->extract_full_text($link);
        }
        
        if (!$content) {
            $content = (string)($item->content ?? $item->summary ?? '');
        }

        // Clean content
        $content = $this->clean_content($content);

        return [
            'title' => $title,
            'content' => $content,
            'source_url' => $link,
            'guid' => (string)$item->id,
            'publish_date' => date('Y-m-d H:i:s', $pubDate),
            'author' => (string)($item->author->name ?? ''),
            'categories' => $this->extract_atom_categories($item),
            'image_url' => $this->extract_image($content),
            'language' => $this->detect_language($title . ' ' . $content)
        ];
    }

    /**
     * Extract categories from RSS item
     *
     * @param SimpleXMLElement $item RSS item
     * @return array Categories
     */
    private function extract_rss_categories($item) {
        $categories = [];
        
        if ($item->category) {
            foreach ($item->category as $category) {
                $categories[] = (string)$category;
            }
        }

        // Check for Dublin Core subjects
        if ($item->children('dc', true)->subject) {
            foreach ($item->children('dc', true)->subject as $subject) {
                $categories[] = (string)$subject;
            }
        }

        return array_unique(array_merge($categories, $this->config['categories']));
    }

    /**
     * Extract categories from Atom entry
     *
     * @param SimpleXMLElement $item Atom entry
     * @return array Categories
     */
    private function extract_atom_categories($item) {
        $categories = [];
        
        if ($item->category) {
            foreach ($item->category as $category) {
                $categories[] = (string)($category['term'] ?? $category['label'] ?? '');
            }
        }

        return array_unique(array_merge($categories, $this->config['categories']));
    }

    /**
     * Check if feed needs updating
     *
     * @return bool Whether feed should be updated
     */
    private function should_update_feed() {
        $last_fetch = get_post_meta($this->config['source_id'], '_mfw_last_fetch', true);
        
        if (!$last_fetch) {
            return true;
        }

        $interval = $this->get_feed_ttl();
        return (strtotime($this->current_time) - $last_fetch) >= $interval;
    }

    /**
     * Get feed TTL (Time To Live)
     *
     * @return int TTL in seconds
     */
    private function get_feed_ttl() {
        // Use configured update interval as default
        $ttl = $this->config['update_interval'];

        // Try to get TTL from feed metadata
        $meta = get_post_meta($this->config['source_id'], '_mfw_feed_meta', true);
        
        if (!empty($meta['ttl'])) {
            $ttl = intval($meta['ttl']) * 60; // Convert minutes to seconds
        } elseif (!empty($meta['sy_updateperiod']) && !empty($meta['sy_updatefrequency'])) {
            // Handle syndication module update settings
            $period = $meta['sy_updateperiod'];
            $frequency = intval($meta['sy_updatefrequency']);
            
            switch ($period) {
                case 'hourly':
                    $ttl = 3600 / $frequency;
                    break;
                case 'daily':
                    $ttl = 86400 / $frequency;
                    break;
                case 'weekly':
                    $ttl = 604800 / $frequency;
                    break;
                case 'monthly':
                    $ttl = 2592000 / $frequency;
                    break;
                case 'yearly':
                    $ttl = 31536000 / $frequency;
                    break;
            }
        }

        return max($ttl, 300); // Minimum 5 minutes
    }

    /**
     * Update feed metadata
     *
     * @param SimpleXMLElement $feed Feed XML
     * @param string $feed_type Feed type
     */
    private function update_feed_metadata($feed, $feed_type) {
        $meta = [];

        if ($feed_type === 'rss') {
            $channel = $feed->channel;
            
            // Basic metadata
            $meta['title'] = (string)$channel->title;
            $meta['description'] = (string)$channel->description;
            $meta['language'] = (string)$channel->language;
            
            // TTL
            if ($channel->ttl) {
                $meta['ttl'] = (int)$channel->ttl;
            }
            
            // Syndication module
            $sy = $channel->children('sy', true);
            if ($sy) {
                $meta['sy_updateperiod'] = (string)$sy->updatePeriod;
                $meta['sy_updatefrequency'] = (int)$sy->updateFrequency;
            }
        } else {
            // Atom metadata
            $meta['title'] = (string)$feed->title;
            $meta['subtitle'] = (string)$feed->subtitle;
            $meta['updated'] = (string)$feed->updated;
        }

        update_post_meta($this->config['source_id'], '_mfw_feed_meta', $meta);
        update_post_meta($this->config['source_id'], '_mfw_last_fetch', strtotime($this->current_time));
    }
}