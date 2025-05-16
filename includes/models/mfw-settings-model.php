<?php
/**
 * Settings Model Class
 * 
 * Handles settings data management and validation.
 * Manages plugin configuration settings.
 *
 * @package MFW
 * @subpackage Models
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Settings_Model extends MFW_Model_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:15:12';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Settings data
     */
    protected $data = [];

    /**
     * Default settings
     */
    protected $defaults = [
        'general' => [
            'site_name' => '',
            'admin_email' => '',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'timezone' => 'UTC',
            'language' => 'en_US'
        ],
        'api' => [
            'enabled' => true,
            'require_auth' => true,
            'rate_limit' => 1000,
            'rate_window' => 3600
        ],
        'security' => [
            'login_attempts' => 5,
            'lockout_duration' => 900,
            'password_expiry' => 90,
            'require_2fa' => false
        ],
        'notifications' => [
            'email' => [
                'enabled' => true,
                'from_name' => '',
                'from_email' => '',
                'smtp_enabled' => false,
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_secure' => 'tls',
                'smtp_auth' => true,
                'smtp_user' => '',
                'smtp_pass' => ''
            ],
            'slack' => [
                'enabled' => false,
                'webhook_url' => '',
                'channel' => '',
                'username' => 'MFW Bot'
            ]
        ],
        'analytics' => [
            'enabled' => true,
            'tracking_code' => '',
            'anonymize_ip' => true,
            'track_admin' => false
        ],
        'performance' => [
            'cache_enabled' => true,
            'cache_lifetime' => 3600,
            'minify_html' => false,
            'minify_css' => false,
            'minify_js' => false
        ]
    ];

    /**
     * Initialize model
     */
    protected function init() {
        $this->table = 'mfw_settings';
        
        $this->fields = [
            'group' => [
                'type' => 'string',
                'length' => 50
            ],
            'key' => [
                'type' => 'string',
                'length' => 100
            ],
            'value' => [
                'type' => 'text'
            ],
            'autoload' => [
                'type' => 'boolean',
                'default' => true
            ]
        ];

        $this->required = ['group', 'key'];

        $this->validations = [
            'group' => [
                'length' => ['min' => 1, 'max' => 50]
            ],
            'key' => [
                'length' => ['min' => 1, 'max' => 100],
                'unique' => true
            ]
        ];

        // Load settings
        $this->load_settings();
    }

    /**
     * Load all settings
     */
    protected function load_settings() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT `group`, `key`, `value` FROM {$wpdb->prefix}{$this->table} WHERE autoload = 1",
            ARRAY_A
        );

        foreach ($results as $row) {
            $this->data[$row['group']][$row['key']] = maybe_unserialize($row['value']);
        }
    }

    /**
     * Get setting value
     *
     * @param string $group Setting group
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get($group, $key = null, $default = null) {
        if ($key === null) {
            return isset($this->data[$group]) 
                ? $this->data[$group] 
                : ($this->defaults[$group] ?? $default);
        }

        return isset($this->data[$group][$key])
            ? $this->data[$group][$key]
            : ($this->defaults[$group][$key] ?? $default);
    }

    /**
     * Set setting value
     *
     * @param string $group Setting group
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Whether update was successful
     */
    public function set($group, $key, $value) {
        try {
            global $wpdb;

            $data = [
                'group' => $group,
                'key' => $key,
                'value' => maybe_serialize($value),
                'updated_by' => $this->current_user,
                'updated_at' => $this->current_time
            ];

            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}{$this->table} WHERE `group` = %s AND `key` = %s",
                    $group,
                    $key
                )
            );

            if ($existing) {
                $result = $wpdb->update(
                    $wpdb->prefix . $this->table,
                    $data,
                    ['id' => $existing->id],
                    ['%s', '%s', '%s', '%s', '%s'],
                    ['%d']
                );
            } else {
                $data['created_by'] = $this->current_user;
                $data['created_at'] = $this->current_time;
                $result = $wpdb->insert(
                    $wpdb->prefix . $this->table,
                    $data,
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );
            }

            if ($result === false) {
                throw new Exception(__('Failed to update setting.', 'mfw'));
            }

            // Update local cache
            $this->data[$group][$key] = $value;

            // Log setting update
            $this->log_setting_update($group, $key, $value);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Delete setting
     *
     * @param string $group Setting group
     * @param string $key Setting key
     * @return bool Whether deletion was successful
     */
    public function delete($group, $key) {
        try {
            global $wpdb;

            $result = $wpdb->delete(
                $wpdb->prefix . $this->table,
                [
                    'group' => $group,
                    'key' => $key
                ],
                ['%s', '%s']
            );

            if ($result === false) {
                throw new Exception(__('Failed to delete setting.', 'mfw'));
            }

            // Update local cache
            unset($this->data[$group][$key]);

            // Log setting deletion
            $this->log_setting_update($group, $key, null, 'delete');

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Reset settings to defaults
     *
     * @param string $group Setting group to reset
     * @return bool Whether reset was successful
     */
    public function reset($group = null) {
        try {
            global $wpdb;

            if ($group) {
                if (!isset($this->defaults[$group])) {
                    throw new Exception(sprintf(__('Invalid setting group: %s', 'mfw'), $group));
                }

                $wpdb->delete(
                    $wpdb->prefix . $this->table,
                    ['group' => $group],
                    ['%s']
                );

                foreach ($this->defaults[$group] as $key => $value) {
                    $this->set($group, $key, $value);
                }
            } else {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$this->table}");

                foreach ($this->defaults as $group => $settings) {
                    foreach ($settings as $key => $value) {
                        $this->set($group, $key, $value);
                    }
                }
            }

            // Log settings reset
            $this->log_setting_update($group, null, null, 'reset');

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Log setting update
     *
     * @param string $group Setting group
     * @param string $key Setting key
     * @param mixed $value New value
     * @param string $operation Operation type
     */
    protected function log_setting_update($group, $key = null, $value = null, $operation = 'update') {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_settings_log',
                [
                    'group' => $group,
                    'key' => $key,
                    'value' => $value !== null ? maybe_serialize($value) : null,
                    'operation' => $operation,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log setting update: %s', $e->getMessage()),
                get_class($this),
                'error'
            );
        }
    }
}