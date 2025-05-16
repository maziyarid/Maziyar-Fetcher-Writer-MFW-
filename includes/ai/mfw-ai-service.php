<?php
/**
 * AIService - Core AI Processing System
 * Handles AI model integration and content processing
 * 
 * @package MFW
 * @subpackage AI
 * @since 1.0.0
 */

namespace MFW\AI;

class AIService {
    private $api_key;
    private $model_config;
    private $cache;
    private $rate_limiter;

    /**
     * Initialize AI service with configuration
     */
    public function __construct() {
        $this->api_key = get_option('mfw_ai_api_key');
        $this->model_config = $this->load_model_config();
        $this->cache = new AICache();
        $this->rate_limiter = new RateLimiter();
    }

    /**
     * Generate text content using AI
     */
    public function generate_text($prompt, $params = []) {
        $this->rate_limiter->check_limits();

        $cache_key = $this->generate_cache_key('text', $prompt, $params);
        if ($cached = $this->cache->get($cache_key)) {
            return $cached;
        }

        $default_params = [
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'frequency_penalty' => 0.3,
            'presence_penalty' => 0.3
        ];

        $params = wp_parse_args($params, $default_params);

        try {
            $response = $this->call_ai_api('text/generate', [
                'prompt' => $this->prepare_prompt($prompt),
                'params' => $params
            ]);

            $this->cache->set($cache_key, $response);
            return $response;
        } catch (\Exception $e) {
            $this->log_error('Text generation failed', $e);
            return false;
        }
    }

    /**
     * Generate image using AI
     */
    public function generate_image($prompt, $params = []) {
        $this->rate_limiter->check_limits();

        $cache_key = $this->generate_cache_key('image', $prompt, $params);
        if ($cached = $this->cache->get($cache_key)) {
            return $cached;
        }

        $default_params = [
            'size' => '1024x1024',
            'quality' => 'standard',
            'style' => 'natural',
            'format' => 'url'
        ];

        $params = wp_parse_args($params, $default_params);

        try {
            $response = $this->call_ai_api('image/generate', [
                'prompt' => $this->prepare_image_prompt($prompt),
                'params' => $params
            ]);

            $this->cache->set($cache_key, $response);
            return $response;
        } catch (\Exception $e) {
            $this->log_error('Image generation failed', $e);
            return false;
        }
    }

    /**
     * Analyze content and provide insights
     */
    public function analyze_content($content, $type = 'general') {
        $this->rate_limiter->check_limits();

        try {
            return $this->call_ai_api('content/analyze', [
                'content' => $content,
                'type' => $type,
                'include_metrics' => true
            ]);
        } catch (\Exception $e) {
            $this->log_error('Content analysis failed', $e);
            return false;
        }
    }

    /**
     * Generate SEO suggestions
     */
    public function generate_seo_suggestions($content) {
        $this->rate_limiter->check_limits();

        try {
            return $this->call_ai_api('seo/suggest', [
                'content' => $content,
                'include' => [
                    'keywords',
                    'meta_description',
                    'title_suggestions',
                    'content_improvements'
                ]
            ]);
        } catch (\Exception $e) {
            $this->log_error('SEO suggestion generation failed', $e);
            return false;
        }
    }

    /**
     * Generate content variations
     */
    public function generate_variations($content, $count = 3) {
        $this->rate_limiter->check_limits();

        try {
            return $this->call_ai_api('content/variations', [
                'content' => $content,
                'count' => $count,
                'preserve_key_points' => true
            ]);
        } catch (\Exception $e) {
            $this->log_error('Variation generation failed', $e);
            return false;
        }
    }

    /**
     * Process and enhance images
     */
    public function enhance_image($image_data, $enhancements = []) {
        $this->rate_limiter->check_limits();

        try {
            return $this->call_ai_api('image/enhance', [
                'image' => $image_data,
                'enhancements' => $enhancements
            ]);
        } catch (\Exception $e) {
            $this->log_error('Image enhancement failed', $e);
            return false;
        }
    }

    /**
     * Generate data visualizations
     */
    public function generate_visualization($data, $type = 'auto') {
        $this->rate_limiter->check_limits();

        try {
            return $this->call_ai_api('data/visualize', [
                'data' => $data,
                'type' => $type,
                'optimize_for' => 'readability'
            ]);
        } catch (\Exception $e) {
            $this->log_error('Visualization generation failed', $e);
            return false;
        }
    }

    /**
     * Prepare prompt for AI processing
     */
    private function prepare_prompt($prompt) {
        return [
            'base_prompt' => $prompt,
            'context' => $this->get_context(),
            'constraints' => $this->get_constraints(),
            'style_guide' => $this->get_style_guide()
        ];
    }

    /**
     * Generate cache key for results
     */
    private function generate_cache_key($type, $prompt, $params) {
        return md5($type . json_encode($prompt) . json_encode($params));
    }

    /**
     * Make API call to AI service
     */
    private function call_ai_api($endpoint, $data) {
        $url = $this->model_config['api_base_url'] . '/' . $endpoint;
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception($body['error']);
        }

        return $body;
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW AI Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}