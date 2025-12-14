<?php
/**
 * Plugin Name: LazyBookings
 * Plugin URI: https://lazytechnologylab.com/lazybookings
 * Description: Professional booking system with dual-mode functionality: Appointments & Hotel/PMS management with premium admin UI.
 * Version: 1.0.0
 * Author: Lazy Technology Lab
 * Author URI: https://lazytechnologylab.com
 * Text Domain: ltl-bookings
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LTLB_VERSION', '1.0.0' );
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