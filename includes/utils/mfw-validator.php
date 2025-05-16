<?php
/**
 * Validator - Data Validation System
 * Handles validation of input data and content formatting
 * 
 * @package MFW
 * @subpackage Utils
 * @since 1.0.0
 */

namespace MFW\Utils;

class Validator {
    private $rules;
    private $errors;
    private $settings;

    /**
     * Initialize the validator
     */
    public function __construct() {
        $this->rules = $this->get_validation_rules();
        $this->errors = [];
        $this->settings = get_option('mfw_validator_settings', []);
    }

    /**
     * Validate field group for FieldRegistry
     */
    public function validate_field_group($args) {
        try {
            // Check required fields
            if (empty($args['title'])) {
                $this->add_error('field_group', 'Title is required');
                return false;
            }

            // Validate context
            $valid_contexts = ['normal', 'advanced', 'side'];
            if (!in_array($args['context'], $valid_contexts)) {
                $this->add_error('field_group', 'Invalid context value');
                return false;
            }

            // Validate priority
            $valid_priorities = ['high', 'core', 'default', 'low'];
            if (!in_array($args['priority'], $valid_priorities)) {
                $this->add_error('field_group', 'Invalid priority value');
                return false;
            }

            // Validate post types
            if (!empty($args['post_types']) && !is_array($args['post_types'])) {
                $this->add_error('field_group', 'Post types must be an array');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->log_error('Field group validation failed', $e);
            return false;
        }
    }

    /**
     * Validate field for FieldRegistry
     */
    public function validate_field($args) {
        try {
            // Check required fields
            if (empty($args['type'])) {
                $this->add_error('field', 'Field type is required');
                return false;
            }

            if (empty($args['group'])) {
                $this->add_error('field', 'Field group is required');
                return false;
            }

            // Validate field type
            $valid_types = ['text', 'textarea', 'select', 'checkbox', 'radio', 'range', 'display', 'checkbox_group'];
            if (!in_array($args['type'], $valid_types)) {
                $this->add_error('field', 'Invalid field type');
                return false;
            }

            // Validate callbacks
            if (!empty($args['sanitize_callback']) && !is_callable($args['sanitize_callback'])) {
                $this->add_error('field', 'Invalid sanitize callback');
                return false;
            }

            if (!empty($args['validate_callback']) && !is_callable($args['validate_callback'])) {
                $this->add_error('field', 'Invalid validate callback');
                return false;
            }

            if (!empty($args['render_callback']) && !is_callable($args['render_callback'])) {
                $this->add_error('field', 'Invalid render callback');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->log_error('Field validation failed', $e);
            return false;
        }
    }

    /**
     * Validate AI content
     */
    public function validate_content($content, $rules = []) {
        try {
            $default_rules = [
                'min_length' => 10,
                'max_length' => 50000,
                'required_elements' => ['p', 'h1', 'h2', 'h3'],
                'forbidden_elements' => ['script', 'iframe', 'object'],
                'max_headings' => 20,
                'max_paragraphs' => 100
            ];

            $rules = wp_parse_args($rules, $default_rules);
            
            // Check content length
            if (strlen($content) < $rules['min_length']) {
                $this->add_error('content', 'Content is too short');
                return false;
            }

            if (strlen($content) > $rules['max_length']) {
                $this->add_error('content', 'Content is too long');
                return false;
            }

            // Validate HTML structure
            if (!$this->validate_html_structure($content, $rules)) {
                return false;
            }

            // Check for required elements
            if (!$this->check_required_elements($content, $rules['required_elements'])) {
                return false;
            }

            // Check for forbidden elements
            if ($this->contains_forbidden_elements($content, $rules['forbidden_elements'])) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->log_error('Content validation failed', $e);
            return false;
        }
    }

    /**
     * Validate prompt
     */
    public function validate_prompt($prompt, $rules = []) {
        try {
            $default_rules = [
                'min_length' => 3,
                'max_length' => 1000,
                'forbidden_words' => [],
                'required_pattern' => null
            ];

            $rules = wp_parse_args($rules, $default_rules);

            // Check length
            if (strlen($prompt) < $rules['min_length']) {
                $this->add_error('prompt', 'Prompt is too short');
                return false;
            }

            if (strlen($prompt) > $rules['max_length']) {
                $this->add_error('prompt', 'Prompt is too long');
                return false;
            }

            // Check for forbidden words
            if (!empty($rules['forbidden_words'])) {
                foreach ($rules['forbidden_words'] as $word) {
                    if (stripos($prompt, $word) !== false) {
                        $this->add_error('prompt', 'Prompt contains forbidden words');
                        return false;
                    }
                }
            }

            // Check pattern if specified
            if ($rules['required_pattern'] && !preg_match($rules['required_pattern'], $prompt)) {
                $this->add_error('prompt', 'Prompt format is invalid');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->log_error('Prompt validation failed', $e);
            return false;
        }
    }

    /**
     * Validate field value
     */
    public function validate_field_value($value, $rules = []) {
        try {
            $default_rules = [
                'type' => 'text',
                'required' => false,
                'min_length' => 0,
                'max_length' => 0,
                'pattern' => '',
                'enum' => [],
                'custom' => null
            ];

            $rules = wp_parse_args($rules, $default_rules);

            // Check if required
            if ($rules['required'] && empty($value)) {
                $this->add_error('field', 'Field is required');
                return false;
            }

            // Skip further validation if empty and not required
            if (empty($value) && !$rules['required']) {
                return true;
            }

            // Validate based on type
            switch ($rules['type']) {
                case 'email':
                    if (!is_email($value)) {
                        $this->add_error('field', 'Invalid email format');
                        return false;
                    }
                    break;

                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $this->add_error('field', 'Invalid URL format');
                        return false;
                    }
                    break;

                case 'number':
                    if (!is_numeric($value)) {
                        $this->add_error('field', 'Value must be a number');
                        return false;
                    }
                    break;

                case 'boolean':
                    if (!is_bool($value) && $value !== '0' && $value !== '1') {
                        $this->add_error('field', 'Value must be boolean');
                        return false;
                    }
                    break;
            }

            // Check length constraints
            if ($rules['min_length'] > 0 && strlen($value) < $rules['min_length']) {
                $this->add_error('field', 'Value is too short');
                return false;
            }

            if ($rules['max_length'] > 0 && strlen($value) > $rules['max_length']) {
                $this->add_error('field', 'Value is too long');
                return false;
            }

            // Check pattern
            if (!empty($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
                $this->add_error('field', 'Value format is invalid');
                return false;
            }

            // Check enum values
            if (!empty($rules['enum']) && !in_array($value, $rules['enum'])) {
                $this->add_error('field', 'Value is not in allowed list');
                return false;
            }

            // Run custom validation if provided
            if (is_callable($rules['custom'])) {
                if (!call_user_func($rules['custom'], $value)) {
                    $this->add_error('field', 'Custom validation failed');
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->log_error('Field validation failed', $e);
            return false;
        }
    }

    /**
     * Get validation errors
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get validation rules
     */
    private function get_validation_rules() {
        return [
            'text' => [
                'min_length' => 1,
                'max_length' => 255
            ],
            'textarea' => [
                'min_length' => 1,
                'max_length' => 65535
            ],
            'email' => [
                'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/'
            ],
            'url' => [
                'pattern' => '/^https?:\\/\\/[^\\s/$.?#].[^\\s]*$/'
            ]
        ];
    }

    /**
     * Validate HTML structure
     */
    private function validate_html_structure($content, $rules) {
        $doc = new \DOMDocument();
        $doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Check heading hierarchy
        $headings = $doc->getElementsByTagName('h1');
        if ($headings->length > $rules['max_headings']) {
            $this->add_error('content', 'Too many headings');
            return false;
        }

        // Check paragraph count
        $paragraphs = $doc->getElementsByTagName('p');
        if ($paragraphs->length > $rules['max_paragraphs']) {
            $this->add_error('content', 'Too many paragraphs');
            return false;
        }

        return true;
    }

    /**
     * Check for required elements
     */
    private function check_required_elements($content, $elements) {
        $doc = new \DOMDocument();
        $doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($elements as $element) {
            if ($doc->getElementsByTagName($element)->length === 0) {
                $this->add_error('content', "Missing required element: {$element}");
                return false;
            }
        }

        return true;
    }

    /**
     * Check for forbidden elements
     */
    private function contains_forbidden_elements($content, $elements) {
        $doc = new \DOMDocument();
        $doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($elements as $element) {
            if ($doc->getElementsByTagName($element)->length > 0) {
                $this->add_error('content', "Found forbidden element: {$element}");
                return true;
            }
        }

        return false;
    }

    /**
     * Add validation error
     */
    private function add_error($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW Validator Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}