<?php
/**
 * URL Handler Class
 *
 * Handles URL parsing, validation, and normalization.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_URL_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:16:13';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Default URL parameters
     *
     * @var array
     */
    private $default_params = [
        'utm_source' => 'mfw',
        'utm_medium' => 'plugin',
        'utm_campaign' => 'content_fetch'
    ];

    /**
     * Blocked domains
     *
     * @var array
     */
    private $blocked_domains = [];

    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option(MFW_SETTINGS_OPTION, []);
        $this->blocked_domains = $settings['blocked_domains'] ?? [];
    }

    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @return bool Whether URL is valid
     */
    public function validate_url($url) {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Parse URL
        $parsed_url = parse_url($url);
        if (!$parsed_url || empty($parsed_url['host'])) {
            return false;
        }

        // Check blocked domains
        $domain = strtolower($parsed_url['host']);
        foreach ($this->blocked_domains as $blocked) {
            if (strpos($domain, strtolower($blocked)) !== false) {
                MFW_Error_Logger::log(
                    sprintf('Blocked domain attempted: %s', $domain),
                    'url_handler',
                    'warning'
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize URL
     *
     * @param string $url URL to normalize
     * @return string Normalized URL
     */
    public function normalize_url($url) {
        // Remove trailing slashes
        $url = rtrim($url, '/');

        // Add scheme if missing
        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'https://' . $url;
        }

        // Convert to lowercase
        $url = strtolower($url);

        // Remove common tracking parameters
        $url = $this->remove_tracking_params($url);

        return $url;
    }

    /**
     * Add tracking parameters
     *
     * @param string $url URL to modify
     * @param array $params Additional parameters
     * @return string Modified URL
     */
    public function add_tracking_params($url, $params = []) {
        // Parse URL
        $parsed_url = parse_url($url);
        if (!$parsed_url) {
            return $url;
        }

        // Merge parameters
        $query_params = [];
        if (!empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
        }

        $tracking_params = array_merge(
            $this->default_params,
            $params
        );

        $query_params = array_merge(
            $query_params,
            $tracking_params
        );

        // Rebuild URL
        $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        if (!empty($parsed_url['path'])) {
            $new_url .= $parsed_url['path'];
        }
        if (!empty($query_params)) {
            $new_url .= '?' . http_build_query($query_params);
        }
        if (!empty($parsed_url['fragment'])) {
            $new_url .= '#' . $parsed_url['fragment'];
        }

        return $new_url;
    }

    /**
     * Remove tracking parameters
     *
     * @param string $url URL to clean
     * @return string Cleaned URL
     */
    public function remove_tracking_params($url) {
        // List of common tracking parameters
        $tracking_params = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'fbclid',
            'gclid',
            'msclkid',
            '_ga',
            'ref'
        ];

        // Parse URL
        $parsed_url = parse_url($url);
        if (!$parsed_url || empty($parsed_url['query'])) {
            return $url;
        }

        // Parse query parameters
        parse_str($parsed_url['query'], $query_params);

        // Remove tracking parameters
        foreach ($tracking_params as $param) {
            unset($query_params[$param]);
        }

        // Rebuild URL
        $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        if (!empty($parsed_url['path'])) {
            $new_url .= $parsed_url['path'];
        }
        if (!empty($query_params)) {
            $new_url .= '?' . http_build_query($query_params);
        }
        if (!empty($parsed_url['fragment'])) {
            $new_url .= '#' . $parsed_url['fragment'];
        }

        return $new_url;
    }

    /**
     * Get domain from URL
     *
     * @param string $url URL to parse
     * @return string|false Domain or false on failure
     */
    public function get_domain($url) {
        $parsed_url = parse_url($url);
        return $parsed_url['host'] ?? false;
    }

    /**
     * Get URL path
     *
     * @param string $url URL to parse
     * @return string URL path
     */
    public function get_path($url) {
        $parsed_url = parse_url($url);
        return $parsed_url['path'] ?? '/';
    }

    /**
     * Check if URL is internal
     *
     * @param string $url URL to check
     * @return bool Whether URL is internal
     */
    public function is_internal_url($url) {
        $site_domain = parse_url(get_site_url(), PHP_URL_HOST);
        $url_domain = parse_url($url, PHP_URL_HOST);

        return $site_domain === $url_domain;
    }

    /**
     * Create short URL
     *
     * @param string $url URL to shorten
     * @return string|false Shortened URL or false on failure
     */
    public function create_short_url($url) {
        try {
            global $wpdb;

            // Check if URL already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT short_code FROM {$wpdb->prefix}mfw_short_urls WHERE long_url = %s",
                $url
            ));

            if ($existing) {
                return get_site_url() . '/mfw/' . $existing;
            }

            // Generate unique short code
            do {
                $short_code = substr(md5(uniqid(rand(), true)), 0, 8);
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mfw_short_urls WHERE short_code = %s",
                    $short_code
                ));
            } while ($exists);

            // Store in database
            $wpdb->insert(
                $wpdb->prefix . 'mfw_short_urls',
                [
                    'long_url' => $url,
                    'short_code' => $short_code,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

            return get_site_url() . '/mfw/' . $short_code;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to create short URL: %s', $e->getMessage()),
                'url_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Resolve short URL
     *
     * @param string $short_code Short URL code
     * @return string|false Original URL or false if not found
     */
    public function resolve_short_url($short_code) {
        global $wpdb;

        $url = $wpdb->get_var($wpdb->prepare(
            "SELECT long_url FROM {$wpdb->prefix}mfw_short_urls WHERE short_code = %s",
            $short_code
        ));

        if ($url) {
            // Update click count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}mfw_short_urls SET clicks = clicks + 1, last_click = %s WHERE short_code = %s",
                $this->current_time,
                $short_code
            ));
        }

        return $url ?: false;
    }
}