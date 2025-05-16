<?php
/**
 * Gemini AI Service Class
 *
 * Handles interaction with Google's Gemini AI API.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Gemini_Service implements MFW_AI_Service_Interface {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:11:42';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * API endpoint
     *
     * @var string
     */
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1';

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
        $this->api_key = $settings['gemini_api_key'] ?? '';

        if (empty($this->api_key)) {
            throw new Exception(__('Gemini API key is not configured.', 'mfw'));
        }
    }

    /**
     * Generate text using Gemini AI
     *
     * @param string $prompt Text generation prompt
     * @param array $options Generation options
     * @return string Generated text
     */
    public function generate_text($prompt, $options = []) {
        try {
            // Merge options with defaults
            $options = wp_parse_args($options, [
                'temperature' => 0.7,
                'top_p' => 0.9,
                'max_tokens' => 1024,
                'stop' => [],
                'safety_settings' => [
                    'harassment' => 'block_medium_and_above',
                    'hate_speech' => 'block_medium_and_above',
                    'sexually_explicit' => 'block_medium_and_above',
                    'dangerous_content' => 'block_medium_and_above'
                ]
            ]);

            // Prepare request data
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => $options['temperature'],
                    'topP' => $options['top_p'],
                    'maxOutputTokens' => $options['max_tokens'],
                    'stopSequences' => $options['stop']
                ],
                'safetySettings' => array_map(function($level, $category) {
                    return [
                        'category' => $category,
                        'threshold' => $level
                    ];
                }, $options['safety_settings'], array_keys($options['safety_settings']))
            ];

            // Make API request
            $response = wp_remote_post(
                self::API_ENDPOINT . '/models/gemini-pro:generateContent?key=' . $this->api_key,
                [
                    'headers' => [
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

            if (empty($body['candidates'][0]['content']['parts'][0]['text'])) {
                throw new Exception(__('Invalid response from Gemini API.', 'mfw'));
            }

            // Log API usage
            $this->log_api_usage('text', strlen($prompt));

            return $body['candidates'][0]['content']['parts'][0]['text'];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Gemini text generation failed: %s', $e->getMessage()),
                'gemini'
            );
            throw $e;
        }
    }

    /**
     * Generate image using Gemini AI
     *
     * @param string $prompt Image generation prompt
     * @param array $options Generation options
     * @return array Generation result
     */
    public function generate_image($prompt, $options = []) {
        try {
            // Merge options with defaults
            $options = wp_parse_args($options, [
                'model' => 'gemini-pro-vision',
                'width' => 1024,
                'height' => 1024,
                'samples' => 1,
                'steps' => 50,
                'cfg_scale' => 7.5,
                'style_preset' => 'photographic'
            ]);

            // Prepare request data
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'imageGenConfig' => [
                        'width' => $options['width'],
                        'height' => $options['height'],
                        'samples' => $options['samples'],
                        'steps' => $options['steps'],
                        'cfgScale' => $options['cfg_scale'],
                        'stylePreset' => $options['style_preset']
                    ]
                ]
            ];

            // Make API request
            $response = wp_remote_post(
                self::API_ENDPOINT . '/models/' . $options['model'] . ':generateContent?key=' . $this->api_key,
                [
                    'headers' => [
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

            if (empty($body['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                throw new Exception(__('Invalid response from Gemini API.', 'mfw'));
            }

            // Log API usage
            $this->log_api_usage('image', strlen($prompt));

            return [
                'success' => true,
                'image_data' => $body['candidates'][0]['content']['parts'][0]['inlineData']['data']
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Gemini image generation failed: %s', $e->getMessage()),
                'gemini'
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
                "Analyze the sentiment of the following text and return a JSON object with 'score' (float between -1 and 1) and 'magnitude' (float >= 0): %s",
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
                'gemini'
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
                "Classify the following text into one or more of these categories (%s). Return a JSON array of category names: %s",
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
                'gemini'
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
            $prompt = "Extract named entities (people, organizations, locations, etc.) from the following text and return a JSON object grouped by entity type: " . $text;
            
            $response = $this->generate_text($prompt);
            $result = json_decode($response, true);

            if (!is_array($result)) {
                throw new Exception(__('Invalid entity extraction response.', 'mfw'));
            }

            return $result;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Entity extraction failed: %s', $e->getMessage()),
                'gemini'
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
                'service' => 'gemini',
                'operation' => $operation,
                'tokens' => $tokens,
                'user' => $this->current_user,
                'timestamp' => $this->current_time
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );
    }
}