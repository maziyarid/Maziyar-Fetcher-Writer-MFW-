<?php
/**
 * Import/Export Handler Class
 * 
 * Manages data import and export functionality.
 * Supports multiple formats and data validation.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Import_Export_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:26:13';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Supported formats
     */
    private $supported_formats = ['json', 'csv', 'xml'];

    /**
     * Validation handler instance
     */
    private $validator;

    /**
     * Initialize import/export handler
     */
    public function __construct() {
        $this->validator = new MFW_Validation_Handler();

        // Add AJAX handlers
        add_action('wp_ajax_mfw_export_data', [$this, 'handle_export_request']);
        add_action('wp_ajax_mfw_import_data', [$this, 'handle_import_request']);
    }

    /**
     * Export data
     *
     * @param string $type Data type
     * @param array $filters Export filters
     * @param string $format Export format
     * @return array|string Exported data
     */
    public function export_data($type, $filters = [], $format = 'json') {
        try {
            // Validate format
            if (!in_array($format, $this->supported_formats)) {
                throw new Exception(__('Unsupported export format', 'mfw'));
            }

            // Get data based on type
            $data = $this->get_export_data($type, $filters);

            // Transform data based on format
            $transformed = $this->transform_data($data, $format);

            // Log export
            $this->log_export([
                'type' => $type,
                'filters' => $filters,
                'format' => $format,
                'count' => count($data)
            ]);

            return $transformed;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Export failed: %s', $e->getMessage()),
                'import_export_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Import data
     *
     * @param string $type Data type
     * @param mixed $data Import data
     * @param string $format Import format
     * @param array $options Import options
     * @return bool Success status
     */
    public function import_data($type, $data, $format = 'json', $options = []) {
        try {
            global $wpdb;

            // Parse options
            $options = wp_parse_args($options, [
                'validate' => true,
                'skip_duplicates' => true,
                'batch_size' => 100
            ]);

            // Parse data based on format
            $parsed = $this->parse_data($data, $format);

            // Validate data if required
            if ($options['validate']) {
                $validation_rules = $this->get_validation_rules($type);
                foreach ($parsed as $item) {
                    $valid = $this->validator->validate_input($item, $validation_rules);
                    if ($valid !== true) {
                        throw new Exception(__('Invalid import data', 'mfw'));
                    }
                }
            }

            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Process data in batches
            $total_imported = 0;
            foreach (array_chunk($parsed, $options['batch_size']) as $batch) {
                $imported = $this->process_import_batch($type, $batch, $options);
                $total_imported += $imported;
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            // Log import
            $this->log_import([
                'type' => $type,
                'format' => $format,
                'options' => $options,
                'count' => $total_imported
            ]);

            return true;

        } catch (Exception $e) {
            // Rollback transaction
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }

            MFW_Error_Logger::log(
                sprintf('Import failed: %s', $e->getMessage()),
                'import_export_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Handle export request
     */
    public function handle_export_request() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_export', 'nonce');

            // Get request parameters
            $type = sanitize_text_field($_POST['type'] ?? '');
            $filters = isset($_POST['filters']) ? (array)$_POST['filters'] : [];
            $format = sanitize_text_field($_POST['format'] ?? 'json');

            // Export data
            $data = $this->export_data($type, $filters, $format);
            if ($data === false) {
                throw new Exception(__('Export failed', 'mfw'));
            }

            // Send response
            wp_send_json_success([
                'data' => $data,
                'format' => $format
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle import request
     */
    public function handle_import_request() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_import', 'nonce');

            // Get request parameters
            $type = sanitize_text_field($_POST['type'] ?? '');
            $format = sanitize_text_field($_POST['format'] ?? 'json');
            $options = isset($_POST['options']) ? (array)$_POST['options'] : [];

            // Get file data
            if (!isset($_FILES['import_file'])) {
                throw new Exception(__('No file uploaded', 'mfw'));
            }

            $file_data = file_get_contents($_FILES['import_file']['tmp_name']);
            if ($file_data === false) {
                throw new Exception(__('Failed to read upload file', 'mfw'));
            }

            // Import data
            if (!$this->import_data($type, $file_data, $format, $options)) {
                throw new Exception(__('Import failed', 'mfw'));
            }

            wp_send_json_success(__('Import completed successfully', 'mfw'));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get export data
     *
     * @param string $type Data type
     * @param array $filters Export filters
     * @return array Data for export
     */
    private function get_export_data($type, $filters) {
        global $wpdb;

        switch ($type) {
            case 'settings':
                return $this->get_settings_data();

            case 'logs':
                return $this->get_logs_data($filters);

            case 'statistics':
                return $this->get_statistics_data($filters);

            default:
                throw new Exception(__('Invalid export type', 'mfw'));
        }
    }

    /**
     * Transform data to specified format
     *
     * @param array $data Data to transform
     * @param string $format Target format
     * @return string Transformed data
     */
    private function transform_data($data, $format) {
        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);

            case 'csv':
                return $this->array_to_csv($data);

            case 'xml':
                return $this->array_to_xml($data);

            default:
                throw new Exception(__('Unsupported format', 'mfw'));
        }
    }

    /**
     * Parse import data
     *
     * @param mixed $data Import data
     * @param string $format Data format
     * @return array Parsed data
     */
    private function parse_data($data, $format) {
        switch ($format) {
            case 'json':
                $parsed = json_decode($data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception(__('Invalid JSON data', 'mfw'));
                }
                return $parsed;

            case 'csv':
                return $this->csv_to_array($data);

            case 'xml':
                return $this->xml_to_array($data);

            default:
                throw new Exception(__('Unsupported format', 'mfw'));
        }
    }

    /**
     * Process import batch
     *
     * @param string $type Data type
     * @param array $batch Batch data
     * @param array $options Import options
     * @return int Number of items imported
     */
    private function process_import_batch($type, $batch, $options) {
        global $wpdb;
        $imported = 0;

        foreach ($batch as $item) {
            // Skip duplicates if configured
            if ($options['skip_duplicates'] && $this->is_duplicate($type, $item)) {
                continue;
            }

            // Insert data based on type
            switch ($type) {
                case 'settings':
                    $this->import_settings($item);
                    break;

                case 'logs':
                    $this->import_logs($item);
                    break;

                case 'statistics':
                    $this->import_statistics($item);
                    break;

                default:
                    throw new Exception(__('Invalid import type', 'mfw'));
            }

            $imported++;
        }

        return $imported;
    }

    /**
     * Convert array to CSV
     *
     * @param array $data Array data
     * @return string CSV data
     */
    private function array_to_csv($data) {
        if (empty($data)) {
            return '';
        }

        ob_start();
        $df = fopen("php://output", 'w');
        
        // Write headers
        fputcsv($df, array_keys(reset($data)));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($df, $row);
        }
        
        fclose($df);
        return ob_get_clean();
    }

    /**
     * Convert CSV to array
     *
     * @param string $data CSV data
     * @return array Parsed data
     */
    private function csv_to_array($data) {
        $lines = explode("\n", trim($data));
        $headers = str_getcsv(array_shift($lines));
        $array = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            $array[] = array_combine($headers, $row);
        }

        return $array;
    }

    /**
     * Log export operation
     *
     * @param array $data Export data
     */
    private function log_export($data) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_export_log',
                [
                    'type' => $data['type'],
                    'filters' => json_encode($data['filters']),
                    'format' => $data['format'],
                    'count' => $data['count'],
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%d', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log export: %s', $e->getMessage()),
                'import_export_handler',
                'error'
            );
        }
    }

    /**
     * Log import operation
     *
     * @param array $data Import data
     */
    private function log_import($data) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_import_log',
                [
                    'type' => $data['type'],
                    'format' => $data['format'],
                    'options' => json_encode($data['options']),
                    'count' => $data['count'],
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%d', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log import: %s', $e->getMessage()),
                'import_export_handler',
                'error'
            );
        }
    }
}