<?php
/**
 * MFW Dependencies Configuration
 *
 * @package MFW
 * @subpackage Config
 * @since 1.0.0
 */

namespace MFW\Config;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return [
    'required_plugins' => [
        'advanced-custom-fields' => [
            'name' => 'Advanced Custom Fields',
            'file' => 'advanced-custom-fields/acf.php',
            'version' => '5.0.0'
        ]
    ],
    'recommended_plugins' => [
        'wordpress-seo' => [
            'name' => 'Yoast SEO',
            'file' => 'wordpress-seo/wp-seo.php',
            'version' => '16.0.0'
        ]
    ],
    'php_extensions' => [
        'curl',
        'json',
        'mbstring',
        'xml'
    ],
    'php_settings' => [
        'memory_limit' => '256M',
        'max_execution_time' => '300',
        'post_max_size' => '64M',
        'upload_max_filesize' => '64M'
    ]
];