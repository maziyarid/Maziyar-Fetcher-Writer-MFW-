<?php
/**
 * Plugin Name: Maziyar Fetcher Writer (MFW)
 * Description: AI-powered content generator
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Maziyar
 * Text Domain: mfw
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MFW_VERSION', '1.0.0');
define('MFW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MFW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MFW_PLUGIN_FILE', __FILE__);
define('MFW_INCLUDES_DIR', MFW_PLUGIN_DIR . 'includes/');

// Load autoloader
require_once MFW_INCLUDES_DIR . 'core/mfw-autoloader.php';

// Initialize autoloader
if (class_exists('MFW\\Core\\Autoloader')) {
    MFW\Core\Autoloader::register();
} else {
    // Log error if autoloader class is not found
    error_log('[MFW Error] Autoloader class not found');
    return;
}

// Activation hook
register_activation_hook(__FILE__, 'mfw_activate_plugin');
function mfw_activate_plugin() {
    try {
        // Load activation class
        require_once MFW_INCLUDES_DIR . 'core/mfw-activator.php';
        
        // Run activation
        MFW_Activator::activate();
        
    } catch (Exception $e) {
        error_log(sprintf('[MFW Error] Activation failed: %s', $e->getMessage()));
        wp_die('Error activating MFW Plugin. Please check error logs for details.');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'mfw_deactivate_plugin');
function mfw_deactivate_plugin() {
    try {
        if (class_exists('MFW\\Core\\PluginInitializer')) {
            $initializer = new MFW\Core\PluginInitializer();
            $initializer->deactivate();
        }
    } catch (Exception $e) {
        error_log(sprintf('[MFW Error] Deactivation failed: %s', $e->getMessage()));
    }
}

// Initialize plugin on plugins_loaded to ensure all plugins are loaded
add_action('plugins_loaded', 'mfw_initialize_plugin');
function mfw_initialize_plugin() {
    try {
        // Check if all required classes exist
        if (!class_exists('MFW\\Core\\PluginInitializer')) {
            throw new Exception('Core classes not found');
        }
        
        // Initialize the plugin
        $GLOBALS['mfw_plugin'] = new MFW\Core\PluginInitializer();
        
    } catch (Exception $e) {
        // Log error and add admin notice
        error_log(sprintf('[MFW Error] Plugin initialization failed: %s', $e->getMessage()));
        add_action('admin_notices', function() use ($e) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(
                    __('MFW Plugin Error: %s', 'mfw'), 
                    $e->getMessage()
                ))
            );
        });
    }
}
