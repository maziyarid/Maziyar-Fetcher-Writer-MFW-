<?php
/**
 * Settings Handler Class
 * 
 * Manages plugin settings and configuration.
 * Handles settings storage, retrieval, and validation.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Settings_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:34:39';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Settings sections
     */
    private $sections = [];

    /**
     * Settings fields
     */
    private $fields = [];

    /**
     * Default settings
     */
    private $defaults = [];

    /**
     * Settings cache
     */
    private $settings_cache = [];

    /**
     * Initialize settings handler
     */
    public function __construct() {
        // Register settings sections and fields
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add settings menu
        add_action('admin_menu', [$this, 'add_settings_menu']);

        // Initialize default settings
        $this->init_defaults();

        // Add AJAX handlers
        add_action('wp_ajax_mfw_save_settings', [$this, 'handle_save_settings']);
        add_action('wp_ajax_mfw_reset_settings', [$this, 'handle_reset_settings']);
    }

    /**
     * Register settings sections and fields
     */
    public function register_settings() {
        // Register sections
        $this->register_sections();

        // Register fields
        $this->register_fields();

        // Register settings in WordPress
        register_setting(
            'mfw_settings',
            'mfw_settings',
            [
                'sanitize_callback' => [$this, 'sanitize_settings']
            ]
        );
    }

    /**
     * Get setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null) {
        try {
            // Check cache first
            if (isset($this->settings_cache[$key])) {
                return $this->settings_cache[$key];
            }

            // Get all settings
            $settings = get_option('mfw_settings', []);

            // Get setting value
            $value = isset($settings[$key]) ? $settings[$key] : 
                    ($default !== null ? $default : 
                    ($this->defaults[$key] ?? null));

            // Cache value
            $this->settings_cache[$key] = $value;

            return $value;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get setting: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
            return $default;
        }
    }

    /**
     * Update setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public function update_setting($key, $value) {
        try {
            // Get current settings
            $settings = get_option('mfw_settings', []);

            // Update value
            $settings[$key] = $value;

            // Save settings
            $updated = update_option('mfw_settings', $settings);

            if ($updated) {
                // Update cache
                $this->settings_cache[$key] = $value;

                // Log update
                $this->log_setting_update($key, $value);
            }

            return $updated;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update setting: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Delete setting
     *
     * @param string $key Setting key
     * @return bool Success status
     */
    public function delete_setting($key) {
        try {
            // Get current settings
            $settings = get_option('mfw_settings', []);

            // Remove setting
            if (isset($settings[$key])) {
                unset($settings[$key]);
                
                // Save settings
                $deleted = update_option('mfw_settings', $settings);

                if ($deleted) {
                    // Update cache
                    unset($this->settings_cache[$key]);

                    // Log deletion
                    $this->log_setting_delete($key);
                }

                return $deleted;
            }

            return false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to delete setting: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Reset settings to defaults
     *
     * @return bool Success status
     */
    public function reset_settings() {
        try {
            // Save default settings
            $reset = update_option('mfw_settings', $this->defaults);

            if ($reset) {
                // Clear cache
                $this->settings_cache = [];

                // Log reset
                $this->log_settings_reset();
            }

            return $reset;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to reset settings: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Export settings
     *
     * @return array|bool Settings array or false on failure
     */
    public function export_settings() {
        try {
            $settings = get_option('mfw_settings', []);
            
            // Add export metadata
            $settings['_export_info'] = [
                'version' => MFW_VERSION,
                'timestamp' => $this->current_time,
                'exported_by' => $this->current_user
            ];

            return $settings;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to export settings: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Import settings
     *
     * @param array $settings Settings to import
     * @return bool Success status
     */
    public function import_settings($settings) {
        try {
            // Validate settings
            if (!$this->validate_import($settings)) {
                return false;
            }

            // Remove export metadata
            unset($settings['_export_info']);

            // Save settings
            $imported = update_option('mfw_settings', $settings);

            if ($imported) {
                // Clear cache
                $this->settings_cache = [];

                // Log import
                $this->log_settings_import();
            }

            return $imported;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to import settings: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Handle settings save via AJAX
     */
    public function handle_save_settings() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_settings', 'nonce');

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'mfw'));
            }

            // Get settings data
            $settings = isset($_POST['settings']) ? (array)$_POST['settings'] : [];

            // Sanitize and validate settings
            $settings = $this->sanitize_settings($settings);

            // Save settings
            $saved = update_option('mfw_settings', $settings);

            if (!$saved) {
                throw new Exception(__('Failed to save settings', 'mfw'));
            }

            wp_send_json_success(__('Settings saved successfully', 'mfw'));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle settings reset via AJAX
     */
    public function handle_reset_settings() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_settings', 'nonce');

            // Check permissions
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'mfw'));
            }

            // Reset settings
            if (!$this->reset_settings()) {
                throw new Exception(__('Failed to reset settings', 'mfw'));
            }

            wp_send_json_success(__('Settings reset successfully', 'mfw'));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Register settings sections
     */
    private function register_sections() {
        $this->sections = [
            'general' => [
                'title' => __('General Settings', 'mfw'),
                'description' => __('Configure general plugin settings', 'mfw')
            ],
            'display' => [
                'title' => __('Display Settings', 'mfw'),
                'description' => __('Configure display and formatting options', 'mfw')
            ],
            'advanced' => [
                'title' => __('Advanced Settings', 'mfw'),
                'description' => __('Configure advanced plugin features', 'mfw')
            ]
        ];

        foreach ($this->sections as $id => $section) {
            add_settings_section(
                'mfw_' . $id,
                $section['title'],
                function() use ($section) {
                    echo '<p>' . esc_html($section['description']) . '</p>';
                },
                'mfw_settings'
            );
        }
    }

    /**
     * Register settings fields
     */
    private function register_fields() {
        $this->fields = [
            'enable_feature' => [
                'section' => 'general',
                'title' => __('Enable Feature', 'mfw'),
                'type' => 'checkbox',
                'default' => true
            ],
            'api_key' => [
                'section' => 'general',
                'title' => __('API Key', 'mfw'),
                'type' => 'text',
                'sanitize' => 'sanitize_text_field'
            ],
            'date_format' => [
                'section' => 'display',
                'title' => __('Date Format', 'mfw'),
                'type' => 'select',
                'options' => [
                    'Y-m-d' => 'YYYY-MM-DD',
                    'd/m/Y' => 'DD/MM/YYYY',
                    'm/d/Y' => 'MM/DD/YYYY'
                ],
                'default' => 'Y-m-d'
            ]
        ];

        foreach ($this->fields as $id => $field) {
            add_settings_field(
                'mfw_' . $id,
                $field['title'],
                [$this, 'render_field'],
                'mfw_settings',
                'mfw_' . $field['section'],
                ['field' => $id] + $field
            );
        }
    }

    /**
     * Initialize default settings
     */
    private function init_defaults() {
        foreach ($this->fields as $id => $field) {
            if (isset($field['default'])) {
                $this->defaults[$id] = $field['default'];
            }
        }
    }

    /**
     * Sanitize settings
     *
     * @param array $settings Settings to sanitize
     * @return array Sanitized settings
     */
    private function sanitize_settings($settings) {
        foreach ($settings as $key => $value) {
            if (isset($this->fields[$key]['sanitize'])) {
                $callback = $this->fields[$key]['sanitize'];
                $settings[$key] = $callback($value);
            }
        }

        return $settings;
    }

    /**
     * Validate import data
     *
     * @param array $settings Settings to validate
     * @return bool Validation result
     */
    private function validate_import($settings) {
        // Check export info
        if (!isset($settings['_export_info'])) {
            return false;
        }

        // Check version compatibility
        $version = $settings['_export_info']['version'];
        if (version_compare($version, MFW_VERSION, '>')) {
            return false;
        }

        return true;
    }

    /**
     * Log setting update
     *
     * @param string $key Setting key
     * @param mixed $value New value
     */
    private function log_setting_update($key, $value) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_settings_log',
                [
                    'setting_key' => $key,
                    'setting_value' => is_array($value) ? json_encode($value) : $value,
                    'action' => 'update',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log setting update: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
        }
    }

    /**
     * Log setting deletion
     *
     * @param string $key Setting key
     */
    private function log_setting_delete($key) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_settings_log',
                [
                    'setting_key' => $key,
                    'action' => 'delete',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log setting deletion: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
        }
    }

    /**
     * Log settings reset
     */
    private function log_settings_reset() {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_settings_log',
                [
                    'action' => 'reset',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log settings reset: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
        }
    }

    /**
     * Log settings import
     */
    private function log_settings_import() {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_settings_log',
                [
                    'action' => 'import',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log settings import: %s', $e->getMessage()),
                'settings_handler',
                'error'
            );
        }
    }
}