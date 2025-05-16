<?php
/**
 * Health Checker Class
 *
 * Monitors plugin health and system requirements.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Health_Checker {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:20:21';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * System requirements
     *
     * @var array
     */
    private $requirements = [
        'php' => '7.4.0',
        'wordpress' => '5.8.0',
        'memory_limit' => '128M',
        'max_execution_time' => 30,
        'upload_max_filesize' => '8M',
        'extensions' => [
            'curl',
            'json',
            'mbstring',
            'zip'
        ]
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('site_status_tests', [$this, 'register_health_tests']);
    }

    /**
     * Register health tests
     *
     * @param array $tests Existing tests
     * @return array Modified tests
     */
    public function register_health_tests($tests) {
        $tests['direct']['mfw_system_check'] = [
            'label' => __('MFW System Requirements', 'mfw'),
            'test' => [$this, 'check_system_requirements']
        ];

        $tests['direct']['mfw_api_health'] = [
            'label' => __('MFW API Connectivity', 'mfw'),
            'test' => [$this, 'check_api_health']
        ];

        $tests['async']['mfw_performance'] = [
            'label' => __('MFW Performance', 'mfw'),
            'test' => 'mfw_performance_test'
        ];

        return $tests;
    }

    /**
     * Check system requirements
     *
     * @return array Test results
     */
    public function check_system_requirements() {
        $result = [
            'label' => __('MFW System Requirements Check', 'mfw'),
            'status' => 'good',
            'badge' => [
                'label' => __('Performance', 'mfw'),
                'color' => 'blue'
            ],
            'description' => '',
            'actions' => '',
            'test' => 'mfw_system_check'
        ];

        $issues = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, $this->requirements['php'], '<')) {
            $issues[] = sprintf(
                __('PHP version %s or higher is required. Current version: %s', 'mfw'),
                $this->requirements['php'],
                PHP_VERSION
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, $this->requirements['wordpress'], '<')) {
            $issues[] = sprintf(
                __('WordPress version %s or higher is required. Current version: %s', 'mfw'),
                $this->requirements['wordpress'],
                $wp_version
            );
        }

        // Check memory limit
        $memory_limit = $this->get_memory_limit();
        if ($memory_limit < $this->convert_to_bytes($this->requirements['memory_limit'])) {
            $issues[] = sprintf(
                __('Memory limit of %s or higher is required. Current limit: %s', 'mfw'),
                $this->requirements['memory_limit'],
                ini_get('memory_limit')
            );
        }

        // Check max execution time
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time != 0 && $max_execution_time < $this->requirements['max_execution_time']) {
            $issues[] = sprintf(
                __('Maximum execution time of %d seconds or higher is required. Current limit: %d', 'mfw'),
                $this->requirements['max_execution_time'],
                $max_execution_time
            );
        }

        // Check upload max filesize
        $upload_max_filesize = $this->convert_to_bytes(ini_get('upload_max_filesize'));
        if ($upload_max_filesize < $this->convert_to_bytes($this->requirements['upload_max_filesize'])) {
            $issues[] = sprintf(
                __('Upload max filesize of %s or higher is required. Current limit: %s', 'mfw'),
                $this->requirements['upload_max_filesize'],
                ini_get('upload_max_filesize')
            );
        }

        // Check required extensions
        foreach ($this->requirements['extensions'] as $extension) {
            if (!extension_loaded($extension)) {
                $issues[] = sprintf(
                    __('PHP extension %s is required but not installed.', 'mfw'),
                    $extension
                );
            }
        }

        // Update result based on issues
        if (!empty($issues)) {
            $result['status'] = count($issues) > 2 ? 'critical' : 'recommended';
            $result['badge']['color'] = count($issues) > 2 ? 'red' : 'orange';
            $result['description'] = '<p>' . __('Some system requirements are not met:', 'mfw') . '</p>';
            $result['description'] .= '<ul><li>' . implode('</li><li>', $issues) . '</li></ul>';
            $result['actions'] = sprintf(
                '<p><a href="%s">%s</a></p>',
                admin_url('admin.php?page=mfw-system-status'),
                __('View detailed system status', 'mfw')
            );
        } else {
            $result['description'] = '<p>' . __('All system requirements are met.', 'mfw') . '</p>';
        }

        return $result;
    }

    /**
     * Check API health
     *
     * @return array Test results
     */
    public function check_api_health() {
        $result = [
            'label' => __('MFW API Connectivity Check', 'mfw'),
            'status' => 'good',
            'badge' => [
                'label' => __('Performance', 'mfw'),
                'color' => 'blue'
            ],
            'description' => '',
            'actions' => '',
            'test' => 'mfw_api_health'
        ];

        $issues = [];
        $settings = get_option(MFW_SETTINGS_OPTION, []);

        // Check API keys
        if (empty($settings['api']['gemini_api_key'])) {
            $issues[] = __('Gemini API key is not configured.', 'mfw');
        }
        if (empty($settings['api']['deepseek_api_key'])) {
            $issues[] = __('DeepSeek API key is not configured.', 'mfw');
        }

        // Test API connectivity
        if (!empty($settings['api']['gemini_api_key'])) {
            $gemini_test = $this->test_api_connectivity('gemini');
            if (!$gemini_test['success']) {
                $issues[] = sprintf(
                    __('Gemini API connection failed: %s', 'mfw'),
                    $gemini_test['message']
                );
            }
        }

        if (!empty($settings['api']['deepseek_api_key'])) {
            $deepseek_test = $this->test_api_connectivity('deepseek');
            if (!$deepseek_test['success']) {
                $issues[] = sprintf(
                    __('DeepSeek API connection failed: %s', 'mfw'),
                    $deepseek_test['message']
                );
            }
        }

        // Update result based on issues
        if (!empty($issues)) {
            $result['status'] = count($issues) > 1 ? 'critical' : 'recommended';
            $result['badge']['color'] = count($issues) > 1 ? 'red' : 'orange';
            $result['description'] = '<p>' . __('API connectivity issues detected:', 'mfw') . '</p>';
            $result['description'] .= '<ul><li>' . implode('</li><li>', $issues) . '</li></ul>';
            $result['actions'] = sprintf(
                '<p><a href="%s">%s</a></p>',
                admin_url('admin.php?page=mfw-settings#api'),
                __('Configure API settings', 'mfw')
            );
        } else {
            $result['description'] = '<p>' . __('API connectivity is working properly.', 'mfw') . '</p>';
        }

        return $result;
    }

    /**
     * Test API connectivity
     *
     * @param string $service Service name
     * @return array Test result
     */
    private function test_api_connectivity($service) {
        try {
            switch ($service) {
                case 'gemini':
                    $api = new MFW_Gemini_Service();
                    break;
                case 'deepseek':
                    $api = new MFW_Deepseek_Service();
                    break;
                default:
                    throw new Exception(__('Invalid service.', 'mfw'));
            }

            $test_result = $api->generate_text('Test connection.');
            return [
                'success' => !empty($test_result),
                'message' => ''
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get memory limit in bytes
     *
     * @return int Memory limit
     */
    private function get_memory_limit() {
        return $this->convert_to_bytes(ini_get('memory_limit'));
    }

    /**
     * Convert size string to bytes
     *
     * @param string $size Size string
     * @return int Size in bytes
     */
    private function convert_to_bytes($size) {
        $unit = strtoupper(substr($size, -1));
        $value = (int)substr($size, 0, -1);

        switch ($unit) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Get health status
     *
     * @return array Health status
     */
    public function get_health_status() {
        return [
            'system' => $this->check_system_requirements(),
            'api' => $this->check_api_health(),
            'last_check' => $this->current_time
        ];
    }
}