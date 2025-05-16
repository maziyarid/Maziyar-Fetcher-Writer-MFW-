<?php
/**
 * Date Helper Class
 * 
 * Provides utility methods for date and time manipulation.
 * Handles common date operations with WordPress timezone support.
 *
 * @package MFW
 * @subpackage Helpers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Date_Helper {
    /**
     * Last operation timestamp
     *
     * @var string
     */
    private static $last_operation = '2025-05-14 07:19:45';

    /**
     * Last operator
     *
     * @var string
     */
    private static $last_operator = 'maziyarid';

    /**
     * WordPress timezone string
     *
     * @var string
     */
    private static $timezone = null;

    /**
     * Date formats
     *
     * @var array
     */
    private static $formats = [
        'datetime' => 'Y-m-d H:i:s',
        'date' => 'Y-m-d',
        'time' => 'H:i:s',
        'mysql' => 'Y-m-d H:i:s',
        'timestamp' => 'U',
        'rfc' => 'r',
        'iso8601' => 'c',
    ];

    /**
     * Initialize timezone
     */
    private static function init_timezone() {
        if (is_null(self::$timezone)) {
            self::$timezone = wp_timezone_string();
        }
    }

    /**
     * Create DateTime object
     *
     * @param string|int|\DateTime $date Date string, timestamp, or DateTime object
     * @param string|null $timezone Timezone string
     * @return \DateTime DateTime object
     */
    public static function create($date = 'now', $timezone = null) {
        self::init_timezone();

        if ($date instanceof \DateTime) {
            return clone $date;
        }

        try {
            if (is_numeric($date)) {
                $datetime = new \DateTime();
                $datetime->setTimestamp($date);
            } else {
                $datetime = new \DateTime($date);
            }

            $timezone = new \DateTimeZone($timezone ?? self::$timezone);
            $datetime->setTimezone($timezone);

            return $datetime;

        } catch (\Exception $e) {
            return new \DateTime('now', new \DateTimeZone(self::$timezone));
        }
    }

    /**
     * Format date
     *
     * @param mixed $date Date to format
     * @param string $format Date format
     * @return string Formatted date
     */
    public static function format($date, $format = 'datetime') {
        $datetime = self::create($date);
        $format = self::$formats[$format] ?? $format;
        return $datetime->format($format);
    }

    /**
     * Convert date to MySQL format
     *
     * @param mixed $date Date to convert
     * @return string MySQL formatted date
     */
    public static function to_mysql($date) {
        return self::format($date, 'mysql');
    }

    /**
     * Convert date to timestamp
     *
     * @param mixed $date Date to convert
     * @return int Unix timestamp
     */
    public static function to_timestamp($date) {
        return (int) self::format($date, 'timestamp');
    }

    /**
     * Get current date/time
     *
     * @param string $format Date format
     * @return string Current date/time
     */
    public static function now($format = 'datetime') {
        return self::format('now', $format);
    }

    /**
     * Add interval to date
     *
     * @param mixed $date Base date
     * @param string $interval Interval specification
     * @param string $format Return format
     * @return string Modified date
     */
    public static function add($date, $interval, $format = 'datetime') {
        $datetime = self::create($date);
        $datetime->modify('+' . $interval);
        return self::format($datetime, $format);
    }

    /**
     * Subtract interval from date
     *
     * @param mixed $date Base date
     * @param string $interval Interval specification
     * @param string $format Return format
     * @return string Modified date
     */
    public static function sub($date, $interval, $format = 'datetime') {
        $datetime = self::create($date);
        $datetime->modify('-' . $interval);
        return self::format($datetime, $format);
    }

    /**
     * Get difference between dates
     *
     * @param mixed $date1 First date
     * @param mixed $date2 Second date
     * @param string $unit Difference unit (seconds|minutes|hours|days|months|years)
     * @return int Time difference
     */
    public static function diff($date1, $date2, $unit = 'seconds') {
        $datetime1 = self::create($date1);
        $datetime2 = self::create($date2);
        $interval = $datetime1->diff($datetime2);

        switch ($unit) {
            case 'minutes':
                return ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
            case 'hours':
                return ($interval->days * 24) + $interval->h;
            case 'days':
                return $interval->days;
            case 'months':
                return ($interval->y * 12) + $interval->m;
            case 'years':
                return $interval->y;
            default: // seconds
                return ($interval->days * 24 * 60 * 60) + ($interval->h * 60 * 60) + ($interval->i * 60) + $interval->s;
        }
    }

    /**
     * Check if date is in the past
     *
     * @param mixed $date Date to check
     * @return bool Whether date is in the past
     */
    public static function is_past($date) {
        return self::to_timestamp($date) < self::to_timestamp('now');
    }

    /**
     * Check if date is in the future
     *
     * @param mixed $date Date to check
     * @return bool Whether date is in the future
     */
    public static function is_future($date) {
        return self::to_timestamp($date) > self::to_timestamp('now');
    }

    /**
     * Get start of period
     *
     * @param mixed $date Base date
     * @param string $period Period (day|week|month|year)
     * @param string $format Return format
     * @return string Period start date
     */
    public static function start_of($date, $period = 'day', $format = 'datetime') {
        $datetime = self::create($date);

        switch ($period) {
            case 'week':
                $datetime->modify('monday this week');
                break;
            case 'month':
                $datetime->modify('first day of this month');
                break;
            case 'year':
                $datetime->modify('first day of january this year');
                break;
            default: // day
                $datetime->setTime(0, 0, 0);
        }

        return self::format($datetime, $format);
    }

    /**
     * Get end of period
     *
     * @param mixed $date Base date
     * @param string $period Period (day|week|month|year)
     * @param string $format Return format
     * @return string Period end date
     */
    public static function end_of($date, $period = 'day', $format = 'datetime') {
        $datetime = self::create($date);

        switch ($period) {
            case 'week':
                $datetime->modify('sunday this week');
                break;
            case 'month':
                $datetime->modify('last day of this month');
                break;
            case 'year':
                $datetime->modify('last day of december this year');
                break;
            default: // day
                $datetime->setTime(23, 59, 59);
        }

        return self::format($datetime, $format);
    }

    /**
     * Format date for humans
     *
     * @param mixed $date Date to format
     * @param bool $full Whether to show full date
     * @return string Formatted date
     */
    public static function for_humans($date, $full = false) {
        $now = new \DateTime('now', new \DateTimeZone(self::$timezone));
        $datetime = self::create($date);
        $diff = $now->diff($datetime);

        if ($diff->days == 0) {
            if ($diff->h == 0) {
                if ($diff->i == 0) {
                    return 'just now';
                }
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            }
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }

        if ($diff->days == 1) {
            return 'yesterday';
        }

        if ($diff->days < 7) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        }

        if (!$full) {
            if ($diff->y == 0) {
                return $datetime->format('F j');
            }
            return $datetime->format('F j, Y');
        }

        return $datetime->format('F j, Y \a\t g:i A');
    }
}