<?php
/**
 * Service Abstract Class
 * 
 * Provides base functionality for all services.
 * Handles service registration, configuration, and lifecycle management.
 *
 * @package MFW
 * @subpackage Abstracts
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Abstract_Service extends MFW_Abstract_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:27:13';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Service configuration
     */
    protected $config = [
        'name' => '',
        'description' => '',
        'version' => '1.0.0',
        'singleton' => true,
        'bootable' => true,
        'dependencies' => [],
        'provides' => []
    ];

    /**
     * Service instance
     */
    protected static $instance;

    /**
     * Service status
     */
    protected $status = 'stopped';

    /**
     * Service container
     */
    protected $container;

    /**
     * Get service instance
     *
     * @return static Service instance
     */
    public static function get_instance() {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Initialize service
     */
    protected function init() {
        // Set service configuration
        $this->setup();

        // Initialize container
        $this->container = new MFW_Container();

        // Register service bindings
        $this->register();

        // Boot service if bootable
        if ($this->config['bootable']) {
            $this->boot();
        }
    }

    /**
     * Setup service configuration
     * Must be implemented by child classes
     */
    abstract protected function setup();

    /**
     * Register service bindings
     * Must be implemented by child classes
     */
    abstract protected function register();

    /**
     * Boot service
     *
     * @return bool Whether boot was successful
     */
    public function boot() {
        try {
            if ($this->status !== 'stopped') {
                throw new Exception(__('Service is already running.', 'mfw'));
            }

            // Check dependencies
            if (!$this->check_dependencies()) {
                throw new Exception(__('Service dependencies not met.', 'mfw'));
            }

            // Boot service
            $this->do_boot();

            // Update status
            $this->status = 'running';

            // Log service boot
            $this->log_service_status('boot');

            return true;

        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Stop service
     *
     * @return bool Whether stop was successful
     */
    public function stop() {
        try {
            if ($this->status !== 'running') {
                throw new Exception(__('Service is not running.', 'mfw'));
            }

            // Stop service
            $this->do_stop();

            // Update status
            $this->status = 'stopped';

            // Log service stop
            $this->log_service_status('stop');

            return true;

        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Restart service
     *
     * @return bool Whether restart was successful
     */
    public function restart() {
        try {
            if ($this->status === 'running') {
                $this->stop();
            }

            return $this->boot();

        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get service status
     *
     * @return string Service status
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get service config
     *
     * @param string $key Config key
     * @param mixed $default Default value
     * @return mixed Config value
     */
    public function get_config($key = null, $default = null) {
        if ($key === null) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Check if service provides capability
     *
     * @param string $capability Capability name
     * @return bool Whether service provides capability
     */
    public function provides($capability) {
        return in_array($capability, $this->config['provides']);
    }

    /**
     * Get container instance
     *
     * @return MFW_Container Container instance
     */
    public function get_container() {
        return $this->container;
    }

    /**
     * Perform actual boot operations
     * Can be overridden by child classes
     */
    protected function do_boot() {
        // Default implementation
    }

    /**
     * Perform actual stop operations
     * Can be overridden by child classes
     */
    protected function do_stop() {
        // Default implementation
    }

    /**
     * Check service dependencies
     *
     * @return bool Whether dependencies are met
     */
    protected function check_dependencies() {
        foreach ($this->config['dependencies'] as $dependency) {
            if (!$this->resolve_dependency($dependency)) {
                $this->add_error(
                    sprintf(
                        __('Dependency not met: %s', 'mfw'),
                        $dependency
                    )
                );
                return false;
            }
        }
        return true;
    }

    /**
     * Resolve service dependency
     *
     * @param string $dependency Dependency name
     * @return bool Whether dependency is resolved
     */
    protected function resolve_dependency($dependency) {
        // Check if dependency is a service
        if (MFW()->services->has($dependency)) {
            $service = MFW()->services->get($dependency);
            return $service && $service->get_status() === 'running';
        }

        // Check if dependency is provided by any running service
        foreach (MFW()->services->all() as $service) {
            if ($service->get_status() === 'running' && $service->provides($dependency)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log service status change
     *
     * @param string $action Status change action
     */
    protected function log_service_status($action) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_service_log',
                [
                    'service_id' => $this->id,
                    'action' => $action,
                    'status' => $this->status,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            $this->log(
                sprintf('Failed to log service status: %s', $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * Prevent cloning of singleton service
     */
    private function __clone() {
        // Prevent cloning
    }

    /**
     * Prevent unserializing of singleton service
     */
    private function __wakeup() {
        // Prevent unserializing
    }
}