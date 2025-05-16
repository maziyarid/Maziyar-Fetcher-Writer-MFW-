<?php
/**
 * Security Handler Class
 * 
 * Manages security features and protections.
 * Handles authentication, authorization, and security measures.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Security_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:44:25';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Security settings
     */
    private $settings;

    /**
     * Failed login attempts
     */
    private $failed_attempts = [];

    /**
     * Initialize security handler
     */
    public function __construct() {
        // Load settings
        $this->settings = get_option('mfw_security_settings', [
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'password_min_length' => 12,
            'require_strong_passwords' => true,
            'enable_2fa' => true,
            'enable_activity_logging' => true,
            'blocked_ips' => [],
            'allowed_ips' => []
        ]);

        // Add security hooks
        add_action('wp_login_failed', [$this, 'handle_failed_login']);
        add_action('wp_login', [$this, 'handle_successful_login'], 10, 2);
        add_filter('authenticate', [$this, 'check_login_attempts'], 30, 3);
        add_filter('password_requirements', [$this, 'set_password_requirements']);
        
        // Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);

        // Add AJAX handlers
        add_action('wp_ajax_mfw_verify_2fa', [$this, 'verify_2fa']);
    }

    /**
     * Handle failed login attempt
     *
     * @param string $username Username
     */
    public function handle_failed_login($username) {
        try {
            // Get IP address
            $ip = $this->get_client_ip();

            // Record failed attempt
            $this->record_failed_attempt($username, $ip);

            // Check if should lock out
            if ($this->should_lockout($ip)) {
                $this->lockout_ip($ip);
            }

            // Log failed attempt
            $this->log_security_event('failed_login', [
                'username' => $username,
                'ip' => $ip
            ]);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed login handling failed: %s', $e->getMessage()),
                'security_handler',
                'error'
            );
        }
    }

    /**
     * Handle successful login
     *
     * @param string $username Username
     * @param WP_User $user User object
     */
    public function handle_successful_login($username, $user) {
        try {
            // Clear failed attempts
            $this->clear_failed_attempts($username);

            // Initialize 2FA if enabled
            if ($this->settings['enable_2fa'] && $this->needs_2fa($user)) {
                $this->initialize_2fa($user);
            }

            // Log successful login
            $this->log_security_event('successful_login', [
                'user_id' => $user->ID,
                'ip' => $this->get_client_ip()
            ]);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Login handling failed: %s', $e->getMessage()),
                'security_handler',
                'error'
            );
        }
    }

    /**
     * Check login attempts before authentication
     *
     * @param WP_User|WP_Error|null $user User object
     * @param string $username Username
     * @param string $password Password
     * @return WP_User|WP_Error User object or error
     */
    public function check_login_attempts($user, $username, $password) {
        // Skip if already authenticated
        if ($user instanceof WP_User) {
            return $user;
        }

        try {
            $ip = $this->get_client_ip();

            // Check if IP is blocked
            if ($this->is_ip_blocked($ip)) {
                return new WP_Error(
                    'ip_blocked',
                    __('Access denied: Your IP address is blocked.', 'mfw')
                );
            }

            // Check if IP is locked out
            if ($this->is_ip_locked($ip)) {
                return new WP_Error(
                    'ip_locked',
                    sprintf(
                        __('Too many failed login attempts. Please try again in %d minutes.', 'mfw'),
                        ceil($this->get_lockout_time_remaining($ip) / 60)
                    )
                );
            }

            return $user;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Login attempt check failed: %s', $e->getMessage()),
                'security_handler',
                'error'
            );
            return $user;
        }
    }

    /**
     * Set password requirements
     *
     * @param array $requirements Current requirements
     * @return array Modified requirements
     */
    public function set_password_requirements($requirements) {
        if ($this->settings['require_strong_passwords']) {
            $requirements = [
                'min_length' => $this->settings['password_min_length'],
                'min_lowercase' => 1,
                'min_uppercase' => 1,
                'min_numbers' => 1,
                'min_special' => 1
            ];
        }

        return $requirements;
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
        
        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Feature-Policy
        header("Feature-Policy: geolocation 'none'; midi 'none'; notifications 'none'; push 'none'; sync-xhr 'none'; microphone 'none'; camera 'none'; magnetometer 'none'; gyroscope 'none'; speaker 'none'; vibrate 'none'; fullscreen 'self'; payment 'none';");
    }

    /**
     * Verify 2FA code
     */
    public function verify_2fa() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_2fa_verify', 'nonce');

            // Get parameters
            $user_id = get_current_user_id();
            $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';

            // Verify code
            if ($this->verify_2fa_code($user_id, $code)) {
                wp_send_json_success();
            } else {
                wp_send_json_error(__('Invalid verification code.', 'mfw'));
            }

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Record failed login attempt
     *
     * @param string $username Username
     * @param string $ip IP address
     */
    private function record_failed_attempt($username, $ip) {
        $attempts = get_transient('mfw_failed_logins_' . $ip) ?: [];
        $attempts[] = [
            'username' => $username,
            'time' => time()
        ];
        
        set_transient('mfw_failed_logins_' . $ip, $attempts, DAY_IN_SECONDS);
    }

    /**
     * Check if IP should be locked out
     *
     * @param string $ip IP address
     * @return bool Should lockout
     */
    private function should_lockout($ip) {
        $attempts = get_transient('mfw_failed_logins_' . $ip) ?: [];
        $recent_attempts = array_filter($attempts, function($attempt) {
            return (time() - $attempt['time']) < 3600;
        });

        return count($recent_attempts) >= $this->settings['max_login_attempts'];
    }

    /**
     * Lock out IP address
     *
     * @param string $ip IP address
     */
    private function lockout_ip($ip) {
        set_transient(
            'mfw_ip_lockout_' . $ip,
            time() + $this->settings['lockout_duration'],
            $this->settings['lockout_duration']
        );

        $this->log_security_event('ip_lockout', ['ip' => $ip]);
    }

    /**
     * Check if IP is locked
     *
     * @param string $ip IP address
     * @return bool Is locked
     */
    private function is_ip_locked($ip) {
        return get_transient('mfw_ip_lockout_' . $ip) !== false;
    }

    /**
     * Get remaining lockout time
     *
     * @param string $ip IP address
     * @return int Remaining time in seconds
     */
    private function get_lockout_time_remaining($ip) {
        $lockout_time = get_transient('mfw_ip_lockout_' . $ip);
        return $lockout_time ? max(0, $lockout_time - time()) : 0;
    }

    /**
     * Clear failed login attempts
     *
     * @param string $username Username
     */
    private function clear_failed_attempts($username) {
        $ip = $this->get_client_ip();
        delete_transient('mfw_failed_logins_' . $ip);
        delete_transient('mfw_ip_lockout_' . $ip);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP);
    }

    /**
     * Check if IP is blocked
     *
     * @param string $ip IP address
     * @return bool Is blocked
     */
    private function is_ip_blocked($ip) {
        // Check blocked IPs
        if (in_array($ip, $this->settings['blocked_ips'])) {
            return true;
        }

        // Check allowed IPs (if configured)
        if (!empty($this->settings['allowed_ips']) && 
            !in_array($ip, $this->settings['allowed_ips'])) {
            return true;
        }

        return false;
    }

    /**
     * Log security event
     *
     * @param string $event Event type
     * @param array $data Event data
     */
    private function log_security_event($event, $data = []) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_security_log',
                [
                    'event' => $event,
                    'data' => json_encode($data),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log security event: %s', $e->getMessage()),
                'security_handler',
                'error'
            );
        }
    }
}