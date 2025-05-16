<?php
// Yoast & Rank Math SEO Integrator

class MFW_SEO_Integrator {

    public static function apply_seo_meta($post_id, $focus_keyword, $meta_title, $meta_description) {
        // Yoast SEO
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
        update_post_meta($post_id, '_yoast_wpseo_title', $meta_title);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);

        // Rank Math SEO
        update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
        update_post_meta($post_id, 'rank_math_title', $meta_title);
        update_post_meta($post_id, 'rank_math_description', $meta_description);
    }

    public static function add_news_schema($post_id) {
        if (defined('RANK_MATH_VERSION')) {
            update_post_meta($post_id, 'rank_math_schema_type', 'newsarticle');
        } elseif (defined('WPSEO_VERSION')) {
            update_post_meta($post_id, '_yoast_wpseo_schema_page_type', 'NewsArticle');
        }
    }
}
