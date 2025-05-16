<?php
class MFW_Deactivator {
    public static function deactivate() {
        // Clear cron jobs
        wp_clear_scheduled_hook( 'mfw_hourly_fetch' );
    }
}
