<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * WP-CLI Migrate Command
 * 
 * Runs database migrations.
 */
class LTLB_CLI_MigrateCommand {

    /**
     * Run database migrations.
     *
     * ## EXAMPLES
     *
     *     wp ltlb migrate
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        if ( ! class_exists('WP_CLI') ) {
            return;
        }

        WP_CLI::line( 'Running LazyBookings migrations...' );

        $before_version = get_option('ltlb_db_version', '0.0.0');
        $target_version = defined('LTLB_VERSION') ? LTLB_VERSION : '0.0.0';

        WP_CLI::line( "Current DB version: {$before_version}" );
        WP_CLI::line( "Target version: {$target_version}" );

        if ( version_compare( $before_version, $target_version, '>=' ) ) {
            WP_CLI::success( 'Database is already up to date.' );
            return;
        }

        try {
            LTLB_DB_Migrator::migrate();
            
            $after_version = get_option('ltlb_db_version', '0.0.0');
            
            if ( version_compare( $after_version, $target_version, '>=' ) ) {
                WP_CLI::success( "Migration complete. DB version: {$after_version}" );
            } else {
                WP_CLI::warning( "Migration ran but DB version is {$after_version}, expected {$target_version}" );
            }
        } catch ( Exception $e ) {
            WP_CLI::error( 'Migration failed: ' . $e->getMessage() );
        }
    }
}
