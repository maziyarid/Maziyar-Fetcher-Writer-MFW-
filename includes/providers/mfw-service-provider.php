<?php
/**
 * Base Service Provider Class
 * 
 * Provides base functionality for all framework service providers.
 * Handles service registration and booting.
 *
 * @package MFW
 * @subpackage Providers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Service_Provider {
    /**
     * Service container
     *
     * @var MFW_Container
     */
    protected $container;

    /**
     * Provider initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:45:52';

    /**
     * Provider initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * Create new provider instance
     *
     * @param MFW_Container $container Service container
     */
    public function __construct($container) {
        $this->container = $container;
    }

    /**
     * Register services
     *
     * @return void
     */
    abstract public function register();

    /**
     * Boot services
     *
     * @return void
     */
    public function boot() {}

    /**
     * Register service binding
     *
     * @param string $abstract Abstract type
     * @param mixed $concrete Concrete type
     * @param bool $shared Whether service is shared
     * @return void
     */
    protected function bind($abstract, $concrete = null, $shared = false) {
        $this->container->bind($abstract, $concrete, $shared);
    }

    /**
     * Register shared service binding
     *
     * @param string $abstract Abstract type
     * @param mixed $concrete Concrete type
     * @return void
     */
    protected function singleton($abstract, $concrete = null) {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register service instance
     *
     * @param string $abstract Abstract type
     * @param mixed $instance Service instance
     * @return void
     */
    protected function instance($abstract, $instance) {
        $this->container->instance($abstract, $instance);
    }
}