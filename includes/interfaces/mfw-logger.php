<?php
/**
 * Logger Interface
 * 
 * Defines the contract for logging operations.
 * Ensures consistent logging behavior across the framework.
 * PSR-3 Logger Interface compatible.
 *
 * @package MFW
 * @subpackage Interfaces
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

interface MFW_Logger_Interface {
    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function log($level, $message, array $context = []);

    /**
     * Log an emergency message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function emergency($message, array $context = []);

    /**
     * Log an alert message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function alert($message, array $context = []);

    /**
     * Log a critical message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function critical($message, array $context = []);

    /**
     * Log an error message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function error($message, array $context = []);

    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function warning($message, array $context = []);

    /**
     * Log a notice message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function notice($message, array $context = []);

    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function info($message, array $context = []);

    /**
     * Log a debug message
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Whether log was successful
     */
    public function debug($message, array $context = []);

    /**
     * Get log entries
     *
     * @param array $filters Log filters
     * @param array $options Query options
     * @return array Log entries
     */
    public function get_logs(array $filters = [], array $options = []);

    /**
     * Clear log entries
     *
     * @param array $filters Log filters
     * @return bool Whether clear was successful
     */
    public function clear_logs(array $filters = []);

    /**
     * Get log statistics
     *
     * @param array $filters Log filters
     * @return array Log statistics
     */
    public function get_stats(array $filters = []);

    /**
     * Export log entries
     *
     * @param array $filters Log filters
     * @param string $format Export format
     * @return string Exported logs
     */
    public function export_logs(array $filters = [], $format = 'json');

    /**
     * Add log handler
     *
     * @param mixed $handler Log handler instance
     * @param string $level Minimum log level
     * @return bool Whether handler was added
     */
    public function add_handler($handler, $level = self::DEBUG);

    /**
     * Remove log handler
     *
     * @param mixed $handler Log handler instance
     * @return bool Whether handler was removed
     */
    public function remove_handler($handler);

    /**
     * Get log handlers
     *
     * @return array Log handlers
     */
    public function get_handlers();

    /**
     * Add log processor
     *
     * @param callable $processor Log processor callback
     * @return bool Whether processor was added
     */
    public function add_processor(callable $processor);

    /**
     * Remove log processor
     *
     * @param callable $processor Log processor callback
     * @return bool Whether processor was removed
     */
    public function remove_processor(callable $processor);

    /**
     * Get log processors
     *
     * @return array Log processors
     */
    public function get_processors();

    /**
     * Set minimum log level
     *
     * @param string $level Minimum log level
     * @return bool Whether level was set
     */
    public function set_minimum_level($level);

    /**
     * Get minimum log level
     *
     * @return string Minimum log level
     */
    public function get_minimum_level();

    /**
     * Check if level is enabled
     *
     * @param string $level Log level
     * @return bool Whether level is enabled
     */
    public function is_level_enabled($level);

    /**
     * Set logger name
     *
     * @param string $name Logger name
     * @return bool Whether name was set
     */
    public function set_name($name);

    /**
     * Get logger name
     *
     * @return string Logger name
     */
    public function get_name();

    /**
     * Set logger timezone
     *
     * @param \DateTimeZone $timezone Logger timezone
     * @return bool Whether timezone was set
     */
    public function set_timezone(\DateTimeZone $timezone);

    /**
     * Get logger timezone
     *
     * @return \DateTimeZone Logger timezone
     */
    public function get_timezone();
}