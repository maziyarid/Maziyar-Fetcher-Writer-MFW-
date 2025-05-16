<?php
/**
 * Localization Handler Class
 * 
 * Manages internationalization and localization functionality.
 * Handles translations, text domains, and locale-specific formatting.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Localization_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:31:32';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Plugin text domain
     */
    private $text_domain = 'mfw';

    /**
     * Loaded translations
     */
    private $translations = [];

    /**
     * Current locale
     */
    private $current_locale;

    /**
     * Initialize localization handler
     */
    public function __construct() {
        $this->current_locale = get_locale();

        // Add hooks
        add_action('init', [$this, 'load_textdomain']);
        add_action('plugins_loaded', [$this, 'init_localization']);
        add_filter('locale', [$this, 'maybe_switch_locale']);
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            $this->text_domain,
            false,
            dirname(plugin_basename(MFW_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Initialize localization
     */
    public function init_localization() {
        // Load custom translations
        $this->load_custom_translations();

        // Set up locale-specific settings
        $this->setup_locale_settings();
    }

    /**
     * Get translated string
     *
     * @param string $text Text to translate
     * @param string $context Translation context
     * @param string $locale Specific locale (optional)
     * @return string Translated text
     */
    public function translate($text, $context = '', $locale = '') {
        try {
            // Use specific locale if provided
            $locale = $locale ?: $this->current_locale;

            // Try custom translations first
            if (isset($this->translations[$locale][$context][$text])) {
                return $this->translations[$locale][$context][$text];
            }

            // Fall back to WordPress translations
            return $context ? 
                   _x($text, $context, $this->text_domain) : 
                   __($text, $this->text_domain);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Translation failed: %s', $e->getMessage()),
                'localization_handler',
                'error'
            );
            return $text;
        }
    }

    /**
     * Get plural form
     *
     * @param string $single Single form
     * @param string $plural Plural form
     * @param int $number Number
     * @param string $context Translation context
     * @return string Appropriate form
     */
    public function translate_plural($single, $plural, $number, $context = '') {
        try {
            return $context ? 
                   _nx($single, $plural, $number, $context, $this->text_domain) : 
                   _n($single, $plural, $number, $this->text_domain);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Plural translation failed: %s', $e->getMessage()),
                'localization_handler',
                'error'
            );
            return $number === 1 ? $single : $plural;
        }
    }

    /**
     * Format number according to locale
     *
     * @param float $number Number to format
     * @param array $options Formatting options
     * @return string Formatted number
     */
    public function format_number($number, $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'decimals' => 2,
                'decimal_point' => $this->get_decimal_point(),
                'thousands_sep' => $this->get_thousands_separator()
            ]);

            return number_format(
                $number,
                $options['decimals'],
                $options['decimal_point'],
                $options['thousands_sep']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Number formatting failed: %s', $e->getMessage()),
                'localization_handler',
                'error'
            );
            return $number;
        }
    }

    /**
     * Format date according to locale
     *
     * @param string|int $date Date to format
     * @param string $format Format string
     * @return string Formatted date
     */
    public function format_date($date, $format = '') {
        try {
            if (empty($date)) {
                return '';
            }

            // Convert to timestamp
            $timestamp = is_numeric($date) ? $date : strtotime($date);
            if ($timestamp === false) {
                return '';
            }

            // Use locale-specific format if not specified
            if (empty($format)) {
                $format = $this->get_date_format();
            }

            return date_i18n($format, $timestamp);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Date formatting failed: %s', $e->getMessage()),
                'localization_handler',
                'error'
            );
            return $date;
        }
    }

    /**
     * Switch to different locale
     *
     * @param string $locale Locale to switch to
     * @return bool Success status
     */
    public function switch_locale($locale) {
        try {
            // Backup current locale
            $old_locale = get_locale();

            // Switch locale
            if (switch_to_locale($locale)) {
                $this->current_locale = $locale;
                
                // Reload text domain
                $this->load_textdomain();
                
                // Log switch
                $this->log_locale_switch($old_locale, $locale);
                
                return true;
            }

            return false;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Locale switch failed: %s', $e->getMessage()),
                'localization_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get available locales
     *
     * @return array List of available locales
     */
    public function get_available_locales() {
        try {
            $locales = [];

            // Get WordPress locales
            $wp_locales = get_available_languages();

            // Get custom locales
            $custom_locales = array_keys($this->translations);

            // Merge and remove duplicates
            $all_locales = array_unique(array_merge($wp_locales, $custom_locales));

            // Get locale information
            foreach ($all_locales as $locale) {
                $locales[$locale] = [
                    'code' => $locale,
                    'name' => $this->get_locale_name($locale),
                    'native_name' => $this->get_locale_native_name($locale),
                    'is_rtl' => $this->is_rtl($locale)
                ];
            }

            return $locales;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to get available locales: %s', $e->getMessage()),
                'localization_handler',
                'error'
            );
            return [];
        }
    }

    /**
     * Load custom translations
     */
    private function load_custom_translations() {
        try {
            // Get custom translations directory
            $translations_dir = MFW_PLUGIN_DIR . 'languages/custom/';
            
            if (!is_dir($translations_dir)) {
                return;
            }

            // Load each translation file
            $files = glob($translations_dir . '*.php');
            foreach ($files as $file) {
                $locale = basename($file, '.php');
                $translations = include $file;
                
                if (is_array($translations)) {
                    $this->translations[$locale] = $translations;
                }
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to load custom translations: %s', $e->getMessage()),
                'localization_handler',
                'error'
            );
        }
    }

    /**
     * Setup locale-specific settings
     */
    private function setup_locale_settings() {
        // Set text direction
        if ($this->is_rtl()) {
            global $wp_locale;
            $wp_locale->text_direction = 'rtl';
        }
    }

    /**
     * Get decimal point for current locale
     *
     * @return string Decimal point
     */
    private function get_decimal_point() {
        return localeconv()['decimal_point'] ?: '.';
    }

    /**
     * Get thousands separator for current locale
     *
     * @return string Thousands separator
     */
    private function get_thousands_separator() {
        return localeconv()['thousands_sep'] ?: ',';
    }

    /**
     * Get date format for current locale
     *
     * @return string Date format
     */
    private function get_date_format() {
        return get_option('date_format') ?: 'Y-m-d';
    }

    /**
     * Check if locale is RTL
     *
     * @param string $locale Locale to check
     * @return bool Is RTL
     */
    private function is_rtl($locale = '') {
        $locale = $locale ?: $this->current_locale;
        return in_array($locale, ['ar', 'he', 'fa']);
    }

    /**
     * Log locale switch
     *
     * @param string $old_locale Previous locale
     * @param string $new_locale New locale
     */
    private function log_locale_switch($old_locale, $new_locale) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_locale_log',
                [
                    'old_locale' => $old_locale,
                    'new_locale' => $new_locale,
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log locale switch: %s', $e->getMessage()),
                'localization_handler',
                'error'
            );
        }
    }
}