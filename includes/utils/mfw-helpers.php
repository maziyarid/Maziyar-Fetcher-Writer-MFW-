<?php
// Miscellaneous helper functions

class MFW_Helpers {

    /**
     * Safely get value from array or object.
     */
    public static function get( $container, $key, $default = '' ) {
        if ( is_array( $container ) && isset( $container[ $key ] ) ) {
            return $container[ $key ];
        }
        if ( is_object( $container ) && isset( $container->$key ) ) {
            return $container->$key;
        }
        return $default;
    }

    /**
     * Normalize date to MySQL format.
     */
    public static function normalize_date( $date_string ) {
        $timestamp = strtotime( $date_string );
        if ( ! $timestamp ) {
            return current_time( 'mysql' );
        }
        return date( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Detect language code (e.g., using simple regex or external API).
     * Stub: returns empty or passed default.
     */
    public static function detect_language( $text, $default = 'en' ) {
        // TODO: integrate real language detection
        return $default;
    }

    /**
     * Translate using configured model.
     */
    public static function translate( $text, $target_lang ) {
        return MFW_Content_Processor::translate( $text, $target_lang );
    }

}
