<?php
/**
 * Language Handler Class
 * 
 * Manages multilingual content, translations, and language switching.
 * Supports integration with WPML and Polylang.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Language_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:57:55';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Active translation plugin
     */
    private $translation_plugin = null;

    /**
     * Available languages
     */
    private $languages = [];

    /**
     * Default language
     */
    private $default_language = '';

    /**
     * Current language
     */
    private $current_language = '';

    /**
     * Translation cache
     */
    private $translation_cache = [];

    /**
     * Initialize language handler
     */
    public function __construct() {
        // Detect active translation plugin
        $this->detect_translation_plugin();

        // Load languages
        $this->load_languages();

        // Set default and current language
        $this->default_language = $this->get_default_language();
        $this->current_language = $this->get_current_language();

        // Add hooks
        add_filter('mfw_translate_string', [$this, 'translate_string'], 10, 4);
        add_filter('mfw_translate_post', [$this, 'translate_post'], 10, 2);
        add_action('mfw_register_strings', [$this, 'register_strings']);
    }

    /**
     * Translate string
     *
     * @param string $string String to translate
     * @param string $context Translation context
     * @param string $language Target language (optional)
     * @param bool $register Whether to register the string for translation
     * @return string Translated string
     */
    public function translate_string($string, $context = '', $language = '', $register = false) {
        try {
            // Use current language if none specified
            $language = $language ?: $this->current_language;

            // Return original if it's the default language
            if ($language === $this->default_language) {
                return $string;
            }

            // Check cache first
            $cache_key = md5($string . $context . $language);
            if (isset($this->translation_cache[$cache_key])) {
                return $this->translation_cache[$cache_key];
            }

            // Register string if required
            if ($register) {
                $this->register_string($string, $context);
            }

            // Get translation based on active plugin
            $translation = '';
            switch ($this->translation_plugin) {
                case 'wpml':
                    $translation = apply_filters(
                        'wpml_translate_single_string',
                        $string,
                        'mfw',
                        $context,
                        $language
                    );
                    break;

                case 'polylang':
                    if (function_exists('pll__')) {
                        $translation = pll__($string);
                    }
                    break;

                default:
                    // No translation plugin active
                    $translation = $string;
            }

            // Cache translation
            $this->translation_cache[$cache_key] = $translation ?: $string;

            return $this->translation_cache[$cache_key];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Translation failed: %s', $e->getMessage()),
                'language_handler',
                'error'
            );
            return $string;
        }
    }

    /**
     * Translate post
     *
     * @param int|WP_Post $post Post ID or object
     * @param string $language Target language (optional)
     * @return int|null Translated post ID or null if not found
     */
    public function translate_post($post, $language = '') {
        try {
            $post_id = is_object($post) ? $post->ID : $post;
            $language = $language ?: $this->current_language;

            // Return original if it's the default language
            if ($language === $this->default_language) {
                return $post_id;
            }

            switch ($this->translation_plugin) {
                case 'wpml':
                    return apply_filters(
                        'wpml_object_id',
                        $post_id,
                        get_post_type($post_id),
                        true,
                        $language
                    );

                case 'polylang':
                    if (function_exists('pll_get_post')) {
                        return pll_get_post($post_id, $language);
                    }
                    break;
            }

            return $post_id;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Post translation failed: %s', $e->getMessage()),
                'language_handler',
                'error'
            );
            return null;
        }
    }

    /**
     * Register strings for translation
     *
     * @param array $strings Strings to register
     * @return bool Success status
     */
    public function register_strings($strings) {
        try {
            foreach ($strings as $context => $group) {
                foreach ($group as $string) {
                    $this->register_string($string, $context);
                }
            }
            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to register strings: %s', $e->getMessage()),
                'language_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get available languages
     *
     * @return array Available languages
     */
    public function get_languages() {
        return $this->languages;
    }

    /**
     * Get current language
     *
     * @return string Current language code
     */
    public function get_current_language() {
        if ($this->current_language) {
            return $this->current_language;
        }

        switch ($this->translation_plugin) {
            case 'wpml':
                return apply_filters('wpml_current_language', null);

            case 'polylang':
                if (function_exists('pll_current_language')) {
                    return pll_current_language();
                }
                break;
        }

        return get_locale();
    }

    /**
     * Get default language
     *
     * @return string Default language code
     */
    public function get_default_language() {
        switch ($this->translation_plugin) {
            case 'wpml':
                return apply_filters('wpml_default_language', null);

            case 'polylang':
                if (function_exists('pll_default_language')) {
                    return pll_default_language();
                }
                break;
        }

        return get_locale();
    }

    /**
     * Check if string is translated
     *
     * @param string $string String to check
     * @param string $context Translation context
     * @param string $language Target language
     * @return bool Whether string is translated
     */
    public function is_translated($string, $context, $language) {
        try {
            switch ($this->translation_plugin) {
                case 'wpml':
                    $translated = apply_filters(
                        'wpml_translate_single_string',
                        $string,
                        'mfw',
                        $context,
                        $language
                    );
                    return $translated !== $string;

                case 'polylang':
                    // Polylang doesn't provide direct way to check
                    return true;

                default:
                    return false;
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Translation check failed: %s', $e->getMessage()),
                'language_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Detect active translation plugin
     */
    private function detect_translation_plugin() {
        if (defined('ICL_SITEPRESS_VERSION')) {
            $this->translation_plugin = 'wpml';
        } elseif (defined('POLYLANG_VERSION')) {
            $this->translation_plugin = 'polylang';
        }
    }

    /**
     * Load available languages
     */
    private function load_languages() {
        try {
            switch ($this->translation_plugin) {
                case 'wpml':
                    $this->languages = apply_filters('wpml_active_languages', null);
                    break;

                case 'polylang':
                    if (function_exists('pll_languages_list')) {
                        $this->languages = pll_languages_list(['fields' => 'locale']);
                    }
                    break;

                default:
                    $this->languages = [get_locale()];
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to load languages: %s', $e->getMessage()),
                'language_handler',
                'error'
            );
            $this->languages = [get_locale()];
        }
    }

    /**
     * Register single string for translation
     *
     * @param string $string String to register
     * @param string $context Translation context
     */
    private function register_string($string, $context) {
        try {
            switch ($this->translation_plugin) {
                case 'wpml':
                    do_action(
                        'wpml_register_single_string',
                        'mfw',
                        $context,
                        $string
                    );
                    break;

                case 'polylang':
                    if (function_exists('pll_register_string')) {
                        pll_register_string($string, $string, 'mfw');
                    }
                    break;
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to register string: %s', $e->getMessage()),
                'language_handler',
                'error'
            );
        }
    }
}