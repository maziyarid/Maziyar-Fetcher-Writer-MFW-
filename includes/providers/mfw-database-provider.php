<?php
/**
 * Database Service Provider
 * 
 * Registers database services and migrations.
 * Handles database connections and query building.
 *
 * @package MFW
 * @subpackage Providers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Database_Provider extends MFW_Service_Provider {
    /**
     * Provider initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:47:24';

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
        // Register query builder
        $this->singleton('db.builder', function($app) {
            return new MFW_Query_Builder($app['db']);
        });

        // Register schema builder
        $this->singleton('db.schema', function($app) {
            return new MFW_Schema_Builder($app['db']);
        });

        // Register migration repository
        $this->singleton('db.migrations', function($app) {
            return new MFW_Migration_Repository(
                $app['db'],
                $app['config']->get('database.migrations', 'mfw_migrations')
            );
        });

        // Register migration runner
        $this->singleton('db.migrator', function($app) {
            return new MFW_Migrator(
                $app['db.migrations'],
                $app['filesystem']
            );
        });

        // Register model factory
        $this->singleton('db.factory', function($app) {
            return new MFW_Model_Factory($app);
        });

        // Register macro provider
        $this->singleton('db.macros', function() {
            return new MFW_Database_Macros();
        });
    }

    /**
     * Boot services
     *
     * @return void
     */
    public function boot() {
        // Create migrations table if it doesn't exist
        if (!$this->migrations_table_exists()) {
            $this->create_migrations_table();
        }

        // Register query builder macros
        $this->register_macros();

        // Register model observers
        $this->register_observers();

        // Register WordPress hooks
        $this->register_hooks();
    }

    /**
     * Check if migrations table exists
     *
     * @return bool Whether table exists
     */
    protected function migrations_table_exists() {
        global $wpdb;
        $table = $this->container['config']->get('database.migrations', 'mfw_migrations');
        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }

    /**
     * Create migrations table
     *
     * @return void
     */
    protected function create_migrations_table() {
        $schema = $this->container['db.schema'];
        $table = $this->container['config']->get('database.migrations', 'mfw_migrations');

        $schema->create($table, function($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
            $table->timestamp('executed_at')->useCurrent();
        });
    }

    /**
     * Register query builder macros
     *
     * @return void
     */
    protected function register_macros() {
        $macros = $this->container['db.macros'];

        // Register common macros
        $macros->register('whereLike', function($query, $column, $value) {
            return $query->where($column, 'LIKE', "%{$value}%");
        });

        $macros->register('whereNotLike', function($query, $column, $value) {
            return $query->where($column, 'NOT LIKE', "%{$value}%");
        });

        $macros->register('whereNull', function($query, $column) {
            return $query->whereRaw("{$column} IS NULL");
        });

        $macros->register('whereNotNull', function($query, $column) {
            return $query->whereRaw("{$column} IS NOT NULL");
        });

        $macros->register('whereBetween', function($query, $column, $values) {
            return $query->whereRaw("{$column} BETWEEN ? AND ?", $values);
        });

        // Register WordPress specific macros
        $macros->register('wherePostType', function($query, $type) {
            return $query->where('post_type', $type);
        });

        $macros->register('wherePostStatus', function($query, $status) {
            return $query->where('post_status', $status);
        });

        $macros->register('whereUserRole', function($query, $role) {
            return $query->whereExists(function($query) use ($role) {
                $query->from('usermeta')
                    ->where('meta_key', 'wp_capabilities')
                    ->where('meta_value', 'LIKE', "%\"{$role}\"%");
            });
        });
    }

    /**
     * Register model observers
     *
     * @return void
     */
    protected function register_observers() {
        // Register post observer
        MFW_Post_Model::observe(new MFW_Post_Observer());

        // Register user observer
        MFW_User_Model::observe(new MFW_User_Observer());

        // Register term observer
        MFW_Term_Model::observe(new MFW_Term_Observer());

        // Register comment observer
        MFW_Comment_Model::observe(new MFW_Comment_Observer());
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function register_hooks() {
        // Run migrations on plugin activation
        register_activation_hook(MFW_PLUGIN_FILE, function() {
            $this->container['db.migrator']->run();
        });

        // Check for pending migrations
        add_action('admin_init', function() {
            if ($this->container['db.migrator']->has_pending()) {
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-warning is-dismissible">
                        <p><?php _e('There are pending database migrations. Please run them from the command line or plugin settings.', 'mfw'); ?></p>
                    </div>
                    <?php
                });
            }
        });

        // Add database tools to plugin settings
        add_action('mfw_settings_tools', function() {
            ?>
            <h3><?php _e('Database Tools', 'mfw'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Migrations', 'mfw'); ?></th>
                    <td>
                        <button class="button" data-mfw-action="run-migrations">
                            <?php _e('Run Migrations', 'mfw'); ?>
                        </button>
                        <button class="button" data-mfw-action="rollback-migration">
                            <?php _e('Rollback Last Migration', 'mfw'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Manage database migrations. Use with caution.', 'mfw'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        });

        // Handle AJAX actions
        add_action('wp_ajax_mfw_run_migrations', function() {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Permission denied.', 'mfw')]);
            }

            try {
                $migrator = $this->container['db.migrator'];
                $ran = $migrator->run();

                wp_send_json_success([
                    'message' => sprintf(
                        __('Successfully ran %d migrations.', 'mfw'),
                        count($ran)
                    )
                ]);
            } catch (\Exception $e) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        });

        add_action('wp_ajax_mfw_rollback_migration', function() {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Permission denied.', 'mfw')]);
            }

            try {
                $migrator = $this->container['db.migrator'];
                $rolledBack = $migrator->rollback();

                wp_send_json_success([
                    'message' => sprintf(
                        __('Successfully rolled back %d migrations.', 'mfw'),
                        count($rolledBack)
                    )
                ]);
            } catch (\Exception $e) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        });
    }
}