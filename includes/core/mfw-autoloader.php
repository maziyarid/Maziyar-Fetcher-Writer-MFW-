<?php
namespace MFW\Core;

class Autoloader {
    private static $plugin_dir;
    private static $includes_dir;

    /**
     * Register the autoloader
     */
    public static function register() {
        try {
            // Set up plugin directories
            self::$plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
            self::$includes_dir = self::$plugin_dir . 'includes/';

            // Verify directories exist
            if (!is_dir(self::$plugin_dir) || !is_dir(self::$includes_dir)) {
                throw new \Exception('Plugin directories not found');
            }

            // Register autoloader
            spl_autoload_register([__CLASS__, 'autoload']);
            
        } catch (\Exception $e) {
            error_log(sprintf('[MFW Autoloader Error] Registration failed: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Autoload MFW classes
     * 
     * @param string $class Full qualified class name
     * @return void
     */
    public static function autoload($class) {
        try {
            // Only handle our namespace
            if (strpos($class, 'MFW\\') !== 0) {
                return;
            }

            // Remove namespace prefix
            $relative_class = substr($class, strlen('MFW\\'));

            // Convert namespace separators to directory separators
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);
            
            // Parse the class name
            $parts = explode(DIRECTORY_SEPARATOR, $file);
            $class_name = end($parts);
            
            // Convert class name to kebab case with mfw prefix
            $file_name = 'mfw-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name));
            $parts[count($parts) - 1] = $file_name;
            
            // Build the full file path
            $file_path = self::$includes_dir . strtolower(implode(DIRECTORY_SEPARATOR, $parts)) . '.php';

            // Debug logging
            self::log_debug('Attempting to load: ' . $file_path);

            // Load the file if it exists
            if (file_exists($file_path)) {
                require_once $file_path;
                
                // Verify the class was loaded
                if (!class_exists($class, false)) {
                    throw new \Exception("File loaded but class not found: $class");
                }
            } else {
                throw new \Exception("File not found: $file_path");
            }

        } catch (\Exception $e) {
            self::log_error($e->getMessage());
            throw $e;
        }
    }

    /**
     * Log debug message
     */
    private static function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[MFW Autoloader Debug] ' . $message);
        }
    }

    /**
     * Log error message
     */
    private static function log_error($message) {
        error_log('[MFW Autoloader Error] ' . $message);
    }
}
