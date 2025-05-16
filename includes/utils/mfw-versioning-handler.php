<?php
/**
 * Versioning Handler Class
 * 
 * Manages version control, database migrations, and updates.
 * Handles plugin upgrades and data schema changes.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Versioning_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:25:08';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Current plugin version
     */
    private $current_version;

    /**
     * Database version
     */
    private $db_version;

    /**
     * Migration directory
     */
    private $migration_dir;

    /**
     * Initialize versioning handler
     */
    public function __construct() {
        $this->current_version = MFW_VERSION;
        $this->db_version = get_option('mfw_db_version', '0.0.0');
        $this->migration_dir = MFW_PLUGIN_DIR . 'includes/migrations/';

        // Add upgrade hooks
        add_action('admin_init', [$this, 'check_version']);
        add_action('plugins_loaded', [$this, 'run_migrations']);
    }

    /**
     * Check plugin version and trigger updates if necessary
     */
    public function check_version() {
        if (version_compare($this->current_version, get_option('mfw_version', '0.0.0'), '>')) {
            $this->update_plugin();
        }
    }

    /**
     * Run database migrations
     *
     * @return bool Success status
     */
    public function run_migrations() {
        try {
            global $wpdb;

            // Get pending migrations
            $migrations = $this->get_pending_migrations();

            if (empty($migrations)) {
                return true;
            }

            // Start transaction
            $wpdb->query('START TRANSACTION');

            foreach ($migrations as $migration) {
                $this->log_migration('start', $migration);

                // Include migration file
                require_once $this->migration_dir . $migration['file'];

                // Get migration class name
                $class_name = 'MFW_Migration_' . $migration['version'];
                if (!class_exists($class_name)) {
                    throw new Exception(sprintf(
                        __('Migration class not found: %s', 'mfw'),
                        $class_name
                    ));
                }

                // Run migration
                $instance = new $class_name();
                if (!$instance->up()) {
                    throw new Exception(sprintf(
                        __('Migration failed: %s', 'mfw'),
                        $migration['version']
                    ));
                }

                // Update database version
                update_option('mfw_db_version', $migration['version']);
                $this->log_migration('complete', $migration);
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return true;

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');

            MFW_Error_Logger::log(
                sprintf('Migration failed: %s', $e->getMessage()),
                'versioning_handler',
                'error'
            );

            return false;
        }
    }

    /**
     * Create backup before update
     *
     * @return bool Success status
     */
    public function create_backup() {
        try {
            global $wpdb;

            $backup_dir = WP_CONTENT_DIR . '/mfw-backups';
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }

            // Get all plugin tables
            $tables = $wpdb->get_col(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $wpdb->esc_like($wpdb->prefix . 'mfw_') . '%'
                )
            );

            if (empty($tables)) {
                return true;
            }

            // Create backup file
            $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
            $handle = fopen($backup_file, 'w');

            foreach ($tables as $table) {
                // Get create table statement
                $create_table = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
                fwrite($handle, "\n\n" . $create_table[1] . ";\n\n");

                // Get table data
                $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
                foreach ($rows as $row) {
                    $values = array_map([$wpdb, '_real_escape'], $row);
                    fwrite($handle, 
                        "INSERT INTO {$table} VALUES ('" . 
                        implode("','", $values) . 
                        "');\n"
                    );
                }
            }

            fclose($handle);

            // Log backup creation
            $this->log_backup($backup_file);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Backup creation failed: %s', $e->getMessage()),
                'versioning_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Update plugin
     */
    private function update_plugin() {
        try {
            // Create backup
            if (!$this->create_backup()) {
                throw new Exception(__('Backup creation failed', 'mfw'));
            }

            // Run migrations
            if (!$this->run_migrations()) {
                throw new Exception(__('Database migration failed', 'mfw'));
            }

            // Update version
            update_option('mfw_version', $this->current_version);

            // Clear caches
            $cache_handler = new MFW_Cache_Handler();
            $cache_handler->flush_all();

            // Log update
            $this->log_update();

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Plugin update failed: %s', $e->getMessage()),
                'versioning_handler',
                'error'
            );
        }
    }

    /**
     * Get pending migrations
     *
     * @return array Pending migrations
     */
    private function get_pending_migrations() {
        $migrations = [];
        $files = glob($this->migration_dir . '*.php');

        foreach ($files as $file) {
            // Extract version from filename (format: YYYYMMDDHHMMSS_description.php)
            $version = basename($file, '.php');
            $version = substr($version, 0, 14);

            if (version_compare($version, $this->db_version, '>')) {
                $migrations[] = [
                    'version' => $version,
                    'file' => basename($file)
                ];
            }
        }

        // Sort by version
        usort($migrations, function($a, $b) {
            return version_compare($a['version'], $b['version']);
        });

        return $migrations;
    }

    /**
     * Log migration
     *
     * @param string $status Migration status
     * @param array $migration Migration data
     */
    private function log_migration($status, $migration) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_migrations_log',
                [
                    'version' => $migration['version'],
                    'file' => $migration['file'],
                    'status' => $status,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log migration: %s', $e->getMessage()),
                'versioning_handler',
                'error'
            );
        }
    }

    /**
     * Log backup
     *
     * @param string $file Backup file path
     */
    private function log_backup($file) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_backups_log',
                [
                    'file_path' => $file,
                    'file_size' => filesize($file),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%d', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log backup: %s', $e->getMessage()),
                'versioning_handler',
                'error'
            );
        }
    }

    /**
     * Log update
     */
    private function log_update() {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_updates_log',
                [
                    'version_from' => get_option('mfw_version', '0.0.0'),
                    'version_to' => $this->current_version,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log update: %s', $e->getMessage()),
                'versioning_handler',
                'error'
            );
        }
    }
}