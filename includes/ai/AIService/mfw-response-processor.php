<?php
/**
 * ResponseProcessor - AI Response Processing System
 * Processes and transforms AI API responses into structured data
 * 
 * @package MFW
 * @subpackage AI
 * @since 1.0.0
 */

namespace MFW\AI\AIService;

class ResponseProcessor {
    private $sanitizer;
    private $validator;
    private $cache;
    private $settings;

    /**
     * Initialize response processor
     */
    public function __construct() {
        $this->sanitizer = new \MFW\Utils\Sanitizer();
        $this->validator = new \MFW\Utils\Validator();
        $this->cache = new \MFW\AI\AICache();
        $this->settings = get_option('mfw_response_settings', []);
    }

    /**
     * Process general API response
     */
    public function process($response) {
        try {
            if (!$this->validator->validate_response($response)) {
                throw new \Exception('Invalid response format');
            }

            $processed = $this->transform_response($response);
            return $this->sanitizer->sanitize_response($processed);
        } catch (\Exception $e) {
            $this->log_error('Response processing failed', $e);
            return false;
        }
    }

    /**
     * Process entity extraction response
     */
    public function process_entities($response) {
        try {
            $entities = [];
            
            foreach ($response['entities'] as $entity) {
                $processed_entity = [
                    'text' => $this->sanitizer->sanitize_text($entity['text']),
                    'type' => $this->validate_entity_type($entity['type']),
                    'confidence' => $this->validate_confidence_score($entity['confidence']),
                    'start' => (int) $entity['start'],
                    'end' => (int) $entity['end'],
                    'metadata' => $this->process_entity_metadata($entity['metadata'] ?? [])
                ];

                if ($this->validator->validate_entity($processed_entity)) {
                    $entities[] = $processed_entity;
                }
            }

            return $entities;
        } catch (\Exception $e) {
            $this->log_error('Entity processing failed', $e);
            return false;
        }
    }

    /**
     * Process topic identification response
     */
    public function process_topics($response) {
        try {
            $topics = [];
            
            foreach ($response['topics'] as $topic) {
                $processed_topic = [
                    'name' => $this->sanitizer->sanitize_text($topic['name']),
                    'confidence' => $this->validate_confidence_score($topic['confidence']),
                    'keywords' => array_map([$this->sanitizer, 'sanitize_text'], $topic['keywords']),
                    'hierarchy' => $this->process_topic_hierarchy($topic['hierarchy'] ?? []),
                    'relevance' => $this->calculate_topic_relevance($topic)
                ];

                if ($this->validator->validate_topic($processed_topic)) {
                    $topics[] = $processed_topic;
                }
            }

            return $topics;
        } catch (\Exception $e) {
            $this->log_error('Topic processing failed', $e);
            return false;
        }
    }

    /**
     * Process keyword extraction response
     */
    public function process_keywords($response) {
        try {
            $keywords = [];
            
            foreach ($response['keywords'] as $keyword) {
                $processed_keyword = [
                    'text' => $this->sanitizer->sanitize_text($keyword['text']),
                    'relevance' => $this->validate_confidence_score($keyword['relevance']),
                    'frequency' => (int) $keyword['frequency'],
                    'sentiment' => $this->process_keyword_sentiment($keyword['sentiment'] ?? null),
                    'context' => $this->process_keyword_context($keyword['context'] ?? [])
                ];

                if ($this->validator->validate_keyword($processed_keyword)) {
                    $keywords[] = $processed_keyword;
                }
            }

            return $keywords;
        } catch (\Exception $e) {
            $this->log_error('Keyword processing failed', $e);
            return false;
        }
    }

    /**
     * Process dependency parsing response
     */
    public function process_dependencies($response) {
        try {
            $dependencies = [];
            
            foreach ($response['dependencies'] as $dep) {
                $processed_dep = [
                    'source' => $this->sanitizer->sanitize_text($dep['source']),
                    'target' => $this->sanitizer->sanitize_text($dep['target']),
                    'type' => $this->validate_dependency_type($dep['type']),
                    'probability' => $this->validate_confidence_score($dep['probability']),
                    'features' => $this->process_dependency_features($dep['features'] ?? [])
                ];

                if ($this->validator->validate_dependency($processed_dep)) {
                    $dependencies[] = $processed_dep;
                }
            }

            return $dependencies;
        } catch (\Exception $e) {
            $this->log_error('Dependency processing failed', $e);
            return false;
        }
    }

    /**
     * Process POS tagging response
     */
    public function process_pos_tags($response) {
        try {
            $tags = [];
            
            foreach ($response['tokens'] as $token) {
                $processed_tag = [
                    'text' => $this->sanitizer->sanitize_text($token['text']),
                    'tag' => $this->validate_pos_tag($token['tag']),
                    'probability' => $this->validate_confidence_score($token['probability']),
                    'features' => $this->process_pos_features($token['features'] ?? [])
                ];

                if ($this->validator->validate_pos_tag($processed_tag)) {
                    $tags[] = $processed_tag;
                }
            }

            return $tags;
        } catch (\Exception $e) {
            $this->log_error('POS tag processing failed', $e);
            return false;
        }
    }

    /**
     * Process sentiment analysis response
     */
    public function process_sentiment($response) {
        try {
            return [
                'overall' => [
                    'sentiment' => $this->validate_sentiment($response['sentiment']),
                    'confidence' => $this->validate_confidence_score($response['confidence'])
                ],
                'aspects' => $this->process_sentiment_aspects($response['aspects'] ?? []),
                'entities' => $this->process_sentiment_entities($response['entities'] ?? [])
            ];
        } catch (\Exception $e) {
            $this->log_error('Sentiment processing failed', $e);
            return false;
        }
    }

    /**
     * Transform raw response into structured data
     */
    private function transform_response($response) {
        return [
            'data' => $response['data'],
            'meta' => [
                'timestamp' => time(),
                'version' => $response['meta']['version'] ?? '1.0',
                'processing_time' => $response['meta']['processing_time'] ?? 0
            ]
        ];
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW ResponseProcessor Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}