<?php
/**
 * Plugin Name: LazyBookings
 * Plugin URI: https://lazytechnologylab.com/lazybookings
 * Description: Professional booking system with dual-mode functionality: Appointments & Hotel/PMS management with premium admin UI.
 * Version: 1.1.0
 * Author: Lazy Technology Lab
 * Author URI: https://lazytechnologylab.com
 * Text Domain: ltl-bookings
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LTLB_VERSION', '1.1.0' );
define( 'LTLB_PATH', plugin_dir_path( __FILE__ ) );
define( 'LTLB_URL', plugin_dir_url( __FILE__ ) );

// Suppress on-page deprecation output (keep logging) to avoid breaking headers during redirects.
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    @ini_set( 'display_errors', '0' );
    add_filter( 'deprecated_function_trigger_error', '__return_false' );
    add_filter( 'deprecated_argument_trigger_error', '__return_false' );
    add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
}

// Optional: enable detailed backtraces for PHP 8.1+ deprecations (and header warnings) to locate sources.
if ( defined( 'LTLB_DEBUG_DEPRECATIONS' ) && LTLB_DEBUG_DEPRECATIONS ) {
    set_error_handler( function ( $errno, $errstr, $errfile, $errline ) {
        static $hits = 0;
        $is_deprecation = ( $errno === E_DEPRECATED && ( str_contains( $errstr, 'strpos(): Passing null' ) || str_contains( $errstr, 'str_replace(): Passing null' ) ) );
        $is_header_warn = ( $errno === E_WARNING && str_contains( $errstr, 'Cannot modify header information' ) );

        if ( $is_deprecation || $is_header_warn ) {
            if ( $hits++ > 40 ) {
                return false; // avoid log flood
            }

            $label = $is_deprecation ? 'LTLB deprecation trace: ' : 'LTLB headers-sent trace: ';
            error_log( $label . $errstr . ' at ' . $errfile . ':' . $errline );

            foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
                $fn   = ( $frame['class'] ?? '' ) . ( $frame['type'] ?? '' ) . ( $frame['function'] ?? '' );
                $file = $frame['file'] ?? 'n/a';
                $line = $frame['line'] ?? 0;
                error_log( '  - ' . $fn . ' @ ' . $file . ':' . $line );
            }
        }
        return false; // allow normal handling
    } );
}

add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'ltl-bookings', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

require_once LTLB_PATH . 'Includes/DB/Schema.php';
require_once LTLB_PATH . 'Includes/DB/Migrator.php';
require_once LTLB_PATH . 'Includes/Core/Activator.php';
require_once LTLB_PATH . 'Includes/Core/Plugin.php';

register_activation_hook( __FILE__, [ 'LTLB_Activator', 'activate' ] );

register_deactivation_hook( __FILE__, function () {
    // Clean up rate limit transients
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ltlb_rate_%' OR option_name LIKE '_transient_timeout_ltlb_rate_%'" );
    
    // Remove scheduled cron jobs (if any added in future)
    wp_clear_scheduled_hook('ltlb_cleanup_old_appointments');
	wp_clear_scheduled_hook('ltlb_retention_cleanup');
} );

$ltlb = new LTLB_Plugin();
$ltlb->run();