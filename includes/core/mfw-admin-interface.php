<?php
namespace MFW\Core;

class AdminInterface {
    private $help_tabs;
    private $settings;
    private $notices;
    private $current_tab;
    private $tabs;

    /**
     * Initialize the admin interface
     */
    public function __construct() {
        $this->help_tabs = [];
        $this->notices = [];
        $this->settings = get_option('mfw_admin_settings', []);
        $this->current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $this->register_tabs();

        add_action('admin_notices', [$this, 'display_notices']);
    }

    /**
     * Initialize interface components
     */
    public function initialize() {
        if (is_admin()) {
            add_action('current_screen', [$this, 'setup_help_tabs']);
            $this->register_ajax_handlers();
        }
    }

    /**
     * Set up contextual help tabs
     */
    public function setup_help_tabs() {
        $screen = \get_current_screen();

        if (!$screen || !in_array($screen->id, ['toplevel_page_mfw-ai', 'mfw_page_mfw-ai-settings'])) {
            return;
        }

        $screen->add_help_tab([
            'id'      => 'mfw_general_help',
            'title'   => __('General Help', 'mfw'),
            'content' => $this->get_general_help_content()
        ]);

        $screen->add_help_tab([
            'id'      => 'mfw_content_help',
            'title'   => __('Content Generation', 'mfw'),
            'content' => $this->get_content_help_content()
        ]);

        $screen->set_help_sidebar($this->get_help_sidebar_content());
    }

    /**
     * Add admin notice
     */
    public function add_notice($message, $type = 'info') {
        $this->notices[] = [
            'message' => $message,
            'type' => $type
        ];
    }

    /**
     * Display admin notices
     */
    public function display_notices() {
        foreach ($this->notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                wp_kses_post($notice['message'])
            );
        }
    }

    /**
     * Register admin tabs
     */
    private function register_tabs() {
        $this->tabs = [
            'general' => [
                'title' => __('General', 'mfw'),
                'callback' => [$this, 'render_general_tab']
            ],
            'content' => [
                'title' => __('Content Generation', 'mfw'),
                'callback' => [$this, 'render_content_tab']
            ],
            'templates' => [
                'title' => __('Templates', 'mfw'),
                'callback' => [$this, 'render_templates_tab']
            ],
            'analytics' => [
                'title' => __('Analytics', 'mfw'),
                'callback' => [$this, 'render_analytics_tab']
            ]
        ];
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_mfw_generate_content', [$this, 'handle_generate_content']);
        add_action('wp_ajax_mfw_save_template', [$this, 'handle_save_template']);
        add_action('wp_ajax_mfw_get_analytics', [$this, 'handle_get_analytics']);
    }

    /**
     * Render main admin page
     */
    public function render_main_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mfw'));
        }

        ?>
        <div class="wrap mfw-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php
                foreach ($this->tabs as $tab_id => $tab) {
                    $active_class = ($this->current_tab === $tab_id) ? 'nav-tab-active' : '';
                    printf(
                        '<a href="?page=mfw-ai&tab=%s" class="nav-tab %s">%s</a>',
                        esc_attr($tab_id),
                        esc_attr($active_class),
                        esc_html($tab['title'])
                    );
                }
                ?>
            </nav>

            <div class="mfw-admin-content">
                <?php $this->render_current_tab(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render current tab content
     */
    private function render_current_tab() {
        if (isset($this->tabs[$this->current_tab]['callback'])) {
            call_user_func($this->tabs[$this->current_tab]['callback']);
        }
    }

    /**
     * Log error message
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW AdminInterface Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }

    /**
     * Get general help content
     */
    private function get_general_help_content() {
        ob_start();
        ?>
        <h2><?php _e('Welcome to MFW AI Content Generator', 'mfw'); ?></h2>
        <p><?php _e('This plugin helps you generate AI-powered content for your WordPress site. Here are some key features:', 'mfw'); ?></p>
        
        <ul>
            <li><?php _e('Generate high-quality articles, product descriptions, and social media posts', 'mfw'); ?></li>
            <li><?php _e('Customize content tone and style to match your brand', 'mfw'); ?></li>
            <li><?php _e('Save and reuse content templates', 'mfw'); ?></li>
            <li><?php _e('Track content performance with built-in analytics', 'mfw'); ?></li>
        </ul>

        <p><?php _e('To get started, select a tab above and follow the instructions for each feature.', 'mfw'); ?></p>
        <?php
        return ob_get_clean();
    }

    /**
     * Get help sidebar content
     */
    private function get_help_sidebar_content() {
        return '<p><strong>' . __('For more information:', 'mfw') . '</strong></p>' .
               '<p><a href="#">' . __('Documentation', 'mfw') . '</a></p>';
    }

    // Add placeholder methods that will be implemented later
    public function render_general_tab() {}
    public function render_content_tab() {}
    public function render_templates_tab() {}
    public function render_analytics_tab() {}
    public function handle_generate_content() {}
    public function handle_save_template() {}
    public function handle_get_analytics() {}
    
    /**
     * Get content generation help content
     */
    private function get_content_help_content() {
        ob_start();
        ?>
        <h2><?php _e('Content Generation Guide', 'mfw'); ?></h2>
        <p><?php _e('Follow these steps to generate AI content:', 'mfw'); ?></p>
        
        <ol>
            <li><?php _e('Select the content type (article, product description, or social post)', 'mfw'); ?></li>
            <li><?php _e('Enter a detailed prompt describing what you want to create', 'mfw'); ?></li>
            <li><?php _e('Choose the appropriate tone for your content', 'mfw'); ?></li>
            <li><?php _e('Click "Generate Content" and wait for the results', 'mfw'); ?></li>
        </ol>
        <?php
        return ob_get_clean();
    }

    /**
     * Get templates help content
     */
    private function get_templates_help_content() {
        ob_start();
        ?>
        <h2><?php _e('Working with Templates', 'mfw'); ?></h2>
        <p><?php _e('Templates help you maintain consistency in your content generation:', 'mfw'); ?></p>
        
        <ul>
            <li><?php _e('Create templates for recurring content types', 'mfw'); ?></li>
            <li><?php _e('Save successful prompts as templates', 'mfw'); ?></li>
            <li><?php _e('Share templates with team members', 'mfw'); ?></li>
            <li><?php _e('Organize templates by categories', 'mfw'); ?></li>
        </ul>
        <?php
        return ob_get_clean();
    }

    /**
     * Render settings sidebar
     */
    private function render_settings_sidebar() {
        ?>
        <div class="mfw-sidebar-widget">
            <h3><?php _e('Quick Tips', 'mfw'); ?></h3>
            <ul>
                <li><?php _e('Configure your API settings first', 'mfw'); ?></li>
                <li><?php _e('Test the connection before generating content', 'mfw'); ?></li>
                <li><?php _e('Save your changes after making adjustments', 'mfw'); ?></li>
            </ul>
        </div>

        <div class="mfw-sidebar-widget">
            <h3><?php _e('Need Help?', 'mfw'); ?></h3>
            <p><?php _e('Check our documentation or contact support:', 'mfw'); ?></p>
            <a href="#" class="button button-secondary"><?php _e('View Documentation', 'mfw'); ?></a>
        </div>
        <?php
    }

    /**
     * Render dashboard widgets
     */
    private function render_dashboard_widgets() {
        ?>
        <div class="mfw-widget">
            <h3><?php _e('Content Statistics', 'mfw'); ?></h3>
            <?php $this->render_content_stats(); ?>
        </div>

        <div class="mfw-widget">
            <h3><?php _e('Recent Activity', 'mfw'); ?></h3>
            <?php $this->render_recent_activity(); ?>
        </div>
        <?php
    }

    /**
     * Render content statistics
     */
    private function render_content_stats() {
        // Add your content statistics rendering code here
        _e('Content statistics will be displayed here.', 'mfw');
    }

    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        // Add your recent activity rendering code here
        _e('Recent activity will be displayed here.', 'mfw');
    }

    /**
     * Render quick actions
     */
    private function render_quick_actions() {
        ?>
        <div class="mfw-quick-actions-wrapper">
            <h3><?php _e('Quick Actions', 'mfw'); ?></h3>
            <div class="mfw-action-buttons">
                <button class="button" id="mfw-new-content">
                    <?php _e('New Content', 'mfw'); ?>
                </button>
                <button class="button" id="mfw-new-template">
                    <?php _e('New Template', 'mfw'); ?>
                </button>
                <button class="button" id="mfw-view-reports">
                    <?php _e('View Reports', 'mfw'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render system status
     */
    private function render_system_status() {
        ?>
        <div class="mfw-system-status-wrapper">
            <h3><?php _e('System Status', 'mfw'); ?></h3>
            <div class="mfw-status-items">
                <?php $this->render_status_items(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render status items
     */
    private function render_status_items() {
        // Add your status items rendering code here
        _e('System status items will be displayed here.', 'mfw');
    }
    /**
     * Render settings page
     */
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mfw'));
        }

        ?>
        <div class="wrap mfw-settings">
            <h1><?php _e('MFW AI Settings', 'mfw'); ?></h1>
            
            <form method="post" action="options.php" class="mfw-settings-form">
                <?php
                settings_fields('mfw_options');
                do_settings_sections('mfw_options');
                submit_button();
                ?>
            </form>

            <div class="mfw-settings-sidebar">
                <?php $this->render_settings_sidebar(); ?>
            </div>
        </div>
        <?php
    }
}