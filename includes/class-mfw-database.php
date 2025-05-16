<?php
/**
 * Database Class
 * 
 * Manages database operations and migrations for the framework.
 * Handles table creation, updates, and query operations.
 *
 * @package MFW
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Database {
    use MFW_Observable;
    use MFW_Configurable;
    use MFW_Loggable;
    use MFW_Cacheable;

    /**
     * Database version
     *
     * @var string
     */
    private $version;

    /**
     * Database tables
     *
     * @var array
     */
    private $tables = [];

    /**
     * Migration history
     *
     * @var array
     */
    private $migrations = [];

    /**
     * Initialize database
     */
    public function __construct() {
        $this->init_timestamp = '2025-05-14 07:14:40';
        $this->init_user = 'maziyarid';

        $this->version = get_option('mfw_db_version', '0.0.0');
        $this->init_tables();

        $this->info('Database manager initialized', [
            'version' => $this->version
        ]);
    }

    /**
     * Initialize database tables
     */
    private function init_tables() {
        global $wpdb;

        $this->tables = [
            'config' => [
                'name' => $wpdb->prefix . 'mfw_config',
                'schema' => [
                    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'config_key' => 'varchar(191) NOT NULL',
                    'config_value' => 'longtext NOT NULL',
                    'created_at' => 'datetime NOT NULL',
                    'created_by' => 'varchar(60) NOT NULL',
                    'updated_at' => 'datetime DEFAULT NULL',
                    'updated_by' => 'varchar(60) DEFAULT NULL',
                    'PRIMARY KEY' => '(id)',
                    'UNIQUE KEY' => 'config_key (config_key)'
                ]
            ],
            'cache' => [
                'name' => $wpdb->prefix . 'mfw_cache',
                'schema' => [
                    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'cache_key' => 'varchar(191) NOT NULL',
                    'cache_value' => 'longtext NOT NULL',
                    'expiration' => 'datetime NOT NULL',
                    'created_at' => 'datetime NOT NULL',
                    'created_by' => 'varchar(60) NOT NULL',
                    'PRIMARY KEY' => '(id)',
                    'UNIQUE KEY' => 'cache_key (cache_key)',
                    'KEY' => 'expiration (expiration)'
                ]
            ],
            'log' => [
                'name' => $wpdb->prefix . 'mfw_log',
                'schema' => [
                    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'level' => 'varchar(20) NOT NULL',
                    'message' => 'text NOT NULL',
                    'context' => 'longtext DEFAULT NULL',
                    'created_at' => 'datetime NOT NULL',
                    'created_by' => 'varchar(60) NOT NULL',
                    'PRIMARY KEY' => '(id)',
                    'KEY' => 'level (level)',
                    'KEY' => 'created_at (created_at)'
                ]
            ],
            'migrations' => [
                'name' => $wpdb->prefix . 'mfw_migrations',
                'schema' => [
                    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'migration' => 'varchar(191) NOT NULL',
                    'batch' => 'int(11) NOT NULL',
                    'created_at' => 'datetime NOT NULL',
                    'created_by' => 'varchar(60) NOT NULL',
                    'PRIMARY KEY' => '(id)'
                ]
            ]
        ];
    }

    /**
     * Run database migrations
     *
     * @return bool Whether migrations were successful
     */
    public function migrate() {
        try {
            $this->create_tables();
            $this->run_migrations();
            $this->update_version();

            $this->notify('database.migrated', [
                'version' => $this->version
            ]);

            return true;

        } catch (Exception $e) {
            $this->error('Migration failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create database tables
     *
     * @return void
     * @throws Exception If table creation fails
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        foreach ($this->tables as $table => $config) {
            $table_name = $config['name'];
            $schema = $config['schema'];

            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $sql = "CREATE TABLE $table_name (\n";
                
                foreach ($schema as $column => $definition) {
                    $sql .= "$column $definition,\n";
                }

                $sql = rtrim($sql, ",\n");
                $sql .= "\n) $charset_collate;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                    throw new Exception("Failed to create table: $table_name");
                }

                $this->debug('Table created', [
                    'table' => $table_name
                ]);
            }
        }
    }

    /**
     * Run database migrations
     *
     * @return void
     */
    private function run_migrations() {
        $migrations_dir = MFW_PATH . 'database/migrations';
        if (!is_dir($migrations_dir)) {
            return;
        }

        $files = glob($migrations_dir . '/*.php');
        sort($files);

        $batch = $this->get_next_batch();

        foreach ($files as $file) {
            $migration = basename($file, '.php');
            
            if ($this->has_migration($migration)) {
                continue;
            }

            require_once $file;
            $class = 'MFW_Migration_' . str_replace(['-', '_'], '', $migration);

            if (class_exists($class)) {
                $instance = new $class();
                
                if (method_exists($instance, 'up')) {
                    $instance->up();
                    $this->record_migration($migration, $batch);

                    $this->debug('Migration completed', [
                        'migration' => $migration,
                        'batch' => $batch
                    ]);
                }
            }
        }
    }

    /**
     * Get next migration batch number
     *
     * @return int Batch number
     */
    private function get_next_batch() {
        global $wpdb;

        $batch = $wpdb->get_var("SELECT MAX(batch) FROM {$this->tables['migrations']['name']}");
        return (int)$batch + 1;
    }

    /**
     * Check if migration has been run
     *
     * @param string $migration Migration name
     * @return bool Whether migration exists
     */
    private function has_migration($migration) {
        global $wpdb;

        return (bool)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['migrations']['name']} WHERE migration = %s",
            $migration
        ));
    }

    /**
     * Record migration
     *
     * @param string $migration Migration name
     * @param int $batch Batch number
     */
    private function record_migration($migration, $batch) {
        global $wpdb;

        $wpdb->insert(
            $this->tables['migrations']['name'],
            [
                'migration' => $migration,
                'batch' => $batch,
                'created_at' => current_time('mysql'),
                'created_by' => wp_get_current_user()->user_login
            ],
            ['%s', '%d', '%s', '%s']
        );
    }

    /**
     * Update database version
     */
    private function update_version() {
        $version = $this->get_latest_version();
        update_option('mfw_db_version', $version);
        $this->version = $version;
    }

    /**
     * Get latest database version
     *
     * @return string Latest version
     */
    private function get_latest_version() {
        $migrations_dir = MFW_PATH . 'database/migrations';
        if (!is_dir($migrations_dir)) {
            return '1.0.0';
        }

        $files = glob($migrations_dir . '/*.php');
        if (empty($files)) {
            return '1.0.0';
        }

        sort($files);
        $latest = basename(end($files), '.php');
        preg_match('/^(\d+\.\d+\.\d+)/', $latest, $matches);

        return $matches[1] ?? '1.0.0';
    }

    /**
     * Get database tables
     *
     * @return array Database tables
     */
    public function get_tables() {
        return $this->tables;
    }

    /**
     * Get database version
     *
     * @return string Database version
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get migration history
     *
     * @return array Migration history
     */
    public function get_migrations() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->tables['migrations']['name']} ORDER BY id DESC",
            ARRAY_A
        );
    }

    /**
     * Clean up database
     *
     * @return bool Whether cleanup was successful
     */
    public function cleanup() {
        global $wpdb;

        try {
            foreach ($this->tables as $config) {
                $wpdb->query("DROP TABLE IF EXISTS {$config['name']}");
            }

            delete_option('mfw_db_version');

            $this->notify('database.cleaned');
            return true;

        } catch (Exception $e) {
            $this->error('Cleanup failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}