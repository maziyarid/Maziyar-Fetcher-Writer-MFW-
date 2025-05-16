<?php
/**
 * Asset Service Provider
 * 
 * Registers asset services and handlers.
 * Handles scripts, styles, and asset compilation.
 *
 * @package MFW
 * @subpackage Providers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Asset_Provider extends MFW_Service_Provider {
    /**
     * Provider initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 08:04:01';

    /**
     * Provider initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * Register services
     *
     * @return void
     */
    public function register() {
        // Register asset manager
        $this->singleton('assets', function($app) {
            return new MFW_Asset_Manager($app);
        });

        // Register script manager
        $this->singleton('assets.scripts', function($app) {
            return new MFW_Script_Manager(
                $app['filesystem'],
                $app['config']->get('assets.scripts', [])
            );
        });

        // Register style manager
        $this->singleton('assets.styles', function($app) {
            return new MFW_Style_Manager(
                $app['filesystem'],
                $app['config']->get('assets.styles', [])
            );
        });

        // Register manifest manager
        $this->singleton('assets.manifest', function($app) {
            return new MFW_Asset_Manifest(
                $app['filesystem'],
                $app['config']->get('assets.manifest', [])
            );
        });

        // Register webpack compiler
        $this->singleton('assets.webpack', function($app) {
            return new MFW_Webpack_Compiler(
                $app['filesystem'],
                $app['config']->get('assets.webpack', [])
            );
        });

        // Register asset cache
        $this->singleton('assets.cache', function($app) {
            return new MFW_Asset_Cache(
                $app['cache.store'],
                $app['config']->get('assets.cache', [])
            );
        });
    }

    /**
     * Boot services
     *
     * @return void
     */
    public function boot() {
        // Register default assets
        $this->register_default_assets();

        // Register asset routes
        $this->register_routes();

        // Register WordPress hooks
        $this->register_hooks();

        // Register CLI commands
        $this->register_commands();

        // Register admin interface
        $this->register_admin_interface();
    }

    /**
     * Register default assets
     *
     * @return void
     */
    protected function register_default_assets() {
        $assets = $this->container['assets'];
        $version = $this->container['config']->get('app.version');

        // Register core scripts
        $assets->script('mfw-core', 'js/core.js')
            ->version($version)
            ->dependencies(['jquery'])
            ->inFooter(true);

        $assets->script('mfw-admin', 'js/admin.js')
            ->version($version)
            ->dependencies(['mfw-core'])
            ->inFooter(true);

        // Register core styles
        $assets->style('mfw-core', 'css/core.css')
            ->version($version);

        $assets->style('mfw-admin', 'css/admin.css')
            ->version($version)
            ->dependencies(['mfw-core']);

        // Register vendor assets
        $this->register_vendor_assets();
    }

    /**
     * Register vendor assets
     *
     * @return void
     */
    protected function register_vendor_assets() {
        $assets = $this->container['assets'];
        $config = $this->container['config'];

        // Load vendor configurations
        $vendors = $config->get('assets.vendors', []);

        foreach ($vendors as $vendor => $assets_config) {
            if (isset($assets_config['scripts'])) {
                foreach ($assets_config['scripts'] as $handle => $script) {
                    $assets->script($handle, $script['src'])
                        ->version($script['version'] ?? null)
                        ->dependencies($script['deps'] ?? [])
                        ->inFooter($script['footer'] ?? true);
                }
            }

            if (isset($assets_config['styles'])) {
                foreach ($assets_config['styles'] as $handle => $style) {
                    $assets->style($handle, $style['src'])
                        ->version($style['version'] ?? null)
                        ->dependencies($style['deps'] ?? [])
                        ->media($style['media'] ?? 'all');
                }
            }
        }
    }

    /**
     * Register asset routes
     *
     * @return void
     */
    protected function register_routes() {
        $router = $this->container['router'];

        // Register compiled asset route
        $router->get('/_assets/{path}', 'MFW_Asset_Controller@serve')
            ->where('path', '.*')
            ->name('assets.serve');

        // Register asset manifest route
        $router->get('/_assets/manifest.json', 'MFW_Asset_Controller@manifest')
            ->name('assets.manifest');
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function register_hooks() {
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', function() {
            $this->container['assets']->enqueueAdmin();
        });

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', function() {
            $this->container['assets']->enqueueFrontend();
        });

        // Add async/defer attributes
        add_filter('script_loader_tag', function($tag, $handle) {
            return $this->container['assets.scripts']->addAttributes($tag, $handle);
        }, 10, 2);

        // Add asset version query string
        add_filter('style_loader_src', function($src) {
            return $this->container['assets']->addVersion($src);
        });

        add_filter('script_loader_src', function($src) {
            return $this->container['assets']->addVersion($src);
        });

        // Clear asset cache on plugin update
        add_action('upgrader_process_complete', function() {
            $this->container['assets.cache']->flush();
        });
    }

    /**
     * Register CLI commands
     *
     * @return void
     */
    protected function register_commands() {
        if (!class_exists('WP_CLI')) {
            return;
        }

        WP_CLI::add_command('mfw assets', MFW_Asset_Command::class);
    }

    /**
     * Register admin interface
     *
     * @return void
     */
    protected function register_admin_interface() {
        // Add assets tab to plugin settings
        add_filter('mfw_settings_tabs', function($tabs) {
            $tabs['assets'] = __('Assets', 'mfw');
            return $tabs;
        });

        // Add assets settings section
        add_action('mfw_settings_assets', function() {
            $this->render_assets_settings();
        });

        // Handle asset compilation
        add_action('admin_post_mfw_compile_assets', function() {
            if (!current_user_can('manage_options')) {
                wp_die(__('Permission denied.', 'mfw'));
            }

            try {
                $this->container['assets.webpack']->compile();
                $this->container['assets.manifest']->refresh();
                $this->container['assets.cache']->flush();

                add_settings_error(
                    'mfw_messages',
                    'mfw_assets_compiled',
                    __('Assets compiled successfully.', 'mfw'),
                    'updated'
                );
            } catch (\Exception $e) {
                add_settings_error(
                    'mfw_messages',
                    'mfw_assets_error',
                    $e->getMessage(),
                    'error'
                );
            }

            wp_redirect(admin_url('admin.php?page=mfw-settings&tab=assets'));
            exit;
        });
    }

    /**
     * Render assets settings page
     *
     * @return void
     */
    protected function render_assets_settings() {
        $config = $this->container['config'];
        $manifest = $this->container['assets.manifest'];
        
        $compilation_enabled = $config->get('assets.compile', false);
        $cache_enabled = $config->get('assets.cache.enabled', true);
        $manifest_data = $manifest->get();

        include MFW_PLUGIN_DIR . 'views/admin/assets-settings.php';
    }
}