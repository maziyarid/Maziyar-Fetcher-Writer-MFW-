<?php
/**
 * File: includes/core/mfw-activator.php
 * Handles plugin activation: registers CPTs, default options, and cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure installer is available
require_once plugin_dir_path( __FILE__ ) . 'mfw-installer.php';

class MFW_Activator {

    /**
     * Fired on plugin activation:
     * - Registers the mfw_source CPT
     * - Creates default plugin settings
     * - Schedules the hourly fetch event
     */
    public static function activate() {
        // 1) Register custom post types via installer
        MFW_Installer::register_post_types();

        // 2) Create default settings if they don't exist
        MFW_Installer::create_default_settings();

        // 3) Schedule hourly fetch, if not already scheduled
        if ( ! wp_next_scheduled( 'mfw_hourly_fetch' ) ) {
            wp_schedule_event( time(), 'hourly', 'mfw_hourly_fetch' );
        }
    }
}
