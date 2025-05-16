<?php
/**
 * APIClient - AI Service API Communication System
 * Handles API requests, rate limiting, and response handling
 * 
 * @package MFW
 * @subpackage AI
 * @since 1.0.0
 */

namespace MFW\AI\AIService;

class APIClient {
    private $api_key;
    private $base_url;
    private $rate_limiter;
    private $retry_handler;
    private $settings;

    /**
     * Initialize API client
     */
    public function __construct() {
        $this->api_key = get_option('mfw_ai_api_key');
        $this->base_url = get_option('mfw_ai_api_url', 'https://api.mfw-ai.com/v1');
        $this->rate_limiter = new RateLimiter();
        $this->retry_handler = new RetryHandler();
        $this->settings = get_option('mfw_api_settings', []);
    }

    /**
     * Make API call with automatic retry and rate limiting
     */
    public function call($endpoint, $data = [], $options = []) {
        $this->rate_limiter->check_limits();

        $default_options = [
            'timeout' => 30,
            'max_retries' => 3,
            'retry_delay' => 1000,
            'priority' => 'normal'
        ];

        $options = wp_parse_args($options, $default_options);

        try {
            return $this->retry_handler->execute(function() use ($endpoint, $data, $options) {
                return $this->make_request($endpoint, $data, $options);
            }, $options);
        } catch (\Exception $e) {
            $this->log_error("API call failed: {$endpoint}", $e);
            throw $e;
        }
    }

    /**
     * Process text with specified model
     */
    public function process_text($text, $model, $options = []) {
        return $this->call('process', [
            'text' => $text,
            'model' => $model,
            'options' => $options
        ]);
    }

    /**
     * Make HTTP request to API
     */
    private function make_request($endpoint, $data, $options) {
        $url = $this->build_url($endpoint);
        $headers = $this->prepare_headers();
        
        $args = [
            'timeout' => $options['timeout'],
            'headers' => $headers,
            'body' => json_encode($data)
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $status = wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            throw new \Exception("API request failed with status: {$status}");
        }

        return $this->process_response($body);
    }

    /**
     * Build complete API URL
     */
    private function build_url($endpoint) {
        return trailingslashit($this->base_url) . ltrim($endpoint, '/');
    }

    /**
     * Prepare request headers
     */
    private function prepare_headers() {
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'MFW-AI-Client/1.0',
            'X-Request-ID' => $this->generate_request_id()
        ];
    }

    /**
     * Process API response
     */
    private function process_response($body) {
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from API');
        }

        if (isset($data['error'])) {
            throw new \Exception($data['error']['message']);
        }

        return $data;
    }

    /**
     * Generate unique request ID
     */
    private function generate_request_id() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Stream API response for large data
     */
    public function stream($endpoint, $data = [], $callback, $options = []) {
        $default_options = [
            'chunk_size' => 8192,
            'timeout' => 0,
            'keep_alive' => true
        ];

        $options = wp_parse_args($options, $default_options);

        try {
            $url = $this->build_url($endpoint);
            $headers = $this->prepare_headers();
            
            $stream = fopen($url, 'r', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $this->headers_to_string($headers),
                    'content' => json_encode($data),
                    'timeout' => $options['timeout']
                ]
            ]));

            if (!$stream) {
                throw new \Exception('Failed to open stream');
            }

            while (!feof($stream)) {
                $chunk = fread($stream, $options['chunk_size']);
                if ($chunk === false) {
                    throw new \Exception('Failed to read from stream');
                }
                $callback($chunk);
            }

            fclose($stream);
        } catch (\Exception $e) {
            $this->log_error('Stream request failed', $e);
            throw $e;
        }
    }

    /**
     * Convert headers array to string
     */
    private function headers_to_string($headers) {
        return implode("\r\n", array_map(
            function($k, $v) { return "$k: $v"; },
            array_keys($headers),
            $headers
        ));
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW APIClient Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}