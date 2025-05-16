<?php
/**
 * Configurable Trait
 * 
 * Implements configuration management for classes.
 * Provides methods for handling configuration values with validation and persistence.
 *
 * @package MFW
 * @subpackage Traits
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

trait MFW_Configurable {
    /**
     * Configuration storage
     *
     * @var array
     */
    private $config = [];

    /**
     * Configuration schema
     *
     * @var array
     */
    private $config_schema = [];

    /**
     * Configuration defaults
     *
     * @var array
     */
    private $config_defaults = [];

    /**
     * Last update timestamp
     *
     * @var string
     */
    private $last_update = '2025-05-14 06:27:09';

    /**
     * Last updater
     *
     * @var string
     */
    private $last_updater = 'maziyarid';

    /**
     * Initialize configuration
     *
     * @param array $config Initial configuration
     * @return bool Whether initialization was successful
     */
    protected function init_config(array $config = []) {
        $this->set_config_schema();
        $this->set_config_defaults();
        return $this->set_config($config);
    }

    /**
     * Set configuration schema
     * Should be overridden by implementing class
     */
    protected function set_config_schema() {
        $this->config_schema = [];
    }

    /**
     * Set configuration defaults
     * Should be overridden by implementing class
     */
    protected function set_config_defaults() {
        $this->config_defaults = [];
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get_config($key = null, $default = null) {
        if ($key === null) {
            return $this->config;
        }

        return $this->get_array_value($this->config, $key, $default);
    }

    /**
     * Set configuration value(s)
     *
     * @param string|array $key Configuration key or array of key-value pairs
     * @param mixed $value Configuration value (if $key is string)
     * @return bool Whether set was successful
     */
    public function set_config($key, $value = null) {
        try {
            if (is_array($key)) {
                $config = array_merge($this->config_defaults, $key);
            } else {
                $config = $this->config;
                $this->set_array_value($config, $key, $value);
            }

            if (!$this->validate_config($config)) {
                return false;
            }

            $this->config = $config;
            $this->last_update = current_time('mysql');
            $this->last_updater = wp_get_current_user()->user_login;

            $this->log_config_update();
            return true;

        } catch (Exception $e) {
            error_log(sprintf('Failed to set configuration: %s', $e->getMessage()));
            return false;
        }
    }

    /**
     * Validate configuration
     *
     * @param array $config Configuration to validate
     * @return bool Whether configuration is valid
     */
    protected function validate_config($config) {
        foreach ($this->config_schema as $key => $schema) {
            $value = $this->get_array_value($config, $key);

            if (!$this->validate_config_value($value, $schema)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate configuration value
     *
     * @param mixed $value Value to validate
     * @param array $schema Validation schema
     * @return bool Whether value is valid
     */
    protected function validate_config_value($value, $schema) {
        if (isset($schema['required']) && $schema['required'] && $value === null) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        switch ($schema['type']) {
            case 'string':
                if (!is_string($value)) return false;
                if (isset($schema['pattern']) && !preg_match($schema['pattern'], $value)) return false;
                if (isset($schema['enum']) && !in_array($value, $schema['enum'])) return false;
                break;

            case 'integer':
                if (!is_int($value)) return false;
                if (isset($schema['minimum']) && $value < $schema['minimum']) return false;
                if (isset($schema['maximum']) && $value > $schema['maximum']) return false;
                break;

            case 'number':
                if (!is_numeric($value)) return false;
                if (isset($schema['minimum']) && $value < $schema['minimum']) return false;
                if (isset($schema['maximum']) && $value > $schema['maximum']) return false;
                break;

            case 'boolean':
                if (!is_bool($value)) return false;
                break;

            case 'array':
                if (!is_array($value)) return false;
                if (isset($schema['items'])) {
                    foreach ($value as $item) {
                        if (!$this->validate_config_value($item, $schema['items'])) return false;
                    }
                }
                break;

            case 'object':
                if (!is_array($value)) return false;
                if (isset($schema['properties'])) {
                    foreach ($schema['properties'] as $prop => $prop_schema) {
                        if (!$this->validate_config_value($value[$prop] ?? null, $prop_schema)) return false;
                    }
                }
                break;
        }

        return true;
    }

    /**
     * Get array value using dot notation
     *
     * @param array $array Array to search
     * @param string $key Key in dot notation
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Array value
     */
    private function get_array_value($array, $key, $default = null) {
        $keys = explode('.', $key);
        $current = $array;

        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set array value using dot notation
     *
     * @param array &$array Array to modify
     * @param string $key Key in dot notation
     * @param mixed $value Value to set
     */
    private function set_array_value(&$array, $key, $value) {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $segment) {
            if (!is_array($current)) {
                $current = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * Log configuration update
     */
    private function log_config_update() {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_config_log',
                [
                    'configurable_class' => get_class($this),
                    'config' => json_encode($this->config),
                    'created_at' => $this->last_update,
                    'created_by' => $this->last_updater
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            error_log(sprintf('Failed to log configuration update: %s', $e->getMessage()));
        }
    }
}