<?php
/**
 * Content Sanitizer Class
 *
 * Sanitizes and validates AI-generated content.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Content_Sanitizer {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:30:16';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Default sanitization rules
     *
     * @var array
     */
    private $default_rules = [
        'remove_scripts' => true,
        'remove_iframes' => true,
        'allow_basic_html' => true,
        'max_length' => 50000,
        'min_length' => 50,
        'check_spam' => true,
        'check_offensive' => true,
        'check_plagiarism' => false
    ];

    /**
     * Allowed HTML tags
     *
     * @var array
     */
    private $allowed_html = [
        'p' => [
            'class' => true,
            'style' => true
        ],
        'h1' => [
            'class' => true,
            'style' => true
        ],
        'h2' => [
            'class' => true,
            'style' => true
        ],
        'h3' => [
            'class' => true,
            'style' => true
        ],
        'h4' => [
            'class' => true,
            'style' => true
        ],
        'h5' => [
            'class' => true,
            'style' => true
        ],
        'h6' => [
            'class' => true,
            'style' => true
        ],
        'a' => [
            'href' => true,
            'title' => true,
            'class' => true,
            'target' => true,
            'rel' => true
        ],
        'ul' => [
            'class' => true,
            'style' => true
        ],
        'ol' => [
            'class' => true,
            'style' => true
        ],
        'li' => [
            'class' => true,
            'style' => true
        ],
        'strong' => [],
        'em' => [],
        'code' => [
            'class' => true
        ],
        'pre' => [
            'class' => true
        ],
        'blockquote' => [
            'class' => true,
            'cite' => true
        ],
        'img' => [
            'src' => true,
            'alt' => true,
            'class' => true,
            'width' => true,
            'height' => true
        ],
        'br' => [],
        'hr' => [],
        'table' => [
            'class' => true,
            'style' => true
        ],
        'tr' => [
            'class' => true,
            'style' => true
        ],
        'td' => [
            'class' => true,
            'style' => true,
            'colspan' => true,
            'rowspan' => true
        ],
        'th' => [
            'class' => true,
            'style' => true,
            'colspan' => true,
            'rowspan' => true
        ]
    ];

    /**
     * Sanitize content
     *
     * @param string|array $content Content to sanitize
     * @param array $rules Custom sanitization rules
     * @return array Sanitization result
     */
    public function sanitize($content, $rules = []) {
        // Merge custom rules with defaults
        $rules = wp_parse_args($rules, $this->default_rules);

        $result = [
            'success' => true,
            'content' => $content,
            'warnings' => [],
            'errors' => []
        ];

        try {
            // Handle array content
            if (is_array($content)) {
                foreach ($content as $key => $value) {
                    $sanitized = $this->sanitize_single($value, $rules);
                    $result['content'][$key] = $sanitized['content'];
                    $result['warnings'] = array_merge($result['warnings'], $sanitized['warnings']);
                    $result['errors'] = array_merge($result['errors'], $sanitized['errors']);
                }
                return $result;
            }

            // Handle single content
            return $this->sanitize_single($content, $rules);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Content sanitization failed: %s', $e->getMessage()),
                'content_sanitizer',
                'error'
            );

            return [
                'success' => false,
                'content' => $content,
                'warnings' => [],
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Sanitize single content item
     *
     * @param string $content Content to sanitize
     * @param array $rules Sanitization rules
     * @return array Sanitization result
     */
    private function sanitize_single($content, $rules) {
        $result = [
            'success' => true,
            'content' => $content,
            'warnings' => [],
            'errors' => []
        ];

        // Check content length
        if (strlen($content) > $rules['max_length']) {
            $result['warnings'][] = sprintf(
                __('Content exceeds maximum length of %d characters.', 'mfw'),
                $rules['max_length']
            );
            $result['content'] = substr($content, 0, $rules['max_length']);
        }

        if (strlen($content) < $rules['min_length']) {
            $result['warnings'][] = sprintf(
                __('Content is shorter than minimum length of %d characters.', 'mfw'),
                $rules['min_length']
            );
        }

        // Remove scripts if configured
        if ($rules['remove_scripts']) {
            $result['content'] = $this->remove_scripts($result['content']);
        }

        // Remove iframes if configured
        if ($rules['remove_iframes']) {
            $result['content'] = $this->remove_iframes($result['content']);
        }

        // Allow basic HTML if configured
        if ($rules['allow_basic_html']) {
            $result['content'] = wp_kses($result['content'], $this->allowed_html);
        } else {
            $result['content'] = wp_kses_post($result['content']);
        }

        // Check for spam if configured
        if ($rules['check_spam'] && $this->contains_spam($result['content'])) {
            $result['errors'][] = __('Content contains spam patterns.', 'mfw');
        }

        // Check for offensive content if configured
        if ($rules['check_offensive'] && $this->contains_offensive_content($result['content'])) {
            $result['errors'][] = __('Content contains offensive material.', 'mfw');
        }

        // Check for plagiarism if configured
        if ($rules['check_plagiarism'] && $this->check_plagiarism($result['content'])) {
            $result['warnings'][] = __('Content may contain plagiarized material.', 'mfw');
        }

        // Log sanitization if there are warnings or errors
        if (!empty($result['warnings']) || !empty($result['errors'])) {
            $this->log_sanitization($content, $result);
        }

        return $result;
    }

    /**
     * Remove script tags and their contents
     *
     * @param string $content Content to process
     * @return string Processed content
     */
    private function remove_scripts($content) {
        return preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
    }

    /**
     * Remove iframe tags
     *
     * @param string $content Content to process
     * @return string Processed content
     */
    private function remove_iframes($content) {
        return preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $content);
    }

    /**
     * Check if content contains spam patterns
     *
     * @param string $content Content to check
     * @return bool True if spam detected
     */
    private function contains_spam($content) {
        $spam_patterns = apply_filters('mfw_spam_patterns', [
            '/\[url=/',
            '/\[link=/',
            '/(viagra|cialis|levitra|pharmacy)\b/i',
            '/\b(free|cheap|discount|buy now)\b/i'
        ]);

        foreach ($spam_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content contains offensive material
     *
     * @param string $content Content to check
     * @return bool True if offensive content detected
     */
    private function contains_offensive_content($content) {
        $offensive_words = apply_filters('mfw_offensive_words', []);
        
        if (empty($offensive_words)) {
            return false;
        }

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $offensive_words)) . ')\b/i';
        return preg_match($pattern, $content) === 1;
    }

    /**
     * Check for plagiarism
     *
     * @param string $content Content to check
     * @return bool True if potential plagiarism detected
     */
    private function check_plagiarism($content) {
        // Implement plagiarism detection logic here
        // This could integrate with external plagiarism detection services
        return false;
    }

    /**
     * Log sanitization results
     *
     * @param string $original_content Original content
     * @param array $result Sanitization result
     */
    private function log_sanitization($original_content, $result) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mfw_sanitization_log',
            [
                'original_content' => $original_content,
                'sanitized_content' => $result['content'],
                'warnings' => json_encode($result['warnings']),
                'errors' => json_encode($result['errors']),
                'created_by' => $this->current_user,
                'created_at' => $this->current_time
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
}