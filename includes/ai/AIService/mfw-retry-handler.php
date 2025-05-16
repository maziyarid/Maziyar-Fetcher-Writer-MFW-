<?php
/**
 * RetryHandler - API Retry Management System
 * Handles retry logic for failed API requests
 * 
 * @package MFW
 * @subpackage AI
 * @since 1.0.0
 */

namespace MFW\AI\AIService;

class RetryHandler {
    private $settings;
    private $cache;

    /**
     * Initialize the retry handler
     */
    public function __construct() {
        $this->cache = new \MFW\AI\AICache();
        $this->settings = get_option('mfw_retry_settings', [
            'max_retries' => 3,
            'initial_delay' => 1000, // milliseconds
            'max_delay' => 8000,     // milliseconds
            'backoff_factor' => 2
        ]);
    }

    /**
     * Execute with retry
     */
    public function execute_with_retry(callable $callback) {
        $attempts = 0;
        $last_error = null;

        while ($attempts < $this->settings['max_retries']) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $last_error = $e;
                $attempts++;

                if (!$this->should_retry($e, $attempts)) {
                    break;
                }

                $this->wait($attempts);
            }
        }

        throw new \Exception(
            'Max retry attempts reached: ' . $last_error->getMessage(),
            $last_error->getCode()
        );
    }

    /**
     * Check if should retry
     */
    private function should_retry(\Exception $e, $attempts) {
        // Always retry on network errors
        if ($e instanceof \GuzzleHttp\Exception\ConnectException) {
            return true;
        }

        // Retry on rate limits
        if ($e instanceof \GuzzleHttp\Exception\ClientException && $e->getCode() === 429) {
            return true;
        }

        // Retry on server errors
        if ($e instanceof \GuzzleHttp\Exception\ServerException) {
            return true;
        }

        return false;
    }

    /**
     * Calculate wait time with exponential backoff
     */
    private function wait($attempt) {
        $delay = min(
            $this->settings['initial_delay'] * pow($this->settings['backoff_factor'], $attempt - 1),
            $this->settings['max_delay']
        );

        usleep($delay * 1000); // Convert to microseconds
    }

    /**
     * Log errors for monitoring
     */
    private function log_error($message, $exception) {
        error_log(sprintf(
            '[MFW RetryHandler Error] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }
}