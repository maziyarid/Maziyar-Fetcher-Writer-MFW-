<?php
/**
 * Data Formatter Class
 * 
 * Handles data formatting, sanitization, and transformation.
 * Supports various data types and format specifications.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Data_Formatter {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:07:09';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Date format settings
     */
    private $date_format;
    private $time_format;
    private $datetime_format;

    /**
     * Number format settings
     */
    private $decimal_separator;
    private $thousand_separator;
    private $decimal_places;

    /**
     * Initialize formatter
     */
    public function __construct() {
        // Load format settings
        $this->load_settings();

        // Add filters for format customization
        add_filter('mfw_format_date', [$this, 'format_date'], 10, 2);
        add_filter('mfw_format_number', [$this, 'format_number'], 10, 2);
        add_filter('mfw_format_currency', [$this, 'format_currency'], 10, 3);
    }

    /**
     * Format date
     *
     * @param string|int $date Date to format
     * @param array $options Format options
     * @return string Formatted date
     */
    public function format_date($date, $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'format' => $this->date_format,
                'type' => 'date',
                'timezone' => get_option('timezone_string', 'UTC'),
                'relative' => false
            ]);

            // Convert to timestamp
            if (is_numeric($date)) {
                $timestamp = $date;
            } else {
                $timestamp = strtotime($date);
            }

            if ($timestamp === false) {
                throw new Exception(__('Invalid date format', 'mfw'));
            }

            // Handle relative dates
            if ($options['relative']) {
                return $this->get_relative_date($timestamp);
            }

            // Set format based on type
            switch ($options['type']) {
                case 'time':
                    $format = $this->time_format;
                    break;
                case 'datetime':
                    $format = $this->datetime_format;
                    break;
                default:
                    $format = $options['format'];
            }

            // Create DateTime object
            $date_obj = new DateTime();
            $date_obj->setTimestamp($timestamp);
            $date_obj->setTimezone(new DateTimeZone($options['timezone']));

            return $date_obj->format($format);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Date formatting failed: %s', $e->getMessage()),
                'data_formatter',
                'error'
            );
            return '';
        }
    }

    /**
     * Format number
     *
     * @param float|int $number Number to format
     * @param array $options Format options
     * @return string Formatted number
     */
    public function format_number($number, $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'decimals' => $this->decimal_places,
                'dec_point' => $this->decimal_separator,
                'thousands_sep' => $this->thousand_separator,
                'pad_zeros' => false
            ]);

            // Format number
            $formatted = number_format(
                (float)$number,
                $options['decimals'],
                $options['dec_point'],
                $options['thousands_sep']
            );

            // Pad with zeros if needed
            if ($options['pad_zeros']) {
                $parts = explode($options['dec_point'], $formatted);
                if (isset($parts[1])) {
                    $parts[1] = str_pad($parts[1], $options['decimals'], '0');
                    $formatted = implode($options['dec_point'], $parts);
                }
            }

            return $formatted;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Number formatting failed: %s', $e->getMessage()),
                'data_formatter',
                'error'
            );
            return '';
        }
    }

    /**
     * Format currency
     *
     * @param float|int $amount Amount to format
     * @param string $currency Currency code
     * @param array $options Format options
     * @return string Formatted currency
     */
    public function format_currency($amount, $currency = 'USD', $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'symbol' => $this->get_currency_symbol($currency),
                'position' => 'left',
                'decimals' => 2,
                'dec_point' => $this->decimal_separator,
                'thousands_sep' => $this->thousand_separator
            ]);

            // Format number
            $formatted = $this->format_number($amount, [
                'decimals' => $options['decimals'],
                'dec_point' => $options['dec_point'],
                'thousands_sep' => $options['thousands_sep'],
                'pad_zeros' => true
            ]);

            // Add currency symbol
            switch ($options['position']) {
                case 'left':
                    return $options['symbol'] . $formatted;
                case 'right':
                    return $formatted . $options['symbol'];
                case 'left_space':
                    return $options['symbol'] . ' ' . $formatted;
                case 'right_space':
                    return $formatted . ' ' . $options['symbol'];
                default:
                    return $options['symbol'] . $formatted;
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Currency formatting failed: %s', $e->getMessage()),
                'data_formatter',
                'error'
            );
            return '';
        }
    }

    /**
     * Format file size
     *
     * @param int $bytes Size in bytes
     * @param array $options Format options
     * @return string Formatted file size
     */
    public function format_filesize($bytes, $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'decimals' => 2,
                'separator' => ' '
            ]);

            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);

            $bytes /= pow(1024, $pow);

            return $this->format_number($bytes, [
                'decimals' => $options['decimals']
            ]) . $options['separator'] . $units[$pow];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('File size formatting failed: %s', $e->getMessage()),
                'data_formatter',
                'error'
            );
            return '';
        }
    }

    /**
     * Format percentage
     *
     * @param float|int $number Number to format
     * @param array $options Format options
     * @return string Formatted percentage
     */
    public function format_percentage($number, $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'decimals' => 1,
                'symbol' => '%',
                'space' => false
            ]);

            $formatted = $this->format_number($number, [
                'decimals' => $options['decimals']
            ]);

            return $formatted . ($options['space'] ? ' ' : '') . $options['symbol'];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Percentage formatting failed: %s', $e->getMessage()),
                'data_formatter',
                'error'
            );
            return '';
        }
    }

    /**
     * Get relative date
     *
     * @param int $timestamp Timestamp
     * @return string Relative date string
     */
    private function get_relative_date($timestamp) {
        $current = strtotime($this->current_time);
        $diff = $current - $timestamp;

        if ($diff < 60) {
            return __('just now', 'mfw');
        }

        $intervals = [
            31536000 => __('year', 'mfw'),
            2592000 => __('month', 'mfw'),
            604800 => __('week', 'mfw'),
            86400 => __('day', 'mfw'),
            3600 => __('hour', 'mfw'),
            60 => __('minute', 'mfw')
        ];

        foreach ($intervals as $seconds => $label) {
            $count = floor($diff / $seconds);
            if ($count > 0) {
                if ($count == 1) {
                    return sprintf(__('%s ago', 'mfw'), $label);
                } else {
                    return sprintf(__('%d %ss ago', 'mfw'), $count, $label);
                }
            }
        }

        return __('just now', 'mfw');
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
            'AUD' => 'A$',
            'CAD' => 'C$',
            // Add more currencies as needed
        ];

        return $symbols[$currency] ?? $currency;
    }

    /**
     * Load format settings
     */
    private function load_settings() {
        // Get WordPress date/time settings
        $this->date_format = get_option('date_format', 'Y-m-d');
        $this->time_format = get_option('time_format', 'H:i:s');
        $this->datetime_format = $this->date_format . ' ' . $this->time_format;

        // Get number format settings
        $this->decimal_separator = get_option('mfw_decimal_separator', '.');
        $this->thousand_separator = get_option('mfw_thousand_separator', ',');
        $this->decimal_places = get_option('mfw_decimal_places', 2);
    }
}