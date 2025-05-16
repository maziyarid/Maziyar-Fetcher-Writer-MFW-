<?php
/**
 * Template Handler Class
 * 
 * Manages template loading, rendering, and caching for the plugin.
 * Supports template overrides in theme directory.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Template_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:52:12';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Template paths
     */
    private $template_paths = [];

    /**
     * Template cache
     */
    private $template_cache = [];

    /**
     * Initialize template handler
     */
    public function __construct() {
        // Set default template paths
        $this->template_paths = [
            'theme' => get_stylesheet_directory() . '/mfw-templates/',
            'plugin' => MFW_PLUGIN_DIR . 'templates/'
        ];

        // Allow template path modification
        $this->template_paths = apply_filters('mfw_template_paths', $this->template_paths);

        // Create theme template directory if it doesn't exist
        if (!file_exists($this->template_paths['theme'])) {
            wp_mkdir_p($this->template_paths['theme']);
        }
    }

    /**
     * Get template
     *
     * @param string $template Template name
     * @param array $data Template data
     * @param bool $return Whether to return or output
     * @return string|void Template content if return is true
     */
    public function get_template($template, $data = [], $return = false) {
        try {
            // Locate template file
            $template_file = $this->locate_template($template);
            if (!$template_file) {
                throw new Exception(sprintf(
                    __('Template file not found: %s', 'mfw'),
                    $template
                ));
            }

            // Extract data to make it available in template
            if (!empty($data)) {
                extract($data);
            }

            if ($return) {
                ob_start();
            }

            // Include template file
            include $template_file;

            if ($return) {
                return ob_get_clean();
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get template: %s', $e->getMessage()),
                'template_handler',
                'error'
            );

            if ($return) {
                return '';
            }
        }
    }

    /**
     * Render template part
     *
     * @param string $slug Template slug
     * @param string $name Template name
     * @param array $data Template data
     * @return void
     */
    public function get_template_part($slug, $name = '', $data = []) {
        try {
            $template = '';

            // Look for template with name
            if ($name) {
                $template = $this->locate_template("{$slug}-{$name}.php");
            }

            // Fall back to default template
            if (!$template) {
                $template = $this->locate_template("{$slug}.php");
            }

            if ($template) {
                $this->get_template($template, $data);
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get template part: %s', $e->getMessage()),
                'template_handler',
                'error'
            );
        }
    }

    /**
     * Cache template
     *
     * @param string $template Template name
     * @param array $data Template data
     * @param int $expiry Cache expiry in seconds
     * @return string Cached template content
     */
    public function cache_template($template, $data = [], $expiry = 3600) {
        try {
            $cache_key = $this->generate_cache_key($template, $data);

            // Check cache
            $cached = $this->get_cached_template($cache_key);
            if ($cached !== false) {
                return $cached;
            }

            // Generate template content
            $content = $this->get_template($template, $data, true);

            // Cache template
            $this->cache_template_content($cache_key, $content, $expiry);

            return $content;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to cache template: %s', $e->getMessage()),
                'template_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Clear template cache
     *
     * @param string $template Template name (optional)
     * @return bool Success status
     */
    public function clear_cache($template = '') {
        try {
            global $wpdb;

            if ($template) {
                // Clear specific template cache
                $wpdb->delete(
                    $wpdb->prefix . 'mfw_template_cache',
                    ['template_name' => $template],
                    ['%s']
                );
            } else {
                // Clear all template cache
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mfw_template_cache");
            }

            // Clear memory cache
            $this->template_cache = [];

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to clear template cache: %s', $e->getMessage()),
                'template_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Copy templates to theme
     *
     * @param array $templates Templates to copy
     * @return array Copy results
     */
    public function copy_templates_to_theme($templates = []) {
        try {
            $results = [
                'success' => [],
                'failed' => []
            ];

            // If no specific templates, copy all
            if (empty($templates)) {
                $templates = $this->get_plugin_templates();
            }

            foreach ($templates as $template) {
                $source = $this->template_paths['plugin'] . $template;
                $destination = $this->template_paths['theme'] . $template;

                // Create destination directory if needed
                $dest_dir = dirname($destination);
                if (!file_exists($dest_dir)) {
                    wp_mkdir_p($dest_dir);
                }

                // Copy template file
                if (copy($source, $destination)) {
                    $results['success'][] = $template;
                } else {
                    $results['failed'][] = $template;
                }
            }

            // Log copy operation
            $this->log_template_copy($results);

            return $results;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to copy templates: %s', $e->getMessage()),
                'template_handler',
                'error'
            );
            return [
                'success' => [],
                'failed' => $templates
            ];
        }
    }

    /**
     * Locate template file
     *
     * @param string $template Template name
     * @return string|false Template path or false if not found
     */
    private function locate_template($template) {
        // Check theme directory first
        if (file_exists($this->template_paths['theme'] . $template)) {
            return $this->template_paths['theme'] . $template;
        }

        // Fall back to plugin directory
        if (file_exists($this->template_paths['plugin'] . $template)) {
            return $this->template_paths['plugin'] . $template;
        }

        return false;
    }

    /**
     * Generate cache key
     *
     * @param string $template Template name
     * @param array $data Template data
     * @return string Cache key
     */
    private function generate_cache_key($template, $data) {
        return md5($template . serialize($data));
    }

    /**
     * Get cached template
     *
     * @param string $cache_key Cache key
     * @return string|false Cached content or false if not found
     */
    private function get_cached_template($cache_key) {
        try {
            // Check memory cache first
            if (isset($this->template_cache[$cache_key])) {
                return $this->template_cache[$cache_key];
            }

            global $wpdb;

            // Check database cache
            $cached = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mfw_template_cache
                WHERE cache_key = %s AND expiry > %s",
                $cache_key,
                $this->current_time
            ));

            if ($cached) {
                // Store in memory cache
                $this->template_cache[$cache_key] = $cached->content;
                return $cached->content;
            }

            return false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get cached template: %s', $e->getMessage()),
                'template_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Cache template content
     *
     * @param string $cache_key Cache key
     * @param string $content Template content
     * @param int $expiry Cache expiry in seconds
     */
    private function cache_template_content($cache_key, $content, $expiry) {
        try {
            global $wpdb;

            // Store in database
            $wpdb->replace(
                $wpdb->prefix . 'mfw_template_cache',
                [
                    'cache_key' => $cache_key,
                    'content' => $content,
                    'expiry' => date('Y-m-d H:i:s', strtotime($this->current_time) + $expiry),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

            // Store in memory cache
            $this->template_cache[$cache_key] = $content;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to cache template content: %s', $e->getMessage()),
                'template_handler',
                'error'
            );
        }
    }

    /**
     * Log template copy operation
     *
     * @param array $results Copy results
     */
    private function log_template_copy($results) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_template_log',
                [
                    'operation' => 'copy_to_theme',
                    'details' => json_encode($results),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log template copy: %s', $e->getMessage()),
                'template_handler',
                'error'
            );
        }
    }
}