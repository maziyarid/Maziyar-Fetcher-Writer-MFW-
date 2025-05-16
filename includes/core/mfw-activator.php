<?php
namespace MFW\Core;

class MFW_Activator {
    /**
     * Run activation tasks
     */
    public static function activate() {
        try {
            // Create necessary directories
            self::create_directories();
            
            // Create database tables
            self::create_database_tables();
            
            // Set default options
            self::set_default_options();
            
            // Register post types
            self::register_post_types();
            
            // Schedule cron jobs
            self::setup_cron_jobs();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
        } catch (\Exception $e) {
            error_log(sprintf('[MFW Activation Error] %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Create required directories
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $mfw_dir = $upload_dir['basedir'] . '/mfw';
        
        $dirs = [
            $mfw_dir,
            $mfw_dir . '/cache',
            $mfw_dir . '/logs',
            $mfw_dir . '/temp'
        ];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Create database tables
     */
    private static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix . 'mfw_';
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Content table
        $sql = "CREATE TABLE IF NOT EXISTS {$table_prefix}content (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            content_type varchar(50) NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            status varchar(20) DEFAULT 'draft',
            author_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY content_type (content_type),
            KEY status (status),
            KEY author_id (author_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Add other tables as needed...
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = [
            'mfw_core_settings' => [
                'api_key' => '',
                'api_url' => 'https://api.openai.com/v1',
                'model_settings' => [
                    'default_model' => 'gpt-3.5-turbo',
                    'temperature' => 0.7,
                    'max_tokens' => 2048
                ],
                'cache_settings' => [
                    'enabled' => true,
                    'expiration' => 3600
                ]
            ]
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }

    /**
     * Register post types
     */
    private static function register_post_types() {
        register_post_type('mfw_ai_content', [
            'labels' => [
                'name' => __('AI Content', 'mfw'),
                'singular_name' => __('AI Content', 'mfw')
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-text-page',
            'show_in_rest' => true
        ]);
    }

    /**
     * Setup cron jobs
     */
    private static function setup_cron_jobs() {
        if (!wp_next_scheduled('mfw_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'mfw_daily_maintenance');
        }
    }
}
