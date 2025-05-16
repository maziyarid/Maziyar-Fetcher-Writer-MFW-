<?php
/**
 * Module Abstract Class
 * 
 * Provides base functionality for all modules.
 * Handles module lifecycle, configuration, and dependencies.
 *
 * @package MFW
 * @subpackage Abstracts
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Abstract_Module extends MFW_Abstract_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:24:46';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Module configuration
     */
    protected $config = [
        'name' => '',
        'description' => '',
        'version' => '1.0.0',
        'author' => '',
        'requires' => [],
        'autoload' => true
    ];

    /**
     * Module status
     */
    protected $status = 'inactive';

    /**
     * Module dependencies
     */
    protected $dependencies = [];

    /**
     * Module settings
     */
    protected $settings = [];

    /**
     * Initialize module
     */
    protected function init() {
        // Set module configuration
        $this->setup();

        // Load module settings
        $this->load_settings();

        // Check dependencies
        if ($this->check_dependencies()) {
            // Register module hooks
            $this->register_hooks();

            // Set module as active
            $this->status = 'active';
        }
    }

    /**
     * Setup module configuration
     * Must be implemented by child classes
     */
    abstract protected function setup();

    /**
     * Register module hooks
     * Must be implemented by child classes
     */
    abstract protected function register_hooks();

    /**
     * Get module status
     *
     * @return string Module status
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get module config
     *
     * @param string $key Config key
     * @param mixed $default Default value
     * @return mixed Config value
     */
    public function get_config($key = null, $default = null) {
        if ($key === null) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Get module setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Update module setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Whether update was successful
     */
    public function update_setting($key, $value) {
        try {
            $this->settings[$key] = $value;
            return $this->save_settings();
        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Load module settings
     */
    protected function load_settings() {
        $settings = get_option('mfw_module_' . $this->id . '_settings', []);
        $this->settings = wp_parse_args($settings, $this->get_default_settings());
    }

    /**
     * Save module settings
     *
     * @return bool Whether save was successful
     */
    protected function save_settings() {
        try {
            $result = update_option('mfw_module_' . $this->id . '_settings', $this->settings);

            if ($result) {
                // Log settings update
                $this->log_settings_update();
            }

            return $result;

        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get default settings
     *
     * @return array Default settings
     */
    protected function get_default_settings() {
        return [];
    }

    /**
     * Check module dependencies
     *
     * @return bool Whether dependencies are met
     */
    protected function check_dependencies() {
        foreach ($this->config['requires'] as $dependency) {
            if (!$this->check_dependency($dependency)) {
                $this->add_error(
                    sprintf(
                        __('Missing required dependency: %s', 'mfw'),
                        $dependency['name']
                    ),
                    'dependency_error'
                );
                return false;
            }
        }
        return true;
    }

    /**
     * Check single dependency
     *
     * @param array $dependency Dependency configuration
     * @return bool Whether dependency is met
     */
    protected function check_dependency($dependency) {
        if (empty($dependency['type']) || empty($dependency['name'])) {
            return false;
        }

        switch ($dependency['type']) {
            case 'plugin':
                return $this->check_plugin_dependency($dependency);

            case 'module':
                return $this->check_module_dependency($dependency);

            case 'php':
                return $this->check_php_dependency($dependency);

            case 'wordpress':
                return $this->check_wordpress_dependency($dependency);

            default:
                return false;
        }
    }

    /**
     * Check plugin dependency
     *
     * @param array $dependency Plugin dependency configuration
     * @return bool Whether dependency is met
     */
    protected function check_plugin_dependency($dependency) {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = $dependency['file'] ?? $dependency['name'];
        return is_plugin_active($plugin_file);
    }

    /**
     * Check module dependency
     *
     * @param array $dependency Module dependency configuration
     * @return bool Whether dependency is met
     */
    protected function check_module_dependency($dependency) {
        $module = MFW()->modules->get($dependency['name']);
        if (!$module) {
            return false;
        }

        if (isset($dependency['version'])) {
            return version_compare($module->get_config('version'), $dependency['version'], '>=');
        }

        return $module->get_status() === 'active';
    }

    /**
     * Check PHP dependency
     *
     * @param array $dependency PHP dependency configuration
     * @return bool Whether dependency is met
     */
    protected function check_php_dependency($dependency) {
        if (isset($dependency['version'])) {
            return version_compare(PHP_VERSION, $dependency['version'], '>=');
        }

        if (isset($dependency['extension'])) {
            return extension_loaded($dependency['extension']);
        }

        return false;
    }

    /**
     * Check WordPress dependency
     *
     * @param array $dependency WordPress dependency configuration
     * @return bool Whether dependency is met
     */
    protected function check_wordpress_dependency($dependency) {
        global $wp_version;
        return version_compare($wp_version, $dependency['version'], '>=');
    }

    /**
     * Log settings update
     */
    protected function log_settings_update() {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_module_log',
                [
                    'module_id' => $this->id,
                    'action' => 'settings_update',
                    'data' => json_encode($this->settings),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            $this->log(
                sprintf('Failed to log settings update: %s', $e->getMessage()),
                'error'
            );
        }
    }
}