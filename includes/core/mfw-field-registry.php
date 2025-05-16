<?php
/**
 * FieldRegistry - Custom Fields Management System
 * Handles registration and management of custom fields for AI content
 * 
 * @package MFW
 * @subpackage Core
 * @since 1.0.0
 */

namespace MFW\Core;

class FieldRegistry {
    private $fields;
    private $field_groups;
    private $settings;
    private $validator;

    /**
     * Initialize the field registry
     */
    public function __construct() {
        $this->fields = [];
        $this->field_groups = [];
        $this->settings = get_option('mfw_field_settings', []);
        $this->validator = new \MFW\Utils\Validator();

        add_action('init', [$this, 'register_default_fields']);
        add_action('admin_init', [$this, 'register_field_settings']);
    }

    /**
     * Register default AI content fields
     */
    public function register_default_fields() {
        // Content Generation Fields
        $this->register_field_group('content_generation', [
            'title' => __('Content Generation', 'mfw'),
            'context' => 'normal',
            'priority' => 'high'
        ]);

        $this->register_field('prompt', [
            'type' => 'textarea',
            'label' => __('AI Prompt', 'mfw'),
            'description' => __('Enter the prompt for AI content generation', 'mfw'),
            'group' => 'content_generation',
            'sanitize_callback' => 'sanitize_textarea_field'
        ]);

        $this->register_field('tone', [
            'type' => 'select',
            'label' => __('Content Tone', 'mfw'),
            'description' => __('Select the tone for generated content', 'mfw'),
            'group' => 'content_generation',
            'options' => [
                'professional' => __('Professional', 'mfw'),
                'casual' => __('Casual', 'mfw'),
                'friendly' => __('Friendly', 'mfw'),
                'authoritative' => __('Authoritative', 'mfw'),
                'humorous' => __('Humorous', 'mfw')
            ]
        ]);

        // AI Parameters Fields
        $this->register_field_group('ai_parameters', [
            'title' => __('AI Parameters', 'mfw'),
            'context' => 'side',
            'priority' => 'default'
        ]);

        $this->register_field('model', [
            'type' => 'select',
            'label' => __('AI Model', 'mfw'),
            'description' => __('Select the AI model to use', 'mfw'),
            'group' => 'ai_parameters',
            'options' => $this->get_available_models()
        ]);

        $this->register_field('temperature', [
            'type' => 'range',
            'label' => __('Temperature', 'mfw'),
            'description' => __('Adjust creativity level (0.1-1.0)', 'mfw'),
            'group' => 'ai_parameters',
            'min' => 0.1,
            'max' => 1.0,
            'step' => 0.1,
            'default' => 0.7
        ]);

        // Content Analysis Fields
        $this->register_field_group('content_analysis', [
            'title' => __('Content Analysis', 'mfw'),
            'context' => 'normal',
            'priority' => 'default'
        ]);

        $this->register_field('readability_score', [
            'type' => 'display',
            'label' => __('Readability Score', 'mfw'),
            'description' => __('AI-calculated readability score', 'mfw'),
            'group' => 'content_analysis',
            'callback' => [$this, 'calculate_readability_score']
        ]);

        $this->register_field('seo_optimization', [
            'type' => 'checkbox_group',
            'label' => __('SEO Optimization', 'mfw'),
            'description' => __('Select SEO elements to optimize', 'mfw'),
            'group' => 'content_analysis',
            'options' => [
                'keywords' => __('Keywords', 'mfw'),
                'meta_description' => __('Meta Description', 'mfw'),
                'headings' => __('Headings', 'mfw'),
                'internal_links' => __('Internal Links', 'mfw')
            ]
        ]);
    }

    /**
     * Register a new field group
     */
    public function register_field_group($id, $args = []) {
        $defaults = [
            'title' => '',
            'description' => '',
            'context' => 'normal',
            'priority' => 'default',
            'post_types' => ['mfw_ai_content']
        ];

        $args = wp_parse_args($args, $defaults);

        if ($this->validator->validate_field_group($args)) {
            $this->field_groups[$id] = $args;
            return true;
        }

        return false;
    }

    /**
     * Register a new field
     */
    public function register_field($id, $args = []) {
        $defaults = [
            'type' => 'text',
            'label' => '',
            'description' => '',
            'group' => '',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => null,
            'render_callback' => null,
            'required' => false
        ];

        $args = wp_parse_args($args, $defaults);

        if ($this->validator->validate_field($args)) {
            $this->fields[$id] = $args;
            return true;
        }

        return false;
    }

    /**
     * Get registered fields
     */
    public function get_fields($group = '') {
        if (!empty($group)) {
            return array_filter($this->fields, function($field) use ($group) {
                return $field['group'] === $group;
            });
        }

        return $this->fields;
    }

    /**
     * Get field groups
     */
    public function get_field_groups() {
        return $this->field_groups;
    }

    /**
     * Render field
     */
    public function render_field($id, $value = '', $post = null) {
        if (!isset($this->fields[$id])) {
            return false;
        }

        $field = $this->fields[$id];

        if (isset($field['render_callback']) && is_callable($field['render_callback'])) {
            call_user_func($field['render_callback'], $field, $value, $post);
            return true;
        }

        $this->render_field_by_type($field, $value, $post);
        return true;
    }

    /**
     * Get available AI models
     */
    private function get_available_models() {
        return [
            'gpt-4' => __('GPT-4 (Latest)', 'mfw'),
            'gpt-4-32k' => __('GPT-4 32K', 'mfw'),
            'gpt-3.5-turbo' => __('GPT-3.5 Turbo', 'mfw'),
            'davinci' => __('Davinci', 'mfw')
        ];
    }

    /**
     * Calculate readability score
     */
    private function calculate_readability_score($content) {
        try {
            $metrics = new \MFW\AI\MetricsCalculator();
            return $metrics->calculate_flesch_kincaid($content);
        } catch (\Exception $e) {
            $this->log_error('Readability calculation failed', $e);
            return false;
        }
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW FieldRegistry Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}