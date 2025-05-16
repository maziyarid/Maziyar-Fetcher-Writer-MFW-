<?php
/**
 * Performance Handler Class
 * 
 * Manages performance optimization and monitoring.
 * Handles caching, optimization, and performance tracking.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Performance_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:46:23';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Performance settings
     */
    private $settings;

    /**
     * Performance metrics
     */
    private $metrics = [];

    /**
     * Query monitor
     */
    private $query_monitor;

    /**
     * Initialize performance handler
     */
    public function __construct() {
        // Load settings
        $this->settings = get_option('mfw_performance_settings', [
            'enable_monitoring' => true,
            'query_logging' => true,
            'memory_limit' => '256M',
            'max_execution_time' => 30,
            'optimize_images' => true,
            'minify_assets' => true,
            'enable_gzip' => true,
            'browser_caching' => true
        ]);

        // Start monitoring
        if ($this->settings['enable_monitoring']) {
            $this->start_monitoring();
        }

        // Add performance hooks
        add_action('init', [$this, 'init_optimization']);
        add_action('shutdown', [$this, 'save_metrics']);
        add_filter('script_loader_src', [$this, 'optimize_assets'], 10, 2);
        add_filter('style_loader_src', [$this, 'optimize_assets'], 10, 2);
    }

    /**
     * Initialize optimization features
     */
    public function init_optimization() {
        // Set PHP limits
        $this->set_php_limits();

        // Initialize query monitoring
        if ($this->settings['query_logging']) {
            $this->init_query_monitor();
        }

        // Add optimization filters
        if ($this->settings['optimize_images']) {
            add_filter('wp_handle_upload', [$this, 'optimize_uploaded_image']);
        }

        if ($this->settings['minify_assets']) {
            add_filter('script_loader_tag', [$this, 'minify_script'], 10, 3);
            add_filter('style_loader_tag', [$this, 'minify_style'], 10, 4);
        }

        if ($this->settings['enable_gzip']) {
            $this->enable_gzip_compression();
        }

        if ($this->settings['browser_caching']) {
            $this->setup_browser_caching();
        }
    }

    /**
     * Start performance monitoring
     */
    public function start_monitoring() {
        // Record start time
        $this->metrics['start_time'] = microtime(true);
        $this->metrics['start_memory'] = memory_get_usage();

        // Add monitoring hooks
        add_action('shutdown', [$this, 'record_end_metrics']);
        
        if ($this->settings['query_logging']) {
            add_filter('query', [$this, 'log_query']);
        }
    }

    /**
     * Record end metrics
     */
    public function record_end_metrics() {
        $this->metrics['end_time'] = microtime(true);
        $this->metrics['end_memory'] = memory_get_usage();
        $this->metrics['peak_memory'] = memory_get_peak_usage();
        $this->metrics['execution_time'] = $this->metrics['end_time'] - $this->metrics['start_time'];
        $this->metrics['memory_usage'] = $this->metrics['end_memory'] - $this->metrics['start_memory'];
    }

    /**
     * Optimize assets
     *
     * @param string $src Asset source URL
     * @param string $handle Asset handle
     * @return string Modified source URL
     */
    public function optimize_assets($src, $handle) {
        if (empty($src)) {
            return $src;
        }

        try {
            // Add version parameter for cache busting
            $src = add_query_arg('ver', $this->get_asset_version($src), $src);

            // Add CDN URL if configured
            if ($cdn_url = $this->get_cdn_url()) {
                $src = str_replace(site_url(), $cdn_url, $src);
            }

            return $src;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Asset optimization failed: %s', $e->getMessage()),
                'performance_handler',
                'error'
            );
            return $src;
        }
    }

    /**
     * Optimize uploaded image
     *
     * @param array $upload Upload data
     * @return array Modified upload data
     */
    public function optimize_uploaded_image($upload) {
        if (!wp_attachment_is_image($upload['file'])) {
            return $upload;
        }

        try {
            $file = $upload['file'];
            $mime_type = $upload['type'];

            // Optimize image based on mime type
            switch ($mime_type) {
                case 'image/jpeg':
                    $this->optimize_jpeg($file);
                    break;
                case 'image/png':
                    $this->optimize_png($file);
                    break;
                case 'image/gif':
                    $this->optimize_gif($file);
                    break;
            }

            return $upload;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Image optimization failed: %s', $e->getMessage()),
                'performance_handler',
                'error'
            );
            return $upload;
        }
    }

    /**
     * Minify script tag
     *
     * @param string $tag Script HTML tag
     * @param string $handle Script handle
     * @param string $src Script source
     * @return string Modified script tag
     */
    public function minify_script($tag, $handle, $src) {
        try {
            // Skip minification for specific handles
            if (in_array($handle, $this->get_minification_exclusions())) {
                return $tag;
            }

            // Get minified version
            $minified_src = $this->get_minified_url($src, 'js');
            
            // Replace source in tag
            return str_replace($src, $minified_src, $tag);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Script minification failed: %s', $e->getMessage()),
                'performance_handler',
                'error'
            );
            return $tag;
        }
    }

    /**
     * Save performance metrics
     */
    public function save_metrics() {
        try {
            if (!$this->settings['enable_monitoring']) {
                return;
            }

            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_performance_log',
                [
                    'page_url' => $_SERVER['REQUEST_URI'],
                    'execution_time' => $this->metrics['execution_time'],
                    'memory_usage' => $this->metrics['memory_usage'],
                    'peak_memory' => $this->metrics['peak_memory'],
                    'query_count' => $this->get_query_count(),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%f', '%d', '%d', '%d', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to save performance metrics: %s', $e->getMessage()),
                'performance_handler',
                'error'
            );
        }
    }

    /**
     * Set PHP limits
     */
    private function set_php_limits() {
        @ini_set('memory_limit', $this->settings['memory_limit']);
        @ini_set('max_execution_time', $this->settings['max_execution_time']);
    }

    /**
     * Initialize query monitor
     */
    private function init_query_monitor() {
        $this->query_monitor = new MFW_Query_Monitor();
        add_filter('query', [$this->query_monitor, 'log_query']);
    }

    /**
     * Enable GZIP compression
     */
    private function enable_gzip_compression() {
        if (!headers_sent() && extension_loaded('zlib')) {
            ob_start('ob_gzhandler');
        }
    }

    /**
     * Setup browser caching
     */
    private function setup_browser_caching() {
        $headers = [
            'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
            'Cache-Control' => 'public, max-age=31536000',
            'Pragma' => 'public'
        ];

        foreach ($headers as $header => $value) {
            header("$header: $value");
        }
    }

    /**
     * Get asset version
     *
     * @param string $src Asset source URL
     * @return string Version string
     */
    private function get_asset_version($src) {
        $file_path = str_replace(
            site_url('/'),
            ABSPATH,
            $src
        );

        return file_exists($file_path) ? filemtime($file_path) : MFW_VERSION;
    }

    /**
     * Get CDN URL
     *
     * @return string|false CDN URL or false if not configured
     */
    private function get_cdn_url() {
        return get_option('mfw_cdn_url', false);
    }

    /**
     * Get minification exclusions
     *
     * @return array List of handles to exclude from minification
     */
    private function get_minification_exclusions() {
        return apply_filters('mfw_minification_exclusions', [
            'jquery',
            'jquery-core',
            'jquery-migrate'
        ]);
    }

    /**
     * Get query count
     *
     * @return int Number of queries executed
     */
    private function get_query_count() {
        return $this->query_monitor ? $this->query_monitor->get_query_count() : 0;
    }

    /**
     * Log performance warning
     *
     * @param string $message Warning message
     * @param array $context Additional context
     */
    private function log_performance_warning($message, $context = []) {
        MFW_Error_Logger::log(
            $message,
            'performance_handler',
            'warning',
            $context
        );
    }
}