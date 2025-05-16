<?php
// Manages schema.org markup for imported posts

class MFW_Schema_Manager {

    public static function add_news_schema($post_id) {
        add_filter( 'wpseo_schema_graph', function( $graph ) use ( $post_id ) {
            if ( get_the_ID() !== $post_id ) {
                return $graph;
            }
            $news_article = array(
                '@type'            => 'NewsArticle',
                'headline'         => get_the_title( $post_id ),
                'datePublished'    => get_the_date( 'c', $post_id ),
                'dateModified'     => get_the_modified_date( 'c', $post_id ),
                'author'           => array(
                    '@type' => 'Person',
                    'name'  => get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) ),
                ),
                'publisher'        => array(
                    '@type' => 'Organization',
                    'name'  => get_bloginfo( 'name' ),
                    'logo'  => array(
                        '@type' => 'ImageObject',
                        'url'   => get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '',
                    ),
                ),
                'mainEntityOfPage' => array(
                    '@type' => 'WebPage',
                    '@id'   => get_permalink( $post_id ),
                ),
            );
            $graph[] = $news_article;
            return $graph;
        }, 10 );
    }
}

// Hook news schema on single posts
add_action( 'wp', function() {
    if ( is_singular() ) {
        MFW_Schema_Manager::add_news_schema( get_the_ID() );
    }
} );
