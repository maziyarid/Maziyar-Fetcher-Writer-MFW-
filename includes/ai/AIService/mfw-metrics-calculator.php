<?php
/**
 * MetricsCalculator - Content Metrics Analysis System
 * Calculates various metrics for AI-generated content
 * 
 * @package MFW
 * @subpackage AI
 * @since 1.0.0
 */

namespace MFW\AI\AIService;

class MetricsCalculator {
    private $settings;
    private $cache;

    /**
     * Initialize the metrics calculator
     */
    public function __construct() {
        $this->settings = get_option('mfw_metrics_settings', []);
        $this->cache = new \MFW\AI\AICache();
    }

    /**
     * Calculate Flesch-Kincaid readability score
     */
    public function calculate_flesch_kincaid($text) {
        try {
            $cache_key = 'mfw_fk_' . md5($text);
            
            if ($cached = $this->cache->get($cache_key)) {
                return $cached;
            }

            $words = $this->count_words($text);
            $sentences = $this->count_sentences($text);
            $syllables = $this->count_syllables($text);

            if ($sentences == 0 || $words == 0) {
                return 0;
            }

            $score = 206.835 - 1.015 * ($words / $sentences) - 84.6 * ($syllables / $words);
            $score = round($score, 2);

            $this->cache->set($cache_key, $score);
            return $score;
        } catch (\Exception $e) {
            $this->log_error('Flesch-Kincaid calculation failed', $e);
            return 0;
        }
    }

    /**
     * Calculate content metrics
     */
    public function calculate_metrics($content) {
        try {
            return [
                'readability' => [
                    'flesch_kincaid' => $this->calculate_flesch_kincaid($content),
                    'avg_sentence_length' => $this->calculate_avg_sentence_length($content),
                    'avg_word_length' => $this->calculate_avg_word_length($content)
                ],
                'structure' => [
                    'paragraph_count' => $this->count_paragraphs($content),
                    'sentence_count' => $this->count_sentences($content),
                    'word_count' => $this->count_words($content)
                ],
                'complexity' => [
                    'complex_word_percentage' => $this->calculate_complex_word_percentage($content),
                    'passive_voice_count' => $this->count_passive_voice($content),
                    'transition_word_count' => $this->count_transition_words($content)
                ],
                'seo' => [
                    'keyword_density' => $this->calculate_keyword_density($content),
                    'heading_distribution' => $this->analyze_heading_distribution($content),
                    'internal_links' => $this->count_internal_links($content)
                ]
            ];
        } catch (\Exception $e) {
            $this->log_error('Metrics calculation failed', $e);
            return [];
        }
    }

    /**
     * Count words in text
     */
    private function count_words($text) {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Split into words and count
        $words = preg_split('/\s+/', trim($text));
        return count(array_filter($words));
    }

    /**
     * Count sentences in text
     */
    private function count_sentences($text) {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Split into sentences and count
        $sentences = preg_split('/[.!?]+/u', $text);
        return count(array_filter($sentences));
    }

    /**
     * Count syllables in text
     */
    private function count_syllables($text) {
        // Remove HTML and numbers
        $text = strip_tags($text);
        $text = preg_replace('/[0-9]+/', '', $text);
        
        // Convert to lowercase
        $text = strtolower($text);
        
        $syllable_count = 0;
        $words = preg_split('/\s+/', trim($text));
        
        foreach ($words as $word) {
            $syllable_count += $this->count_word_syllables($word);
        }
        
        return $syllable_count;
    }

    /**
     * Count syllables in a word
     */
    private function count_word_syllables($word) {
        $word = strtolower($word);
        $word = preg_replace('/[^a-z]/', '', $word);
        
        // Special cases
        if (empty($word)) {
            return 0;
        }
        
        // Count vowel groups
        $count = preg_match_all('/[aeiouy]+/', $word);
        
        // Adjust for silent 'e' at end
        if (substr($word, -1) === 'e') {
            $count--;
        }
        
        return max(1, $count);
    }

    /**
     * Calculate average sentence length
     */
    private function calculate_avg_sentence_length($text) {
        $words = $this->count_words($text);
        $sentences = $this->count_sentences($text);
        
        return $sentences > 0 ? round($words / $sentences, 2) : 0;
    }

    /**
     * Calculate average word length
     */
    private function calculate_avg_word_length($text) {
        $text = strip_tags($text);
        $words = preg_split('/\s+/', trim($text));
        $total_length = array_sum(array_map('strlen', $words));
        
        return count($words) > 0 ? round($total_length / count($words), 2) : 0;
    }

    /**
     * Calculate complex word percentage
     */
    private function calculate_complex_word_percentage($text) {
        $words = preg_split('/\s+/', strip_tags($text));
        $complex_words = 0;
        
        foreach ($words as $word) {
            if ($this->count_word_syllables($word) > 2) {
                $complex_words++;
            }
        }
        
        return count($words) > 0 ? round(($complex_words / count($words)) * 100, 2) : 0;
    }

    /**
     * Count passive voice occurrences
     */
    private function count_passive_voice($text) {
        $patterns = [
            '/\b(am|is|are|was|were|be|been|being)\s+(\w+ed)\b/i',
            '/\b(has|have|had)\s+been\s+(\w+ed)\b/i'
        ];
        
        $count = 0;
        foreach ($patterns as $pattern) {
            $count += preg_match_all($pattern, $text);
        }
        
        return $count;
    }

    /**
     * Count transition words
     */
    private function count_transition_words($text) {
        $transition_words = [
            'furthermore', 'moreover', 'additionally', 'therefore',
            'consequently', 'however', 'nevertheless', 'alternatively',
            'meanwhile', 'subsequently', 'finally', 'in conclusion'
        ];
        
        $count = 0;
        foreach ($transition_words as $word) {
            $count += preg_match_all('/\b' . preg_quote($word, '/') . '\b/i', $text);
        }
        
        return $count;
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW MetricsCalculator Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}