<?php
/**
 * Uninstall handler for MFW plugin
 * Cleans up plugin data when uninstalled
 *
 * @package MFW
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('mfw_core_settings');
delete_option('mfw_ai_helper_settings');
delete_option('mfw_data_settings');
delete_option('mfw_field_settings');
delete_option('mfw_metrics_settings');
delete_option('mfw_cache_settings');
delete_option('mfw_rate_limit_settings');
delete_option('mfw_retry_settings');
delete_option('mfw_validator_settings');
delete_option('mfw_sanitizer_settings');

// Remove custom tables
global $wpdb;
$tables = [
    $wpdb->prefix . 'mfw_content',
    $wpdb->prefix . 'mfw_analytics',
    $wpdb->prefix . 'mfw_logs',
    $wpdb->prefix . 'mfw_templates'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Clear cache
wp_cache_flush_group('mfw_ai_cache');

// Remove uploaded files
$upload_dir = wp_upload_dir();
$mfw_dir = $upload_dir['basedir'] . '/mfw';
if (is_dir($mfw_dir)) {
    array_map('unlink', glob("$mfw_dir/*.*"));
    rmdir($mfw_dir);
}