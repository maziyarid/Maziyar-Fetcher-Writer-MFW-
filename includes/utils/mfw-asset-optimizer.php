<?php
/**
 * Asset Optimizer Class
 *
 * Manages optimization and loading of plugin assets (CSS, JavaScript, images).
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Asset_Optimizer {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:49:32';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Asset types
     */
    const TYPE_CSS = 'css';
    const TYPE_JS = 'js';
    const TYPE_IMAGE = 'image';

    /**
     * Cache directory
     */
    private $cache_dir;

    /**
     * Initialize optimizer
     */
    public function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/cache/mfw-assets';
        
        // Create cache directory if it doesn't exist
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }

        // Add optimization hooks
        add_action('wp_enqueue_scripts', [$this, 'optimize_enqueued_assets'], 999);
        add_filter('script_loader_tag', [$this, 'add_async_defer_attributes'], 10, 2);
        add_filter('style_loader_tag', [$this, 'optimize_css_delivery'], 10, 4);
    }

    /**
     * Optimize asset
     *
     * @param string $file File path
     * @param string $type Asset type
     * @param array $options Optimization options
     * @return string|false Optimized asset URL or false on failure
     */
    public function optimize_asset($file, $type, $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'minify' => true,
                'combine' => true,
                'async' => false,
                'defer' => false,
                'preload' => false,
                'version' => null
            ]);

            // Generate cache key
            $cache_key = $this->generate_cache_key($file, $options);

            // Check if cached version exists
            $cached_file = $this->get_cached_file($cache_key, $type);
            if ($cached_file) {
                return $cached_file;
            }

            // Process based on type
            switch ($type) {
                case self::TYPE_CSS:
                    return $this->optimize_css($file, $cache_key, $options);
                
                case self::TYPE_JS:
                    return $this->optimize_js($file, $cache_key, $options);
                
                case self::TYPE_IMAGE:
                    return $this->optimize_image($file, $cache_key, $options);
                
                default:
                    throw new Exception(__('Invalid asset type.', 'mfw'));
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Asset optimization failed: %s', $e->getMessage()),
                'asset_optimizer',
                'error'
            );
            return false;
        }
    }

    /**
     * Optimize enqueued assets
     */
    public function optimize_enqueued_assets() {
        global $wp_scripts, $wp_styles;

        // Get plugin settings
        $settings_handler = new MFW_Settings_Handler();
        $optimization_settings = $settings_handler->get_setting('asset_optimization');

        if (empty($optimization_settings['enabled'])) {
            return;
        }

        // Optimize CSS
        if (!empty($optimization_settings['optimize_css'])) {
            $this->optimize_enqueued_styles($wp_styles, $optimization_settings);
        }

        // Optimize JavaScript
        if (!empty($optimization_settings['optimize_js'])) {
            $this->optimize_enqueued_scripts($wp_scripts, $optimization_settings);
        }
    }

    /**
     * Add async/defer attributes to script tags
     *
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @return string Modified script tag
     */
    public function add_async_defer_attributes($tag, $handle) {
        // Check if script should be async/deferred
        if (wp_scripts()->get_data($handle, 'async')) {
            $tag = str_replace(' src', ' async src', $tag);
        }
        if (wp_scripts()->get_data($handle, 'defer')) {
            $tag = str_replace(' src', ' defer src', $tag);
        }
        return $tag;
    }

    /**
     * Optimize CSS delivery
     *
     * @param string $tag Style tag
     * @param string $handle Style handle
     * @param string $href Style URL
     * @param string $media Media attribute
     * @return string Modified style tag
     */
    public function optimize_css_delivery($tag, $handle, $href, $media) {
        // Check if style should be preloaded
        if (wp_styles()->get_data($handle, 'preload')) {
            $preload_tag = sprintf(
                '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">',
                esc_url($href)
            );
            $noscript_tag = sprintf(
                '<noscript>%s</noscript>',
                $tag
            );
            return $preload_tag . $noscript_tag;
        }
        return $tag;
    }

    /**
     * Optimize CSS file
     *
     * @param string $file File path
     * @param string $cache_key Cache key
     * @param array $options Optimization options
     * @return string|false Optimized CSS URL or false on failure
     */
    private function optimize_css($file, $cache_key, $options) {
        try {
            $content = file_get_contents($file);

            if ($options['minify']) {
                // Remove comments
                $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
                // Remove whitespace
                $content = str_replace(["\r\n", "\r", "\n", "\t"], '', $content);
                $content = preg_replace('/\s+/', ' ', $content);
            }

            // Save optimized file
            $cache_file = $this->cache_dir . '/' . $cache_key . '.css';
            if (file_put_contents($cache_file, $content)) {
                return str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $cache_file);
            }

            return false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('CSS optimization failed: %s', $e->getMessage()),
                'asset_optimizer',
                'error'
            );
            return false;
        }
    }

    /**
     * Optimize JavaScript file
     *
     * @param string $file File path
     * @param string $cache_key Cache key
     * @param array $options Optimization options
     * @return string|false Optimized JavaScript URL or false on failure
     */
    private function optimize_js($file, $cache_key, $options) {
        try {
            $content = file_get_contents($file);

            if ($options['minify']) {
                // Remove comments
                $content = preg_replace('/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\'|\")\/\/.*))/', '', $content);
                // Remove whitespace
                $content = preg_replace('/\s+/', ' ', $content);
            }

            // Save optimized file
            $cache_file = $this->cache_dir . '/' . $cache_key . '.js';
            if (file_put_contents($cache_file, $content)) {
                return str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $cache_file);
            }

            return false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('JavaScript optimization failed: %s', $e->getMessage()),
                'asset_optimizer',
                'error'
            );
            return false;
        }
    }

    /**
     * Optimize image file
     *
     * @param string $file File path
     * @param string $cache_key Cache key
     * @param array $options Optimization options
     * @return string|false Optimized image URL or false on failure
     */
    private function optimize_image($file, $cache_key, $options) {
        try {
            // Get image information
            $info = getimagesize($file);
            if (!$info) {
                throw new Exception(__('Invalid image file.', 'mfw'));
            }

            // Create image resource
            switch ($info[2]) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($file);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($file);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($file);
                    break;
                default:
                    throw new Exception(__('Unsupported image type.', 'mfw'));
            }

            // Optimize image
            $cache_file = $this->cache_dir . '/' . $cache_key . '.' . image_type_to_extension($info[2], false);
            
            switch ($info[2]) {
                case IMAGETYPE_JPEG:
                    imagejpeg($image, $cache_file, 85); // 85% quality
                    break;
                case IMAGETYPE_PNG:
                    imagepng($image, $cache_file, 9); // Maximum compression
                    break;
                case IMAGETYPE_GIF:
                    imagegif($image, $cache_file);
                    break;
            }

            imagedestroy($image);

            return str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $cache_file);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Image optimization failed: %s', $e->getMessage()),
                'asset_optimizer',
                'error'
            );
            return false;
        }
    }

    /**
     * Generate cache key for asset
     *
     * @param string $file File path
     * @param array $options Optimization options
     * @return string Cache key
     */
    private function generate_cache_key($file, $options) {
        $data = [
            'file' => $file,
            'options' => $options,
            'version' => $options['version'] ?? filemtime($file)
        ];
        return md5(serialize($data));
    }

    /**
     * Get cached file if exists and valid
     *
     * @param string $cache_key Cache key
     * @param string $type Asset type
     * @return string|false Cached file URL or false if not found/invalid
     */
    private function get_cached_file($cache_key, $type) {
        $extensions = [
            self::TYPE_CSS => '.css',
            self::TYPE_JS => '.js',
            self::TYPE_IMAGE => '.*'
        ];

        $pattern = $this->cache_dir . '/' . $cache_key . $extensions[$type];
        $files = glob($pattern);

        if (!empty($files) && file_exists($files[0])) {
            return str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $files[0]);
        }

        return false;
    }
}