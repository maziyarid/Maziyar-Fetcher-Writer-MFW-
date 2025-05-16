<?php
/**
 * Database Handler Class
 * 
 * Manages database operations and schema management.
 * Provides advanced query building and optimization.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Database_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:38:39';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Database prefix
     */
    private $prefix;

    /**
     * Query builder instance
     */
    private $builder;

    /**
     * Table schemas
     */
    private $schemas = [];

    /**
     * Initialize database handler
     */
    public function __construct() {
        global $wpdb;
        $this->prefix = $wpdb->prefix . 'mfw_';

        // Register tables
        $this->register_tables();

        // Add maintenance hooks
        add_action('mfw_daily_maintenance', [$this, 'optimize_tables']);
    }

    /**
     * Create database tables
     *
     * @return bool Success status
     */
    public function create_tables() {
        try {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            // Get SQL queries for table creation
            $queries = $this->get_table_queries($charset_collate);

            // Execute queries
            foreach ($queries as $query) {
                if ($wpdb->query($query) === false) {
                    throw new Exception($wpdb->last_error);
                }
            }

            // Log table creation
            $this->log_database_operation('create_tables');

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Table creation failed: %s', $e->getMessage()),
                'database_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Insert data into table
     *
     * @param string $table Table name
     * @param array $data Data to insert
     * @param array $format Column formats
     * @return int|false The number of rows inserted, or false on error
     */
    public function insert($table, $data, $format = []) {
        try {
            global $wpdb;
            
            // Add audit fields
            $data['created_by'] = $this->current_user;
            $data['created_at'] = $this->current_time;
            $format[] = '%s';
            $format[] = '%s';

            // Perform insert
            $result = $wpdb->insert(
                $this->prefix . $table,
                $data,
                $format
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            return $wpdb->insert_id;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Insert failed: %s', $e->getMessage()),
                'database_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Update data in table
     *
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where Where conditions
     * @param array $format Column formats
     * @param array $where_format Where formats
     * @return int|false The number of rows updated, or false on error
     */
    public function update($table, $data, $where, $format = [], $where_format = []) {
        try {
            global $wpdb;
            
            // Add audit fields
            $data['updated_by'] = $this->current_user;
            $data['updated_at'] = $this->current_time;
            $format[] = '%s';
            $format[] = '%s';

            // Perform update
            $result = $wpdb->update(
                $this->prefix . $table,
                $data,
                $where,
                $format,
                $where_format
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            return $result;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Update failed: %s', $e->getMessage()),
                'database_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Delete data from table
     *
     * @param string $table Table name
     * @param array $where Where conditions
     * @param array $where_format Where formats
     * @return int|false The number of rows deleted, or false on error
     */
    public function delete($table, $where, $where_format = []) {
        try {
            global $wpdb;

            // Log deletion
            $this->log_database_operation('delete', [
                'table' => $table,
                'where' => $where
            ]);

            // Perform delete
            $result = $wpdb->delete(
                $this->prefix . $table,
                $where,
                $where_format
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            return $result;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Delete failed: %s', $e->getMessage()),
                'database_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get single row
     *
     * @param string $table Table name
     * @param array $where Where conditions
     * @param string $output_format Output format (ARRAY_A|ARRAY_N|OBJECT)
     * @return mixed Query result
     */
    public function get_row($table, $where = [], $output_format = OBJECT) {
        try {
            global $wpdb;

            // Build query
            $query = "SELECT * FROM {$this->prefix}{$table}";
            
            if (!empty($where)) {
                $conditions = [];
                foreach ($where as $field => $value) {
                    $conditions[] = $wpdb->prepare("`$field` = %s", $value);
                }
                $query .= " WHERE " . implode(' AND ', $conditions);
            }

            // Execute query
            return $wpdb->get_row($query, $output_format);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Get row failed: %s', $e->getMessage()),
                'database_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get multiple rows
     *
     * @param string $table Table name
     * @param array $args Query arguments
     * @param string $output_format Output format (ARRAY_A|ARRAY_N|OBJECT)
     * @return array Query results
     */
    public function get_rows($table, $args = [], $output_format = OBJECT) {
        try {
            global $wpdb;

            // Parse arguments
            $defaults = [
                'where' => [],
                'orderby' => 'id',
                'order' => 'DESC',
                'limit' => 0,
                'offset' => 0
            ];
            $args = wp_parse_args($args, $defaults);

            // Build query
            $query = "SELECT * FROM {$this->prefix}{$table}";

            // Add where clause
            if (!empty($args['where'])) {
                $conditions = [];
                foreach ($args['where'] as $field => $value) {
                    $conditions[] = $wpdb->prepare("`$field` = %s", $value);
                }
                $query .= " WHERE " . implode(' AND ', $conditions);
            }

            // Add order clause
            $query .= " ORDER BY {$args['orderby']} {$args['order']}";

            // Add limit clause
            if ($args['limit'] > 0) {
                $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", 
                    $args['limit'], 
                    $args['offset']
                );
            }

            // Execute query
            return $wpdb->get_results($query, $output_format);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Get rows failed: %s', $e->getMessage()),
                'database_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Optimize database tables
     */
    public function optimize_tables() {
        try {
            global $wpdb;

            // Get plugin tables
            $tables = $wpdb->get_col(
                $wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $wpdb->esc_like($this->prefix) . '%'
                )
            );

            foreach ($tables as $table) {
                $wpdb->query("OPTIMIZE TABLE $table");
            }

            // Log optimization
            $this->log_database_operation('optimize');

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Table optimization failed: %s', $e->getMessage()),
                'database_handler',
                'error'
            );
        }
    }

    /**
     * Register database tables
     */
    private function register_tables() {
        $this->schemas = [
            'logs' => [
                'id' => ['bigint(20)', 'NOT NULL', 'AUTO_INCREMENT'],
                'level' => ['varchar(20)', 'NOT NULL'],
                'message' => ['text', 'NOT NULL'],
                'context' => ['varchar(255)', 'NOT NULL'],
                'created_by' => ['varchar(60)', 'NOT NULL'],
                'created_at' => ['datetime', 'NOT NULL'],
                'PRIMARY KEY' => '(id)',
                'KEY' => ['level', 'context', 'created_at']
            ],
            'settings' => [
                'id' => ['bigint(20)', 'NOT NULL', 'AUTO_INCREMENT'],
                'setting_key' => ['varchar(255)', 'NOT NULL'],
                'setting_value' => ['longtext', 'NOT NULL'],
                'created_by' => ['varchar(60)', 'NOT NULL'],
                'created_at' => ['datetime', 'NOT NULL'],
                'updated_by' => ['varchar(60)', 'NULL'],
                'updated_at' => ['datetime', 'NULL'],
                'PRIMARY KEY' => '(id)',
                'UNIQUE KEY' => ['setting_key']
            ]
            // Add more table schemas as needed
        ];
    }

    /**
     * Get table creation queries
     *
     * @param string $charset_collate Character set and collation
     * @return array SQL queries
     */
    private function get_table_queries($charset_collate) {
        $queries = [];

        foreach ($this->schemas as $table => $columns) {
            $query = "CREATE TABLE IF NOT EXISTS {$this->prefix}{$table} (\n";
            
            foreach ($columns as $column => $definition) {
                if (is_array($definition)) {
                    $query .= "`$column` " . implode(' ', $definition) . ",\n";
                } else {
                    $query .= "$definition,\n";
                }
            }

            $query = rtrim($query, ",\n");
            $query .= "\n) $charset_collate;";
            
            $queries[] = $query;
        }

        return $queries;
    }

    /**
     * Log database operation
     *
     * @param string $operation Operation type
     * @param array $details Operation details
     */
    private function log_database_operation($operation, $details = []) {
        try {
            global $wpdb;

            $wpdb->insert(
                $this->prefix . 'database_log',
                [
                    'operation' => $operation,
                    'details' => json_encode($details),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log database operation: %s', $e->getMessage()),
                'database_handler',
                'error'
            );
        }
    }
}