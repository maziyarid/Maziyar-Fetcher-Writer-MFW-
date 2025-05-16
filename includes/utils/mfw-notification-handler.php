<?php
/**
 * Notification Handler Class
 * 
 * Manages system notifications, alerts, and user messages.
 * Supports multiple notification types and delivery methods.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Notification_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:22:25';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Notification settings
     */
    private $settings;

    /**
     * Email handler instance
     */
    private $email_handler;

    /**
     * Cache handler instance
     */
    private $cache;

    /**
     * Initialize notification handler
     */
    public function __construct() {
        $this->settings = get_option('mfw_notification_settings', []);
        $this->email_handler = new MFW_Email_Handler();
        $this->cache = new MFW_Cache_Handler();

        // Add notification hooks
        add_action('init', [$this, 'init_notifications']);
        add_action('admin_init', [$this, 'register_notification_settings']);
        add_action('wp_ajax_mfw_mark_notification_read', [$this, 'mark_notification_read']);
        add_action('wp_ajax_mfw_get_notifications', [$this, 'get_user_notifications_ajax']);
    }

    /**
     * Create notification
     *
     * @param string $type Notification type
     * @param string $message Notification message
     * @param array $options Notification options
     * @return bool|int Notification ID or false on failure
     */
    public function create_notification($type, $message, $options = []) {
        try {
            global $wpdb;

            // Parse options
            $options = wp_parse_args($options, [
                'user_id' => 0, // 0 for all users
                'priority' => 'normal',
                'expiry' => null,
                'action_url' => '',
                'action_text' => '',
                'channel' => 'web', // web, email, both
                'meta' => []
            ]);

            // Insert notification
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'mfw_notifications',
                [
                    'type' => $type,
                    'message' => $message,
                    'user_id' => $options['user_id'],
                    'priority' => $options['priority'],
                    'expiry' => $options['expiry'],
                    'action_url' => $options['action_url'],
                    'action_text' => $options['action_text'],
                    'channel' => $options['channel'],
                    'metadata' => json_encode($options['meta']),
                    'status' => 'unread',
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                throw new Exception($wpdb->last_error);
            }

            $notification_id = $wpdb->insert_id;

            // Send email notification if configured
            if (in_array($options['channel'], ['email', 'both'])) {
                $this->send_email_notification($notification_id);
            }

            // Clear cache
            $this->clear_notifications_cache($options['user_id']);

            return $notification_id;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to create notification: %s', $e->getMessage()),
                'notification_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get user notifications
     *
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Notifications
     */
    public function get_user_notifications($user_id, $args = []) {
        try {
            // Parse arguments
            $args = wp_parse_args($args, [
                'status' => 'unread',
                'type' => '',
                'priority' => '',
                'limit' => 10,
                'offset' => 0,
                'order' => 'DESC',
                'cache' => true
            ]);

            // Generate cache key
            $cache_key = 'mfw_notifications_' . md5($user_id . serialize($args));

            // Check cache
            if ($args['cache']) {
                $cached = $this->cache->get($cache_key, 'mfw_notifications');
                if ($cached !== false) {
                    return $cached;
                }
            }

            global $wpdb;

            // Build query
            $query = "SELECT * FROM {$wpdb->prefix}mfw_notifications WHERE 
                     (user_id = %d OR user_id = 0)";
            $params = [$user_id];

            if ($args['status']) {
                $query .= " AND status = %s";
                $params[] = $args['status'];
            }

            if ($args['type']) {
                $query .= " AND type = %s";
                $params[] = $args['type'];
            }

            if ($args['priority']) {
                $query .= " AND priority = %s";
                $params[] = $args['priority'];
            }

            // Check expiry
            $query .= " AND (expiry IS NULL OR expiry > %s)";
            $params[] = $this->current_time;

            // Add order and limit
            $query .= " ORDER BY created_at " . $args['order'];
            $query .= " LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];

            // Execute query
            $notifications = $wpdb->get_results(
                $wpdb->prepare($query, $params)
            );

            // Format notifications
            $formatted = array_map([$this, 'format_notification'], $notifications);

            // Cache results
            if ($args['cache']) {
                $this->cache->set($cache_key, $formatted, 'mfw_notifications', 300);
            }

            return $formatted;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get notifications: %s', $e->getMessage()),
                'notification_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Mark notification as read
     *
     * @param int $notification_id Notification ID
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function mark_notification_read($notification_id, $user_id = null) {
        try {
            global $wpdb;

            $user_id = $user_id ?: get_current_user_id();

            $updated = $wpdb->update(
                $wpdb->prefix . 'mfw_notifications',
                [
                    'status' => 'read',
                    'read_at' => $this->current_time
                ],
                [
                    'id' => $notification_id,
                    'user_id' => [$user_id, 0]
                ],
                ['%s', '%s'],
                ['%d', '%d']
            );

            if ($updated) {
                $this->clear_notifications_cache($user_id);
                return true;
            }

            return false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to mark notification as read: %s', $e->getMessage()),
                'notification_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Delete notification
     *
     * @param int $notification_id Notification ID
     * @return bool Success status
     */
    public function delete_notification($notification_id) {
        try {
            global $wpdb;

            $deleted = $wpdb->delete(
                $wpdb->prefix . 'mfw_notifications',
                ['id' => $notification_id],
                ['%d']
            );

            if ($deleted) {
                $this->clear_notifications_cache();
                return true;
            }

            return false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to delete notification: %s', $e->getMessage()),
                'notification_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Handle AJAX request for user notifications
     */
    public function get_user_notifications_ajax() {
        try {
            // Verify nonce
            check_ajax_referer('mfw_notifications', 'nonce');

            // Get notifications
            $notifications = $this->get_user_notifications(
                get_current_user_id(),
                [
                    'limit' => 10,
                    'status' => 'unread'
                ]
            );

            wp_send_json_success([
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Format notification
     *
     * @param object $notification Notification object
     * @return array Formatted notification
     */
    private function format_notification($notification) {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'message' => $notification->message,
            'priority' => $notification->priority,
            'action_url' => $notification->action_url,
            'action_text' => $notification->action_text,
            'metadata' => json_decode($notification->metadata, true),
            'created_at' => $notification->created_at,
            'relative_time' => human_time_diff(
                strtotime($notification->created_at),
                strtotime($this->current_time)
            ) . ' ago'
        ];
    }

    /**
     * Send email notification
     *
     * @param int $notification_id Notification ID
     * @return bool Success status
     */
    private function send_email_notification($notification_id) {
        try {
            global $wpdb;

            // Get notification
            $notification = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mfw_notifications WHERE id = %d",
                $notification_id
            ));

            if (!$notification) {
                return false;
            }

            // Get user email
            $user_email = '';
            if ($notification->user_id > 0) {
                $user = get_user_by('id', $notification->user_id);
                if ($user) {
                    $user_email = $user->user_email;
                }
            }

            // Send email
            return $this->email_handler->send(
                $user_email,
                sprintf(__('New Notification: %s', 'mfw'), $notification->type),
                [
                    'template' => 'notification',
                    'variables' => [
                        'notification' => $this->format_notification($notification)
                    ]
                ]
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to send email notification: %s', $e->getMessage()),
                'notification_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Clear notifications cache
     *
     * @param int $user_id Optional user ID
     */
    private function clear_notifications_cache($user_id = null) {
        if ($user_id) {
            $this->cache->delete_pattern(
                'mfw_notifications_' . md5($user_id . '*'),
                'mfw_notifications'
            );
        } else {
            $this->cache->flush_group('mfw_notifications');
        }
    }
}