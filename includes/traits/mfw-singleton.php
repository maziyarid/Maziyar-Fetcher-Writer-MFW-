<?php
/**
 * Singleton Trait
 * 
 * Implements the singleton pattern for classes.
 * Ensures only one instance of a class exists.
 *
 * @package MFW
 * @subpackage Traits
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

trait MFW_Singleton {
    /**
     * Instance storage
     *
     * @var array
     */
    private static $instances = [];

    /**
     * Creation timestamp
     *
     * @var string
     */
    private $created_at = '2025-05-14 06:25:58';

    /**
     * Created by user
     *
     * @var string
     */
    private $created_by = 'maziyarid';

    /**
     * Get instance of the class
     *
     * @param mixed ...$args Constructor arguments
     * @return static Class instance
     */
    public static function get_instance(...$args) {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static(...$args);
            self::register_instance($class);
        }

        return self::$instances[$class];
    }

    /**
     * Register instance with the framework
     *
     * @param string $class Class name
     */
    private static function register_instance($class) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_singleton_instances',
                [
                    'class_name' => $class,
                    'created_at' => self::$instances[$class]->created_at,
                    'created_by' => self::$instances[$class]->created_by
                ],
                ['%s', '%s', '%s']
            );

        } catch (Exception $e) {
            error_log(sprintf('Failed to register singleton instance: %s', $e->getMessage()));
        }
    }

    /**
     * Get all registered instances
     *
     * @return array Registered instances
     */
    public static function get_instances() {
        return self::$instances;
    }

    /**
     * Check if instance exists
     *
     * @param string $class Class name
     * @return bool Whether instance exists
     */
    public static function has_instance($class) {
        return isset(self::$instances[$class]);
    }

    /**
     * Clear instance
     *
     * @param string $class Class name
     * @return bool Whether clear was successful
     */
    public static function clear_instance($class) {
        if (isset(self::$instances[$class])) {
            unset(self::$instances[$class]);
            return true;
        }
        return false;
    }

    /**
     * Clear all instances
     */
    public static function clear_all_instances() {
        self::$instances = [];
    }

    /**
     * Get instance creation time
     *
     * @return string Creation timestamp
     */
    public function get_creation_time() {
        return $this->created_at;
    }

    /**
     * Get instance creator
     *
     * @return string Creator username
     */
    public function get_creator() {
        return $this->created_by;
    }

    /**
     * Prevent direct object creation
     */
    private function __construct() {
        // Ensure singleton
    }

    /**
     * Prevent object cloning
     */
    private function __clone() {
        // Prevent cloning
    }

    /**
     * Prevent object unserialization
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}