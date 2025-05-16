<?php
/**
 * DeepSeek AI Service Class
 *
 * Handles interaction with DeepSeek's AI API.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Deepseek_Service implements MFW_AI_Service_Interface {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:12:42';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * API endpoint
     *
     * @var string
     */
    private const API_ENDPOINT = 'https://api.deepseek.com/v1';

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option(MFW_SETTINGS_OPTION, []);
        $this->api_key = $settings['deepseek_api_key'] ?? '';

        if (empty($this->api_key)) {
            throw new Exception(__('DeepSeek API key is not configured.', 'mfw'));
        }
    }

    /**
     * Generate text using DeepSeek AI
     *
     * @param string $prompt Text generation prompt
     * @param array $options Generation options
     * @return string Generated text
     */
    public function generate_text($prompt, $options = []) {
        try {
            // Merge options with defaults
            $options = wp_parse_args($options, [
                'model' => 'deepseek-chat',
                'temperature' => 0.7,
                'max_tokens' => 1024,
                'top_p' => 0.95,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
                'stop' => []
            ]);

            // Prepare request data
            $data = [
                'model' => $options['model'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => $options['temperature'],
                'max_tokens' => $options['max_tokens'],
                'top_p' => $options['top_p'],
                'frequency_penalty' => $options['frequency_penalty'],
                'presence_penalty' => $options['presence_penalty'],
                'stop' => $options['stop']
            ];

            // Make API request
            $response = wp_remote_post(
                self::API_ENDPOINT . '/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($data),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (empty($body['choices'][0]['message']['content'])) {
                throw new Exception(__('Invalid response from DeepSeek API.', 'mfw'));
            }

            // Log API usage
            $this->log_api_usage('text', strlen($prompt));

            return $body['choices'][0]['message']['content'];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('DeepSeek text generation failed: %s', $e->getMessage()),
                'deepseek'
            );
            throw $e;
        }
    }

    /**
     * Generate image using DeepSeek AI
     *
     * @param string $prompt Image generation prompt
     * @param array $options Generation options
     * @return array Generation result
     */
    public function generate_image($prompt, $options = []) {
        try {
            // Merge options with defaults
            $options = wp_parse_args($options, [
                'model' => 'deepseek-vision',
                'size' => '1024x1024',
                'quality' => 'standard',
                'style' => 'natural',
                'n' => 1
            ]);

            // Prepare request data
            $data = [
                'model' => $options['model'],
                'prompt' => $prompt,
                'size' => $options['size'],
                'quality' => $options['quality'],
                'style' => $options['style'],
                'n' => $options['n']
            ];

            // Make API request
            $response = wp_remote_post(
                self::API_ENDPOINT . '/images/generations',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($data),
                    'timeout' => 60
                ]
            );

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (empty($body['data'][0]['url'])) {
                throw new Exception(__('Invalid response from DeepSeek API.', 'mfw'));
            }

            // Download the generated image
            $image_response = wp_remote_get($body['data'][0]['url']);
            if (is_wp_error($image_response)) {
                throw new Exception($image_response->get_error_message());
            }

            // Log API usage
            $this->log_api_usage('image', strlen($prompt));

            return [
                'success' => true,
                'image_data' => wp_remote_retrieve_body($image_response)
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('DeepSeek image generation failed: %s', $e->getMessage()),
                'deepseek'
            );
            throw $e;
        }
    }

    /**
     * Analyze sentiment of text
     *
     * @param string $text Text to analyze
     * @return array Sentiment analysis result
     */
    public function analyze_sentiment($text) {
        try {
            $prompt = sprintf(
                "Analyze the sentiment of the following text and provide a JSON response with 'score' (between -1 and 1) and 'magnitude' (>= 0): %s",
                $text
            );

            $response = $this->generate_text($prompt);
            $result = json_decode($response, true);

            if (!isset($result['score']) || !isset($result['magnitude'])) {
                throw new Exception(__('Invalid sentiment analysis response.', 'mfw'));
            }

            return $result;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Sentiment analysis failed: %s', $e->getMessage()),
                'deepseek'
            );
            throw $e;
        }
    }

    /**
     * Classify content
     *
     * @param string $text Text to classify
     * @param array $categories Available categories
     * @return array Classification results
     */
    public function classify_content($text, $categories) {
        try {
            $prompt = sprintf(
                "Classify the following text into these categories (%s). Return a JSON array of matching categories: %s",
                implode(', ', $categories),
                $text
            );

            $response = $this->generate_text($prompt);
            $result = json_decode($response, true);

            if (!is_array($result)) {
                throw new Exception(__('Invalid classification response.', 'mfw'));
            }

            return array_intersect($result, $categories);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Content classification failed: %s', $e->getMessage()),
                'deepseek'
            );
            throw $e;
        }
    }

    /**
     * Extract entities from text
     *
     * @param string $text Text to analyze
     * @return array Extracted entities
     */
    public function extract_entities($text) {
        try {
            $prompt = "Extract named entities from this text and return them as a JSON object grouped by entity type (person, organization, location, etc.): " . $text;
            
            $response = $this->generate_text($prompt);
            $result = json_decode($response, true);

            if (!is_array($result)) {
                throw new Exception(__('Invalid entity extraction response.', 'mfw'));
            }

            return $result;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Entity extraction failed: %s', $e->getMessage()),
                'deepseek'
            );
            throw $e;
        }
    }

    /**
     * Log API usage
     *
     * @param string $operation Operation type
     * @param int $tokens Number of tokens
     */
    private function log_api_usage($operation, $tokens) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mfw_api_usage',
            [
                'service' => 'deepseek',
                'operation' => $operation,
                'tokens' => $tokens,
                'user' => $this->current_user,
                'timestamp' => $this->current_time
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );
    }
}