<?php
/**
 * Admin Dashboard Class
 * 
 * Handles the main dashboard interface for the plugin.
 * Displays overview, statistics, and quick actions.
 *
 * @package MFW
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Admin_Dashboard {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:53:38';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Dashboard widgets
     */
    private $widgets = [];

    /**
     * Initialize dashboard
     */
    public function __construct() {
        // Register widgets
        $this->register_widgets();

        // Add Ajax handlers
        add_action('wp_ajax_mfw_update_widget', [$this, 'handle_widget_update']);
        add_action('wp_ajax_mfw_refresh_widget', [$this, 'handle_widget_refresh']);
    }

    /**
     * Register dashboard widgets
     */
    private function register_widgets() {
        $this->widgets = [
            'statistics' => new MFW_Dashboard_Widget_Statistics(),
            'recent_activity' => new MFW_Dashboard_Widget_Recent_Activity(),
            'performance' => new MFW_Dashboard_Widget_Performance(),
            'quick_actions' => new MFW_Dashboard_Widget_Quick_Actions(),
            'system_status' => new MFW_Dashboard_Widget_System_Status()
        ];
    }

    /**
     * Render dashboard page
     */
    public function render() {
        // Get user preferences
        $layout = get_user_meta($this->current_user, 'mfw_dashboard_layout', true);
        
        if (!$layout) {
            $layout = $this->get_default_layout();
        }

        // Start output buffering
        ob_start();
        ?>
        <div class="wrap mfw-dashboard">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->render_welcome_panel(); ?>

            <div class="mfw-dashboard-widgets <?php echo esc_attr($layout); ?>">
                <?php
                // Render widgets in layout order
                foreach ($this->get_widget_order() as $widget_id) {
                    if (isset($this->widgets[$widget_id])) {
                        $this->render_widget($widget_id);
                    }
                }
                ?>
            </div>

            <?php $this->render_footer(); ?>
        </div>

        <?php
        // Get and clean buffer
        $output = ob_get_clean();

        // Add any necessary inline scripts
        $this->add_inline_scripts();

        // Output page
        echo $output;
    }

    /**
     * Render welcome panel
     */
    private function render_welcome_panel() {
        // Check if welcome panel is dismissed
        $dismissed = get_user_meta($this->current_user, 'mfw_welcome_panel_dismissed', true);
        if ($dismissed) {
            return;
        }

        ?>
        <div class="welcome-panel mfw-welcome-panel">
            <button type="button" class="welcome-panel-close" aria-label="<?php esc_attr_e('Dismiss welcome panel', 'mfw'); ?>">
                <span class="dashicons dashicons-dismiss"></span>
            </button>

            <div class="welcome-panel-content">
                <h2><?php _e('Welcome to Modern Framework!', 'mfw'); ?></h2>
                
                <p class="about-description">
                    <?php _e('Here\'s how to get started:', 'mfw'); ?>
                </p>

                <div class="welcome-panel-column-container">
                    <div class="welcome-panel-column">
                        <h3><?php _e('Get Started', 'mfw'); ?></h3>
                        <ul>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=mfw-settings')); ?>" class="button button-primary">
                                    <?php _e('Configure Settings', 'mfw'); ?>
                                </a>
                            </li>
                            <li>
                                <?php _e('Review system status', 'mfw'); ?>
                            </li>
                            <li>
                                <?php _e('Check documentation', 'mfw'); ?>
                            </li>
                        </ul>
                    </div>
                    <div class="welcome-panel-column">
                        <h3><?php _e('Next Steps', 'mfw'); ?></h3>
                        <ul>
                            <li>
                                <?php _e('Configure analytics tracking', 'mfw'); ?>
                            </li>
                            <li>
                                <?php _e('Set up email notifications', 'mfw'); ?>
                            </li>
                            <li>
                                <?php _e('Review security settings', 'mfw'); ?>
                            </li>
                        </ul>
                    </div>
                    <div class="welcome-panel-column">
                        <h3><?php _e('More Actions', 'mfw'); ?></h3>
                        <ul>
                            <li>
                                <?php _e('Import/Export data', 'mfw'); ?>
                            </li>
                            <li>
                                <?php _e('View logs', 'mfw'); ?>
                            </li>
                            <li>
                                <?php _e('Get support', 'mfw'); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render widget
     *
     * @param string $widget_id Widget identifier
     */
    private function render_widget($widget_id) {
        $widget = $this->widgets[$widget_id];
        ?>
        <div id="mfw-widget-<?php echo esc_attr($widget_id); ?>" class="mfw-dashboard-widget">
            <div class="mfw-widget-header">
                <h2><?php echo esc_html($widget->get_title()); ?></h2>
                <div class="mfw-widget-actions">
                    <?php if ($widget->is_refreshable()): ?>
                        <button type="button" class="refresh-widget" aria-label="<?php esc_attr_e('Refresh widget', 'mfw'); ?>">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="toggle-widget" aria-label="<?php esc_attr_e('Toggle widget', 'mfw'); ?>">
                        <span class="dashicons dashicons-arrow-up"></span>
                    </button>
                </div>
            </div>
            <div class="mfw-widget-content">
                <?php $widget->render(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render dashboard footer
     */
    private function render_footer() {
        ?>
        <div class="mfw-dashboard-footer">
            <div class="mfw-dashboard-footer-left">
                <p>
                    <?php
                    printf(
                        /* translators: %s: Plugin version */
                        esc_html__('Modern Framework v%s', 'mfw'),
                        MFW_VERSION
                    );
                    ?>
                </p>
            </div>
            <div class="mfw-dashboard-footer-right">
                <a href="<?php echo esc_url(admin_url('admin.php?page=mfw-settings')); ?>">
                    <?php _e('Settings', 'mfw'); ?>
                </a>
                <span class="separator">|</span>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mfw-tools')); ?>">
                    <?php _e('Tools', 'mfw'); ?>
                </a>
                <span class="separator">|</span>
                <a href="https://docs.example.com/mfw" target="_blank">
                    <?php _e('Documentation', 'mfw'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Add inline scripts
     */
    private function add_inline_scripts() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Welcome panel dismiss
                $('.mfw-welcome-panel-close').on('click', function() {
                    $('.mfw-welcome-panel').slideUp();
                    $.post(ajaxurl, {
                        action: 'mfw_dismiss_welcome',
                        nonce: mfwAdmin.nonce
                    });
                });

                // Widget refresh
                $('.mfw-dashboard-widget .refresh-widget').on('click', function() {
                    var widget = $(this).closest('.mfw-dashboard-widget');
                    var widgetId = widget.attr('id').replace('mfw-widget-', '');
                    
                    widget.addClass('is-loading');
                    
                    $.post(ajaxurl, {
                        action: 'mfw_refresh_widget',
                        widget: widgetId,
                        nonce: mfwAdmin.nonce
                    })
                    .done(function(response) {
                        if (response.success) {
                            widget.find('.mfw-widget-content').html(response.data.content);
                        }
                    })
                    .always(function() {
                        widget.removeClass('is-loading');
                    });
                });

                // Widget toggle
                $('.mfw-dashboard-widget .toggle-widget').on('click', function() {
                    var widget = $(this).closest('.mfw-dashboard-widget');
                    var content = widget.find('.mfw-widget-content');
                    var icon = $(this).find('.dashicons');
                    
                    content.slideToggle();
                    icon.toggleClass('dashicons-arrow-up dashicons-arrow-down');
                });
            });
        </script>
        <?php
    }

    /**
     * Handle widget update
     */
    public function handle_widget_update() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_admin_nonce');

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'mfw'));
            }

            // Get widget data
            $widget_id = isset($_POST['widget']) ? sanitize_text_field($_POST['widget']) : '';
            $widget_data = isset($_POST['data']) ? $_POST['data'] : [];

            if (!isset($this->widgets[$widget_id])) {
                throw new Exception(__('Invalid widget', 'mfw'));
            }

            // Update widget
            $this->widgets[$widget_id]->update($widget_data);

            wp_send_json_success();

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle widget refresh
     */
    public function handle_widget_refresh() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_admin_nonce');

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'mfw'));
            }

            // Get widget
            $widget_id = isset($_POST['widget']) ? sanitize_text_field($_POST['widget']) : '';
            
            if (!isset($this->widgets[$widget_id])) {
                throw new Exception(__('Invalid widget', 'mfw'));
            }

            // Get fresh content
            ob_start();
            $this->widgets[$widget_id]->render();
            $content = ob_get_clean();

            wp_send_json_success([
                'content' => $content
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get default dashboard layout
     *
     * @return string Layout class
     */
    private function get_default_layout() {
        return 'mfw-dashboard-layout-2col';
    }

    /**
     * Get widget display order
     *
     * @return array Widget IDs in display order
     */
    private function get_widget_order() {
        $order = get_user_meta($this->current_user, 'mfw_dashboard_widget_order', true);
        
        if (!$order) {
            $order = [
                'statistics',
                'recent_activity',
                'performance',
                'quick_actions',
                'system_status'
            ];
        }

        return $order;
    }
}