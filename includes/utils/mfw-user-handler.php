<?php
/**
 * User Handler Class
 * 
 * Manages user-related functionality and permissions.
 * Handles user roles, capabilities, and access control.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_User_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:33:01';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Custom capabilities
     */
    private $custom_caps = [];

    /**
     * User settings
     */
    private $settings;

    /**
     * Initialize user handler
     */
    public function __construct() {
        $this->settings = get_option('mfw_user_settings', []);

        // Register custom roles and capabilities
        add_action('init', [$this, 'register_roles']);
        
        // Add capability checks
        add_filter('user_has_cap', [$this, 'check_user_caps'], 10, 4);
        
        // Add user management hooks
        add_action('user_register', [$this, 'setup_new_user']);
        add_action('delete_user', [$this, 'cleanup_user_data']);
    }

    /**
     * Register custom roles and capabilities
     */
    public function register_roles() {
        // Register custom capabilities
        $this->register_custom_capabilities();

        // Register custom roles
        $this->register_custom_roles();
    }

    /**
     * Check if user has specific capability
     *
     * @param string $capability Capability to check
     * @param int $user_id User ID (optional)
     * @param array $args Additional arguments
     * @return bool Has capability
     */
    public function has_capability($capability, $user_id = null, $args = []) {
        try {
            $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
            
            if (!$user || !$user->exists()) {
                return false;
            }

            // Check custom capabilities first
            if (isset($this->custom_caps[$capability])) {
                return $this->check_custom_capability($user, $capability, $args);
            }

            // Fall back to WordPress capability check
            return $user->has_cap($capability);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Capability check failed: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Grant capability to user
     *
     * @param int $user_id User ID
     * @param string $capability Capability to grant
     * @return bool Success status
     */
    public function grant_capability($user_id, $capability) {
        try {
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return false;
            }

            $user->add_cap($capability);

            // Log capability grant
            $this->log_capability_change($user_id, $capability, 'grant');

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to grant capability: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Revoke capability from user
     *
     * @param int $user_id User ID
     * @param string $capability Capability to revoke
     * @return bool Success status
     */
    public function revoke_capability($user_id, $capability) {
        try {
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return false;
            }

            $user->remove_cap($capability);

            // Log capability revocation
            $this->log_capability_change($user_id, $capability, 'revoke');

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to revoke capability: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Setup new user
     *
     * @param int $user_id New user ID
     */
    public function setup_new_user($user_id) {
        try {
            // Add default capabilities
            $default_caps = $this->get_default_capabilities();
            foreach ($default_caps as $cap) {
                $this->grant_capability($user_id, $cap);
            }

            // Set default user preferences
            $this->set_default_preferences($user_id);

            // Log user setup
            $this->log_user_setup($user_id);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('New user setup failed: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
        }
    }

    /**
     * Cleanup user data
     *
     * @param int $user_id User ID being deleted
     */
    public function cleanup_user_data($user_id) {
        try {
            global $wpdb;

            // Remove user preferences
            delete_user_meta($user_id, 'mfw_preferences');

            // Remove user capabilities
            $user = get_user_by('id', $user_id);
            if ($user) {
                $custom_caps = array_keys($this->custom_caps);
                foreach ($custom_caps as $cap) {
                    $user->remove_cap($cap);
                }
            }

            // Remove user-specific data from custom tables
            $tables = [
                'mfw_user_activity',
                'mfw_user_preferences',
                'mfw_user_settings'
            ];

            foreach ($tables as $table) {
                $wpdb->delete(
                    $wpdb->prefix . $table,
                    ['user_id' => $user_id],
                    ['%d']
                );
            }

            // Log user cleanup
            $this->log_user_cleanup($user_id);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('User cleanup failed: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
        }
    }

    /**
     * Get user preferences
     *
     * @param int $user_id User ID
     * @param string $key Specific preference key (optional)
     * @return mixed User preferences
     */
    public function get_preferences($user_id, $key = '') {
        try {
            $preferences = get_user_meta($user_id, 'mfw_preferences', true);
            
            if (empty($preferences)) {
                $preferences = $this->get_default_preferences();
            }

            if ($key) {
                return $preferences[$key] ?? null;
            }

            return $preferences;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get user preferences: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Update user preferences
     *
     * @param int $user_id User ID
     * @param array $preferences New preferences
     * @return bool Success status
     */
    public function update_preferences($user_id, $preferences) {
        try {
            $current = $this->get_preferences($user_id);
            $updated = wp_parse_args($preferences, $current);

            // Update preferences
            $success = update_user_meta($user_id, 'mfw_preferences', $updated);

            if ($success) {
                // Log preference update
                $this->log_preference_update($user_id, $preferences);
            }

            return $success;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to update user preferences: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Register custom capabilities
     */
    private function register_custom_capabilities() {
        $this->custom_caps = [
            'mfw_manage_settings' => [
                'label' => __('Manage Framework Settings', 'mfw'),
                'default' => ['administrator']
            ],
            'mfw_view_reports' => [
                'label' => __('View Framework Reports', 'mfw'),
                'default' => ['administrator', 'editor']
            ],
            'mfw_manage_data' => [
                'label' => __('Manage Framework Data', 'mfw'),
                'default' => ['administrator']
            ]
        ];
    }

    /**
     * Register custom roles
     */
    private function register_custom_roles() {
        // Framework Manager role
        add_role('mfw_manager', __('Framework Manager', 'mfw'), [
            'read' => true,
            'mfw_manage_settings' => true,
            'mfw_view_reports' => true,
            'mfw_manage_data' => true
        ]);

        // Framework User role
        add_role('mfw_user', __('Framework User', 'mfw'), [
            'read' => true,
            'mfw_view_reports' => true
        ]);
    }

    /**
     * Check custom capability
     *
     * @param WP_User $user User object
     * @param string $capability Capability to check
     * @param array $args Additional arguments
     * @return bool Has capability
     */
    private function check_custom_capability($user, $capability, $args) {
        // Check if user has capability directly
        if ($user->has_cap($capability)) {
            return true;
        }

        // Check if user has a role that includes this capability
        foreach ($this->custom_caps[$capability]['default'] as $role) {
            if ($user->has_cap($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get default capabilities
     *
     * @return array Default capabilities
     */
    private function get_default_capabilities() {
        return [
            'read',
            'mfw_view_reports'
        ];
    }

    /**
     * Get default preferences
     *
     * @return array Default preferences
     */
    private function get_default_preferences() {
        return [
            'notifications' => true,
            'email_notifications' => true,
            'display_mode' => 'light',
            'language' => get_locale()
        ];
    }

    /**
     * Set default preferences
     *
     * @param int $user_id User ID
     */
    private function set_default_preferences($user_id) {
        update_user_meta($user_id, 'mfw_preferences', $this->get_default_preferences());
    }

    /**
     * Log capability change
     *
     * @param int $user_id User ID
     * @param string $capability Capability
     * @param string $action Change action
     */
    private function log_capability_change($user_id, $capability, $action) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_capability_log',
                [
                    'user_id' => $user_id,
                    'capability' => $capability,
                    'action' => $action,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log capability change: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
        }
    }

    /**
     * Log user setup
     *
     * @param int $user_id User ID
     */
    private function log_user_setup($user_id) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_user_log',
                [
                    'user_id' => $user_id,
                    'action' => 'setup',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%d', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log user setup: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
        }
    }

    /**
     * Log user cleanup
     *
     * @param int $user_id User ID
     */
    private function log_user_cleanup($user_id) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_user_log',
                [
                    'user_id' => $user_id,
                    'action' => 'cleanup',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%d', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log user cleanup: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
        }
    }

    /**
     * Log preference update
     *
     * @param int $user_id User ID
     * @param array $preferences Updated preferences
     */
    private function log_preference_update($user_id, $preferences) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_preference_log',
                [
                    'user_id' => $user_id,
                    'preferences' => json_encode($preferences),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%d', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log preference update: %s', $e->getMessage()),
                'user_handler',
                'error'
            );
        }
    }
}