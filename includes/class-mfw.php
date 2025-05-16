<?php
/**
 * Main Framework Class
 * 
 * Core class that bootstraps and manages the entire framework.
 * Handles initialization, component registration, and framework lifecycle.
 *
 * @package MFW
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

final class MFW {
    use MFW_Singleton;
    use MFW_Configurable;
    use MFW_Loggable;

    /**
     * Framework version
     */
    const VERSION = '1.0.0';

    /**
     * Framework components
     *
     * @var array
     */
    private $components = [];

    /**
     * Components registry
     *
     * @var MFW_Registry
     */
    public $registry;

    /**
     * Service container
     *
     * @var MFW_Container
     */
    public $container;

    /**
     * Service manager
     *
     * @var MFW_Services
     */
    public $services;

    /**
     * Initialize framework
     */
    protected function __construct() {
        $this->init_timestamp = '2025-05-14 06:30:28';
        $this->init_user = 'maziyarid';

        // Initialize components
        $this->init_components();

        // Load framework
        $this->load();

        // Register hooks
        $this->register_hooks();

        $this->info('Framework initialized', [
            'version' => self::VERSION,
            'timestamp' => $this->init_timestamp,
            'user' => $this->init_user
        ]);
    }

    /**
     * Initialize framework components
     */
    private function init_components() {
        // Initialize registry
        $this->registry = new MFW_Registry();

        // Initialize container
        $this->container = new MFW_Container();

        // Initialize services
        $this->services = new MFW_Services();

        // Register core services
        $this->register_core_services();
    }

    /**
     * Register core services
     */
    private function register_core_services() {
        $core_services = [
            'cache' => MFW_Cache_Service::class,
            'config' => MFW_Config_Service::class,
            'database' => MFW_Database_Service::class,
            'events' => MFW_Events_Service::class,
            'http' => MFW_Http_Service::class,
            'logger' => MFW_Logger_Service::class,
            'security' => MFW_Security_Service::class,
            'storage' => MFW_Storage_Service::class
        ];

        foreach ($core_services as $name => $class) {
            $this->services->register($name, $class);
        }
    }

    /**
     * Load framework
     */
    private function load() {
        // Load configuration
        $this->load_config();

        // Load components
        $this->load_components();

        // Boot services
        $this->boot_services();
    }

    /**
     * Load framework configuration
     */
    private function load_config() {
        $config_file = MFW_PATH . 'config/framework.php';
        
        if (file_exists($config_file)) {
            $config = require $config_file;
            $this->set_config($config);
        }
    }

    /**
     * Load framework components
     */
    private function load_components() {
        $components = $this->get_config('components', []);

        foreach ($components as $component => $config) {
            $this->load_component($component, $config);
        }
    }

    /**
     * Load framework component
     *
     * @param string $component Component name
     * @param array $config Component configuration
     */
    private function load_component($component, $config) {
        $class = isset($config['class']) ? $config['class'] : 'MFW_' . ucfirst($component);

        if (class_exists($class)) {
            $this->components[$component] = new $class($config);
            $this->registry->register($component, $this->components[$component]);
        } else {
            $this->error('Failed to load component', [
                'component' => $component,
                'class' => $class
            ]);
        }
    }

    /**
     * Boot framework services
     */
    private function boot_services() {
        $services = $this->services->get_bootable();

        foreach ($services as $service) {
            $service->boot();
        }
    }

    /**
     * Register framework hooks
     */
    private function register_hooks() {
        // Register activation hook
        register_activation_hook(MFW_FILE, [$this, 'activate']);

        // Register deactivation hook
        register_deactivation_hook(MFW_FILE, [$this, 'deactivate']);

        // Register uninstall hook
        register_uninstall_hook(MFW_FILE, [__CLASS__, 'uninstall']);

        // Add init action
        add_action('init', [$this, 'init']);

        // Add admin init action
        add_action('admin_init', [$this, 'admin_init']);
    }

    /**
     * Framework activation
     */
    public function activate() {
        $this->info('Framework activated');

        // Run database migrations
        $this->services->get('database')->migrate();

        // Clear cache
        $this->services->get('cache')->clear();

        do_action('mfw_activated');
    }

    /**
     * Framework deactivation
     */
    public function deactivate() {
        $this->info('Framework deactivated');

        // Clear cache
        $this->services->get('cache')->clear();

        do_action('mfw_deactivated');
    }

    /**
     * Framework uninstall
     */
    public static function uninstall() {
        // Get instance
        $instance = self::get_instance();

        $instance->info('Framework uninstalled');

        // Run database cleanup
        $instance->services->get('database')->cleanup();

        do_action('mfw_uninstalled');
    }

    /**
     * Framework initialization
     */
    public function init() {
        do_action('mfw_init');
    }

    /**
     * Admin initialization
     */
    public function admin_init() {
        do_action('mfw_admin_init');
    }

    /**
     * Get component
     *
     * @param string $name Component name
     * @return object|null Component instance
     */
    public function get_component($name) {
        return $this->components[$name] ?? null;
    }

    /**
     * Get framework info
     *
     * @return array Framework information
     */
    public function get_info() {
        return [
            'version' => self::VERSION,
            'initialized' => $this->init_timestamp,
            'initialized_by' => $this->init_user,
            'components' => array_keys($this->components),
            'services' => $this->services->get_info(),
            'config' => $this->get_config()
        ];
    }
}