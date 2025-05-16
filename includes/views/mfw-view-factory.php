<?php
/**
 * View Factory Class
 * 
 * Handles view file resolution and loading.
 * Manages view paths and namespaces.
 *
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_View_Factory {
    /**
     * Factory initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 08:23:00';

    /**
     * Factory initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * View paths
     *
     * @var array
     */
    protected $paths = [];

    /**
     * View namespaces
     *
     * @var array
     */
    protected $namespaces = [];

    /**
     * View extensions
     *
     * @var array
     */
    protected $extensions = ['php', 'html'];

    /**
     * Create new factory instance
     */
    public function __construct() {
        // Add default view path
        $this->addPath(MFW_PLUGIN_DIR . 'views');
    }

    /**
     * Add view path
     *
     * @param string $path View path
     * @return void
     */
    public function addPath($path) {
        if (!in_array($path, $this->paths)) {
            $this->paths[] = $path;
        }
    }

    /**
     * Add view namespace
     *
     * @param string $namespace Namespace name
     * @param string|array $paths Namespace paths
     * @return void
     */
    public function addNamespace($namespace, $paths) {
        $this->namespaces[$namespace] = (array) $paths;
    }

    /**
     * Add view extension
     *
     * @param string $extension Extension name
     * @return void
     */
    public function addExtension($extension) {
        if (!in_array($extension, $this->extensions)) {
            $this->extensions[] = $extension;
        }
    }

    /**
     * Find view file
     *
     * @param string $name View name
     * @return string|null
     */
    public function find($name) {
        // Check if view uses namespace
        if (strpos($name, '::') !== false) {
            return $this->findNamespacedView($name);
        }

        // Check in view paths
        return $this->findInPaths($name, $this->paths);
    }

    /**
     * Find namespaced view
     *
     * @param string $name View name
     * @return string|null
     */
    protected function findNamespacedView($name) {
        list($namespace, $view) = explode('::', $name);

        if (!isset($this->namespaces[$namespace])) {
            throw new MFW_View_Exception("View namespace [{$namespace}] not found.");
        }

        return $this->findInPaths($view, $this->namespaces[$namespace]);
    }

    /**
     * Find view in paths
     *
     * @param string $name View name
     * @param array $paths Search paths
     * @return string|null
     */
    protected function findInPaths($name, $paths) {
        $name = str_replace('.', '/', $name);

        foreach ($paths as $path) {
            foreach ($this->extensions as $extension) {
                $file = $path . '/' . $name . '.' . $extension;
                if (file_exists($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Check if view exists
     *
     * @param string $name View name
     * @return bool
     */
    public function exists($name) {
        return (bool) $this->find($name);
    }

    /**
     * Get registered paths
     *
     * @return array
     */
    public function getPaths() {
        return $this->paths;
    }

    /**
     * Get registered namespaces
     *
     * @return array
     */
    public function getNamespaces() {
        return $this->namespaces;
    }

    /**
     * Get registered extensions
     *
     * @return array
     */
    public function getExtensions() {
        return $this->extensions;
    }

    /**
     * Register default namespaces
     *
     * @return void
     */
    public function registerDefaultNamespaces() {
        // Register admin views
        $this->addNamespace('admin', MFW_PLUGIN_DIR . 'views/admin');

        // Register mail views
        $this->addNamespace('mail', MFW_PLUGIN_DIR . 'views/mail');

        // Register component views
        $this->addNamespace('components', MFW_PLUGIN_DIR . 'views/components');

        // Register layout views
        $this->addNamespace('layouts', MFW_PLUGIN_DIR . 'views/layouts');

        // Register partial views
        $this->addNamespace('partials', MFW_PLUGIN_DIR . 'views/partials');
    }

    /**
     * Register view paths from active theme
     *
     * @return void
     */
    public function registerThemePaths() {
        // Add theme views directory
        $theme_views = get_stylesheet_directory() . '/mfw/views';
        if (is_dir($theme_views)) {
            $this->addPath($theme_views);
        }

        // Add parent theme views directory
        if (is_child_theme()) {
            $parent_views = get_template_directory() . '/mfw/views';
            if (is_dir($parent_views)) {
                $this->addPath($parent_views);
            }
        }
    }

    /**
     * Register view paths from active plugins
     *
     * @return void
     */
    public function registerPluginPaths() {
        $plugins = apply_filters('mfw_view_plugins', []);

        foreach ($plugins as $plugin) {
            if (is_string($plugin)) {
                $path = WP_PLUGIN_DIR . '/' . $plugin . '/views';
                if (is_dir($path)) {
                    $this->addPath($path);
                }
            } elseif (is_array($plugin) && isset($plugin['path'])) {
                if (is_dir($plugin['path'])) {
                    if (isset($plugin['namespace'])) {
                        $this->addNamespace($plugin['namespace'], $plugin['path']);
                    } else {
                        $this->addPath($plugin['path']);
                    }
                }
            }
        }
    }
}