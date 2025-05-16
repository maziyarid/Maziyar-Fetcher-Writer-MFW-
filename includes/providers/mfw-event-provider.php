<?php
/**
 * Event Service Provider
 * 
 * Registers event services and listeners.
 * Handles event dispatching, listening, and subscription.
 *
 * @package MFW
 * @subpackage Providers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Event_Provider extends MFW_Service_Provider {
    /**
     * Provider initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:51:44';

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
        // Register event dispatcher
        $this->singleton('events', function($app) {
            return new MFW_Event_Dispatcher($app);
        });

        // Register event subscriber
        $this->singleton('events.subscriber', function($app) {
            return new MFW_Event_Subscriber($app['events']);
        });

        // Register event bus
        $this->singleton('events.bus', function($app) {
            return new MFW_Event_Bus(
                $app['events'],
                $app['config']->get('events.bus', [])
            );
        });

        // Register event store
        $this->singleton('events.store', function($app) {
            return new MFW_Event_Store(
                $app['db'],
                $app['config']->get('events.store', [])
            );
        });

        // Register event logger
        $this->singleton('events.logger', function($app) {
            return new MFW_Event_Logger(
                $app['log'],
                $app['config']->get('events.logging', true)
            );
        });
    }

    /**
     * Boot services
     *
     * @return void
     */
    public function boot() {
        // Create events table if it doesn't exist
        if (!$this->events_table_exists()) {
            $this->create_events_table();
        }

        // Register event listeners
        $this->register_listeners();

        // Register event subscribers
        $this->register_subscribers();

        // Register WordPress hooks
        $this->register_hooks();

        // Register system events
        $this->register_system_events();
    }

    /**
     * Check if events table exists
     *
     * @return bool Whether table exists
     */
    protected function events_table_exists() {
        global $wpdb;
        $table = $this->container['config']->get('events.store.table', 'mfw_events');
        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }

    /**
     * Create events table
     *
     * @return void
     */
    protected function create_events_table() {
        $schema = $this->container['db.schema'];
        $table = $this->container['config']->get('events.store.table', 'mfw_events');

        $schema->create($table, function($table) {
            $table->increments('id');
            $table->string('event');
            $table->text('payload');
            $table->integer('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Register event listeners
     *
     * @return void
     */
    protected function register_listeners() {
        $events = $this->container['events'];
        $config = $this->container['config'];

        // Get listeners from configuration
        $listeners = $config->get('events.listeners', []);

        // Register each listener
        foreach ($listeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }

        // Register wildcard listeners
        $wildcards = $config->get('events.wildcards', []);
        foreach ($wildcards as $pattern => $listener) {
            $events->listen($pattern, $listener);
        }
    }

    /**
     * Register event subscribers
     *
     * @return void
     */
    protected function register_subscribers() {
        $subscriber = $this->container['events.subscriber'];
        $config = $this->container['config'];

        // Get subscribers from configuration
        $subscribers = $config->get('events.subscribers', []);

        // Register each subscriber
        foreach ($subscribers as $subscriber_class) {
            $subscriber->subscribe(new $subscriber_class);
        }
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function register_hooks() {
        // Log events in admin
        add_action('admin_init', function() {
            if (!current_user_can('manage_options')) {
                return;
            }

            add_action('admin_menu', function() {
                add_submenu_page(
                    'tools.php',
                    __('Event Log', 'mfw'),
                    __('Event Log', 'mfw'),
                    'manage_options',
                    'mfw-events',
                    [$this, 'render_event_log']
                );
            });
        });

        // Clear old events periodically
        add_action('mfw_daily_maintenance', function() {
            $this->cleanup_old_events();
        });
    }

    /**
     * Register system events
     *
     * @return void
     */
    protected function register_system_events() {
        $events = $this->container['events'];

        // Authentication events
        $events->listen('auth.login', function($user) {
            $this->log_event('User logged in', [
                'user_id' => $user->ID,
                'username' => $user->user_login
            ]);
        });

        $events->listen('auth.logout', function($user) {
            $this->log_event('User logged out', [
                'user_id' => $user->ID,
                'username' => $user->user_login
            ]);
        });

        // Post events
        $events->listen('post.created', function($post) {
            $this->log_event('Post created', [
                'post_id' => $post->ID,
                'post_type' => $post->post_type,
                'title' => $post->post_title
            ]);
        });

        $events->listen('post.updated', function($post) {
            $this->log_event('Post updated', [
                'post_id' => $post->ID,
                'post_type' => $post->post_type,
                'title' => $post->post_title
            ]);
        });

        // User events
        $events->listen('user.created', function($user) {
            $this->log_event('User created', [
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email
            ]);
        });

        $events->listen('user.updated', function($user) {
            $this->log_event('User updated', [
                'user_id' => $user->ID,
                'username' => $user->user_login
            ]);
        });
    }

    /**
     * Log event
     *
     * @param string $message Event message
     * @param array $data Event data
     * @return void
     */
    protected function log_event($message, $data = []) {
        $store = $this->container['events.store'];
        $logger = $this->container['events.logger'];

        // Store event
        $store->log([
            'event' => $message,
            'payload' => $data,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->container['request']->ip()
        ]);

        // Log event
        $logger->info($message, $data);
    }

    /**
     * Cleanup old events
     *
     * @return void
     */
    protected function cleanup_old_events() {
        $store = $this->container['events.store'];
        $days = $this->container['config']->get('events.retention_days', 30);

        $store->cleanup($days);
    }

    /**
     * Render event log page
     *
     * @return void
     */
    public function render_event_log() {
        $store = $this->container['events.store'];
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $events = $store->paginate($per_page, $page);
        $total_pages = ceil($store->count() / $per_page);

        include MFW_PLUGIN_DIR . 'views/admin/event-log.php';
    }
}