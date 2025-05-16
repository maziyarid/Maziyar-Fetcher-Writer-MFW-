<?php
/**
 * Admin Analytics Class
 * 
 * Handles the analytics dashboard and reporting interface.
 * Manages data visualization and analytics reporting.
 *
 * @package MFW
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Admin_Analytics {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:58:23';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Analytics handler
     */
    private $analytics;

    /**
     * Reports configuration
     */
    private $reports = [];

    /**
     * Initialize analytics page
     */
    public function __construct() {
        // Initialize analytics handler
        $this->analytics = new MFW_Analytics_Handler();

        // Register reports
        $this->register_reports();

        // Add Ajax handlers
        add_action('wp_ajax_mfw_get_report_data', [$this, 'handle_get_report_data']);
        add_action('wp_ajax_mfw_export_report', [$this, 'handle_export_report']);
    }

    /**
     * Register available reports
     */
    private function register_reports() {
        $this->reports = [
            'overview' => [
                'id' => 'overview',
                'title' => __('Overview', 'mfw'),
                'description' => __('General analytics overview.', 'mfw'),
                'type' => 'dashboard',
                'metrics' => ['pageviews', 'visitors', 'bounce_rate', 'avg_duration']
            ],
            'traffic' => [
                'id' => 'traffic',
                'title' => __('Traffic Analysis', 'mfw'),
                'description' => __('Detailed traffic statistics.', 'mfw'),
                'type' => 'graph',
                'metrics' => ['pageviews', 'visitors'],
                'dimensions' => ['date', 'source', 'medium']
            ],
            'performance' => [
                'id' => 'performance',
                'title' => __('Performance Metrics', 'mfw'),
                'description' => __('System performance analytics.', 'mfw'),
                'type' => 'graph',
                'metrics' => ['load_time', 'memory_usage', 'query_count']
            ],
            'events' => [
                'id' => 'events',
                'title' => __('Event Tracking', 'mfw'),
                'description' => __('Custom event analytics.', 'mfw'),
                'type' => 'table',
                'metrics' => ['event_count', 'unique_events']
            ]
        ];
    }

    /**
     * Render analytics page
     */
    public function render() {
        // Get current report
        $current_report = isset($_GET['report']) ? sanitize_text_field($_GET['report']) : 'overview';

        // Get date range
        $date_range = $this->get_date_range();

        // Start output buffering
        ob_start();
        ?>
        <div class="wrap mfw-analytics">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->render_date_filter($date_range); ?>

            <div class="mfw-analytics-container">
                <?php $this->render_report_navigation($current_report); ?>

                <div class="mfw-analytics-content">
                    <?php $this->render_report($current_report, $date_range); ?>
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
     * Render date range filter
     *
     * @param array $date_range Current date range
     */
    private function render_date_filter($date_range) {
        ?>
        <div class="mfw-date-filter">
            <form method="get" action="">
                <input type="hidden" name="page" value="mfw-analytics">
                <?php if (isset($_GET['report'])): ?>
                    <input type="hidden" name="report" value="<?php echo esc_attr($_GET['report']); ?>">
                <?php endif; ?>

                <select name="range" id="date-range">
                    <option value="today" <?php selected($date_range['range'], 'today'); ?>>
                        <?php _e('Today', 'mfw'); ?>
                    </option>
                    <option value="yesterday" <?php selected($date_range['range'], 'yesterday'); ?>>
                        <?php _e('Yesterday', 'mfw'); ?>
                    </option>
                    <option value="last7" <?php selected($date_range['range'], 'last7'); ?>>
                        <?php _e('Last 7 Days', 'mfw'); ?>
                    </option>
                    <option value="last30" <?php selected($date_range['range'], 'last30'); ?>>
                        <?php _e('Last 30 Days', 'mfw'); ?>
                    </option>
                    <option value="custom" <?php selected($date_range['range'], 'custom'); ?>>
                        <?php _e('Custom Range', 'mfw'); ?>
                    </option>
                </select>

                <div class="date-inputs <?php echo $date_range['range'] === 'custom' ? '' : 'hidden'; ?>">
                    <input type="date" 
                           name="start_date" 
                           value="<?php echo esc_attr($date_range['start_date']); ?>"
                           max="<?php echo esc_attr(date('Y-m-d')); ?>">
                    <span>-</span>
                    <input type="date" 
                           name="end_date" 
                           value="<?php echo esc_attr($date_range['end_date']); ?>"
                           max="<?php echo esc_attr(date('Y-m-d')); ?>">
                </div>

                <button type="submit" class="button">
                    <?php _e('Apply', 'mfw'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Render report navigation
     *
     * @param string $current_report Current report ID
     */
    private function render_report_navigation($current_report) {
        ?>
        <div class="mfw-analytics-nav">
            <ul>
                <?php foreach ($this->reports as $report): ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('report', $report['id'])); ?>"
                           class="<?php echo $current_report === $report['id'] ? 'active' : ''; ?>">
                            <?php echo esc_html($report['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Render specific report
     *
     * @param string $report_id Report identifier
     * @param array $date_range Date range
     */
    private function render_report($report_id, $date_range) {
        if (!isset($this->reports[$report_id])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Invalid report.', 'mfw') . '</p></div>';
            return;
        }

        $report = $this->reports[$report_id];
        ?>
        <div class="mfw-report" data-report="<?php echo esc_attr($report_id); ?>">
            <div class="mfw-report-header">
                <h2><?php echo esc_html($report['title']); ?></h2>
                <div class="mfw-report-actions">
                    <button type="button" class="button refresh-report">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'mfw'); ?>
                    </button>
                    <button type="button" class="button export-report">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export', 'mfw'); ?>
                    </button>
                </div>
            </div>

            <div class="mfw-report-description">
                <?php echo esc_html($report['description']); ?>
            </div>

            <div class="mfw-report-content">
                <div class="mfw-loading">
                    <span class="spinner is-active"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get date range from request
     *
     * @return array Date range parameters
     */
    private function get_date_range() {
        $range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : 'last30';
        $today = date('Y-m-d');

        switch ($range) {
            case 'today':
                return [
                    'range' => 'today',
                    'start_date' => $today,
                    'end_date' => $today
                ];

            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                return [
                    'range' => 'yesterday',
                    'start_date' => $yesterday,
                    'end_date' => $yesterday
                ];

            case 'last7':
                return [
                    'range' => 'last7',
                    'start_date' => date('Y-m-d', strtotime('-6 days')),
                    'end_date' => $today
                ];

            case 'custom':
                return [
                    'range' => 'custom',
                    'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-29 days')),
                    'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : $today
                ];

            case 'last30':
            default:
                return [
                    'range' => 'last30',
                    'start_date' => date('Y-m-d', strtotime('-29 days')),
                    'end_date' => $today
                ];
        }
    }

    /**
     * Add inline scripts
     */
    private function add_inline_scripts() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle date range selection
                $('#date-range').on('change', function() {
                    $('.date-inputs').toggleClass('hidden', $(this).val() !== 'custom');
                });

                // Load initial report data
                loadReportData();

                // Handle report refresh
                $('.refresh-report').on('click', function() {
                    loadReportData();
                });

                // Handle report export
                $('.export-report').on('click', function() {
                    var report = $(this).closest('.mfw-report').data('report');
                    exportReport(report);
                });

                function loadReportData() {
                    var report = $('.mfw-report').data('report');
                    var content = $('.mfw-report-content');
                    
                    content.addClass('loading');
                    
                    $.post(ajaxurl, {
                        action: 'mfw_get_report_data',
                        nonce: mfwAdmin.nonce,
                        report: report,
                        range: $('#date-range').val(),
                        start_date: $('input[name="start_date"]').val(),
                        end_date: $('input[name="end_date"]').val()
                    })
                    .done(function(response) {
                        if (response.success) {
                            content.html(response.data.html);
                            initializeCharts(response.data.chartData);
                        } else {
                            content.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    })
                    .fail(function() {
                        content.html('<div class="notice notice-error"><p>' + mfwAdmin.i18n.error + '</p></div>');
                    })
                    .always(function() {
                        content.removeClass('loading');
                    });
                }

                function exportReport(report) {
                    window.location.href = ajaxurl + '?' + $.param({
                        action: 'mfw_export_report',
                        nonce: mfwAdmin.nonce,
                        report: report,
                        range: $('#date-range').val(),
                        start_date: $('input[name="start_date"]').val(),
                        end_date: $('input[name="end_date"]').val()
                    });
                }

                function initializeCharts(chartData) {
                    // Initialize charts using Chart.js or similar library
                    if (typeof Chart === 'undefined' || !chartData) {
                        return;
                    }

                    Object.keys(chartData).forEach(function(chartId) {
                        var ctx = document.getElementById(chartId).getContext('2d');
                        new Chart(ctx, chartData[chartId]);
                    });
                }
            });
        </script>
        <?php
    }
}