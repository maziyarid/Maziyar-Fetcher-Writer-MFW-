<?php
/**
 * Admin Logs Class
 * 
 * Handles the logs interface and log management.
 * Manages displaying, filtering, and exporting logs.
 *
 * @package MFW
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Admin_Logs {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 19:01:45';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Log types
     */
    private $log_types = [];

    /**
     * Items per page
     */
    private $per_page = 20;

    /**
     * Initialize logs page
     */
    public function __construct() {
        // Register log types
        $this->register_log_types();

        // Add Ajax handlers
        add_action('wp_ajax_mfw_get_logs', [$this, 'handle_get_logs']);
        add_action('wp_ajax_mfw_delete_logs', [$this, 'handle_delete_logs']);
        add_action('wp_ajax_mfw_export_logs', [$this, 'handle_export_logs']);
    }

    /**
     * Register available log types
     */
    private function register_log_types() {
        $this->log_types = [
            'error' => [
                'id' => 'error',
                'label' => __('Error Logs', 'mfw'),
                'table' => 'mfw_error_log',
                'columns' => [
                    'message' => __('Message', 'mfw'),
                    'code' => __('Code', 'mfw'),
                    'file' => __('File', 'mfw'),
                    'line' => __('Line', 'mfw'),
                    'created_at' => __('Date', 'mfw')
                ]
            ],
            'security' => [
                'id' => 'security',
                'label' => __('Security Logs', 'mfw'),
                'table' => 'mfw_security_log',
                'columns' => [
                    'event' => __('Event', 'mfw'),
                    'ip' => __('IP Address', 'mfw'),
                    'user_agent' => __('User Agent', 'mfw'),
                    'created_at' => __('Date', 'mfw')
                ]
            ],
            'activity' => [
                'id' => 'activity',
                'label' => __('Activity Logs', 'mfw'),
                'table' => 'mfw_activity_log',
                'columns' => [
                    'action' => __('Action', 'mfw'),
                    'description' => __('Description', 'mfw'),
                    'user_id' => __('User', 'mfw'),
                    'created_at' => __('Date', 'mfw')
                ]
            ],
            'api' => [
                'id' => 'api',
                'label' => __('API Logs', 'mfw'),
                'table' => 'mfw_api_log',
                'columns' => [
                    'endpoint' => __('Endpoint', 'mfw'),
                    'method' => __('Method', 'mfw'),
                    'status' => __('Status', 'mfw'),
                    'response_time' => __('Response Time', 'mfw'),
                    'created_at' => __('Date', 'mfw')
                ]
            ]
        ];
    }

    /**
     * Render logs page
     */
    public function render() {
        // Get current log type
        $current_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'error';

        // Start output buffering
        ob_start();
        ?>
        <div class="wrap mfw-logs">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="mfw-logs-container">
                <?php $this->render_log_navigation($current_type); ?>

                <div class="mfw-logs-content">
                    <?php $this->render_log_filters(); ?>

                    <div class="mfw-logs-table-container">
                        <div class="mfw-loading">
                            <span class="spinner is-active"></span>
                        </div>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <?php $this->render_table_header($current_type); ?>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="pagination-links"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
     * Render log navigation
     *
     * @param string $current_type Current log type
     */
    private function render_log_navigation($current_type) {
        ?>
        <div class="mfw-logs-nav">
            <ul>
                <?php foreach ($this->log_types as $type): ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('type', $type['id'])); ?>"
                           class="<?php echo $current_type === $type['id'] ? 'active' : ''; ?>">
                            <?php echo esc_html($type['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Render log filters
     */
    private function render_log_filters() {
        ?>
        <div class="mfw-logs-filters">
            <div class="filter-group">
                <input type="text" 
                       id="log-search" 
                       placeholder="<?php esc_attr_e('Search logs...', 'mfw'); ?>">
            </div>

            <div class="filter-group">
                <select id="log-level">
                    <option value=""><?php _e('All Levels', 'mfw'); ?></option>
                    <option value="error"><?php _e('Error', 'mfw'); ?></option>
                    <option value="warning"><?php _e('Warning', 'mfw'); ?></option>
                    <option value="info"><?php _e('Info', 'mfw'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <input type="date" 
                       id="log-date-start" 
                       max="<?php echo esc_attr(date('Y-m-d')); ?>">
                <span>-</span>
                <input type="date" 
                       id="log-date-end" 
                       max="<?php echo esc_attr(date('Y-m-d')); ?>">
            </div>

            <div class="filter-actions">
                <button type="button" class="button" id="apply-filters">
                    <?php _e('Apply Filters', 'mfw'); ?>
                </button>
                <button type="button" class="button" id="reset-filters">
                    <?php _e('Reset', 'mfw'); ?>
                </button>
            </div>

            <div class="bulk-actions">
                <select id="bulk-action">
                    <option value=""><?php _e('Bulk Actions', 'mfw'); ?></option>
                    <option value="delete"><?php _e('Delete', 'mfw'); ?></option>
                    <option value="export"><?php _e('Export', 'mfw'); ?></option>
                </select>
                <button type="button" class="button" id="apply-bulk-action">
                    <?php _e('Apply', 'mfw'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render table header
     *
     * @param string $type Log type
     */
    private function render_table_header($type) {
        if (!isset($this->log_types[$type])) {
            return;
        }

        $columns = $this->log_types[$type]['columns'];
        ?>
        <tr>
            <th class="check-column">
                <input type="checkbox" id="select-all-logs">
            </th>
            <?php foreach ($columns as $key => $label): ?>
                <th class="column-<?php echo esc_attr($key); ?> sortable">
                    <a href="#" data-sort="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                        <span class="sorting-indicator"></span>
                    </a>
                </th>
            <?php endforeach; ?>
            <th class="column-actions"><?php _e('Actions', 'mfw'); ?></th>
        </tr>
        <?php
    }

    /**
     * Handle get logs request
     */
    public function handle_get_logs() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_admin_nonce');

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'mfw'));
            }

            // Get parameters
            $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'error';
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $filters = isset($_POST['filters']) ? $_POST['filters'] : [];

            // Get logs
            $logs = $this->get_logs($type, $page, $filters);

            wp_send_json_success([
                'logs' => $logs['items'],
                'total' => $logs['total'],
                'pages' => $logs['pages']
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Add inline scripts
     */
    private function add_inline_scripts() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var currentPage = 1;
                var currentSort = {field: 'created_at', order: 'desc'};

                // Load initial logs
                loadLogs();

                // Handle filter application
                $('#apply-filters').on('click', function() {
                    currentPage = 1;
                    loadLogs();
                });

                // Handle filter reset
                $('#reset-filters').on('click', function() {
                    $('#log-search').val('');
                    $('#log-level').val('');
                    $('#log-date-start').val('');
                    $('#log-date-end').val('');
                    currentPage = 1;
                    loadLogs();
                });

                // Handle sorting
                $('.sortable a').on('click', function(e) {
                    e.preventDefault();
                    var field = $(this).data('sort');
                    
                    if (currentSort.field === field) {
                        currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
                    } else {
                        currentSort.field = field;
                        currentSort.order = 'asc';
                    }
                    
                    loadLogs();
                });

                // Handle bulk actions
                $('#apply-bulk-action').on('click', function() {
                    var action = $('#bulk-action').val();
                    var selected = [];
                    
                    $('input[name="log[]"]:checked').each(function() {
                        selected.push($(this).val());
                    });
                    
                    if (selected.length === 0) {
                        alert(mfwAdmin.i18n.noSelection);
                        return;
                    }
                    
                    switch (action) {
                        case 'delete':
                            if (confirm(mfwAdmin.i18n.confirmDelete)) {
                                deleteLogs(selected);
                            }
                            break;
                        case 'export':
                            exportLogs(selected);
                            break;
                    }
                });

                function loadLogs() {
                    var container = $('.mfw-logs-table-container');
                    container.addClass('loading');
                    
                    $.post(ajaxurl, {
                        action: 'mfw_get_logs',
                        nonce: mfwAdmin.nonce,
                        type: '<?php echo esc_js(isset($_GET['type']) ? $_GET['type'] : 'error'); ?>',
                        page: currentPage,
                        filters: {
                            search: $('#log-search').val(),
                            level: $('#log-level').val(),
                            date_start: $('#log-date-start').val(),
                            date_end: $('#log-date-end').val(),
                            sort: currentSort
                        }
                    })
                    .done(function(response) {
                        if (response.success) {
                            renderLogs(response.data);
                        } else {
                            container.find('tbody').html(
                                '<tr><td colspan="100%">' + 
                                response.data.message + 
                                '</td></tr>'
                            );
                        }
                    })
                    .fail(function() {
                        container.find('tbody').html(
                            '<tr><td colspan="100%">' + 
                            mfwAdmin.i18n.error + 
                            '</td></tr>'
                        );
                    })
                    .always(function() {
                        container.removeClass('loading');
                    });
                }

                function renderLogs(data) {
                    var tbody = $('.mfw-logs-table-container tbody');
                    var pagination = $('.pagination-links');
                    var html = '';
                    
                    if (data.logs.length === 0) {
                        html = '<tr><td colspan="100%">' + 
                               mfwAdmin.i18n.noLogs + 
                               '</td></tr>';
                    } else {
                        data.logs.forEach(function(log) {
                            html += renderLogRow(log);
                        });
                    }
                    
                    tbody.html(html);
                    renderPagination(data.total, data.pages);
                }

                function renderPagination(total, pages) {
                    // Implementation of pagination rendering
                }
            });
        </script>
        <?php
    }
}