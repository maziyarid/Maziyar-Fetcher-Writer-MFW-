<?php
namespace MFW\Core;

class DataManager {
    private $db;
    private $cache;
    private $settings;
    private $table_prefix;

    public function __construct() {
        try {
            global $wpdb;
            $this->db = $wpdb;
            
            // Initialize settings and table prefix first
            $this->settings = get_option('mfw_data_settings', []);
            $this->table_prefix = $this->db->prefix . 'mfw_';

            // Initialize cache handler
            $this->initialize_cache_handler();

        } catch (\Exception $e) {
            $this->log_error('Initialization failed', $e);
            throw $e;
        }
    }

    /**
     * Create database tables
     */
    public function create_database_tables() {
        try {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            // Create content table
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table_prefix}content (
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
            ) {$this->db->get_charset_collate()};";
            
            dbDelta($sql);

            // Create templates table
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table_prefix}templates (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                template_name varchar(100) NOT NULL,
                template_content longtext NOT NULL,
                category varchar(50) DEFAULT 'general',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY template_name (template_name),
                KEY category (category)
            ) {$this->db->get_charset_collate()};";
            
            dbDelta($sql);

            // Create metrics table
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table_prefix}metrics (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                metric_type varchar(50) NOT NULL,
                metric_value text NOT NULL,
                target_id bigint(20) unsigned DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY metric_type (metric_type),
                KEY target_id (target_id)
            ) {$this->db->get_charset_collate()};";
            
            dbDelta($sql);

            // Create logs table
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table_prefix}logs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                log_type varchar(50) NOT NULL,
                message text NOT NULL,
                context text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY log_type (log_type)
            ) {$this->db->get_charset_collate()};";
            
            dbDelta($sql);

            return true;
        } catch (\Exception $e) {
            $this->log_error('Database tables creation failed', $e);
            return false;
        }
    }

    /**
     * Initialize cache handler
     */
    private function initialize_cache_handler() {
        // Check if we can use WordPress Object Cache
        if (wp_using_ext_object_cache()) {
            $this->cache = wp_cache_get_multiple([], 'mfw');
            return;
        }

        // Fallback to transients if no external cache is available
        $this->cache = new class {
            public function set($key, $value, $expiration = 3600) {
                return set_transient('mfw_' . $key, $value, $expiration);
            }

            public function get($key) {
                return get_transient('mfw_' . $key);
            }

            public function delete($key) {
                return delete_transient('mfw_' . $key);
            }

            public function flush() {
                global $wpdb;
                return $wpdb->query(
                    "DELETE FROM $wpdb->options WHERE option_name LIKE '%mfw_%'"
                );
            }
        };
    }

    /**
     * Initialize the data manager
     */
    public function initialize() {
        try {
            // Set up hooks
            add_action('init', [$this, 'register_data_handlers']);
            add_action('admin_init', [$this, 'register_data_settings']);

            // Initialize caching
            $this->initialize_cache();

            return true;
        } catch (\Exception $e) {
            $this->log_error('Initialization failed', $e);
            return false;
        }
    }

    /**
     * Initialize caching system
     */
    private function initialize_cache() {
        if ($this->cache) {
            $this->cache->set('mfw_data_initialized', true);
            $this->cache->set('mfw_last_init', current_time('mysql'));
        }
    }

    /**
     * Register data handlers
     */
    public function register_data_handlers() {
        add_action('save_post', [$this, 'handle_post_save'], 10, 3);
        add_action('before_delete_post', [$this, 'handle_post_deletion']);
        add_action('wp_ajax_mfw_cache_clear', [$this, 'handle_cache_clear']);
    }

    /**
     * Register data-related settings
     */
    public function register_data_settings() {
        register_setting('mfw_options', 'mfw_data_settings');
        
        add_settings_section(
            'mfw_data_settings',
            __('Data Management', 'mfw'),
            [$this, 'render_data_settings_section'],
            'mfw_options'
        );
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = [
            'cache_enabled' => true,
            'cache_expiration' => 3600,
            'log_level' => 'info'
        ];

        update_option('mfw_data_settings', array_merge($defaults, $this->settings));
    }

    /**
     * Handle post save
     */
    public function handle_post_save($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        // Add your post save handling logic here
    }

    /**
     * Handle post deletion
     */
    public function handle_post_deletion($post_id) {
        // Add your post deletion handling logic here
    }

    /**
     * Handle cache clear
     */
    public function handle_cache_clear() {
        check_ajax_referer('mfw_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if ($this->cache) {
            $this->cache->flush();
            wp_send_json_success('Cache cleared successfully');
        }

        wp_send_json_error('Cache clear failed');
    }

    /**
     * Log error
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW DataManager Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}