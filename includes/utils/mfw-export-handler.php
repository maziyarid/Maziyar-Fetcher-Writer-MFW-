<?php
/**
 * Export Handler Class
 *
 * Manages data export functionality for generated content and settings.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Export_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:26:25';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Export types
     */
    const TYPE_CONTENT = 'content';
    const TYPE_SETTINGS = 'settings';
    const TYPE_TEMPLATES = 'templates';
    const TYPE_METADATA = 'metadata';
    const TYPE_ALL = 'all';

    /**
     * Export formats
     */
    const FORMAT_JSON = 'json';
    const FORMAT_CSV = 'csv';
    const FORMAT_XML = 'xml';

    /**
     * Export data
     *
     * @param string $type Export type
     * @param array $options Export options
     * @return array|false Export data or false on failure
     */
    public function export($type, $options = []) {
        try {
            // Validate export type
            if (!in_array($type, [self::TYPE_CONTENT, self::TYPE_SETTINGS, self::TYPE_TEMPLATES, self::TYPE_METADATA, self::TYPE_ALL])) {
                throw new Exception(__('Invalid export type.', 'mfw'));
            }

            // Prepare export data
            $data = [
                'type' => $type,
                'version' => MFW_VERSION,
                'timestamp' => $this->current_time,
                'exported_by' => $this->current_user,
                'data' => []
            ];

            // Get export data based on type
            switch ($type) {
                case self::TYPE_ALL:
                    $data['data'] = $this->get_all_data($options);
                    break;
                case self::TYPE_CONTENT:
                    $data['data'] = $this->get_content_data($options);
                    break;
                case self::TYPE_SETTINGS:
                    $data['data'] = $this->get_settings_data($options);
                    break;
                case self::TYPE_TEMPLATES:
                    $data['data'] = $this->get_templates_data($options);
                    break;
                case self::TYPE_METADATA:
                    $data['data'] = $this->get_metadata_data($options);
                    break;
            }

            // Add export metadata
            $data['metadata'] = [
                'item_count' => count($data['data']),
                'file_size' => strlen(json_encode($data)),
                'checksum' => md5(json_encode($data['data']))
            ];

            // Log export
            $this->log_export($type, $data['metadata']);

            return $data;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Export failed: %s', $e->getMessage()),
                'export_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Format export data
     *
     * @param array $data Export data
     * @param string $format Export format
     * @return string|false Formatted data or false on failure
     */
    public function format_export($data, $format = self::FORMAT_JSON) {
        try {
            switch ($format) {
                case self::FORMAT_JSON:
                    return json_encode($data, JSON_PRETTY_PRINT);

                case self::FORMAT_CSV:
                    return $this->format_as_csv($data);

                case self::FORMAT_XML:
                    return $this->format_as_xml($data);

                default:
                    throw new Exception(__('Invalid export format.', 'mfw'));
            }
        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Export formatting failed: %s', $e->getMessage()),
                'export_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get all exportable data
     *
     * @param array $options Export options
     * @return array Export data
     */
    private function get_all_data($options) {
        return [
            'content' => $this->get_content_data($options),
            'settings' => $this->get_settings_data($options),
            'templates' => $this->get_templates_data($options),
            'metadata' => $this->get_metadata_data($options)
        ];
    }

    /**
     * Get content export data
     *
     * @param array $options Export options
     * @return array Content data
     */
    private function get_content_data($options) {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}mfw_generated_content WHERE 1=1";
        $params = [];

        // Apply filters
        if (!empty($options['date_from'])) {
            $query .= " AND created_at >= %s";
            $params[] = $options['date_from'];
        }

        if (!empty($options['date_to'])) {
            $query .= " AND created_at <= %s";
            $params[] = $options['date_to'];
        }

        if (!empty($options['user'])) {
            $query .= " AND created_by = %s";
            $params[] = $options['user'];
        }

        if (!empty($options['status'])) {
            $query .= " AND status = %s";
            $params[] = $options['status'];
        }

        // Execute query
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get settings export data
     *
     * @param array $options Export options
     * @return array Settings data
     */
    private function get_settings_data($options) {
        $settings = get_option(MFW_SETTINGS_OPTION, []);

        // Filter sensitive data if specified
        if (!empty($options['exclude_sensitive'])) {
            unset($settings['api']['gemini_api_key']);
            unset($settings['api']['deepseek_api_key']);
            unset($settings['security']['allowed_ip_ranges']);
        }

        return $settings;
    }

    /**
     * Get templates export data
     *
     * @param array $options Export options
     * @return array Templates data
     */
    private function get_templates_data($options) {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}mfw_templates WHERE status = 'active'";

        if (!empty($options['type'])) {
            $query = $wpdb->prepare($query . " AND type = %s", $options['type']);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get metadata export data
     *
     * @param array $options Export options
     * @return array Metadata
     */
    private function get_metadata_data($options) {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}mfw_metadata WHERE 1=1";
        $params = [];

        if (!empty($options['type'])) {
            $query .= " AND type = %s";
            $params[] = $options['type'];
        }

        if (!empty($options['date_from'])) {
            $query .= " AND created_at >= %s";
            $params[] = $options['date_from'];
        }

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Format data as CSV
     *
     * @param array $data Export data
     * @return string CSV formatted data
     */
    private function format_as_csv($data) {
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        if (!empty($data['data'])) {
            fputcsv($output, array_keys(reset($data['data'])));
            
            // Write data rows
            foreach ($data['data'] as $row) {
                fputcsv($output, $row);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Format data as XML
     *
     * @param array $data Export data
     * @return string XML formatted data
     */
    private function format_as_xml($data) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><export></export>');
        
        // Add metadata
        $metadata = $xml->addChild('metadata');
        $metadata->addChild('type', $data['type']);
        $metadata->addChild('version', $data['version']);
        $metadata->addChild('timestamp', $data['timestamp']);
        $metadata->addChild('exported_by', $data['exported_by']);

        // Add data
        $dataNode = $xml->addChild('data');
        $this->array_to_xml($data['data'], $dataNode);

        return $xml->asXML();
    }

    /**
     * Convert array to XML
     *
     * @param array $array Array to convert
     * @param SimpleXMLElement $xml XML element
     */
    private function array_to_xml($array, &$xml) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item' . $key;
                }
                $subnode = $xml->addChild($key);
                $this->array_to_xml($value, $subnode);
            } else {
                $xml->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }

    /**
     * Log export activity
     *
     * @param string $type Export type
     * @param array $metadata Export metadata
     */
    private function log_export($type, $metadata) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mfw_export_log',
            [
                'export_type' => $type,
                'metadata' => json_encode($metadata),
                'exported_by' => $this->current_user,
                'exported_at' => $this->current_time
            ],
            ['%s', '%s', '%s', '%s']
        );
    }
}