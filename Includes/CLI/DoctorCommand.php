<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * WP-CLI Doctor Command
 * 
 * Diagnoses system health and configuration.
 */
class LTLB_CLI_DoctorCommand {

    /**
     * Run system diagnostics.
     *
     * ## EXAMPLES
     *
     *     wp ltlb doctor
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        if ( ! class_exists('WP_CLI') ) {
            return;
        }

        WP_CLI::line( '=== LazyBookings System Diagnostics ===' );
        WP_CLI::line( '' );

        // Version info
        $plugin_version = defined('LTLB_VERSION') ? LTLB_VERSION : 'unknown';
        $db_version = get_option('ltlb_db_version', 'not set');
        WP_CLI::line( "Plugin Version: {$plugin_version}" );
        WP_CLI::line( "DB Version: {$db_version}" );
        
        if ( version_compare( $plugin_version, $db_version, '>' ) ) {
            WP_CLI::warning( 'DB version is behind plugin version. Run: wp ltlb migrate' );
        } elseif ( version_compare( $plugin_version, $db_version, '=' ) ) {
            WP_CLI::success( 'DB version matches plugin version' );
        }
        WP_CLI::line( '' );

        // Template mode
        $settings = get_option('lazy_settings', []);
        $template_mode = $settings['template_mode'] ?? 'service';
        WP_CLI::line( "Template Mode: {$template_mode}" );
        WP_CLI::line( '' );

        // Database tables
        global $wpdb;
        $tables = [
            'services' => $wpdb->prefix . 'lazy_services',
            'customers' => $wpdb->prefix . 'lazy_customers',
            'appointments' => $wpdb->prefix . 'lazy_appointments',
            'staff_hours' => $wpdb->prefix . 'lazy_staff_hours',
            'staff_exceptions' => $wpdb->prefix . 'lazy_staff_exceptions',
            'resources' => $wpdb->prefix . 'lazy_resources',
            'appointment_resources' => $wpdb->prefix . 'lazy_appointment_resources',
            'service_resources' => $wpdb->prefix . 'lazy_service_resources',
        ];

        WP_CLI::line( 'Database Tables:' );
        $all_exist = true;
        foreach ( $tables as $label => $table_name ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
            $status = $exists ? '✓' : '✗';
            $count = $exists ? $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ) : 0;
            
            if ( $exists ) {
                WP_CLI::line( "  {$status} {$label}: {$count} rows" );
            } else {
                WP_CLI::error( "  {$status} {$label}: MISSING", false );
                $all_exist = false;
            }
        }
        
        if ( ! $all_exist ) {
            WP_CLI::warning( 'Some tables are missing. Run: wp ltlb migrate' );
        }
        WP_CLI::line( '' );

        // Lock support (MySQL GET_LOCK)
        $lock_test = $wpdb->get_var( "SELECT GET_LOCK('ltlb_test_lock', 0)" );
        $lock_supported = ( $lock_test === '1' );
        if ( $lock_supported ) {
            $wpdb->query( "SELECT RELEASE_LOCK('ltlb_test_lock')" );
            WP_CLI::success( 'MySQL Named Locks: Supported' );
        } else {
            WP_CLI::warning( 'MySQL Named Locks: Not supported (race condition protection disabled)' );
        }
        WP_CLI::line( '' );

        // Mail configuration
        WP_CLI::line( 'Email Configuration:' );
        $from_email = $settings['mail_from_email'] ?? get_option('admin_email');
        $from_name = $settings['mail_from_name'] ?? get_bloginfo('name');
        $reply_to = $settings['mail_reply_to'] ?? '';
        WP_CLI::line( "  From: {$from_name} <{$from_email}>" );
        if ( ! empty( $reply_to ) ) {
            WP_CLI::line( "  Reply-To: {$reply_to}" );
        }
        WP_CLI::line( '' );

        // Logging status
        $logging_enabled = ! empty( $settings['logging_enabled'] );
        $log_level = $settings['log_level'] ?? 'error';
        $log_status = $logging_enabled ? "Enabled ({$log_level})" : 'Disabled';
        WP_CLI::line( "Logging: {$log_status}" );
        WP_CLI::line( '' );

        // Dev tools gate
        $dev_tools_enabled = ( defined('WP_DEBUG') && WP_DEBUG ) || ! empty( $settings['enable_dev_tools'] );
        $dev_status = $dev_tools_enabled ? 'Enabled' : 'Disabled';
        WP_CLI::line( "Dev Tools: {$dev_status}" );
        WP_CLI::line( '' );

        // Last migration timestamp
        $last_migration = get_option('ltlb_last_migration_time', 'never');
        WP_CLI::line( "Last Migration: {$last_migration}" );

        WP_CLI::success( 'Diagnostics complete' );
    }
}
