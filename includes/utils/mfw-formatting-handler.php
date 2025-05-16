<?php
/**
 * Formatting Handler Class
 * 
 * Manages data formatting and presentation.
 * Provides methods for consistent data display across the plugin.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Formatting_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:30:21';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Date format
     */
    private $date_format;

    /**
     * Time format
     */
    private $time_format;

    /**
     * Number format settings
     */
    private $number_format;

    /**
     * Initialize formatting handler
     */
    public function __construct() {
        // Load settings
        $this->date_format = get_option('mfw_date_format', 'Y-m-d');
        $this->time_format = get_option('mfw_time_format', 'H:i:s');
        $this->number_format = [
            'decimals' => get_option('mfw_number_decimals', 2),
            'decimal_sep' => get_option('mfw_decimal_separator', '.'),
            'thousands_sep' => get_option('mfw_thousands_separator', ',')
        ];
    }

    /**
     * Format date
     *
     * @param string|int $date Date to format
     * @param string $format Custom format (optional)
     * @return string Formatted date
     */
    public function format_date($date, $format = '') {
        if (empty($date)) {
            return '';
        }

        try {
            if (is_numeric($date)) {
                $timestamp = $date;
            } else {
                $timestamp = strtotime($date);
            }

            if ($timestamp === false) {
                return '';
            }

            return date($format ?: $this->date_format, $timestamp);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Date formatting failed: %s', $e->getMessage()),
                'formatting_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Format datetime
     *
     * @param string|int $datetime Datetime to format
     * @param string $format Custom format (optional)
     * @return string Formatted datetime
     */
    public function format_datetime($datetime, $format = '') {
        if (empty($datetime)) {
            return '';
        }

        try {
            if (is_numeric($datetime)) {
                $timestamp = $datetime;
            } else {
                $timestamp = strtotime($datetime);
            }

            if ($timestamp === false) {
                return '';
            }

            $format = $format ?: $this->date_format . ' ' . $this->time_format;
            return date($format, $timestamp);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Datetime formatting failed: %s', $e->getMessage()),
                'formatting_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Format number
     *
     * @param float $number Number to format
     * @param int $decimals Number of decimal places
     * @return string Formatted number
     */
    public function format_number($number, $decimals = null) {
        if (!is_numeric($number)) {
            return '';
        }

        try {
            $decimals = $decimals ?? $this->number_format['decimals'];
            return number_format(
                $number,
                $decimals,
                $this->number_format['decimal_sep'],
                $this->number_format['thousands_sep']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Number formatting failed: %s', $e->getMessage()),
                'formatting_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Format currency
     *
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @param array $options Formatting options
     * @return string Formatted currency
     */
    public function format_currency($amount, $currency = 'USD', $options = []) {
        if (!is_numeric($amount)) {
            return '';
        }

        try {
            // Parse options
            $options = wp_parse_args($options, [
                'decimals' => 2,
                'symbol_position' => 'before',
                'space_between' => false
            ]);

            // Get currency symbol
            $symbol = $this->get_currency_symbol($currency);

            // Format amount
            $formatted = $this->format_number($amount, $options['decimals']);

            // Add symbol
            $space = $options['space_between'] ? ' ' : '';
            return $options['symbol_position'] === 'before' ? 
                   $symbol . $space . $formatted : 
                   $formatted . $space . $symbol;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Currency formatting failed: %s', $e->getMessage()),
                'formatting_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Format file size
     *
     * @param int $bytes Size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted file size
     */
    public function format_filesize($bytes, $precision = 2) {
        if (!is_numeric($bytes)) {
            return '';
        }

        try {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];

            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);

            $bytes /= (1 << (10 * $pow));

            return $this->format_number($bytes, $precision) . ' ' . $units[$pow];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('File size formatting failed: %s', $e->getMessage()),
                'formatting_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Format percentage
     *
     * @param float $value Value to format
     * @param int $decimals Number of decimal places
     * @return string Formatted percentage
     */
    public function format_percentage($value, $decimals = 1) {
        if (!is_numeric($value)) {
            return '';
        }

        try {
            return $this->format_number($value, $decimals) . '%';

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Percentage formatting failed: %s', $e->getMessage()),
                'formatting_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Format time duration
     *
     * @param int $seconds Duration in seconds
     * @param bool $short Use short format
     * @return string Formatted duration
     */
    public function format_duration($seconds, $short = false) {
        if (!is_numeric($seconds)) {
            return '';
        }

        try {
            $seconds = abs($seconds);
            $units = [
                'year' => 31536000,
                'month' => 2592000,
                'week' => 604800,
                'day' => 86400,
                'hour' => 3600,
                'minute' => 60,
                'second' => 1
            ];

            $parts = [];
            foreach ($units as $unit => $value) {
                if ($seconds >= $value) {
                    $count = floor($seconds / $value);
                    $seconds %= $value;
                    
                    if ($short) {
                        $unit = substr($unit, 0, 1);
                        $parts[] = $count . $unit;
                    } else {
                        $unit = _n($unit, $unit . 's', $count, 'mfw');
                        $parts[] = $count . ' ' . $unit;
                    }
                }
            }

            return implode($short ? ' ' : ', ', $parts);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Duration formatting failed: %s', $e->getMessage()),
                'formatting_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Format text excerpt
     *
     * @param string $text Text to excerpt
     * @param int $length Maximum length
     * @param string $more More text
     * @return string Formatted excerpt
     */
    public function format_excerpt($text, $length = 55, $more = '...') {
        try {
            if (empty($text)) {
                return '';
            }

            // Strip HTML and shortcodes
            $text = strip_shortcodes($text);
            $text = strip_tags($text);

            // Trim to length
            if (strlen($text) > $length) {
                $text = substr($text, 0, $length);
                $text = substr($text, 0, strrpos($text, ' '));
                $text .= $more;
            }

            return $text;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Excerpt formatting failed: %s', $e->getMessage()),
                'formatting_handler',
                'error'
            );
            return '';
        }
    }

    /**
     * Get currency symbol
     *
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    private function get_currency_symbol($currency) {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
            'INR' => '₹'
        ];

        return $symbols[$currency] ?? $currency;
    }
}