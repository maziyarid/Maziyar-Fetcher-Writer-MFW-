<?php
/**
 * Config Interface
 * 
 * Defines the contract for configuration management.
 * Ensures consistent configuration handling across the framework.
 *
 * @package MFW
 * @subpackage Interfaces
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

interface MFW_Config_Interface {
    /**
     * Load configuration
     *
     * @param string $source Configuration source
     * @param string $format Configuration format (json, yaml, php, etc.)
     * @return bool Whether load was successful
     */
    public function load($source, $format = 'php');

    /**
     * Save configuration
     *
     * @param string $destination Configuration destination
     * @param string $format Configuration format (json, yaml, php, etc.)
     * @return bool Whether save was successful
     */
    public function save($destination, $format = 'php');

    /**
     * Get configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get($key, $default = null);

    /**
     * Set configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $value Configuration value
     * @return bool Whether set was successful
     */
    public function set($key, $value);

    /**
     * Check if configuration key exists
     *
     * @param string $key Configuration key (dot notation supported)
     * @return bool Whether key exists
     */
    public function has($key);

    /**
     * Remove configuration key
     *
     * @param string $key Configuration key (dot notation supported)
     * @return bool Whether removal was successful
     */
    public function remove($key);

    /**
     * Get all configuration
     *
     * @return array All configuration values
     */
    public function all();

    /**
     * Set multiple configuration values
     *
     * @param array $values Configuration key-value pairs
     * @return bool Whether set was successful
     */
    public function set_many(array $values);

    /**
     * Get multiple configuration values
     *
     * @param array $keys Configuration keys
     * @param mixed $default Default value for missing keys
     * @return array Configuration values
     */
    public function get_many(array $keys, $default = null);

    /**
     * Remove multiple configuration keys
     *
     * @param array $keys Configuration keys
     * @return bool Whether removal was successful
     */
    public function remove_many(array $keys);

    /**
     * Clear all configuration
     *
     * @return bool Whether clear was successful
     */
    public function clear();

    /**
     * Get configuration section
     *
     * @param string $section Section name
     * @return array Section configuration
     */
    public function get_section($section);

    /**
     * Set configuration section
     *
     * @param string $section Section name
     * @param array $values Section configuration
     * @return bool Whether set was successful
     */
    public function set_section($section, array $values);

    /**
     * Remove configuration section
     *
     * @param string $section Section name
     * @return bool Whether removal was successful
     */
    public function remove_section($section);

    /**
     * Get configuration schema
     *
     * @return array Configuration schema
     */
    public function get_schema();

    /**
     * Validate configuration
     *
     * @param array $config Configuration to validate
     * @return bool Whether configuration is valid
     */
    public function validate(array $config);

    /**
     * Get validation errors
     *
     * @return array Validation errors
     */
    public function get_validation_errors();

    /**
     * Merge configuration
     *
     * @param array $config Configuration to merge
     * @param bool $recursive Whether to merge recursively
     * @return bool Whether merge was successful
     */
    public function merge(array $config, $recursive = true);

    /**
     * Export configuration
     *
     * @param string $format Export format (json, yaml, php, etc.)
     * @return string Exported configuration
     */
    public function export($format = 'php');

    /**
     * Import configuration
     *
     * @param string $config Configuration string
     * @param string $format Import format (json, yaml, php, etc.)
     * @return bool Whether import was successful
     */
    public function import($config, $format = 'php');

    /**
     * Get configuration metadata
     *
     * @param string $key Metadata key
     * @return mixed Metadata value
     */
    public function get_metadata($key);

    /**
     * Set configuration metadata
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return bool Whether set was successful
     */
    public function set_metadata($key, $value);

    /**
     * Watch configuration for changes
     *
     * @param string $key Configuration key
     * @param callable $callback Change callback
     * @return bool Whether watch was set
     */
    public function watch($key, callable $callback);

    /**
     * Stop watching configuration
     *
     * @param string $key Configuration key
     * @param callable|null $callback Specific callback to remove (null for all)
     * @return bool Whether watch was removed
     */
    public function unwatch($key, callable $callback = null);
}