<?php
/**
 * Events Class
 * 
 * Implements an event management system for the framework.
 * Handles event registration, dispatching, and listener management.
 *
 * @package MFW
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Events {
    use MFW_Observable;
    use MFW_Configurable;
    use MFW_Loggable;
    use MFW_Cacheable;

    /**
     * Event listeners
     *
     * @var array
     */
    private $listeners = [];

    /**
     * Event wildcards
     *
     * @var array
     */
    private $wildcards = [];

    /**
     * Event history
     *
     * @var array
     */
    private $history = [];

    /**
     * Maximum history size
     *
     * @var int
     */
    private $max_history = 1000;

    /**
     * Initialize events
     */
    public function __construct() {
        $this->init_timestamp = '2025-05-14 07:13:17';
        $this->init_user = 'maziyarid';

        $this->info('Events manager initialized');
    }

    /**
     * Add event listener
     *
     * @param string|array $events Event name(s)
     * @param callable $listener Event listener
     * @param int $priority Listener priority
     * @return bool Whether listener was added
     */
    public function add_listener($events, callable $listener, $priority = 10) {
        foreach ((array) $events as $event) {
            if (strpos($event, '*') !== false) {
                return $this->add_wildcard_listener($event, $listener, $priority);
            }

            $this->listeners[$event][$priority][] = [
                'callback' => $listener,
                'added_at' => current_time('mysql'),
                'added_by' => wp_get_current_user()->user_login
            ];

            $this->sort_listeners($event);
        }

        $this->debug('Listener added', [
            'events' => $events,
            'priority' => $priority
        ]);

        return true;
    }

    /**
     * Add wildcard event listener
     *
     * @param string $pattern Event pattern
     * @param callable $listener Event listener
     * @param int $priority Listener priority
     * @return bool Whether listener was added
     */
    private function add_wildcard_listener($pattern, callable $listener, $priority) {
        $this->wildcards[$pattern][$priority][] = [
            'callback' => $listener,
            'added_at' => current_time('mysql'),
            'added_by' => wp_get_current_user()->user_login
        ];

        $this->sort_wildcards($pattern);

        return true;
    }

    /**
     * Remove event listener
     *
     * @param string|array $events Event name(s)
     * @param callable $listener Event listener
     * @return bool Whether listener was removed
     */
    public function remove_listener($events, callable $listener) {
        $removed = false;

        foreach ((array) $events as $event) {
            if (strpos($event, '*') !== false) {
                $removed = $this->remove_wildcard_listener($event, $listener) || $removed;
                continue;
            }

            if (!isset($this->listeners[$event])) {
                continue;
            }

            foreach ($this->listeners[$event] as $priority => $listeners) {
                foreach ($listeners as $key => $registered) {
                    if ($registered['callback'] === $listener) {
                        unset($this->listeners[$event][$priority][$key]);
                        $removed = true;
                    }
                }
            }
        }

        if ($removed) {
            $this->debug('Listener removed', ['events' => $events]);
        }

        return $removed;
    }

    /**
     * Remove wildcard event listener
     *
     * @param string $pattern Event pattern
     * @param callable $listener Event listener
     * @return bool Whether listener was removed
     */
    private function remove_wildcard_listener($pattern, callable $listener) {
        if (!isset($this->wildcards[$pattern])) {
            return false;
        }

        $removed = false;

        foreach ($this->wildcards[$pattern] as $priority => $listeners) {
            foreach ($listeners as $key => $registered) {
                if ($registered['callback'] === $listener) {
                    unset($this->wildcards[$pattern][$priority][$key]);
                    $removed = true;
                }
            }
        }

        return $removed;
    }

    /**
     * Dispatch event
     *
     * @param string $event Event name
     * @param array $payload Event payload
     * @return mixed Event result
     */
    public function dispatch($event, array $payload = []) {
        $this->record_event($event, $payload);

        $responses = [];

        // Normal listeners
        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    $responses[] = call_user_func($listener['callback'], $payload);
                }
            }
        }

        // Wildcard listeners
        foreach ($this->wildcards as $pattern => $priorities) {
            if ($this->pattern_matches($pattern, $event)) {
                foreach ($priorities as $priority => $listeners) {
                    foreach ($listeners as $listener) {
                        $responses[] = call_user_func($listener['callback'], $payload);
                    }
                }
            }
        }

        $this->notify('event.dispatched', [
            'event' => $event,
            'payload' => $payload,
            'responses' => $responses
        ]);

        $this->debug('Event dispatched', [
            'event' => $event,
            'listeners' => count($responses)
        ]);

        return $responses;
    }

    /**
     * Check if event has listeners
     *
     * @param string $event Event name
     * @return bool Whether event has listeners
     */
    public function has_listeners($event) {
        if (isset($this->listeners[$event]) && !empty($this->listeners[$event])) {
            return true;
        }

        foreach (array_keys($this->wildcards) as $pattern) {
            if ($this->pattern_matches($pattern, $event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get event listeners
     *
     * @param string $event Event name
     * @return array Event listeners
     */
    public function get_listeners($event) {
        $listeners = [];

        // Normal listeners
        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $priority => $priority_listeners) {
                foreach ($priority_listeners as $listener) {
                    $listeners[] = $listener;
                }
            }
        }

        // Wildcard listeners
        foreach ($this->wildcards as $pattern => $priorities) {
            if ($this->pattern_matches($pattern, $event)) {
                foreach ($priorities as $priority => $priority_listeners) {
                    foreach ($priority_listeners as $listener) {
                        $listeners[] = $listener;
                    }
                }
            }
        }

        return $listeners;
    }

    /**
     * Get event history
     *
     * @param array $filters History filters
     * @return array Event history
     */
    public function get_history(array $filters = []) {
        if (empty($filters)) {
            return $this->history;
        }

        return array_filter($this->history, function($entry) use ($filters) {
            foreach ($filters as $key => $value) {
                if (!isset($entry[$key]) || $entry[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Clear event history
     *
     * @return void
     */
    public function clear_history() {
        $this->history = [];
        $this->debug('Event history cleared');
    }

    /**
     * Record event in history
     *
     * @param string $event Event name
     * @param array $payload Event payload
     */
    private function record_event($event, array $payload) {
        $entry = [
            'event' => $event,
            'payload' => $payload,
            'timestamp' => current_time('mysql'),
            'user' => wp_get_current_user()->user_login
        ];

        array_unshift($this->history, $entry);

        if (count($this->history) > $this->max_history) {
            array_pop($this->history);
        }
    }

    /**
     * Sort event listeners by priority
     *
     * @param string $event Event name
     */
    private function sort_listeners($event) {
        if (!isset($this->listeners[$event])) {
            return;
        }

        ksort($this->listeners[$event]);
    }

    /**
     * Sort wildcard listeners by priority
     *
     * @param string $pattern Event pattern
     */
    private function sort_wildcards($pattern) {
        if (!isset($this->wildcards[$pattern])) {
            return;
        }

        ksort($this->wildcards[$pattern]);
    }

    /**
     * Check if pattern matches event name
     *
     * @param string $pattern Event pattern
     * @param string $event Event name
     * @return bool Whether pattern matches
     */
    private function pattern_matches($pattern, $event) {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        return preg_match('/^' . $pattern . '$/', $event) === 1;
    }
}