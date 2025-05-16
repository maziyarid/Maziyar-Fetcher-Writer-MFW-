<?php
/**
 * Sanitizer - Data Sanitization System
 * Handles sanitization of input data and content for security
 * 
 * @package MFW
 * @subpackage Utils
 * @since 1.0.0
 */

namespace MFW\Utils;

class Sanitizer {
    private $allowed_html;
    private $allowed_protocols;
    private $settings;

    /**
     * Initialize the sanitizer
     */
    public function __construct() {
        $this->allowed_html = $this->get_allowed_html();
        $this->allowed_protocols = $this->get_allowed_protocols();
        $this->settings = get_option('mfw_sanitizer_settings', []);
    }

    /**
     * Sanitize AI-generated content
     */
    public function sanitize_ai_content($content) {
        try {
            // Remove any potentially harmful scripts
            $content = $this->remove_scripts($content);
            
            // Sanitize HTML content
            $content = wp_kses($content, $this->allowed_html, $this->allowed_protocols);
            
            // Fix formatting issues
            $content = $this->fix_formatting($content);
            
            // Normalize whitespace
            $content = $this->normalize_whitespace($content);
            
            return $content;
        } catch (\Exception $e) {
            $this->log_error('Content sanitization failed', $e);
            return wp_strip_all_tags($content);
        }
    }

    /**
     * Sanitize prompt input
     */
    public function sanitize_prompt($prompt) {
        try {
            // Basic sanitization
            $prompt = sanitize_text_field($prompt);
            
            // Remove potentially harmful characters
            $prompt = $this->remove_harmful_chars($prompt);
            
            // Normalize line endings
            $prompt = $this->normalize_line_endings($prompt);
            
            return $prompt;
        } catch (\Exception $e) {
            $this->log_error('Prompt sanitization failed', $e);
            return '';
        }
    }

    /**
     * Sanitize metadata
     */
    public function sanitize_metadata($metadata) {
        try {
            if (is_array($metadata)) {
                return array_map([$this, 'sanitize_metadata_field'], $metadata);
            }
            
            return $this->sanitize_metadata_field($metadata);
        } catch (\Exception $e) {
            $this->log_error('Metadata sanitization failed', $e);
            return [];
        }
    }

    /**
     * Sanitize field value based on type
     */
    public function sanitize_field($value, $type = 'text') {
        try {
            switch ($type) {
                case 'email':
                    return sanitize_email($value);
                
                case 'url':
                    return esc_url_raw($value);
                
                case 'textarea':
                    return sanitize_textarea_field($value);
                
                case 'html':
                    return wp_kses_post($value);
                
                case 'int':
                    return intval($value);
                
                case 'float':
                    return floatval($value);
                
                case 'bool':
                    return (bool) $value;
                
                case 'array':
                    return $this->sanitize_array($value);
                
                default:
                    return sanitize_text_field($value);
            }
        } catch (\Exception $e) {
            $this->log_error('Field sanitization failed', $e);
            return '';
        }
    }

    /**
     * Get allowed HTML tags and attributes
     */
    private function get_allowed_html() {
        $allowed = wp_kses_allowed_html('post');
        
        // Add additional allowed tags
        $allowed['div'] = [
            'class' => true,
            'id' => true,
            'style' => true
        ];
        
        $allowed['span'] = [
            'class' => true,
            'id' => true,
            'style' => true
        ];
        
        $allowed['code'] = [
            'class' => true
        ];
        
        $allowed['pre'] = [
            'class' => true
        ];

        return $allowed;
    }

    /**
     * Get allowed protocols
     */
    private function get_allowed_protocols() {
        return array_merge(
            wp_allowed_protocols(),
            ['data'] // Allow data URIs
        );
    }

    /**
     * Remove potentially harmful characters
     */
    private function remove_harmful_chars($input) {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Remove control characters
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // Remove Unicode line and paragraph separators
        $input = str_replace(["\xE2\x80\xA8", "\xE2\x80\xA9"], '', $input);
        
        return $input;
    }

    /**
     * Normalize line endings
     */
    private function normalize_line_endings($input) {
        // Convert all line-endings to UNIX format
        $input = str_replace(["\r\n", "\r"], "\n", $input);
        
        // Don't allow out-of-control blank lines
        $input = preg_replace("/\n{3,}/", "\n\n", $input);
        
        return trim($input);
    }

    /**
     * Remove scripts from content
     */
    private function remove_scripts($content) {
        // Remove script tags
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        
        // Remove on* attributes
        $content = preg_replace('/\bon\w+\s*=\s*"[^"]*"/i', '', $content);
        $content = preg_replace("/\bon\w+\s*=\s*'[^']*'/i", '', $content);
        
        return $content;
    }

    /**
     * Fix formatting issues
     */
    private function fix_formatting($content) {
        // Fix broken HTML entities
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = htmlentities($content, ENT_QUOTES, 'UTF-8', false);
        
        // Fix incorrectly nested tags
        $content = force_balance_tags($content);
        
        return $content;
    }

    /**
     * Normalize whitespace
     */
    private function normalize_whitespace($content) {
        // Normalize spaces
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Fix spacing around punctuation
        $content = preg_replace('/\s+([.,!?:;])/', '$1', $content);
        
        // Ensure single space after punctuation
        $content = preg_replace('/([.,!?:;])\s*/', '$1 ', $content);
        
        return trim($content);
    }

    /**
     * Sanitize array values
     */
    private function sanitize_array($array) {
        if (!is_array($array)) {
            return [];
        }

        return array_map(function($value) {
            if (is_array($value)) {
                return $this->sanitize_array($value);
            }
            return $this->sanitize_field($value);
        }, $array);
    }

    /**
     * Sanitize metadata field
     */
    private function sanitize_metadata_field($value) {
        if (is_array($value)) {
            return $this->sanitize_array($value);
        }
        
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        if (is_bool($value)) {
            return (bool) $value;
        }
        
        return sanitize_text_field($value);
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW Sanitizer Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}