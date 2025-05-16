<?php
/**
 * AIHelper - AI Utility Functions
 * Provides helper functions for AI content generation and processing
 * 
 * @package MFW
 * @subpackage Utils
 * @since 1.0.0
 */

namespace MFW\Utils;

class AIHelper {
    private $settings;
    private $cache;
    private $sanitizer;
    private $validator;

    /**
     * Initialize the AI helper
     */
    public function __construct() {
        $this->settings = get_option('mfw_ai_helper_settings', []);
        $this->cache = new \MFW\AI\AICache();
        $this->sanitizer = new Sanitizer();
        $this->validator = new Validator();
    }

    /**
     * Process text with AI enhancement
     */
    public function enhance_text($text, $options = []) {
        try {
            $default_options = [
                'improve_readability' => true,
                'fix_grammar' => true,
                'optimize_seo' => true,
                'tone' => 'professional',
                'max_length' => 5000
            ];

            $options = wp_parse_args($options, $default_options);
            
            // Sanitize input
            $text = $this->sanitizer->sanitize_ai_content($text);
            
            // Apply enhancements
            $enhanced = $this->apply_enhancements($text, $options);
            
            // Validate output
            if (!$this->validator->validate_content($enhanced)) {
                throw new \Exception('Enhanced content validation failed');
            }
            
            return $enhanced;
        } catch (\Exception $e) {
            $this->log_error('Text enhancement failed', $e);
            return $text;
        }
    }

    /**
     * Generate content variations
     */
    public function generate_variations($content, $count = 3, $options = []) {
        try {
            $variations = [];
            $default_options = [
                'creativity_level' => 0.7,
                'preserve_keywords' => true,
                'maintain_structure' => true,
                'max_length_variation' => 0.2
            ];

            $options = wp_parse_args($options, $default_options);

            for ($i = 0; $i < $count; $i++) {
                $variation = $this->create_content_variation($content, $options);
                if ($variation && $this->validator->validate_content($variation)) {
                    $variations[] = $variation;
                }
            }

            return $variations;
        } catch (\Exception $e) {
            $this->log_error('Variation generation failed', $e);
            return [];
        }
    }

    /**
     * Optimize content for SEO
     */
    public function optimize_for_seo($content, $keywords = [], $options = []) {
        try {
            $default_options = [
                'keyword_density' => 2.0,
                'optimize_headings' => true,
                'add_meta_description' => true,
                'optimize_images' => true
            ];

            $options = wp_parse_args($options, $default_options);
            
            // Apply SEO optimizations
            $optimized = $this->apply_seo_optimizations($content, $keywords, $options);
            
            return $optimized;
        } catch (\Exception $e) {
            $this->log_error('SEO optimization failed', $e);
            return $content;
        }
    }

    /**
     * Extract key information from content
     */
    public function extract_key_info($content) {
        try {
            return [
                'summary' => $this->generate_summary($content),
                'keywords' => $this->extract_keywords($content),
                'entities' => $this->extract_entities($content),
                'topics' => $this->identify_topics($content),
                'sentiment' => $this->analyze_sentiment($content)
            ];
        } catch (\Exception $e) {
            $this->log_error('Information extraction failed', $e);
            return [];
        }
    }

    /**
     * Apply content enhancements
     */
    private function apply_enhancements($text, $options) {
        // Improve readability
        if ($options['improve_readability']) {
            $text = $this->improve_readability($text);
        }

        // Fix grammar and spelling
        if ($options['fix_grammar']) {
            $text = $this->fix_grammar($text);
        }

        // Optimize for SEO
        if ($options['optimize_seo']) {
            $text = $this->optimize_for_seo($text);
        }

        // Adjust tone
        $text = $this->adjust_tone($text, $options['tone']);

        // Ensure length constraints
        if (strlen($text) > $options['max_length']) {
            $text = $this->truncate_content($text, $options['max_length']);
        }

        return $text;
    }

    /**
     * Create content variation
     */
    private function create_content_variation($content, $options) {
        // Extract key elements
        $structure = $this->analyze_content_structure($content);
        $keywords = $options['preserve_keywords'] ? $this->extract_keywords($content) : [];

        // Generate variation while preserving important elements
        $variation = $this->generate_alternative_content($content, [
            'creativity_level' => $options['creativity_level'],
            'structure' => $options['maintain_structure'] ? $structure : null,
            'keywords' => $keywords
        ]);

        // Adjust length if needed
        $max_variation = strlen($content) * (1 + $options['max_length_variation']);
        if (strlen($variation) > $max_variation) {
            $variation = $this->truncate_content($variation, $max_variation);
        }

        return $variation;
    }

    /**
     * Apply SEO optimizations
     */
    private function apply_seo_optimizations($content, $keywords, $options) {
        // Analyze current keyword density
        $current_density = $this->calculate_keyword_density($content, $keywords);

        // Adjust keyword usage if needed
        if ($current_density < $options['keyword_density']) {
            $content = $this->optimize_keyword_usage($content, $keywords, $options['keyword_density']);
        }

        // Optimize headings
        if ($options['optimize_headings']) {
            $content = $this->optimize_headings($content, $keywords);
        }

        // Add meta description
        if ($options['add_meta_description']) {
            $content = $this->add_meta_description($content, $keywords);
        }

        // Optimize images
        if ($options['optimize_images']) {
            $content = $this->optimize_images($content, $keywords);
        }

        return $content;
    }

    /**
     * Improve content readability
     */
    private function improve_readability($text) {
        // Break long sentences
        $text = $this->break_long_sentences($text);

        // Simplify complex words
        $text = $this->simplify_vocabulary($text);

        // Add transition words
        $text = $this->add_transitions($text);

        // Optimize paragraph length
        $text = $this->optimize_paragraphs($text);

        return $text;
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW AIHelper Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}