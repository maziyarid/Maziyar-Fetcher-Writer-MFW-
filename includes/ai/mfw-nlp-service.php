<?php
/**
 * NLPService - Natural Language Processing Service
 * Handles communication with NLP APIs and processes linguistic data
 * 
 * @package MFW
 * @subpackage AI
 * @since 1.0.0
 */

namespace MFW\AI;

class NLPService {
    private $api_client;
    private $model_manager;
    private $response_processor;
    private $settings;

    /**
     * Initialize NLP service
     */
    public function __construct() {
        $this->api_client = new APIClient();
        $this->model_manager = new ModelManager();
        $this->response_processor = new ResponseProcessor();
        $this->settings = get_option('mfw_nlp_settings', []);
    }

    /**
     * Process text with specified NLP model
     */
    public function process($text, $model_type = 'general', $options = []) {
        try {
            $model = $this->model_manager->get_model($model_type);
            $processed = $this->api_client->process_text($text, $model, $options);
            return $this->response_processor->process($processed);
        } catch (\Exception $e) {
            $this->log_error('Text processing failed', $e);
            return false;
        }
    }

    /**
     * Extract named entities from text
     */
    public function extract_entities($text, $options = []) {
        try {
            $response = $this->api_client->call('entities', [
                'text' => $text,
                'options' => array_merge([
                    'confidence_threshold' => 0.7,
                    'include_metadata' => true,
                    'entity_types' => [
                        'PERSON', 'ORGANIZATION', 'LOCATION', 
                        'DATE', 'TIME', 'MONEY', 'PERCENT'
                    ]
                ], $options)
            ]);

            return $this->response_processor->process_entities($response);
        } catch (\Exception $e) {
            $this->log_error('Entity extraction failed', $e);
            return false;
        }
    }

    /**
     * Identify topics in text
     */
    public function identify_topics($text, $options = []) {
        try {
            $response = $this->api_client->call('topics', [
                'text' => $text,
                'options' => array_merge([
                    'max_topics' => 5,
                    'min_confidence' => 0.6,
                    'include_hierarchy' => true,
                    'include_keywords' => true
                ], $options)
            ]);

            return $this->response_processor->process_topics($response);
        } catch (\Exception $e) {
            $this->log_error('Topic identification failed', $e);
            return false;
        }
    }

    /**
     * Extract keywords from text
     */
    public function extract_keywords($text, $options = []) {
        try {
            $response = $this->api_client->call('keywords', [
                'text' => $text,
                'options' => array_merge([
                    'max_keywords' => 10,
                    'min_relevance' => 0.5,
                    'include_ngrams' => true,
                    'include_sentiment' => true
                ], $options)
            ]);

            return $this->response_processor->process_keywords($response);
        } catch (\Exception $e) {
            $this->log_error('Keyword extraction failed', $e);
            return false;
        }
    }

    /**
     * Perform dependency parsing
     */
    public function dependency_parse($text, $options = []) {
        try {
            $response = $this->api_client->call('dependency', [
                'text' => $text,
                'options' => array_merge([
                    'model' => 'neural',
                    'include_probabilities' => true,
                    'include_tokens' => true
                ], $options)
            ]);

            return $this->response_processor->process_dependencies($response);
        } catch (\Exception $e) {
            $this->log_error('Dependency parsing failed', $e);
            return false;
        }
    }

    /**
     * Perform part-of-speech tagging
     */
    public function pos_tag($text, $options = []) {
        try {
            $response = $this->api_client->call('pos', [
                'text' => $text,
                'options' => array_merge([
                    'model' => 'accurate',
                    'include_features' => true,
                    'include_probabilities' => true
                ], $options)
            ]);

            return $this->response_processor->process_pos_tags($response);
        } catch (\Exception $e) {
            $this->log_error('POS tagging failed', $e);
            return false;
        }
    }

    /**
     * Tokenize text
     */
    public function tokenize($text, $options = []) {
        try {
            $response = $this->api_client->call('tokenize', [
                'text' => $text,
                'options' => array_merge([
                    'split_sentences' => true,
                    'include_spans' => true,
                    'include_features' => true
                ], $options)
            ]);

            return $this->response_processor->process_tokens($response);
        } catch (\Exception $e) {
            $this->log_error('Tokenization failed', $e);
            return false;
        }
    }

    /**
     * Analyze sentiment in text
     */
    public function analyze_sentiment($text, $options = []) {
        try {
            $response = $this->api_client->call('sentiment', [
                'text' => $text,
                'options' => array_merge([
                    'model' => 'neural',
                    'include_aspects' => true,
                    'include_entities' => true
                ], $options)
            ]);

            return $this->response_processor->process_sentiment($response);
        } catch (\Exception $e) {
            $this->log_error('Sentiment analysis failed', $e);
            return false;
        }
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW NLPService Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}