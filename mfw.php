<?php
/**
 * Plugin Name: Maziyar Fetcher Writer (MFW)
 * Plugin URI: your-plugin-uri
 * Description: AI-powered content generator
 * Version: 1.0.0
 * Author: Maziyar
 * Author URI: your-uri
 * License: GPL v2 or later
 * Text Domain: mfw
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

// Load autoloader
require_once plugin_dir_path(__FILE__) . 'includes/core/mfw-autoloader.php';
MFW\Core\Autoloader::register();

// Global plugin instance
$GLOBALS['mfw_plugin'] = null;

// Activation hook
register_activation_hook(__FILE__, function() {
    $initializer = new MFW\Core\PluginInitializer();
    $initializer->activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    $initializer = new MFW\Core\PluginInitializer();
    $initializer->deactivate();
});

// Initialize plugin
add_action('plugins_loaded', function() {
    try {
        $GLOBALS['mfw_plugin'] = new MFW\Core\PluginInitializer();
    } catch (\Exception $e) {
        error_log(sprintf('[MFW Error] Plugin initialization failed: %s', $e->getMessage()));
    }
});