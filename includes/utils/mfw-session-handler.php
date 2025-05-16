<?php
/**
 * Session Handler Class
 *
 * Manages plugin-specific session data and state management.
 * Implements secure session handling with database storage.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Session_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:50:38';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Session ID
     */
    private $session_id;

    /**
     * Session data
     */
    private $data = [];

    /**
     * Session lifetime in seconds
     */
    const SESSION_LIFETIME = 3600; // 1 hour

    /**
     * Initialize session handler
     */
    public function __construct() {
        // Start session if not already started
        if (!session_id()) {
            session_start();
        }

        // Generate or retrieve session ID
        $this->session_id = $this->get_session_id();

        // Load session data
        $this->load_session();

        // Set up cleanup hook
        add_action('wp_scheduled_delete', [$this, 'cleanup_expired_sessions']);
    }

    /**
     * Get session value
     *
     * @param string $key Session key
     * @param mixed $default Default value
     * @return mixed Session value
     */
    public function get($key, $default = null) {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * Set session value
     *
     * @param string $key Session key
     * @param mixed $value Session value
     * @return bool Success status
     */
    public function set($key, $value) {
        try {
            $this->data[$key] = $value;
            return $this->save_session();

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to set session value: %s', $e->getMessage()),
                'session_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Delete session value
     *
     * @param string $key Session key
     * @return bool Success status
     */
    public function delete($key) {
        try {
            if (isset($this->data[$key])) {
                unset($this->data[$key]);
                return $this->save_session();
            }
            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to delete session value: %s', $e->getMessage()),
                'session_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Clear all session data
     *
     * @return bool Success status
     */
    public function clear() {
        try {
            $this->data = [];
            return $this->save_session();

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to clear session: %s', $e->getMessage()),
                'session_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Regenerate session ID
     *
     * @return bool Success status
     */
    public function regenerate() {
        try {
            global $wpdb;

            // Generate new session ID
            $new_session_id = $this->generate_session_id();

            // Update database record
            $updated = $wpdb->update(
                $wpdb->prefix . 'mfw_sessions',
                [
                    'session_id' => $new_session_id,
                    'updated_at' => $this->current_time
                ],
                ['session_id' => $this->session_id],
                ['%s', '%s'],
                ['%s']
            );

            if ($updated) {
                $this->session_id = $new_session_id;
                $_SESSION['mfw_session_id'] = $new_session_id;
                return true;
            }

            return false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to regenerate session: %s', $e->getMessage()),
                'session_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get session lifetime
     *
     * @return int Session lifetime in seconds
     */
    public function get_lifetime() {
        return self::SESSION_LIFETIME;
    }

    /**
     * Clean up expired sessions
     */
    public function cleanup_expired_sessions() {
        try {
            global $wpdb;

            $expiry_time = date('Y-m-d H:i:s', strtotime($this->current_time) - self::SESSION_LIFETIME);

            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}mfw_sessions WHERE updated_at < %s",
                $expiry_time
            ));

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to cleanup expired sessions: %s', $e->getMessage()),
                'session_handler',
                'error'
            );
        }
    }

    /**
     * Get or generate session ID
     *
     * @return string Session ID
     */
    private function get_session_id() {
        // Check for existing session ID
        if (isset($_SESSION['mfw_session_id'])) {
            return $_SESSION['mfw_session_id'];
        }

        // Generate new session ID
        $session_id = $this->generate_session_id();
        $_SESSION['mfw_session_id'] = $session_id;

        return $session_id;
    }

    /**
     * Generate unique session ID
     *
     * @return string Session ID
     */
    private function generate_session_id() {
        return wp_hash(uniqid('mfw_', true));
    }

    /**
     * Load session data from database
     */
    private function load_session() {
        try {
            global $wpdb;

            $session = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mfw_sessions WHERE session_id = %s",
                $this->session_id
            ));

            if ($session) {
                // Check if session has expired
                $expiry_time = strtotime($session->updated_at) + self::SESSION_LIFETIME;
                if ($expiry_time < strtotime($this->current_time)) {
                    $this->clear();
                    return;
                }

                $this->data = json_decode($session->session_data, true) ?: [];

                // Update last access time
                $wpdb->update(
                    $wpdb->prefix . 'mfw_sessions',
                    ['updated_at' => $this->current_time],
                    ['session_id' => $this->session_id],
                    ['%s'],
                    ['%s']
                );
            } else {
                // Create new session
                $wpdb->insert(
                    $wpdb->prefix . 'mfw_sessions',
                    [
                        'session_id' => $this->session_id,
                        'user_id' => get_current_user_id(),
                        'session_data' => json_encode([]),
                        'ip_address' => $this->get_client_ip(),
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                        'created_by' => $this->current_user,
                        'created_at' => $this->current_time,
                        'updated_at' => $this->current_time
                    ],
                    [
                        '%s', '%d', '%s', '%s', '%s',
                        '%s', '%s', '%s'
                    ]
                );
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to load session: %s', $e->getMessage()),
                'session_handler',
                'error'
            );
        }
    }

    /**
     * Save session data to database
     *
     * @return bool Success status
     */
    private function save_session() {
        try {
            global $wpdb;

            $updated = $wpdb->update(
                $wpdb->prefix . 'mfw_sessions',
                [
                    'session_data' => json_encode($this->data),
                    'updated_at' => $this->current_time
                ],
                ['session_id' => $this->session_id],
                ['%s', '%s'],
                ['%s']
            );

            return (bool)$updated;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to save session: %s', $e->getMessage()),
                'session_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (isset($_SERVER[$header])) {
                foreach (explode(',', $_SERVER[$header]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return '0.0.0.0';
    }
}