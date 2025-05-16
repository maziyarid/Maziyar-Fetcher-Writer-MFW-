<?php
/**
 * Import Handler Class
 *
 * Manages data import functionality for content and settings.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Import_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:27:35';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Import types
     */
    const TYPE_CONTENT = 'content';
    const TYPE_SETTINGS = 'settings';
    const TYPE_TEMPLATES = 'templates';
    const TYPE_METADATA = 'metadata';
    const TYPE_ALL = 'all';

    /**
     * Import formats
     */
    const FORMAT_JSON = 'json';
    const FORMAT_CSV = 'csv';
    const FORMAT_XML = 'xml';

    /**
     * Import data
     *
     * @param mixed $data Import data
     * @param string $format Import format
     * @param array $options Import options
     * @return array Import results
     */
    public function import($data, $format = self::FORMAT_JSON, $options = []) {
        try {
            // Parse data based on format
            $parsed_data = $this->parse_import_data($data, $format);
            if (!$parsed_data) {
                throw new Exception(__('Failed to parse import data.', 'mfw'));
            }

            // Validate import data
            $this->validate_import_data($parsed_data);

            // Begin transaction
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            $results = [
                'success' => true,
                'imported' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => []
            ];

            try {
                // Process import based on type
                switch ($parsed_data['type']) {
                    case self::TYPE_ALL:
                        $results = $this->import_all_data($parsed_data['data'], $options);
                        break;
                    case self::TYPE_CONTENT:
                        $results = $this->import_content_data($parsed_data['data'], $options);
                        break;
                    case self::TYPE_SETTINGS:
                        $results = $this->import_settings_data($parsed_data['data'], $options);
                        break;
                    case self::TYPE_TEMPLATES:
                        $results = $this->import_templates_data($parsed_data['data'], $options);
                        break;
                    case self::TYPE_METADATA:
                        $results = $this->import_metadata_data($parsed_data['data'], $options);
                        break;
                    default:
                        throw new Exception(__('Invalid import type.', 'mfw'));
                }

                // Commit transaction if successful
                $wpdb->query('COMMIT');

                // Log import
                $this->log_import($parsed_data['type'], $results);

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }

            return $results;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Import failed: %s', $e->getMessage()),
                'import_handler',
                'error'
            );
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Parse import data
     *
     * @param mixed $data Import data
     * @param string $format Import format
     * @return array|false Parsed data or false on failure
     */
    private function parse_import_data($data, $format) {
        try {
            switch ($format) {
                case self::FORMAT_JSON:
                    if (is_string($data)) {
                        return json_decode($data, true);
                    }
                    return $data;

                case self::FORMAT_CSV:
                    return $this->parse_csv_data($data);

                case self::FORMAT_XML:
                    return $this->parse_xml_data($data);

                default:
                    throw new Exception(__('Invalid import format.', 'mfw'));
            }
        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to parse import data: %s', $e->getMessage()),
                'import_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Validate import data
     *
     * @param array $data Import data
     * @throws Exception If validation fails
     */
    private function validate_import_data($data) {
        if (!isset($data['type']) || !isset($data['version']) || !isset($data['data'])) {
            throw new Exception(__('Invalid import data structure.', 'mfw'));
        }

        // Validate version compatibility
        if (version_compare($data['version'], MFW_VERSION, '>')) {
            throw new Exception(__('Import data is from a newer version of the plugin.', 'mfw'));
        }
    }

    /**
     * Import all data
     *
     * @param array $data Import data
     * @param array $options Import options
     * @return array Import results
     */
    private function import_all_data($data, $options) {
        $results = [
            'success' => true,
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($data as $type => $type_data) {
            $type_results = [];
            
            switch ($type) {
                case 'content':
                    $type_results = $this->import_content_data($type_data, $options);
                    break;
                case 'settings':
                    $type_results = $this->import_settings_data($type_data, $options);
                    break;
                case 'templates':
                    $type_results = $this->import_templates_data($type_data, $options);
                    break;
                case 'metadata':
                    $type_results = $this->import_metadata_data($type_data, $options);
                    break;
            }

            $results['imported'] += $type_results['imported'];
            $results['skipped'] += $type_results['skipped'];
            $results['failed'] += $type_results['failed'];
            $results['errors'] = array_merge($results['errors'], $type_results['errors']);
        }

        $results['success'] = empty($results['errors']);
        return $results;
    }

    /**
     * Import content data
     *
     * @param array $data Content data
     * @param array $options Import options
     * @return array Import results
     */
    private function import_content_data($data, $options) {
        global $wpdb;
        $results = $this->initialize_results();

        foreach ($data as $item) {
            try {
                // Check if content already exists
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}mfw_generated_content
                    WHERE external_id = %s",
                    $item['external_id'] ?? ''
                ));

                if ($exists && empty($options['overwrite'])) {
                    $results['skipped']++;
                    continue;
                }

                // Prepare content data
                $content_data = [
                    'title' => $item['title'],
                    'content' => $item['content'],
                    'status' => $item['status'],
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time,
                    'updated_at' => $this->current_time
                ];

                if ($exists) {
                    // Update existing content
                    $wpdb->update(
                        $wpdb->prefix . 'mfw_generated_content',
                        $content_data,
                        ['id' => $exists],
                        ['%s', '%s', '%s', '%s', '%s', '%s'],
                        ['%d']
                    );
                } else {
                    // Insert new content
                    $wpdb->insert(
                        $wpdb->prefix . 'mfw_generated_content',
                        $content_data,
                        ['%s', '%s', '%s', '%s', '%s', '%s']
                    );
                }

                $results['imported']++;

            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = sprintf(
                    __('Failed to import content item: %s', 'mfw'),
                    $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Import settings data
     *
     * @param array $data Settings data
     * @param array $options Import options
     * @return array Import results
     */
    private function import_settings_data($data, $options) {
        $results = $this->initialize_results();

        try {
            $settings_handler = new MFW_Settings_Handler();
            $current_settings = $settings_handler->get_all_settings();

            // Merge or overwrite settings based on options
            if (empty($options['overwrite'])) {
                $settings = array_merge_recursive($current_settings, $data);
            } else {
                $settings = $data;
            }

            // Update settings
            if ($settings_handler->update_settings($settings)) {
                $results['imported']++;
            } else {
                $results['failed']++;
                $results['errors'][] = __('Failed to update settings.', 'mfw');
            }

        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Initialize results array
     *
     * @return array Results structure
     */
    private function initialize_results() {
        return [
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];
    }

    /**
     * Log import activity
     *
     * @param string $type Import type
     * @param array $results Import results
     */
    private function log_import($type, $results) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mfw_import_log',
            [
                'import_type' => $type,
                'results' => json_encode($results),
                'imported_by' => $this->current_user,
                'imported_at' => $this->current_time
            ],
            ['%s', '%s', '%s', '%s']
        );
    }
}