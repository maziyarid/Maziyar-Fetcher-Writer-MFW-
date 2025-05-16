<?php
/**
 * App Service Provider
 * 
 * Registers core framework services and bindings.
 *
 * @package MFW
 * @subpackage Providers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_App_Provider extends MFW_Service_Provider {
    /**
     * Register services
     *
     * @return void
     */
    public function register() {
        // Register core services
        $this->singleton('config', function() {
            return new MFW_Config();
        });

        $this->singleton('request', function() {
            return new MFW_Request();
        });

        $this->singleton('response', function() {
            return new MFW_Response();
        });

        $this->singleton('router', function($app) {
            return new MFW_Router($app['request']);
        });

        $this->singleton('view', function() {
            return new MFW_View();
        });

        $this->singleton('cache', function($app) {
            return new MFW_Cache($app['config']->get('cache'));
        });

        $this->singleton('log', function($app) {
            return new MFW_Logger($app['config']->get('logging'));
        });

        $this->singleton('event', function() {
            return new MFW_Event_Manager();
        });

        $this->singleton('auth', function($app) {
            return new MFW_Auth($app['config']->get('auth'));
        });

        $this->singleton('mail', function($app) {
            return new MFW_Mailer($app['config']->get('mail'));
        });

        // Register database services
        $this->singleton('db', function($app) {
            return new MFW_Database($app['config']->get('database'));
        });

        $this->singleton('schema', function($app) {
            return new MFW_Schema_Builder($app['db']);
        });

        // Register utility services
        $this->singleton('validator', function() {
            return new MFW_Validator();
        });

        $this->singleton('filesystem', function() {
            return new MFW_Filesystem();
        });

        $this->singleton('assets', function($app) {
            return new MFW_Asset_Manager($app['config']->get('assets'));
        });
    }

    /**
     * Boot services
     *
     * @return void
     */
    public function boot() {
        // Load configuration
        $this->container['config']->load();

        // Boot router
        $this->container['router']->boot();

        // Register error handlers
        $this->register_error_handlers();

        // Register WordPress hooks
        $this->register_hooks();
    }

    /**
     * Register error handlers
     *
     * @return void
     */
    protected function register_error_handlers() {
        set_error_handler(function($level, $message, $file = '', $line = 0) {
            $this->container['log']->error($message, [
                'level' => $level,
                'file' => $file,
                'line' => $line
            ]);
        });

        set_exception_handler(function($exception) {
            $this->container['log']->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw $exception;
            }
        });
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function register_hooks() {
        // Initialize framework
        add_action('init', function() {
            do_action('mfw_init', $this->container);
        });

        // Handle requests
        add_action('wp', function() {
            $this->container['router']->dispatch();
        });

        // Clean up on shutdown
        add_action('shutdown', function() {
            $this->container['cache']->cleanup();
            $this->container['log']->flush();
        });
    }
}