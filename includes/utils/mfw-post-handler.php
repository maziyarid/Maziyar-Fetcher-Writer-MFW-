<?php
// Creates or updates WP posts from fetched items

class MFW_Post_Handler {

    public static function handle_items( $items, $source ) {
        foreach ( $items as $item ) {
            $existing = post_exists( $item['title'], '', $item['date'] );
            if ( $existing ) {
                continue; // skip duplicates
            }

            $post_data = array(
                'post_title'   => wp_strip_all_tags( $item['title'] ),
                'post_content' => $item['content'],
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
                'post_date'    => $item['date'],
                'post_type'    => 'post',
            );
            $post_id = wp_insert_post( $post_data );

            if ( is_wp_error( $post_id ) ) {
                MFW_Error_Logger::log( 'post_handler', $post_id->get_error_message() );
                continue;
            }

            // Categories & tags
            if ( ! empty( $source->mfw_category ) ) {
                wp_set_post_terms( $post_id, array( $source->mfw_category ), 'category', true );
            }
            if ( ! empty( $source->mfw_tags ) ) {
                wp_set_post_terms( $post_id, $source->mfw_tags, 'post_tag', true );
            }

            // SEO meta
            MFW_SEO_Integrator::apply_seo_meta(
                $post_id,
                $item['focus_keyword'],
                $item['meta_title'],
                $item['meta_description']
            );
            MFW_SEO_Integrator::add_news_schema( $post_id );

            // Affiliate links
            $content_with_aff = MFW_Affiliate_Manager::inject_affiliate_links( $item['content'], $source->mfw_source_type );
            wp_update_post( array( 'ID' => $post_id, 'post_content' => $content_with_aff ) );
        }
    }
}
