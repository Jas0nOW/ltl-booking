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

register_activation_hook( __FILE__, function () {
    // später: Tabellen/Optionen anlegen
} );

register_deactivation_hook( __FILE__, function () {
    // später: Cronjobs entfernen etc.
} );

add_action( 'init', function () {
    // später: CPTs / REST / Shortcodes registrieren
} );