<?php
namespace MFW\Core;

class PluginInitializer {
    private $field_registry;
    private $admin_interface;
    private $data_manager;
    private $settings;
    private $version;

    public function __construct() {
        $this->version = '1.0.0';
        
        // Make sure these classes exist and are loaded
        if (!class_exists('\\MFW\\Core\\FieldRegistry')) {
            throw new \Exception('FieldRegistry class not found');
        }
        if (!class_exists('\\MFW\\Core\\AdminInterface')) {
            throw new \Exception('AdminInterface class not found');
        }
        if (!class_exists('\\MFW\\Core\\DataManager')) {
            throw new \Exception('DataManager class not found');
        }

        $this->field_registry = new FieldRegistry();
        $this->admin_interface = new AdminInterface();
        $this->data_manager = new DataManager();
        $this->settings = get_option('mfw_core_settings', []);

        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Add WordPress hooks
        add_action('init', [$this, 'register_post_types']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Initialize components
        $this->initialize_components();
        $this->register_api_endpoints();
        $this->setup_cron_jobs();
    }

    /**
     * Activation handler
     */
    public function activate() {
        try {
            // Create database tables
            $this->data_manager->create_database_tables();

            // Set default options
            $this->set_default_options();

            // Setup initial data
            $this->setup_initial_data();

            // Flush rewrite rules
            flush_rewrite_rules();

            return true;
        } catch (\Exception $e) {
            $this->log_error('Activation failed', $e);
            return false;
        }
    }

    /**
     * Deactivation handler
     */
    public function deactivate() {
        try {
            // Clean up scheduled events
            wp_clear_scheduled_hook('mfw_daily_maintenance');

            // Clean up temporary data
            $this->cleanup_temp_data();

            // Flush rewrite rules
            flush_rewrite_rules();

            return true;
        } catch (\Exception $e) {
            $this->log_error('Deactivation failed', $e);
            return false;
        }
    }

    /**
     * Register custom post types
     */
    public function register_post_types() {
        // Register AI Content post type
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

        // Register AI Template post type
        register_post_type('mfw_ai_template', [
            'labels' => [
                'name' => __('AI Templates', 'mfw'),
                'singular_name' => __('AI Template', 'mfw')
            ],
            'public' => true,
            'has_archive' => false,
            'supports' => ['title', 'editor', 'custom-fields'],
            'menu_icon' => 'dashicons-layout',
            'show_in_rest' => true
        ]);
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('mfw_options', 'mfw_api_key');
        register_setting('mfw_options', 'mfw_api_url');
        register_setting('mfw_options', 'mfw_model_settings');
        register_setting('mfw_options', 'mfw_cache_settings');
        register_setting('mfw_options', 'mfw_rate_limit_settings');
        
        // Register setting sections
        add_settings_section(
            'mfw_api_settings',
            __('API Settings', 'mfw'),
            [$this, 'render_api_settings_section'],
            'mfw_options'
        );

        // Register setting fields
        add_settings_field(
            'mfw_api_key',
            __('API Key', 'mfw'),
            [$this, 'render_api_key_field'],
            'mfw_options',
            'mfw_api_settings'
        );
    }

    /**
     * Register admin menu items
     */
    public function register_admin_menu() {
        add_menu_page(
            __('MFW AI', 'mfw'),
            __('MFW AI', 'mfw'),
            'manage_options',
            'mfw-ai',
            [$this->admin_interface, 'render_main_page'],
            'dashicons-artificial-intelligence',
            20
        );

        add_submenu_page(
            'mfw-ai',
            __('Settings', 'mfw'),
            __('Settings', 'mfw'),
            'manage_options',
            'mfw-ai-settings',
            [$this->admin_interface, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        wp_enqueue_style(
            'mfw-admin',
            MFW_PLUGIN_URL . 'assets/css/mfw-admin.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'mfw-admin',
            MFW_PLUGIN_URL . 'assets/js/mfw-admin.js',
            ['jquery'],
            $this->version,
            true
        );

        wp_localize_script('mfw-admin', 'mfwAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mfw_admin_nonce')
        ]);
    }

    /**
     * Initialize plugin components
     */
    private function initialize_components() {
        // Initialize field registry
        $this->field_registry->register_default_fields();

        // Initialize admin interface
        $this->admin_interface->initialize();

        // Initialize data manager
        $this->data_manager->initialize();
    }
    
    /**
     * Register API endpoints
     */
    private function register_api_endpoints() {
        add_action('rest_api_init', function() {
            register_rest_route('mfw/v1', '/generate', [
                'methods' => 'POST',
                'callback' => [$this, 'handle_generate_request'],
                'permission_callback' => [$this, 'check_api_permission']
            ]);
        });
    }

    /**
     * Set up cron jobs
     */
    private function setup_cron_jobs() {
        if (!wp_next_scheduled('mfw_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'mfw_daily_maintenance');
        }
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW PluginInitializer Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = [
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
        ];

        update_option('mfw_core_settings', array_merge($defaults, $this->settings));
    }

    /**
     * Setup initial data
     */
    private function setup_initial_data() {
        // Add your initial data setup code here
    }

    /**
     * Cleanup temporary data
     */
    private function cleanup_temp_data() {
        // Add your cleanup code here
    }
}