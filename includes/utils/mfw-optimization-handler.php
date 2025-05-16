<?php
/**
 * Optimization Handler Class
 * 
 * Manages performance optimization, caching, and resource management.
 * Includes methods for code minification, asset optimization, and database optimization.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Optimization_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:20:43';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Cache handler instance
     */
    private $cache;

    /**
     * Optimization settings
     */
    private $settings;

    /**
     * Initialize optimization handler
     */
    public function __construct() {
        $this->cache = new MFW_Cache_Handler();
        $this->settings = get_option('mfw_optimization_settings', []);

        // Add optimization hooks
        add_action('init', [$this, 'init_optimization']);
        add_action('admin_init', [$this, 'register_optimization_settings']);
        add_action('wp_enqueue_scripts', [$this, 'optimize_assets'], 999);
        add_action('mfw_optimize_database', [$this, 'optimize_database']);

        // Schedule optimization tasks
        if (!wp_next_scheduled('mfw_optimize_database')) {
            wp_schedule_event(time(), 'weekly', 'mfw_optimize_database');
        }
    }

    /**
     * Initialize optimization features
     */
    public function init_optimization() {
        // Enable output buffering for HTML minification
        if (!empty($this->settings['minify_html'])) {
            ob_start([$this, 'minify_html']);
        }

        // Set up cache headers if enabled
        if (!empty($this->settings['browser_caching'])) {
            $this->setup_cache_headers();
        }

        // Initialize lazy loading
        if (!empty($this->settings['lazy_loading'])) {
            add_filter('the_content', [$this, 'add_lazy_loading']);
        }
    }

    /**
     * Optimize assets (CSS/JS)
     */
    public function optimize_assets() {
        // Skip optimization for admin
        if (is_admin()) {
            return;
        }

        // Combine and minify CSS
        if (!empty($this->settings['optimize_css'])) {
            $this->optimize_css();
        }

        // Combine and minify JavaScript
        if (!empty($this->settings['optimize_js'])) {
            $this->optimize_js();
        }

        // Defer non-critical JavaScript
        if (!empty($this->settings['defer_js'])) {
            add_filter('script_loader_tag', [$this, 'defer_js'], 10, 3);
        }
    }

    /**
     * Optimize database
     */
    public function optimize_database() {
        try {
            global $wpdb;

            // Start time
            $start_time = microtime(true);

            // Clean post revisions
            if (!empty($this->settings['clean_revisions'])) {
                $wpdb->query(
                    "DELETE FROM {$wpdb->posts} 
                    WHERE post_type = 'revision'"
                );
            }

            // Clean auto drafts
            if (!empty($this->settings['clean_autodrafts'])) {
                $wpdb->query(
                    "DELETE FROM {$wpdb->posts} 
                    WHERE post_status = 'auto-draft'"
                );
            }

            // Clean trashed posts
            if (!empty($this->settings['clean_trash'])) {
                $wpdb->query(
                    "DELETE FROM {$wpdb->posts} 
                    WHERE post_status = 'trash'"
                );
            }

            // Clean post meta
            if (!empty($this->settings['clean_postmeta'])) {
                $wpdb->query(
                    "DELETE pm FROM {$wpdb->postmeta} pm
                    LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE p.ID IS NULL"
                );
            }

            // Optimize tables
            if (!empty($this->settings['optimize_tables'])) {
                $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
                foreach ($tables as $table) {
                    $wpdb->query("OPTIMIZE TABLE {$table}");
                }
            }

            // Calculate execution time
            $execution_time = microtime(true) - $start_time;

            // Log optimization
            $this->log_optimization([
                'type' => 'database',
                'execution_time' => $execution_time
            ]);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Database optimization failed: %s', $e->getMessage()),
                'optimization_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Minify HTML content
     *
     * @param string $content HTML content
     * @return string Minified HTML
     */
    public function minify_html($content) {
        if (is_admin() || empty($content)) {
            return $content;
        }

        // Remove comments (except IE conditional comments)
        $content = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $content);

        // Remove whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/>\s+</', '><', $content);
        $content = trim($content);

        return $content;
    }

    /**
     * Optimize CSS
     */
    private function optimize_css() {
        global $wp_styles;

        // Get all enqueued styles
        $styles = [];
        foreach ($wp_styles->queue as $handle) {
            $styles[] = $wp_styles->registered[$handle];
        }

        // Generate cache key
        $cache_key = 'mfw_optimized_css_' . md5(serialize($styles));

        // Check cache
        $cached = $this->cache->get($cache_key, 'mfw_assets');
        if ($cached === false) {
            $combined = '';

            foreach ($styles as $style) {
                // Get file content
                $file_path = ABSPATH . str_replace(site_url(), '', $style->src);
                if (file_exists($file_path)) {
                    $css = file_get_contents($file_path);
                    
                    // Minify CSS
                    $css = $this->minify_css($css);
                    
                    $combined .= $css . "\n";
                }
            }

            // Cache combined CSS
            $this->cache->set($cache_key, $combined, 'mfw_assets', WEEK_IN_SECONDS);
        } else {
            $combined = $cached;
        }

        // Deregister original styles
        foreach ($styles as $style) {
            wp_deregister_style($style->handle);
        }

        // Register combined style
        wp_register_style('mfw-optimized-css', false);
        wp_enqueue_style('mfw-optimized-css');
        wp_add_inline_style('mfw-optimized-css', $combined);
    }

    /**
     * Optimize JavaScript
     */
    private function optimize_js() {
        global $wp_scripts;

        // Get all enqueued scripts
        $scripts = [];
        foreach ($wp_scripts->queue as $handle) {
            $scripts[] = $wp_scripts->registered[$handle];
        }

        // Generate cache key
        $cache_key = 'mfw_optimized_js_' . md5(serialize($scripts));

        // Check cache
        $cached = $this->cache->get($cache_key, 'mfw_assets');
        if ($cached === false) {
            $combined = '';

            foreach ($scripts as $script) {
                // Skip if script is in footer
                if ($script->extra['group'] === 1) {
                    continue;
                }

                // Get file content
                $file_path = ABSPATH . str_replace(site_url(), '', $script->src);
                if (file_exists($file_path)) {
                    $js = file_get_contents($file_path);
                    
                    // Minify JavaScript
                    $js = $this->minify_js($js);
                    
                    $combined .= $js . ";\n";
                }
            }

            // Cache combined JavaScript
            $this->cache->set($cache_key, $combined, 'mfw_assets', WEEK_IN_SECONDS);
        } else {
            $combined = $cached;
        }

        // Deregister original scripts
        foreach ($scripts as $script) {
            if ($script->extra['group'] !== 1) {
                wp_deregister_script($script->handle);
            }
        }

        // Register combined script
        wp_register_script('mfw-optimized-js', false);
        wp_enqueue_script('mfw-optimized-js');
        wp_add_inline_script('mfw-optimized-js', $combined);
    }

    /**
     * Add lazy loading to images
     *
     * @param string $content Post content
     * @return string Modified content
     */
    private function add_lazy_loading($content) {
        if (empty($content)) {
            return $content;
        }

        // Add lazy loading to images
        $content = preg_replace_callback('/<img([^>]+)>/i', function($matches) {
            // Skip if already has loading attribute
            if (strpos($matches[1], 'loading=') !== false) {
                return $matches[0];
            }

            return '<img' . $matches[1] . ' loading="lazy">';
        }, $content);

        return $content;
    }

    /**
     * Minify CSS
     *
     * @param string $css CSS content
     * @return string Minified CSS
     */
    private function minify_css($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*(,|:|;|\{|\})\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);
        
        return trim($css);
    }

    /**
     * Minify JavaScript
     *
     * @param string $js JavaScript content
     * @return string Minified JavaScript
     */
    private function minify_js($js) {
        // Remove comments
        $js = preg_replace('/\/\*[\s\S]*?\*\/|([^:]|^)\/\/.*$/m', '', $js);
        
        // Remove whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        return trim($js);
    }

    /**
     * Setup cache headers
     */
    private function setup_cache_headers() {
        $expires = 60 * 60 * 24 * 7; // 1 week
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expires) . " GMT");
        header("Cache-Control: public, max-age={$expires}");
        header("Pragma: cache");
    }

    /**
     * Log optimization
     *
     * @param array $data Optimization data
     */
    private function log_optimization($data) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_optimization_log',
                [
                    'type' => $data['type'],
                    'details' => json_encode($data),
                    'execution_time' => $data['execution_time'],
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%f', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log optimization: %s', $e->getMessage()),
                'optimization_handler',
                'error'
            );
        }
    }
}