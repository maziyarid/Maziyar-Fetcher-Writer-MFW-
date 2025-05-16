<?php
/**
 * MFW Constants
 *
 * @package MFW
 * @subpackage Config
 * @since 1.0.0
 */

namespace MFW\Config;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin Information
define('MFW_VERSION', '1.0.0');
define('MFW_DB_VERSION', '1.0.0');
define('MFW_REQUIRED_WP_VERSION', '5.8');
define('MFW_REQUIRED_PHP_VERSION', '7.4');

// Paths
define('MFW_PLUGIN_DIR', plugin_dir_path(dirname(__DIR__)));
define('MFW_PLUGIN_URL', plugin_dir_url(dirname(__DIR__)));
define('MFW_INCLUDES_DIR', MFW_PLUGIN_DIR . 'includes/');
define('MFW_ADMIN_DIR', MFW_PLUGIN_DIR . 'admin/');
define('MFW_ASSETS_DIR', MFW_PLUGIN_DIR . 'assets/');
define('MFW_VIEWS_DIR', MFW_INCLUDES_DIR . 'views/');
define('MFW_LANGUAGES_DIR', MFW_PLUGIN_DIR . 'languages/');

// URLs
define('MFW_ASSETS_URL', MFW_PLUGIN_URL . 'assets/');
define('MFW_CSS_URL', MFW_ASSETS_URL . 'css/');
define('MFW_JS_URL', MFW_ASSETS_URL . 'js/');
define('MFW_IMAGES_URL', MFW_ASSETS_URL . 'images/');

// Database Tables
define('MFW_TABLE_PREFIX', $wpdb->prefix . 'mfw_');
define('MFW_CONTENT_TABLE', MFW_TABLE_PREFIX . 'content');
define('MFW_ANALYTICS_TABLE', MFW_TABLE_PREFIX . 'analytics');
define('MFW_LOGS_TABLE', MFW_TABLE_PREFIX . 'logs');
define('MFW_TEMPLATES_TABLE', MFW_TABLE_PREFIX . 'templates');

// Cache Groups
define('MFW_CACHE_GROUP', 'mfw_cache');
define('MFW_TRANSIENT_PREFIX', 'mfw_');

// API
define('MFW_API_NAMESPACE', 'mfw/v1');
define('MFW_API_VERSION', '1.0.0');

// Misc
define('MFW_DEBUG', false);
define('MFW_MIN_PHP_VERSION', '7.4');
define('MFW_MIN_WP_VERSION', '5.8');