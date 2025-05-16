<?php
/**
 * RateLimiter - API Rate Limiting System
 * Manages API request rates and limits
 * 
 * @package MFW
 * @subpackage AI
 * @since 1.0.0
 */

namespace MFW\AI\AIService;

class RateLimiter {
    private $cache;
    private $settings;
    private $prefix = 'mfw_rate_limit_';

    /**
     * Initialize the rate limiter
     */
    public function __construct() {
        $this->cache = new \MFW\AI\AICache();
        $this->settings = get_option('mfw_rate_limit_settings', [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'requests_per_day' => 10000,
            'cooldown_period' => 300 // 5 minutes
        ]);
    }

    /**
     * Check if request is allowed
     */
    public function can_make_request($user_id) {
        try {
            $minute_key = $this->prefix . 'minute_' . $user_id;
            $hour_key = $this->prefix . 'hour_' . $user_id;
            $day_key = $this->prefix . 'day_' . $user_id;

            // Check minute limit
            if (!$this->check_limit($minute_key, $this->settings['requests_per_minute'], 60)) {
                return false;
            }

            // Check hour limit
            if (!$this->check_limit($hour_key, $this->settings['requests_per_hour'], 3600)) {
                return false;
            }

            // Check day limit
            if (!$this->check_limit($day_key, $this->settings['requests_per_day'], 86400)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->log_error('Rate limit check failed', $e);
            return false;
        }
    }

    /**
     * Record an API request
     */
    public function record_request($user_id) {
        try {
            $minute_key = $this->prefix . 'minute_' . $user_id;
            $hour_key = $this->prefix . 'hour_' . $user_id;
            $day_key = $this->prefix . 'day_' . $user_id;

            $this->increment_counter($minute_key, 60);
            $this->increment_counter($hour_key, 3600);
            $this->increment_counter($day_key, 86400);

            return true;
        } catch (\Exception $e) {
            $this->log_error('Request recording failed', $e);
            return false;
        }
    }

    /**
     * Get remaining requests
     */
    public function get_remaining_requests($user_id) {
        try {
            return [
                'per_minute' => $this->get_remaining($user_id, 'minute', $this->settings['requests_per_minute']),
                'per_hour' => $this->get_remaining($user_id, 'hour', $this->settings['requests_per_hour']),
                'per_day' => $this->get_remaining($user_id, 'day', $this->settings['requests_per_day'])
            ];
        } catch (\Exception $e) {
            $this->log_error('Remaining requests check failed', $e);
            return [
                'per_minute' => 0,
                'per_hour' => 0,
                'per_day' => 0
            ];
        }
    }

    /**
     * Check if user is in cooldown
     */
    public function is_in_cooldown($user_id) {
        $cooldown_key = $this->prefix . 'cooldown_' . $user_id;
        return (bool) $this->cache->get($cooldown_key);
    }

    /**
     * Set user in cooldown
     */
    public function set_cooldown($user_id) {
        $cooldown_key = $this->prefix . 'cooldown_' . $user_id;
        return $this->cache->set($cooldown_key, true, $this->settings['cooldown_period']);
    }

    /**
     * Check rate limit
     */
    private function check_limit($key, $limit, $window) {
        $current = $this->cache->get($key) ?: 0;
        return $current < $limit;
    }

    /**
     * Increment request counter
     */
    private function increment_counter($key, $window) {
        $current = $this->cache->get($key) ?: 0;
        return $this->cache->set($key, $current + 1, $window);
    }

    /**
     * Get remaining requests for period
     */
    private function get_remaining($user_id, $period, $limit) {
        $key = $this->prefix . $period . '_' . $user_id;
        $current = $this->cache->get($key) ?: 0;
        return max(0, $limit - $current);
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW RateLimiter Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}