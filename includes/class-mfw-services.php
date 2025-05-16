<?php
/**
 * Services Class
 * 
 * Manages framework services and their lifecycle.
 * Handles service registration, booting, and dependencies.
 *
 * @package MFW
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Services {
    use MFW_Observable;
    use MFW_Configurable;
    use MFW_Loggable;

    /**
     * Service providers
     *
     * @var array
     */
    private $providers = [];

    /**
     * Booted services
     *
     * @var array
     */
    private $booted = [];

    /**
     * Service dependencies
     *
     * @var array
     */
    private $dependencies = [];

    /**
     * Initialize services
     */
    public function __construct() {
        $this->init_timestamp = '2025-05-14 07:12:20';
        $this->init_user = 'maziyarid';

        $this->info('Services manager initialized');
    }

    /**
     * Register a service provider
     *
     * @param string $name Service name
     * @param string|object $provider Service provider class or instance
     * @param array $config Provider configuration
     * @return bool Whether registration was successful
     */
    public function register($name, $provider, array $config = []) {
        if (isset($this->providers[$name])) {
            $this->warning('Service already registered', ['name' => $name]);
            return false;
        }

        if (is_string($provider)) {
            try {
                $provider = new $provider();
            } catch (Exception $e) {
                $this->error('Failed to instantiate service provider', [
                    'name' => $name,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        }

        if (!method_exists($provider, 'register')) {
            $this->error('Invalid service provider', [
                'name' => $name,
                'class' => get_class($provider)
            ]);
            return false;
        }

        $this->providers[$name] = [
            'provider' => $provider,
            'config' => $config,
            'registered_at' => current_time('mysql'),
            'registered_by' => wp_get_current_user()->user_login
        ];

        if (method_exists($provider, 'provides')) {
            $this->register_dependencies($name, $provider->provides());
        }

        $this->notify('service.registered', [
            'name' => $name,
            'provider' => $provider
        ]);

        $this->debug('Service registered', ['name' => $name]);
        return true;
    }

    /**
     * Register service dependencies
     *
     * @param string $name Service name
     * @param array $provides Service dependencies
     */
    private function register_dependencies($name, array $provides) {
        foreach ($provides as $service) {
            $this->dependencies[$service] = $name;
        }
    }

    /**
     * Boot a service
     *
     * @param string $name Service name
     * @return bool Whether boot was successful
     */
    public function boot($name) {
        if (!isset($this->providers[$name])) {
            $this->error('Service not registered', ['name' => $name]);
            return false;
        }

        if (isset($this->booted[$name])) {
            return true;
        }

        $provider = $this->providers[$name]['provider'];
        $config = $this->providers[$name]['config'];

        try {
            if (method_exists($provider, 'boot')) {
                $provider->boot($config);
            }

            $this->booted[$name] = [
                'booted_at' => current_time('mysql'),
                'booted_by' => wp_get_current_user()->user_login
            ];

            $this->notify('service.booted', [
                'name' => $name,
                'provider' => $provider
            ]);

            $this->debug('Service booted', ['name' => $name]);
            return true;

        } catch (Exception $e) {
            $this->error('Failed to boot service', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get a service provider
     *
     * @param string $name Service name
     * @return object|null Service provider instance
     */
    public function get($name) {
        if (!isset($this->providers[$name])) {
            return null;
        }

        return $this->providers[$name]['provider'];
    }

    /**
     * Get all bootable services
     *
     * @return array Bootable service providers
     */
    public function get_bootable() {
        $bootable = [];

        foreach ($this->providers as $name => $data) {
            if (method_exists($data['provider'], 'boot') && !isset($this->booted[$name])) {
                $bootable[$name] = $data['provider'];
            }
        }

        return $bootable;
    }

    /**
     * Check if service is registered
     *
     * @param string $name Service name
     * @return bool Whether service is registered
     */
    public function has($name) {
        return isset($this->providers[$name]);
    }

    /**
     * Check if service is booted
     *
     * @param string $name Service name
     * @return bool Whether service is booted
     */
    public function is_booted($name) {
        return isset($this->booted[$name]);
    }

    /**
     * Get service information
     *
     * @return array Service information
     */
    public function get_info() {
        $info = [];

        foreach ($this->providers as $name => $data) {
            $info[$name] = [
                'class' => get_class($data['provider']),
                'registered_at' => $data['registered_at'],
                'registered_by' => $data['registered_by'],
                'booted' => isset($this->booted[$name]),
                'booted_at' => $this->booted[$name]['booted_at'] ?? null,
                'booted_by' => $this->booted[$name]['booted_by'] ?? null,
                'config' => $data['config']
            ];
        }

        return $info;
    }

    /**
     * Get service dependencies
     *
     * @param string $name Service name
     * @return array Service dependencies
     */
    public function get_dependencies($name) {
        if (!isset($this->providers[$name])) {
            return [];
        }

        $provider = $this->providers[$name]['provider'];
        return method_exists($provider, 'provides') ? $provider->provides() : [];
    }

    /**
     * Get services that depend on a service
     *
     * @param string $name Service name
     * @return array Dependent services
     */
    public function get_dependents($name) {
        $dependents = [];

        foreach ($this->providers as $service => $data) {
            $dependencies = $this->get_dependencies($service);
            if (in_array($name, $dependencies)) {
                $dependents[] = $service;
            }
        }

        return $dependents;
    }

    /**
     * Register multiple services
     *
     * @param array $services Services to register
     * @return array Registration results
     */
    public function register_many(array $services) {
        $results = [];

        foreach ($services as $name => $service) {
            $provider = $service;
            $config = [];

            if (is_array($service)) {
                $provider = $service['provider'];
                $config = $service['config'] ?? [];
            }

            $results[$name] = $this->register($name, $provider, $config);
        }

        return $results;
    }

    /**
     * Boot multiple services
     *
     * @param array $names Service names
     * @return array Boot results
     */
    public function boot_many(array $names) {
        $results = [];

        foreach ($names as $name) {
            $results[$name] = $this->boot($name);
        }

        return $results;
    }
}