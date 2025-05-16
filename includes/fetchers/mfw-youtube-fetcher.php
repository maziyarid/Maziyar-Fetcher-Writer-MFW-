<?php
/**
 * YouTube Fetcher Class
 *
 * Handles fetching content from YouTube channels and playlists.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Youtube_Fetcher extends MFW_Abstract_Fetcher {
    /**
     * YouTube Data API endpoint
     *
     * @var string
     */
    private $api_endpoint = 'https://www.googleapis.com/youtube/v3';

    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:06:28';

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
            'api_key' => '',
            'channel_id' => '',
            'playlist_id' => '',
            'search_query' => '',
            'fetch_type' => 'channel', // channel, playlist, search
            'content_type' => 'video',  // video, playlist, channel
            'order' => 'date',          // date, rating, relevance, title, videoCount, viewCount
            'max_results' => 10,
            'include_description' => true,
            'include_transcript' => true,
            'embed_video' => true,
            'embed_width' => 800,
            'embed_height' => 450,
            'video_template' => '<div class="mfw-youtube-video">
                <h2>{title}</h2>
                <div class="video-wrapper">
                    {embed}
                </div>
                <div class="video-meta">
                    <p class="publish-date">{publish_date}</p>
                    <p class="view-count">{view_count} views</p>
                </div>
                <div class="video-description">
                    {description}
                </div>
            </div>'
        ]);
    }

    /**
     * Fetch content from YouTube
     *
     * @return array Fetch results
     */
    public function fetch() {
        if (empty($this->config['api_key'])) {
            throw new Exception(__('YouTube API key is required.', 'mfw'));
        }

        try {
            $items = [];
            $processed_count = 0;
            $total_items = 0;

            switch ($this->config['fetch_type']) {
                case 'channel':
                    if (empty($this->config['channel_id'])) {
                        throw new Exception(__('Channel ID is required.', 'mfw'));
                    }
                    $response = $this->fetch_channel_content();
                    break;

                case 'playlist':
                    if (empty($this->config['playlist_id'])) {
                        throw new Exception(__('Playlist ID is required.', 'mfw'));
                    }
                    $response = $this->fetch_playlist_content();
                    break;

                case 'search':
                    if (empty($this->config['search_query'])) {
                        throw new Exception(__('Search query is required.', 'mfw'));
                    }
                    $response = $this->fetch_search_results();
                    break;

                default:
                    throw new Exception(__('Invalid fetch type.', 'mfw'));
            }

            if (!empty($response['items'])) {
                $total_items = $response['pageInfo']['totalResults'] ?? count($response['items']);

                foreach ($response['items'] as $item) {
                    if (count($items) >= $this->config['items_per_fetch']) {
                        break;
                    }

                    $processed_item = $this->process_item($item);
                    if ($processed_item && $this->validate_item($processed_item)) {
                        $items[] = $processed_item;
                        $processed_count++;
                    }
                }
            }

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
     * Fetch channel content
     *
     * @return array API response
     */
    private function fetch_channel_content() {
        // First get upload playlist ID
        $channel_response = $this->make_api_request('channels', [
            'id' => $this->config['channel_id'],
            'part' => 'contentDetails'
        ]);

        if (empty($channel_response['items'])) {
            throw new Exception(__('Channel not found.', 'mfw'));
        }

        $uploads_playlist_id = $channel_response['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

        // Then get videos from upload playlist
        return $this->make_api_request('playlistItems', [
            'playlistId' => $uploads_playlist_id,
            'part' => 'snippet,contentDetails',
            'maxResults' => $this->config['max_results']
        ]);
    }

    /**
     * Fetch playlist content
     *
     * @return array API response
     */
    private function fetch_playlist_content() {
        return $this->make_api_request('playlistItems', [
            'playlistId' => $this->config['playlist_id'],
            'part' => 'snippet,contentDetails',
            'maxResults' => $this->config['max_results']
        ]);
    }

    /**
     * Fetch search results
     *
     * @return array API response
     */
    private function fetch_search_results() {
        $params = [
            'q' => $this->config['search_query'],
            'part' => 'snippet',
            'type' => $this->config['content_type'],
            'order' => $this->config['order'],
            'maxResults' => $this->config['max_results']
        ];

        if ($this->config['channel_id']) {
            $params['channelId'] = $this->config['channel_id'];
        }

        return $this->make_api_request('search', $params);
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array API response
     */
    private function make_api_request($endpoint, $params) {
        $params['key'] = $this->config['api_key'];
        
        $url = add_query_arg($params, "{$this->api_endpoint}/{$endpoint}");
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            throw new Exception(__('Invalid response from YouTube API.', 'mfw'));
        }

        if (!empty($data['error'])) {
            throw new Exception($data['error']['message']);
        }

        return $data;
    }

    /**
     * Process YouTube item
     *
     * @param array $item YouTube API item
     * @return array|false Processed item or false on failure
     */
    private function process_item($item) {
        try {
            $snippet = $item['snippet'];
            $video_id = $item['contentDetails']['videoId'] ?? 
                       $item['id']['videoId'] ?? 
                       null;

            if (!$video_id) {
                return false;
            }

            // Get additional video details if needed
            $video_details = null;
            if ($this->config['include_description'] || $this->config['include_transcript']) {
                $video_details = $this->get_video_details($video_id);
            }

            // Build content
            $content = $this->build_content(
                $video_id,
                $snippet,
                $video_details
            );

            return [
                'title' => $snippet['title'],
                'content' => $content,
                'source_url' => "https://www.youtube.com/watch?v={$video_id}",
                'publish_date' => date('Y-m-d H:i:s', strtotime($snippet['publishedAt'])),
                'author' => $snippet['channelTitle'],
                'image_url' => $this->get_best_thumbnail($snippet['thumbnails']),
                'video_id' => $video_id,
                'channel_id' => $snippet['channelId'],
                'categories' => $this->config['categories'],
                'language' => $snippet['defaultLanguage'] ?? $this->config['source_language']
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process YouTube item: %s', $e->getMessage()),
                'youtube'
            );
            return false;
        }
    }

    /**
     * Get video details
     *
     * @param string $video_id Video ID
     * @return array Video details
     */
    private function get_video_details($video_id) {
        $response = $this->make_api_request('videos', [
            'id' => $video_id,
            'part' => 'snippet,statistics,contentDetails'
        ]);

        return !empty($response['items']) ? $response['items'][0] : null;
    }

    /**
     * Build content for video
     *
     * @param string $video_id Video ID
     * @param array $snippet Video snippet
     * @param array|null $details Additional video details
     * @return string Formatted content
     */
    private function build_content($video_id, $snippet, $details = null) {
        $content = $this->config['video_template'];
        
        // Replace template variables
        $replacements = [
            '{title}' => $snippet['title'],
            '{embed}' => $this->config['embed_video'] ? $this->get_embed_code($video_id) : '',
            '{description}' => $this->config['include_description'] ? $snippet['description'] : '',
            '{publish_date}' => date_i18n(
                get_option('date_format'),
                strtotime($snippet['publishedAt'])
            ),
            '{view_count}' => $details ? number_format_i18n($details['statistics']['viewCount']) : '0'
        ];

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );

        // Add transcript if enabled and available
        if ($this->config['include_transcript'] && $details) {
            $transcript = $this->get_video_transcript($video_id);
            if ($transcript) {
                $content .= sprintf(
                    '<div class="video-transcript"><h3>%s</h3>%s</div>',
                    __('Transcript', 'mfw'),
                    $transcript
                );
            }
        }

        return $content;
    }

    /**
     * Get video embed code
     *
     * @param string $video_id Video ID
     * @return string Embed code
     */
    private function get_embed_code($video_id) {
        return sprintf(
            '<iframe width="%d" height="%d" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe>',
            $this->config['embed_width'],
            $this->config['embed_height'],
            esc_attr($video_id)
        );
    }

    /**
     * Get best quality thumbnail
     *
     * @param array $thumbnails Available thumbnails
     * @return string Thumbnail URL
     */
    private function get_best_thumbnail($thumbnails) {
        $priorities = ['maxres', 'standard', 'high', 'medium', 'default'];
        
        foreach ($priorities as $size) {
            if (!empty($thumbnails[$size]['url'])) {
                return $thumbnails[$size]['url'];
            }
        }

        return '';
    }

    /**
     * Get video transcript
     *
     * @param string $video_id Video ID
     * @return string|false Transcript or false if not available
     */
    private function get_video_transcript($video_id) {
        // This would require additional implementation using the YouTube Captions API
        // or a third-party service for transcript retrieval
        return false;
    }
}