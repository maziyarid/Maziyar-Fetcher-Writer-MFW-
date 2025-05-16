<?php
/**
 * Main Admin Class
 * 
 * Handles all admin-related functionality and interfaces.
 * Manages admin menus, screens, and settings.
 *
 * @package MFW
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Admin {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:51:32';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Admin pages
     */
    private $pages = [];

    /**
     * Initialize admin functionality
     */
    public function __construct() {
        // Add admin hooks
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'init_settings']);
        
        // Add Ajax handlers
        add_action('wp_ajax_mfw_admin_action', [$this, 'handle_admin_action']);
        
        // Add notices
        add_action('admin_notices', [$this, 'display_admin_notices']);

        // Register admin pages
        $this->register_pages();
    }

    /**
     * Register admin pages
     */
    private function register_pages() {
        $this->pages = [
            'dashboard' => new MFW_Admin_Dashboard(),
            'settings' => new MFW_Admin_Settings(),
            'analytics' => new MFW_Admin_Analytics(),
            'tools' => new MFW_Admin_Tools(),
            'logs' => new MFW_Admin_Logs()
        ];
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        // Add main menu
        add_menu_page(
            __('Modern Framework', 'mfw'),
            __('MF Framework', 'mfw'),
            'manage_options',
            'mfw-dashboard',
            [$this->pages['dashboard'], 'render'],
            'dashicons-chart-area',
            30
        );

        // Add submenus
        add_submenu_page(
            'mfw-dashboard',
            __('Dashboard', 'mfw'),
            __('Dashboard', 'mfw'),
            'manage_options',
            'mfw-dashboard'
        );

        add_submenu_page(
            'mfw-dashboard',
            __('Settings', 'mfw'),
            __('Settings', 'mfw'),
            'manage_options',
            'mfw-settings',
            [$this->pages['settings'], 'render']
        );

        add_submenu_page(
            'mfw-dashboard',
            __('Analytics', 'mfw'),
            __('Analytics', 'mfw'),
            'manage_options',
            'mfw-analytics',
            [$this->pages['analytics'], 'render']
        );

        add_submenu_page(
            'mfw-dashboard',
            __('Tools', 'mfw'),
            __('Tools', 'mfw'),
            'manage_options',
            'mfw-tools',
            [$this->pages['tools'], 'render']
        );

        add_submenu_page(
            'mfw-dashboard',
            __('Logs', 'mfw'),
            __('Logs', 'mfw'),
            'manage_options',
            'mfw-logs',
            [$this->pages['logs'], 'render']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix The current admin page
     */
    public function enqueue_assets($hook_suffix) {
        // Only load on plugin pages
        if (strpos($hook_suffix, 'mfw-') === false) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'mfw-admin',
            MFW_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MFW_VERSION
        );

        wp_enqueue_style(
            'mfw-admin-components',
            MFW_PLUGIN_URL . 'assets/css/admin-components.css',
            [],
            MFW_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'mfw-admin',
            MFW_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util'],
            MFW_VERSION,
            true
        );

        wp_enqueue_script(
            'mfw-admin-components',
            MFW_PLUGIN_URL . 'assets/js/admin-components.js',
            ['jquery', 'wp-util'],
            MFW_VERSION,
            true
        );

        // Localize scripts
        wp_localize_script('mfw-admin', 'mfwAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mfw_admin_nonce'),
            'currentUser' => $this->current_user,
            'currentTime' => $this->current_time,
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'mfw'),
                'success' => __('Operation completed successfully.', 'mfw'),
                'error' => __('An error occurred. Please try again.', 'mfw')
            ]
        ]);
    }

    /**
     * Initialize admin settings
     */
    public function init_settings() {
        // Register settings
        register_setting(
            'mfw_settings',
            'mfw_general_settings',
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings']
            ]
        );

        // Add settings sections and fields
        add_settings_section(
            'mfw_general_section',
            __('General Settings', 'mfw'),
            [$this, 'render_general_section'],
            'mfw_settings'
        );

        // Add settings fields
        add_settings_field(
            'mfw_enable_feature',
            __('Enable Feature', 'mfw'),
            [$this, 'render_checkbox_field'],
            'mfw_settings',
            'mfw_general_section',
            ['field' => 'enable_feature']
        );
    }

    /**
     * Handle admin ajax actions
     */
    public function handle_admin_action() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_admin_nonce');

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'mfw'));
            }

            // Get action
            $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
            
            // Handle action
            switch ($action) {
                case 'save_settings':
                    $result = $this->handle_save_settings();
                    break;
                
                case 'clear_cache':
                    $result = $this->handle_clear_cache();
                    break;
                
                default:
                    throw new Exception(__('Invalid action', 'mfw'));
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $notices = get_transient('mfw_admin_notices');
        
        if ($notices) {
            foreach ($notices as $notice) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($notice['type']),
                    esc_html($notice['message'])
                );
            }
            
            delete_transient('mfw_admin_notices');
        }
    }

    /**
     * Handle save settings action
     *
     * @return array Result data
     */
    private function handle_save_settings() {
        // Validate and sanitize settings
        $settings = $this->sanitize_settings($_POST['settings']);

        // Save settings
        update_option('mfw_general_settings', $settings);

        // Log settings update
        $this->log_admin_action('save_settings', [
            'settings' => $settings
        ]);

        return [
            'message' => __('Settings saved successfully', 'mfw')
        ];
    }

    /**
     * Handle clear cache action
     *
     * @return array Result data
     */
    private function handle_clear_cache() {
        // Clear plugin cache
        MFW()->cache->flush_all();

        // Log cache clear
        $this->log_admin_action('clear_cache');

        return [
            'message' => __('Cache cleared successfully', 'mfw')
        ];
    }

    /**
     * Sanitize settings
     *
     * @param array $settings Settings to sanitize
     * @return array Sanitized settings
     */
    private function sanitize_settings($settings) {
        $sanitized = [];

        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'enable_feature':
                    $sanitized[$key] = (bool) $value;
                    break;
                
                case 'api_key':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                
                default:
                    $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Log admin action
     *
     * @param string $action Action type
     * @param array $data Additional data
     */
    private function log_admin_action($action, $data = []) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_admin_log',
                [
                    'action' => $action,
                    'data' => json_encode($data),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log admin action: %s', $e->getMessage()),
                'admin',
                'error'
            );
        }
    }
}