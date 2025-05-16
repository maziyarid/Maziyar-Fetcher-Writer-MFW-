<?php
/**
 * Admin Tools Class
 * 
 * Handles the tools and utilities interface.
 * Manages maintenance, import/export, and diagnostic tools.
 *
 * @package MFW
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Admin_Tools {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 19:00:12';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Available tools
     */
    private $tools = [];

    /**
     * Initialize tools page
     */
    public function __construct() {
        // Register tools
        $this->register_tools();

        // Add Ajax handlers
        add_action('wp_ajax_mfw_run_tool', [$this, 'handle_run_tool']);
        add_action('wp_ajax_mfw_import_data', [$this, 'handle_import_data']);
        add_action('wp_ajax_mfw_export_data', [$this, 'handle_export_data']);
    }

    /**
     * Register available tools
     */
    private function register_tools() {
        $this->tools = [
            'maintenance' => [
                'id' => 'maintenance',
                'title' => __('Maintenance', 'mfw'),
                'description' => __('Run maintenance tasks and cleanup operations.', 'mfw'),
                'actions' => [
                    'clear_cache' => __('Clear Cache', 'mfw'),
                    'optimize_tables' => __('Optimize Database Tables', 'mfw'),
                    'cleanup_logs' => __('Clean Up Logs', 'mfw')
                ]
            ],
            'diagnostics' => [
                'id' => 'diagnostics',
                'title' => __('Diagnostics', 'mfw'),
                'description' => __('Run system diagnostics and checks.', 'mfw'),
                'actions' => [
                    'system_check' => __('System Check', 'mfw'),
                    'security_scan' => __('Security Scan', 'mfw'),
                    'performance_test' => __('Performance Test', 'mfw')
                ]
            ],
            'import_export' => [
                'id' => 'import_export',
                'title' => __('Import/Export', 'mfw'),
                'description' => __('Import and export plugin data.', 'mfw'),
                'actions' => [
                    'export_settings' => __('Export Settings', 'mfw'),
                    'import_settings' => __('Import Settings', 'mfw'),
                    'export_data' => __('Export Data', 'mfw'),
                    'import_data' => __('Import Data', 'mfw')
                ]
            ],
            'utilities' => [
                'id' => 'utilities',
                'title' => __('Utilities', 'mfw'),
                'description' => __('Additional utility tools.', 'mfw'),
                'actions' => [
                    'generate_report' => __('Generate Report', 'mfw'),
                    'rebuild_index' => __('Rebuild Index', 'mfw'),
                    'validate_data' => __('Validate Data', 'mfw')
                ]
            ]
        ];
    }

    /**
     * Render tools page
     */
    public function render() {
        // Start output buffering
        ob_start();
        ?>
        <div class="wrap mfw-tools">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="mfw-tools-container">
                <?php
                foreach ($this->tools as $tool) {
                    $this->render_tool_card($tool);
                }
                ?>
            </div>

            <?php $this->render_import_export_modal(); ?>
        </div>
        <?php
        // Get and clean buffer
        $output = ob_get_clean();

        // Add inline scripts
        $this->add_inline_scripts();

        // Output page
        echo $output;
    }

    /**
     * Render tool card
     *
     * @param array $tool Tool configuration
     */
    private function render_tool_card($tool) {
        ?>
        <div class="mfw-tool-card" id="tool-<?php echo esc_attr($tool['id']); ?>">
            <div class="mfw-tool-header">
                <h2><?php echo esc_html($tool['title']); ?></h2>
                <p><?php echo esc_html($tool['description']); ?></p>
            </div>

            <div class="mfw-tool-actions">
                <?php foreach ($tool['actions'] as $action => $label): ?>
                    <button type="button" 
                            class="button tool-action" 
                            data-tool="<?php echo esc_attr($tool['id']); ?>"
                            data-action="<?php echo esc_attr($action); ?>">
                        <?php echo esc_html($label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="mfw-tool-status"></div>
        </div>
        <?php
    }

    /**
     * Render import/export modal
     */
    private function render_import_export_modal() {
        ?>
        <div id="mfw-import-export-modal" class="mfw-modal">
            <div class="mfw-modal-content">
                <div class="mfw-modal-header">
                    <h2></h2>
                    <button type="button" class="mfw-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <div class="mfw-modal-body">
                    <!-- Import Form -->
                    <div class="import-form" style="display: none;">
                        <form method="post" enctype="multipart/form-data">
                            <p class="description">
                                <?php _e('Select a file to import:', 'mfw'); ?>
                            </p>
                            <input type="file" name="import_file" accept=".json">
                            <button type="submit" class="button button-primary">
                                <?php _e('Import', 'mfw'); ?>
                            </button>
                        </form>
                    </div>

                    <!-- Export Options -->
                    <div class="export-form" style="display: none;">
                        <form method="post">
                            <p class="description">
                                <?php _e('Select data to export:', 'mfw'); ?>
                            </p>
                            <label>
                                <input type="checkbox" name="export_settings" value="1" checked>
                                <?php _e('Settings', 'mfw'); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="export_data" value="1" checked>
                                <?php _e('Data', 'mfw'); ?>
                            </label>
                            <button type="submit" class="button button-primary">
                                <?php _e('Export', 'mfw'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle tool execution
     */
    public function handle_run_tool() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_admin_nonce');

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'mfw'));
            }

            // Get tool and action
            $tool = isset($_POST['tool']) ? sanitize_text_field($_POST['tool']) : '';
            $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

            if (!isset($this->tools[$tool])) {
                throw new Exception(__('Invalid tool', 'mfw'));
            }

            // Run tool action
            $result = $this->run_tool_action($tool, $action);

            wp_send_json_success([
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Run specific tool action
     *
     * @param string $tool Tool identifier
     * @param string $action Action identifier
     * @return array Result data
     */
    private function run_tool_action($tool, $action) {
        switch ($action) {
            case 'clear_cache':
                MFW()->cache->flush_all();
                return [
                    'message' => __('Cache cleared successfully.', 'mfw')
                ];

            case 'optimize_tables':
                global $wpdb;
                $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}mfw_%'");
                foreach ($tables as $table) {
                    $wpdb->query("OPTIMIZE TABLE $table");
                }
                return [
                    'message' => __('Database tables optimized.', 'mfw')
                ];

            case 'cleanup_logs':
                $deleted = MFW()->logger->cleanup_old_logs();
                return [
                    'message' => sprintf(
                        __('Cleaned up %d old log entries.', 'mfw'),
                        $deleted
                    )
                ];

            case 'system_check':
                $results = $this->run_system_check();
                return [
                    'message' => __('System check completed.', 'mfw'),
                    'data' => $results
                ];

            default:
                throw new Exception(__('Invalid action', 'mfw'));
        }
    }

    /**
     * Run system check
     *
     * @return array Check results
     */
    private function run_system_check() {
        return [
            'php_version' => [
                'label' => __('PHP Version', 'mfw'),
                'value' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4', '>=') ? 'pass' : 'fail'
            ],
            'wp_version' => [
                'label' => __('WordPress Version', 'mfw'),
                'value' => get_bloginfo('version'),
                'status' => version_compare(get_bloginfo('version'), '5.6', '>=') ? 'pass' : 'fail'
            ],
            'memory_limit' => [
                'label' => __('Memory Limit', 'mfw'),
                'value' => ini_get('memory_limit'),
                'status' => $this->check_memory_limit() ? 'pass' : 'warn'
            ]
        ];
    }

    /**
     * Check memory limit
     *
     * @return bool Check result
     */
    private function check_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);
        return $memory_limit_bytes >= 64 * 1024 * 1024; // 64MB minimum
    }

    /**
     * Add inline scripts
     */
    private function add_inline_scripts() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle tool actions
                $('.tool-action').on('click', function() {
                    var button = $(this);
                    var tool = button.data('tool');
                    var action = button.data('action');
                    var status = button.closest('.mfw-tool-card').find('.mfw-tool-status');
                    
                    button.prop('disabled', true);
                    status.html('<div class="spinner is-active"></div>');
                    
                    $.post(ajaxurl, {
                        action: 'mfw_run_tool',
                        nonce: mfwAdmin.nonce,
                        tool: tool,
                        action_type: action
                    })
                    .done(function(response) {
                        if (response.success) {
                            status.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            if (response.data.data) {
                                displayResults(status, response.data.data);
                            }
                        } else {
                            status.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    })
                    .fail(function() {
                        status.html('<div class="notice notice-error"><p>' + mfwAdmin.i18n.error + '</p></div>');
                    })
                    .always(function() {
                        button.prop('disabled', false);
                    });
                });

                function displayResults(container, results) {
                    var html = '<table class="widefat">';
                    html += '<thead><tr><th>Check</th><th>Value</th><th>Status</th></tr></thead><tbody>';
                    
                    Object.keys(results).forEach(function(key) {
                        var result = results[key];
                        html += '<tr>';
                        html += '<td>' + result.label + '</td>';
                        html += '<td>' + result.value + '</td>';
                        html += '<td><span class="status-' + result.status + '">' + result.status + '</span></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    container.append(html);
                }
            });
        </script>
        <?php
    }
}