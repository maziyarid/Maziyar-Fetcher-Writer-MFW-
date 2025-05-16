<?php
/**
 * Cache Service Provider
 * 
 * Registers caching services and drivers.
 * Handles cache storage, retrieval, and invalidation.
 *
 * @package MFW
 * @subpackage Providers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Cache_Provider extends MFW_Service_Provider {
    /**
     * Provider initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:50:29';

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
        // Register cache manager
        $this->singleton('cache.manager', function($app) {
            return new MFW_Cache_Manager($app);
        });

        // Register cache store
        $this->singleton('cache.store', function($app) {
            return $app['cache.manager']->driver();
        });

        // Register file cache driver
        $this->singleton('cache.file', function($app) {
            return new MFW_File_Cache(
                $app['filesystem'],
                $app['config']->get('cache.file.path', WP_CONTENT_DIR . '/cache/mfw')
            );
        });

        // Register object cache driver
        $this->singleton('cache.object', function() {
            return new MFW_Object_Cache();
        });

        // Register Redis cache driver
        $this->singleton('cache.redis', function($app) {
            return new MFW_Redis_Cache(
                $app['config']->get('cache.redis', [])
            );
        });

        // Register Memcached cache driver
        $this->singleton('cache.memcached', function($app) {
            return new MFW_Memcached_Cache(
                $app['config']->get('cache.memcached', [])
            );
        });

        // Register rate limiter
        $this->singleton('cache.limiter', function($app) {
            return new MFW_Rate_Limiter(
                $app['cache.store'],
                $app['request']
            );
        });
    }

    /**
     * Boot services
     *
     * @return void
     */
    public function boot() {
        // Create cache directory if using file driver
        if ($this->container['config']->get('cache.driver') === 'file') {
            $this->ensure_cache_directory();
        }

        // Register cache middleware
        $this->register_middleware();

        // Register cache management commands
        $this->register_commands();

        // Register WordPress hooks
        $this->register_hooks();

        // Register cache invalidation events
        $this->register_events();
    }

    /**
     * Ensure cache directory exists
     *
     * @return void
     */
    protected function ensure_cache_directory() {
        $path = $this->container['config']->get(
            'cache.file.path',
            WP_CONTENT_DIR . '/cache/mfw'
        );

        if (!is_dir($path)) {
            wp_mkdir_p($path);
            file_put_contents($path . '/.htaccess', 'Deny from all');
            file_put_contents($path . '/index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Register cache middleware
     *
     * @return void
     */
    protected function register_middleware() {
        $router = $this->container['router'];

        // Page cache middleware
        $router->middleware('cache', function($request, $next) {
            $key = 'page:' . md5($request->url());
            $ttl = $this->container['config']->get('cache.page_ttl', 3600);

            if ($cached = $this->container['cache.store']->get($key)) {
                return $cached;
            }

            $response = $next($request);
            $this->container['cache.store']->put($key, $response, $ttl);

            return $response;
        });

        // Rate limiting middleware
        $router->middleware('throttle', function($request, $next, $maxAttempts = 60, $decayMinutes = 1) {
            $limiter = $this->container['cache.limiter'];

            if (!$limiter->attempt($maxAttempts, $decayMinutes)) {
                return response()->json([
                    'error' => 'Too many attempts. Please try again later.'
                ], 429);
            }

            $response = $next($request);
            return $response->withHeaders($limiter->headers());
        });
    }

    /**
     * Register cache management commands
     *
     * @return void
     */
    protected function register_commands() {
        if (!class_exists('WP_CLI')) {
            return;
        }

        WP_CLI::add_command('mfw cache', MFW_Cache_Command::class);
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function register_hooks() {
        // Clear page cache on post update
        add_action('save_post', function($post_id) {
            $this->clear_post_cache($post_id);
        });

        // Clear page cache on comment update
        add_action('wp_insert_comment', function($comment_id) {
            $comment = get_comment($comment_id);
            if ($comment) {
                $this->clear_post_cache($comment->comment_post_ID);
            }
        });

        // Clear user cache on profile update
        add_action('profile_update', function($user_id) {
            $this->clear_user_cache($user_id);
        });

        // Clear term cache on term update
        add_action('edited_term', function($term_id) {
            $this->clear_term_cache($term_id);
        });

        // Add cache controls to admin bar
        add_action('admin_bar_menu', function($admin_bar) {
            if (!current_user_can('manage_options')) {
                return;
            }

            $admin_bar->add_menu([
                'id' => 'mfw-cache',
                'title' => __('Cache', 'mfw'),
                'href' => '#'
            ]);

            $admin_bar->add_menu([
                'parent' => 'mfw-cache',
                'id' => 'mfw-cache-clear',
                'title' => __('Clear All Cache', 'mfw'),
                'href' => wp_nonce_url(
                    admin_url('admin-ajax.php?action=mfw_clear_cache'),
                    'mfw_clear_cache'
                )
            ]);
        }, 100);

        // Handle cache clear AJAX action
        add_action('wp_ajax_mfw_clear_cache', function() {
            if (!current_user_can('manage_options') || !check_ajax_referer('mfw_clear_cache', false, false)) {
                wp_send_json_error(['message' => __('Permission denied.', 'mfw')]);
            }

            $this->container['cache.store']->flush();
            wp_send_json_success(['message' => __('Cache cleared successfully.', 'mfw')]);
        });
    }

    /**
     * Register cache invalidation events
     *
     * @return void
     */
    protected function register_events() {
        $events = $this->container['event'];

        // Clear model cache on save
        $events->listen('model.saved', function($model) {
            $key = get_class($model) . ':' . $model->id;
            $this->container['cache.store']->forget($key);
        });

        // Clear model cache on delete
        $events->listen('model.deleted', function($model) {
            $key = get_class($model) . ':' . $model->id;
            $this->container['cache.store']->forget($key);
        });
    }

    /**
     * Clear post related cache
     *
     * @param int $post_id Post ID
     * @return void
     */
    protected function clear_post_cache($post_id) {
        $store = $this->container['cache.store'];
        $post = get_post($post_id);

        if (!$post) {
            return;
        }

        // Clear single post cache
        $store->forget('post:' . $post_id);

        // Clear archive pages
        $store->forget('archive:' . $post->post_type);

        // Clear term archives
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = get_the_terms($post_id, $taxonomy);
            if ($terms) {
                foreach ($terms as $term) {
                    $store->forget('term:' . $term->term_id);
                }
            }
        }

        // Clear author archive
        $store->forget('author:' . $post->post_author);

        // Clear homepage if needed
        if ($post->post_type === 'post') {
            $store->forget('page:home');
        }
    }

    /**
     * Clear user related cache
     *
     * @param int $user_id User ID
     * @return void
     */
    protected function clear_user_cache($user_id) {
        $store = $this->container['cache.store'];

        // Clear user data cache
        $store->forget('user:' . $user_id);

        // Clear user posts archive
        $store->forget('author:' . $user_id);
    }

    /**
     * Clear term related cache
     *
     * @param int $term_id Term ID
     * @return void
     */
    protected function clear_term_cache($term_id) {
        $store = $this->container['cache.store'];
        $term = get_term($term_id);

        if (!$term) {
            return;
        }

        // Clear term data cache
        $store->forget('term:' . $term_id);

        // Clear taxonomy archive
        $store->forget('taxonomy:' . $term->taxonomy);
    }
}