<?php
/**
 * Update Handler Class
 *
 * Manages plugin updates and database migrations.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Update_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:35:07';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Database version option name
     */
    const DB_VERSION_OPTION = 'mfw_db_version';

    /**
     * Plugin version option name
     */
    const PLUGIN_VERSION_OPTION = 'mfw_version';

    /**
     * Version history option name
     */
    const VERSION_HISTORY_OPTION = 'mfw_version_history';

    /**
     * Check if update is needed
     *
     * @return bool True if update needed
     */
    public function needs_update() {
        $current_db_version = get_option(self::DB_VERSION_OPTION, '1.0.0');
        $current_plugin_version = get_option(self::PLUGIN_VERSION_OPTION, '1.0.0');

        return version_compare($current_db_version, MFW_DB_VERSION, '<') ||
               version_compare($current_plugin_version, MFW_VERSION, '<');
    }

    /**
     * Run update process
     *
     * @return array Update results
     */
    public function run_update() {
        try {
            global $wpdb;
            $results = [
                'success' => true,
                'updates_run' => [],
                'errors' => []
            ];

            // Start transaction
            $wpdb->query('START TRANSACTION');

            try {
                // Get current versions
                $current_db_version = get_option(self::DB_VERSION_OPTION, '1.0.0');
                $current_plugin_version = get_option(self::PLUGIN_VERSION_OPTION, '1.0.0');

                // Run database updates if needed
                if (version_compare($current_db_version, MFW_DB_VERSION, '<')) {
                    $db_results = $this->run_database_updates($current_db_version);
                    $results['updates_run'][] = 'database';
                    
                    if (!empty($db_results['errors'])) {
                        $results['errors'] = array_merge($results['errors'], $db_results['errors']);
                    }
                }

                // Run plugin updates if needed
                if (version_compare($current_plugin_version, MFW_VERSION, '<')) {
                    $plugin_results = $this->run_plugin_updates($current_plugin_version);
                    $results['updates_run'][] = 'plugin';
                    
                    if (!empty($plugin_results['errors'])) {
                        $results['errors'] = array_merge($results['errors'], $plugin_results['errors']);
                    }
                }

                // Update version numbers if no errors occurred
                if (empty($results['errors'])) {
                    update_option(self::DB_VERSION_OPTION, MFW_DB_VERSION);
                    update_option(self::PLUGIN_VERSION_OPTION, MFW_VERSION);
                    
                    // Record update in version history
                    $this->record_update_history($current_plugin_version, MFW_VERSION);
                }

                // Commit transaction if successful
                $wpdb->query('COMMIT');

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }

            // Set success status based on errors
            $results['success'] = empty($results['errors']);

            // Log update results
            $this->log_update_results($results);

            return $results;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Update failed: %s', $e->getMessage()),
                'update_handler',
                'error'
            );
            
            return [
                'success' => false,
                'updates_run' => [],
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Run database updates
     *
     * @param string $current_version Current database version
     * @return array Update results
     */
    private function run_database_updates($current_version) {
        $results = ['errors' => []];

        // Get all available updates
        $updates = $this->get_database_updates();

        // Sort updates by version
        uksort($updates, 'version_compare');

        // Run each update that's newer than current version
        foreach ($updates as $version => $update) {
            if (version_compare($current_version, $version, '<')) {
                try {
                    $update();
                } catch (Exception $e) {
                    $results['errors'][] = sprintf(
                        'Database update %s failed: %s',
                        $version,
                        $e->getMessage()
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Run plugin updates
     *
     * @param string $current_version Current plugin version
     * @return array Update results
     */
    private function run_plugin_updates($current_version) {
        $results = ['errors' => []];

        // Get all available updates
        $updates = $this->get_plugin_updates();

        // Sort updates by version
        uksort($updates, 'version_compare');

        // Run each update that's newer than current version
        foreach ($updates as $version => $update) {
            if (version_compare($current_version, $version, '<')) {
                try {
                    $update();
                } catch (Exception $e) {
                    $results['errors'][] = sprintf(
                        'Plugin update %s failed: %s',
                        $version,
                        $e->getMessage()
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Get database updates
     *
     * @return array Database updates
     */
    private function get_database_updates() {
        return [
            '1.1.0' => function() {
                global $wpdb;

                // Add new columns to existing tables
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}mfw_generated_content
                    ADD COLUMN metadata TEXT AFTER content"
                );

                // Create new tables if needed
                $wpdb->query(
                    "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mfw_content_revisions (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        content_id BIGINT(20) UNSIGNED NOT NULL,
                        revision_data TEXT NOT NULL,
                        created_by VARCHAR(60) NOT NULL,
                        created_at DATETIME NOT NULL,
                        PRIMARY KEY (id),
                        KEY content_id (content_id)
                    ) {$wpdb->get_charset_collate()}"
                );
            },
            '1.2.0' => function() {
                global $wpdb;

                // Add indexes for performance
                $wpdb->query(
                    "ALTER TABLE {$wpdb->prefix}mfw_rate_limit_log
                    ADD INDEX idx_user_type (user, type),
                    ADD INDEX idx_created_at (created_at)"
                );
            }
        ];
    }

    /**
     * Get plugin updates
     *
     * @return array Plugin updates
     */
    private function get_plugin_updates() {
        return [
            '1.1.0' => function() {
                // Update settings structure
                $settings_handler = new MFW_Settings_Handler();
                $current_settings = $settings_handler->get_all_settings();

                // Add new settings
                $current_settings['content']['enable_ai_suggestions'] = true;
                $current_settings['content']['max_suggestions'] = 5;

                $settings_handler->update_settings($current_settings);
            },
            '1.2.0' => function() {
                // Clear caches
                $cache_handler = new MFW_Cache_Handler();
                $cache_handler->flush_group(MFW_Cache_Handler::GROUP_CONTENT);
                $cache_handler->flush_group(MFW_Cache_Handler::GROUP_SETTINGS);
            }
        ];
    }

    /**
     * Record update in version history
     *
     * @param string $from_version Previous version
     * @param string $to_version New version
     */
    private function record_update_history($from_version, $to_version) {
        $history = get_option(self::VERSION_HISTORY_OPTION, []);
        
        $history[] = [
            'from_version' => $from_version,
            'to_version' => $to_version,
            'updated_by' => $this->current_user,
            'updated_at' => $this->current_time
        ];

        update_option(self::VERSION_HISTORY_OPTION, $history);
    }

    /**
     * Log update results
     *
     * @param array $results Update results
     */
    private function log_update_results($results) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mfw_update_log',
            [
                'from_version' => get_option(self::PLUGIN_VERSION_OPTION, '1.0.0'),
                'to_version' => MFW_VERSION,
                'updates_run' => json_encode($results['updates_run']),
                'errors' => json_encode($results['errors']),
                'updated_by' => $this->current_user,
                'updated_at' => $this->current_time
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
}