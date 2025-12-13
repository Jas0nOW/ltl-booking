<?php
/**
 * Plugin Name: Lazy Bookings
 * Description: Booking plugin (Amelia replacement MVP).
 * Version: 0.1.0
 * Author: LazyTechLab
 * Text Domain: ltl-bookings
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LTLB_VERSION', '0.1.0' );
define( 'LTLB_PATH', plugin_dir_path( __FILE__ ) );
define( 'LTLB_URL', plugin_dir_url( __FILE__ ) );

require_once LTLB_PATH . 'includes/DB/Schema.php';
require_once LTLB_PATH . 'includes/DB/Migrator.php';
require_once LTLB_PATH . 'includes/Core/Activator.php';
require_once LTLB_PATH . 'includes/Core/Plugin.php';

register_activation_hook( __FILE__, [ 'LTLB_Activator', 'activate' ] );

register_deactivation_hook( __FILE__, function () {
    // später: Cronjobs entfernen etc.
} );

$ltlb = new LTLB_Plugin();
$ltlb->run();

register_deactivation_hook( __FILE__, function () {
    // später: Cronjobs entfernen etc.
} );

add_action( 'init', function () {
    // später: CPTs / REST / Shortcodes registrieren
} );