<?php
/**
 * String Helper Class
 * 
 * Provides utility methods for string manipulation.
 * Handles common string operations with additional functionality.
 *
 * @package MFW
 * @subpackage Helpers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_String_Helper {
    /**
     * Last operation timestamp
     *
     * @var string
     */
    private static $last_operation = '2025-05-14 07:18:31';

    /**
     * Last operator
     *
     * @var string
     */
    private static $last_operator = 'maziyarid';

    /**
     * Generate URL friendly slug
     *
     * @param string $string String to slugify
     * @param string $separator Word separator
     * @return string Slugified string
     */
    public static function slug($string, $separator = '-') {
        // Convert to lowercase
        $string = mb_strtolower($string);

        // Remove accents
        $string = remove_accents($string);

        // Replace non-alphanumeric characters with separator
        $string = preg_replace('/[^\p{L}\p{Nd}]+/u', $separator, $string);

        // Remove duplicate separators
        $string = preg_replace('/[' . preg_quote($separator, '/') . ']+/', $separator, $string);

        // Trim separators from ends
        return trim($string, $separator);
    }

    /**
     * Generate random string
     *
     * @param int $length String length
     * @param string $type Character type (alpha|numeric|alphanumeric|special)
     * @return string Random string
     */
    public static function random($length = 16, $type = 'alphanumeric') {
        $chars = [];

        switch ($type) {
            case 'alpha':
                $chars = array_merge(range('a', 'z'), range('A', 'Z'));
                break;
            case 'numeric':
                $chars = range('0', '9');
                break;
            case 'special':
                $chars = array_merge(
                    range('a', 'z'),
                    range('A', 'Z'),
                    range('0', '9'),
                    str_split('!@#$%^&*()_+-=[]{}|;:,.<>?')
                );
                break;
            default: // alphanumeric
                $chars = array_merge(
                    range('a', 'z'),
                    range('A', 'Z'),
                    range('0', '9')
                );
        }

        $string = '';
        $max = count($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $string .= $chars[random_int(0, $max)];
        }

        return $string;
    }

    /**
     * Convert string to camelCase
     *
     * @param string $string String to convert
     * @return string Converted string
     */
    public static function camel($string) {
        $string = ucwords(str_replace(['-', '_'], ' ', $string));
        $string = str_replace(' ', '', $string);
        return lcfirst($string);
    }

    /**
     * Convert string to PascalCase
     *
     * @param string $string String to convert
     * @return string Converted string
     */
    public static function pascal($string) {
        return ucfirst(self::camel($string));
    }

    /**
     * Convert string to snake_case
     *
     * @param string $string String to convert
     * @return string Converted string
     */
    public static function snake($string) {
        $string = preg_replace('/\s+/u', '', ucwords($string));
        $string = preg_replace('/(.)(?=[A-Z])/u', '$1_', $string);
        return mb_strtolower($string);
    }

    /**
     * Convert string to kebab-case
     *
     * @param string $string String to convert
     * @return string Converted string
     */
    public static function kebab($string) {
        return str_replace('_', '-', self::snake($string));
    }

    /**
     * Truncate string to specified length
     *
     * @param string $string String to truncate
     * @param int $length Maximum length
     * @param string $append String to append if truncated
     * @return string Truncated string
     */
    public static function truncate($string, $length, $append = '...') {
        if (mb_strlen($string) <= $length) {
            return $string;
        }

        $truncated = mb_substr($string, 0, $length);
        return rtrim($truncated) . $append;
    }

    /**
     * Extract excerpt from text
     *
     * @param string $text Text to extract from
     * @param int $length Maximum length
     * @param string $append String to append if truncated
     * @return string Extracted excerpt
     */
    public static function excerpt($text, $length = 55, $append = '...') {
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        return self::truncate($text, $length, $append);
    }

    /**
     * Highlight text within string
     *
     * @param string $string String to search in
     * @param string $text Text to highlight
     * @param string $tag HTML tag to wrap highlight in
     * @param array $attributes Tag attributes
     * @return string String with highlighted text
     */
    public static function highlight($string, $text, $tag = 'mark', $attributes = []) {
        if (empty($text)) {
            return $string;
        }

        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= ' ' . $key . '="' . esc_attr($value) . '"';
        }

        return preg_replace(
            '/(' . preg_quote($text, '/') . ')/i',
            '<' . $tag . $attrs . '>$1</' . $tag . '>',
            $string
        );
    }

    /**
     * Generate initials from string
     *
     * @param string $string String to generate from
     * @param int $length Maximum length
     * @return string Generated initials
     */
    public static function initials($string, $length = 2) {
        $words = explode(' ', $string);
        $initials = '';

        foreach ($words as $word) {
            if (strlen($initials) >= $length) {
                break;
            }
            if ($word) {
                $initials .= mb_substr($word, 0, 1);
            }
        }

        return mb_strtoupper($initials);
    }

    /**
     * Clean string for usage in file names
     *
     * @param string $string String to clean
     * @return string Cleaned string
     */
    public static function clean_filename($string) {
        $string = str_replace(['/', '\\'], '-', $string);
        $string = preg_replace('/[^a-zA-Z0-9-_.]/', '', $string);
        return $string;
    }

    /**
     * Format file size
     *
     * @param int $size Size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    public static function format_size($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return round($size, $precision) . ' ' . $units[$index];
    }

    /**
     * Check if string contains substring
     *
     * @param string $haystack String to search in
     * @param string|array $needle String(s) to search for
     * @param bool $case_sensitive Whether search is case sensitive
     * @return bool Whether string contains substring
     */
    public static function contains($haystack, $needle, $case_sensitive = false) {
        if (!$case_sensitive) {
            $haystack = mb_strtolower($haystack);
        }

        foreach ((array) $needle as $n) {
            if (!$case_sensitive) {
                $n = mb_strtolower($n);
            }
            if (mb_strpos($haystack, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string starts with substring
     *
     * @param string $haystack String to search in
     * @param string|array $needle String(s) to search for
     * @param bool $case_sensitive Whether search is case sensitive
     * @return bool Whether string starts with substring
     */
    public static function starts_with($haystack, $needle, $case_sensitive = false) {
        if (!$case_sensitive) {
            $haystack = mb_strtolower($haystack);
        }

        foreach ((array) $needle as $n) {
            if (!$case_sensitive) {
                $n = mb_strtolower($n);
            }
            if (mb_strpos($haystack, $n) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string ends with substring
     *
     * @param string $haystack String to search in
     * @param string|array $needle String(s) to search for
     * @param bool $case_sensitive Whether search is case sensitive
     * @return bool Whether string ends with substring
     */
    public static function ends_with($haystack, $needle, $case_sensitive = false) {
        if (!$case_sensitive) {
            $haystack = mb_strtolower($haystack);
        }

        foreach ((array) $needle as $n) {
            if (!$case_sensitive) {
                $n = mb_strtolower($n);
            }
            if (mb_substr($haystack, -mb_strlen($n)) === $n) {
                return true;
            }
        }

        return false;
    }
}