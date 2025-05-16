<?php
/**
 * MFW Configuration
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
    'api' => [
        'version' => '1.0',
        'base_url' => 'https://api.openai.com/v1',
        'timeout' => 30,
        'models' => [
            'gpt-4',
            'gpt-4-32k',
            'gpt-3.5-turbo'
        ]
    ],
    'cache' => [
        'enabled' => true,
        'expiration' => 3600,
        'max_size' => 50 * 1024 * 1024 // 50MB
    ],
    'rate_limiting' => [
        'enabled' => true,
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'requests_per_day' => 10000
    ],
    'security' => [
        'allowed_html_tags' => [
            'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
        ],
        'allowed_protocols' => [
            'http', 'https', 'mailto'
        ]
    ],
    'features' => [
        'content_generation' => true,
        'image_generation' => true,
        'translation' => true,
        'analytics' => true
    ]
];