<?php
/**
 * Observable Trait
 * 
 * Implements the observer pattern for classes.
 * Allows objects to maintain a list of observers and notify them of changes.
 *
 * @package MFW
 * @subpackage Traits
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

trait MFW_Observable {
    /**
     * Observers storage
     *
     * @var array
     */
    private $observers = [];

    /**
     * Last update timestamp
     *
     * @var string
     */
    private $last_update = '2025-05-14 06:25:58';

    /**
     * Last updater
     *
     * @var string
     */
    private $last_updater = 'maziyarid';

    /**
     * Add observer
     *
     * @param object $observer Observer instance
     * @param string $event Event to observe (empty for all events)
     * @return bool Whether observer was added
     */
    public function add_observer($observer, $event = '') {
        if (!is_object($observer) || !method_exists($observer, 'update')) {
            return false;
        }

        $observer_id = spl_object_hash($observer);
        $this->observers[$event][$observer_id] = $observer;

        $this->log_observer_action('add', $observer_id, $event);
        return true;
    }

    /**
     * Remove observer
     *
     * @param object $observer Observer instance
     * @param string $event Event to remove (empty for all events)
     * @return bool Whether observer was removed
     */
    public function remove_observer($observer, $event = '') {
        $observer_id = spl_object_hash($observer);

        if ($event === '') {
            foreach ($this->observers as $evt => $obs) {
                unset($this->observers[$evt][$observer_id]);
                if (empty($this->observers[$evt])) {
                    unset($this->observers[$evt]);
                }
            }
        } elseif (isset($this->observers[$event][$observer_id])) {
            unset($this->observers[$event][$observer_id]);
            if (empty($this->observers[$event])) {
                unset($this->observers[$event]);
            }
        }

        $this->log_observer_action('remove', $observer_id, $event);
        return true;
    }

    /**
     * Notify observers
     *
     * @param string $event Event name
     * @param mixed $data Event data
     * @return void
     */
    protected function notify($event, $data = null) {
        $this->last_update = current_time('mysql');
        $this->last_updater = wp_get_current_user()->user_login;

        // Notify specific event observers
        if (isset($this->observers[$event])) {
            foreach ($this->observers[$event] as $observer) {
                $observer->update($this, $event, $data);
            }
        }

        // Notify global observers
        if (isset($this->observers[''])) {
            foreach ($this->observers[''] as $observer) {
                $observer->update($this, $event, $data);
            }
        }

        $this->log_notification($event, $data);
    }

    /**
     * Get observers
     *
     * @param string $event Event filter (empty for all)
     * @return array Observer instances
     */
    public function get_observers($event = '') {
        if ($event === '') {
            return $this->observers;
        }
        return isset($this->observers[$event]) ? $this->observers[$event] : [];
    }

    /**
     * Count observers
     *
     * @param string $event Event filter (empty for all)
     * @return int Observer count
     */
    public function count_observers($event = '') {
        if ($event === '') {
            $count = 0;
            foreach ($this->observers as $obs) {
                $count += count($obs);
            }
            return $count;
        }
        return isset($this->observers[$event]) ? count($this->observers[$event]) : 0;
    }

    /**
     * Clear observers
     *
     * @param string $event Event filter (empty for all)
     * @return void
     */
    public function clear_observers($event = '') {
        if ($event === '') {
            $this->observers = [];
        } else {
            unset($this->observers[$event]);
        }
    }

    /**
     * Get last update time
     *
     * @return string Last update timestamp
     */
    public function get_last_update() {
        return $this->last_update;
    }

    /**
     * Get last updater
     *
     * @return string Last updater username
     */
    public function get_last_updater() {
        return $this->last_updater;
    }

    /**
     * Log observer action
     *
     * @param string $action Action performed
     * @param string $observer_id Observer ID
     * @param string $event Event name
     */
    private function log_observer_action($action, $observer_id, $event) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_observer_log',
                [
                    'observable_class' => get_class($this),
                    'observer_id' => $observer_id,
                    'action' => $action,
                    'event' => $event,
                    'created_at' => current_time('mysql'),
                    'created_by' => wp_get_current_user()->user_login
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            error_log(sprintf('Failed to log observer action: %s', $e->getMessage()));
        }
    }

    /**
     * Log notification
     *
     * @param string $event Event name
     * @param mixed $data Event data
     */
    private function log_notification($event, $data) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_notification_log',
                [
                    'observable_class' => get_class($this),
                    'event' => $event,
                    'data' => is_scalar($data) ? (string)$data : json_encode($data),
                    'observer_count' => $this->count_observers($event),
                    'created_at' => $this->last_update,
                    'created_by' => $this->last_updater
                ],
                ['%s', '%s', '%s', '%d', '%s', '%s']
            );

        } catch (Exception $e) {
            error_log(sprintf('Failed to log notification: %s', $e->getMessage()));
        }
    }
}