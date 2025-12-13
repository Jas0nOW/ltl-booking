<?php
/**
 * Plugin Name: Lazy Bookings
 * Description: Booking plugin (Amelia replacement MVP).
 * Version: 0.4.4
 * Author: LazyTechLab
 * Text Domain: ltl-bookings
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LTLB_VERSION', '0.4.4' );
define( 'LTLB_PATH', plugin_dir_path( __FILE__ ) );
define( 'LTLB_URL', plugin_dir_url( __FILE__ ) );

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