<?php
/**
 * Compatibility Checker Class
 *
 * Checks and ensures compatibility with WordPress version, PHP version,
 * required plugins, and server environment.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Compatibility_Checker {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:40:36';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Minimum requirements
     */
    private $requirements = [
        'php' => '8.1.0',
        'wordpress' => '6.4.0',
        'mysql' => '5.7.0',
        'memory_limit' => '128M',
        'max_execution_time' => 30,
        'upload_max_filesize' => '8M',
        'extensions' => [
            'curl',
            'json',
            'mbstring',
            'openssl',
            'zip'
        ]
    ];

    /**
     * Check compatibility
     *
     * @return array Compatibility check results
     */
    public function check_compatibility() {
        try {
            $results = [
                'compatible' => true,
                'checks' => [],
                'warnings' => [],
                'errors' => []
            ];

            // Check PHP version
            $results['checks']['php'] = $this->check_php_version();
            if (!$results['checks']['php']['compatible']) {
                $results['compatible'] = false;
                $results['errors'][] = $results['checks']['php']['message'];
            }

            // Check WordPress version
            $results['checks']['wordpress'] = $this->check_wordpress_version();
            if (!$results['checks']['wordpress']['compatible']) {
                $results['compatible'] = false;
                $results['errors'][] = $results['checks']['wordpress']['message'];
            }

            // Check MySQL version
            $results['checks']['mysql'] = $this->check_mysql_version();
            if (!$results['checks']['mysql']['compatible']) {
                $results['compatible'] = false;
                $results['errors'][] = $results['checks']['mysql']['message'];
            }

            // Check PHP extensions
            $results['checks']['extensions'] = $this->check_php_extensions();
            if (!$results['checks']['extensions']['compatible']) {
                $results['compatible'] = false;
                $results['errors'][] = $results['checks']['extensions']['message'];
            }

            // Check PHP configuration
            $results['checks']['php_config'] = $this->check_php_configuration();
            if (!$results['checks']['php_config']['compatible']) {
                $results['warnings'][] = $results['checks']['php_config']['message'];
            }

            // Check write permissions
            $results['checks']['permissions'] = $this->check_write_permissions();
            if (!$results['checks']['permissions']['compatible']) {
                $results['warnings'][] = $results['checks']['permissions']['message'];
            }

            // Check SSL
            $results['checks']['ssl'] = $this->check_ssl();
            if (!$results['checks']['ssl']['compatible']) {
                $results['warnings'][] = $results['checks']['ssl']['message'];
            }

            // Log compatibility check results
            $this->log_compatibility_check($results);

            return $results;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Compatibility check failed: %s', $e->getMessage()),
                'compatibility_checker',
                'error'
            );

            return [
                'compatible' => false,
                'checks' => [],
                'warnings' => [],
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Check PHP version
     *
     * @return array Check results
     */
    private function check_php_version() {
        $current_version = phpversion();
        $required_version = $this->requirements['php'];

        return [
            'compatible' => version_compare($current_version, $required_version, '>='),
            'current' => $current_version,
            'required' => $required_version,
            'message' => sprintf(
                __('PHP version %s or higher is required. Current version is %s.', 'mfw'),
                $required_version,
                $current_version
            )
        ];
    }

    /**
     * Check WordPress version
     *
     * @return array Check results
     */
    private function check_wordpress_version() {
        global $wp_version;
        $required_version = $this->requirements['wordpress'];

        return [
            'compatible' => version_compare($wp_version, $required_version, '>='),
            'current' => $wp_version,
            'required' => $required_version,
            'message' => sprintf(
                __('WordPress version %s or higher is required. Current version is %s.', 'mfw'),
                $required_version,
                $wp_version
            )
        ];
    }

    /**
     * Check MySQL version
     *
     * @return array Check results
     */
    private function check_mysql_version() {
        global $wpdb;
        $current_version = $wpdb->db_version();
        $required_version = $this->requirements['mysql'];

        return [
            'compatible' => version_compare($current_version, $required_version, '>='),
            'current' => $current_version,
            'required' => $required_version,
            'message' => sprintf(
                __('MySQL version %s or higher is required. Current version is %s.', 'mfw'),
                $required_version,
                $current_version
            )
        ];
    }

    /**
     * Check PHP extensions
     *
     * @return array Check results
     */
    private function check_php_extensions() {
        $missing_extensions = [];
        foreach ($this->requirements['extensions'] as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }

        return [
            'compatible' => empty($missing_extensions),
            'missing' => $missing_extensions,
            'message' => sprintf(
                __('Required PHP extensions missing: %s', 'mfw'),
                implode(', ', $missing_extensions)
            )
        ];
    }

    /**
     * Check PHP configuration
     *
     * @return array Check results
     */
    private function check_php_configuration() {
        $issues = [];

        // Check memory limit
        $memory_limit = $this->convert_to_bytes(ini_get('memory_limit'));
        $required_memory = $this->convert_to_bytes($this->requirements['memory_limit']);
        if ($memory_limit < $required_memory) {
            $issues[] = sprintf(
                __('Memory limit is %s. Recommended minimum is %s.', 'mfw'),
                ini_get('memory_limit'),
                $this->requirements['memory_limit']
            );
        }

        // Check max execution time
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time != 0 && $max_execution_time < $this->requirements['max_execution_time']) {
            $issues[] = sprintf(
                __('Maximum execution time is %s seconds. Recommended minimum is %s seconds.', 'mfw'),
                $max_execution_time,
                $this->requirements['max_execution_time']
            );
        }

        // Check upload max filesize
        $upload_max_filesize = $this->convert_to_bytes(ini_get('upload_max_filesize'));
        $required_upload = $this->convert_to_bytes($this->requirements['upload_max_filesize']);
        if ($upload_max_filesize < $required_upload) {
            $issues[] = sprintf(
                __('Upload max filesize is %s. Recommended minimum is %s.', 'mfw'),
                ini_get('upload_max_filesize'),
                $this->requirements['upload_max_filesize']
            );
        }

        return [
            'compatible' => empty($issues),
            'issues' => $issues,
            'message' => implode(' ', $issues)
        ];
    }

    /**
     * Check write permissions
     *
     * @return array Check results
     */
    private function check_write_permissions() {
        $issues = [];
        $paths_to_check = [
            WP_CONTENT_DIR . '/uploads',
            WP_CONTENT_DIR . '/cache',
            plugin_dir_path(MFW_PLUGIN_FILE) . 'logs'
        ];

        foreach ($paths_to_check as $path) {
            if (!is_writable($path)) {
                $issues[] = sprintf(
                    __('Directory %s is not writable.', 'mfw'),
                    $path
                );
            }
        }

        return [
            'compatible' => empty($issues),
            'issues' => $issues,
            'message' => implode(' ', $issues)
        ];
    }

    /**
     * Check SSL
     *
     * @return array Check results
     */
    private function check_ssl() {
        $is_ssl = is_ssl();

        return [
            'compatible' => $is_ssl,
            'message' => !$is_ssl ? 
                __('SSL is not enabled. Some features may not work properly.', 'mfw') : 
                ''
        ];
    }

    /**
     * Convert PHP configuration values to bytes
     *
     * @param string $value Value to convert
     * @return int Value in bytes
     */
    private function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int)$value;
        
        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Log compatibility check results
     *
     * @param array $results Check results
     */
    private function log_compatibility_check($results) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mfw_compatibility_log',
            [
                'check_results' => json_encode($results),
                'checked_by' => $this->current_user,
                'checked_at' => $this->current_time
            ],
            ['%s', '%s', '%s']
        );
    }
}