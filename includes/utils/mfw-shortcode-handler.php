<?php
/**
 * Shortcode Handler Class
 * 
 * Manages registration, rendering, and caching of plugin shortcodes.
 * Includes security checks and content sanitization.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Shortcode_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:56:06';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Registered shortcodes
     */
    private $shortcodes = [];

    /**
     * Cache instance
     */
    private $cache;

    /**
     * Initialize shortcode handler
     */
    public function __construct() {
        $this->cache = new MFW_Cache_Handler();

        // Add hooks for shortcode preview in admin
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_mfw_preview_shortcode', [$this, 'ajax_preview_shortcode']);
    }

    /**
     * Register shortcode
     *
     * @param string $tag Shortcode tag
     * @param array $args Shortcode arguments
     * @return bool Success status
     */
    public function register_shortcode($tag, $args) {
        try {
            // Validate required arguments
            $required = ['callback', 'description'];
            foreach ($required as $arg) {
                if (!isset($args[$arg])) {
                    throw new Exception(sprintf(
                        __('Missing required argument: %s', 'mfw'),
                        $arg
                    ));
                }
            }

            // Parse arguments
            $args = wp_parse_args($args, [
                'attributes' => [],
                'cache_ttl' => 0,
                'sanitize_callback' => null,
                'validation_rules' => [],
                'example' => '',
                'category' => 'general'
            ]);

            // Register WordPress shortcode
            add_shortcode($tag, [$this, 'process_shortcode']);

            // Store shortcode configuration
            $this->shortcodes[$tag] = $args;

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to register shortcode: %s', $e->getMessage()),
                'shortcode_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Process shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @param string $tag Shortcode tag
     * @return string Processed shortcode output
     */
    public function process_shortcode($atts, $content = null, $tag = '') {
        try {
            if (!isset($this->shortcodes[$tag])) {
                return '';
            }

            $shortcode = $this->shortcodes[$tag];

            // Parse attributes
            $atts = shortcode_atts($shortcode['attributes'], $atts, $tag);

            // Validate attributes
            if (!$this->validate_attributes($atts, $shortcode['validation_rules'])) {
                return $this->get_error_message('invalid_attributes');
            }

            // Generate cache key if caching is enabled
            $cache_key = '';
            if ($shortcode['cache_ttl'] > 0) {
                $cache_key = $this->generate_cache_key($tag, $atts, $content);
                $cached = $this->cache->get($cache_key, 'mfw_shortcodes');
                if ($cached !== false) {
                    return $cached;
                }
            }

            // Process shortcode
            $output = call_user_func(
                $shortcode['callback'],
                $atts,
                $content,
                $tag
            );

            // Sanitize output if callback provided
            if ($shortcode['sanitize_callback']) {
                $output = call_user_func($shortcode['sanitize_callback'], $output);
            }

            // Cache output if enabled
            if ($shortcode['cache_ttl'] > 0) {
                $this->cache->set($cache_key, $output, 'mfw_shortcodes', $shortcode['cache_ttl']);
            }

            // Log shortcode usage
            $this->log_shortcode_usage($tag, $atts);

            return $output;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process shortcode: %s', $e->getMessage()),
                'shortcode_handler',
                'error'
            );
            return $this->get_error_message('processing_error');
        }
    }

    /**
     * Preview shortcode (AJAX handler)
     */
    public function ajax_preview_shortcode() {
        try {
            // Verify nonce
            if (!check_ajax_referer('mfw_preview_shortcode', 'nonce', false)) {
                wp_send_json_error(__('Invalid security token.', 'mfw'));
            }

            // Check permissions
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(__('Insufficient permissions.', 'mfw'));
            }

            $tag = sanitize_text_field($_POST['tag'] ?? '');
            $atts = isset($_POST['attributes']) ? (array)$_POST['attributes'] : [];
            $content = wp_kses_post($_POST['content'] ?? '');

            if (!isset($this->shortcodes[$tag])) {
                wp_send_json_error(__('Invalid shortcode.', 'mfw'));
            }

            // Process shortcode
            $output = $this->process_shortcode($atts, $content, $tag);

            wp_send_json_success(['preview' => $output]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get registered shortcodes
     *
     * @param string $category Optional category filter
     * @return array Registered shortcodes
     */
    public function get_shortcodes($category = '') {
        if ($category) {
            return array_filter($this->shortcodes, function($shortcode) use ($category) {
                return $shortcode['category'] === $category;
            });
        }
        return $this->shortcodes;
    }

    /**
     * Clear shortcode cache
     *
     * @param string $tag Optional shortcode tag to clear specific cache
     * @return bool Success status
     */
    public function clear_cache($tag = '') {
        try {
            if ($tag) {
                $pattern = "mfw_shortcode_{$tag}_*";
                return $this->cache->delete_pattern($pattern, 'mfw_shortcodes');
            }
            return $this->cache->flush_group('mfw_shortcodes');

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to clear shortcode cache: %s', $e->getMessage()),
                'shortcode_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        wp_enqueue_style(
            'mfw-shortcode-preview',
            MFW_PLUGIN_URL . 'assets/css/shortcode-preview.css',
            [],
            MFW_VERSION
        );

        wp_enqueue_script(
            'mfw-shortcode-preview',
            MFW_PLUGIN_URL . 'assets/js/shortcode-preview.js',
            ['jquery'],
            MFW_VERSION,
            true
        );

        wp_localize_script('mfw-shortcode-preview', 'mfwShortcodes', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mfw_preview_shortcode'),
            'shortcodes' => $this->get_shortcodes()
        ]);
    }

    /**
     * Validate shortcode attributes
     *
     * @param array $atts Attributes to validate
     * @param array $rules Validation rules
     * @return bool Validation result
     */
    private function validate_attributes($atts, $rules) {
        foreach ($rules as $attr => $rule) {
            if (!isset($atts[$attr])) {
                continue;
            }

            $value = $atts[$attr];

            switch ($rule['type']) {
                case 'number':
                    if (!is_numeric($value) ||
                        (isset($rule['min']) && $value < $rule['min']) ||
                        (isset($rule['max']) && $value > $rule['max'])) {
                        return false;
                    }
                    break;

                case 'enum':
                    if (!in_array($value, $rule['values'])) {
                        return false;
                    }
                    break;

                case 'regex':
                    if (!preg_match($rule['pattern'], $value)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Generate cache key for shortcode
     *
     * @param string $tag Shortcode tag
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string Cache key
     */
    private function generate_cache_key($tag, $atts, $content) {
        return 'mfw_shortcode_' . md5($tag . serialize($atts) . $content);
    }

    /**
     * Log shortcode usage
     *
     * @param string $tag Shortcode tag
     * @param array $atts Shortcode attributes
     */
    private function log_shortcode_usage($tag, $atts) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_shortcode_log',
                [
                    'shortcode_tag' => $tag,
                    'attributes' => json_encode($atts),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log shortcode usage: %s', $e->getMessage()),
                'shortcode_handler',
                'error'
            );
        }
    }

    /**
     * Get error message
     *
     * @param string $code Error code
     * @return string Error message
     */
    private function get_error_message($code) {
        $messages = [
            'invalid_attributes' => __('Invalid shortcode attributes.', 'mfw'),
            'processing_error' => __('Error processing shortcode.', 'mfw')
        ];

        return isset($messages[$code]) ? 
               '<!-- MFW Shortcode Error: ' . $messages[$code] . ' -->' : 
               '';
    }
}