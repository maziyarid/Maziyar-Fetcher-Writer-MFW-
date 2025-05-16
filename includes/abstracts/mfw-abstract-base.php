<?php
/**
 * Base Abstract Class
 * 
 * Provides core functionality for all abstract classes.
 * Handles common methods and properties.
 *
 * @package MFW
 * @subpackage Abstracts
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Abstract_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:22:39';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Unique identifier
     */
    protected $id;

    /**
     * Error messages
     */
    protected $errors = [];

    /**
     * Debug messages
     */
    protected $debug = [];

    /**
     * Is initialized flag
     */
    private $initialized = false;

    /**
     * Constructor
     *
     * @param mixed $id Object identifier
     */
    public function __construct($id = null) {
        $this->id = $id;
        $this->init();
        $this->initialized = true;
    }

    /**
     * Initialize object
     * 
     * @return void
     */
    abstract protected function init();

    /**
     * Get object ID
     *
     * @return mixed Object ID
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Set object ID
     *
     * @param mixed $id Object ID
     * @return void
     */
    public function set_id($id) {
        $this->id = $id;
    }

    /**
     * Get error messages
     *
     * @return array Error messages
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Add error message
     *
     * @param string $message Error message
     * @param string $code Error code
     * @return void
     */
    protected function add_error($message, $code = '') {
        $this->errors[] = [
            'message' => $message,
            'code' => $code,
            'time' => $this->current_time
        ];
    }

    /**
     * Clear error messages
     *
     * @return void
     */
    protected function clear_errors() {
        $this->errors = [];
    }

    /**
     * Has errors
     *
     * @return bool Whether object has errors
     */
    public function has_errors() {
        return !empty($this->errors);
    }

    /**
     * Get debug messages
     *
     * @return array Debug messages
     */
    public function get_debug() {
        return $this->debug;
    }

    /**
     * Add debug message
     *
     * @param string $message Debug message
     * @param string $context Debug context
     * @return void
     */
    protected function add_debug($message, $context = '') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug[] = [
                'message' => $message,
                'context' => $context,
                'time' => $this->current_time
            ];
        }
    }

    /**
     * Clear debug messages
     *
     * @return void
     */
    protected function clear_debug() {
        $this->debug = [];
    }

    /**
     * Is initialized
     *
     * @return bool Whether object is initialized
     */
    protected function is_initialized() {
        return $this->initialized;
    }

    /**
     * Convert object to array
     *
     * @return array Object data
     */
    public function to_array() {
        return [
            'id' => $this->id,
            'errors' => $this->errors,
            'debug' => $this->debug
        ];
    }

    /**
     * Convert object to JSON
     *
     * @return string JSON representation
     */
    public function to_json() {
        return wp_json_encode($this->to_array());
    }

    /**
     * Magic getter
     *
     * @param string $name Property name
     * @return mixed Property value
     */
    public function __get($name) {
        $method = "get_{$name}";
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }

    /**
     * Magic setter
     *
     * @param string $name Property name
     * @param mixed $value Property value
     * @return void
     */
    public function __set($name, $value) {
        $method = "set_{$name}";
        if (method_exists($this, $method)) {
            $this->$method($value);
        }
    }

    /**
     * Magic isset
     *
     * @param string $name Property name
     * @return bool Whether property exists
     */
    public function __isset($name) {
        $method = "get_{$name}";
        return method_exists($this, $method);
    }

    /**
     * Log message
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     * @return void
     */
    protected function log($message, $level = 'info', $context = []) {
        MFW_Error_Logger::log(
            $message,
            get_class($this),
            $level,
            array_merge($context, [
                'object_id' => $this->id,
                'user' => $this->current_user,
                'time' => $this->current_time
            ])
        );
    }
}