<?php
// Manages scheduled fetch intervals and tasks

class MFW_Cron_Manager {

    public static function init() {
        add_action( 'mfw_minutely_fetch',    array( __CLASS__, 'run_fetch' ) );
        add_action( 'mfw_hourly_fetch',      array( __CLASS__, 'run_fetch' ) );
        add_action( 'mfw_daily_fetch',       array( __CLASS__, 'run_fetch' ) );
    }

    public static function schedule_intervals() {
        add_filter( 'cron_schedules', function( $schedules ) {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display'  => __( 'Every Minute', 'mfw' ),
            );
            return $schedules;
        } );
    }

    public static function run_fetch() {
        // Pull settings for which feed groups to run
        $settings = get_option( 'mfw_settings', array() );
        if ( empty( $settings['sources'] ) ) {
            return;
        }

        foreach ( $settings['sources'] as $source_id ) {
            $source = get_post( $source_id );
            if ( ! $source ) {
                continue;
            }
            // Determine fetcher class by CPT meta
            $type    = get_post_meta( $source_id, 'mfw_source_type', true );
            $fetcher = MFW_Abstract_Fetcher::factory( $type, $source_id );
            if ( $fetcher ) {
                $items = $fetcher->fetch();
                MFW_Post_Handler::handle_items( $items, $source );
            }
        }
    }
}

// Hook init
MFW_Cron_Manager::schedule_intervals();
add_action( 'init', array( 'MFW_Cron_Manager', 'init' ) );
