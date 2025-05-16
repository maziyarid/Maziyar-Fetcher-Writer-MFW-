<?php
// WPML and Polylang Translation Integrator

class MFW_Translation_Integrator {

    public static function translate_post($post_id, $target_lang, $translated_content) {
        if (function_exists('pll_save_post_translations')) {
            // Polylang integration
            $original_id = pll_get_post($post_id, pll_default_language());
            $translated_post = array(
                'post_title'    => $translated_content['title'],
                'post_content'  => $translated_content['content'],
                'post_status'   => 'publish',
                'post_type'     => get_post_type($post_id)
            );
            $translated_id = wp_insert_post($translated_post);
            pll_save_post_translations(array(
                pll_default_language() => $original_id,
                $target_lang => $translated_id
            ));
        } elseif (defined('ICL_SITEPRESS_VERSION')) {
            // WPML integration
            do_action('wpml_add_translation', $post_id, $target_lang, $translated_content['title'], $translated_content['content']);
        }
    }
}
