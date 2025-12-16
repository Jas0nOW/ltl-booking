<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Elementor Integration
 * 
 * Widgets for Elementor page builder with proper controls.
 */
class LTLB_Elementor_Integration {

    public static function init(): void {
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widgets' ] );
        add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_category' ] );
    }

    public static function register_category( $elements_manager ): void {
        $elements_manager->add_category( 'ltlb', [
            'title' => __( 'LazyBookings', 'ltl-bookings' ),
            'icon' => 'fa fa-calendar',
        ] );
    }

    public static function register_widgets( $widgets_manager ): void {
        $widget_files = [
            'booking_form' => __DIR__ . '/BookingFormWidget.php',
            'calendar' => __DIR__ . '/CalendarWidget.php',
        ];

        foreach ( $widget_files as $key => $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
            } else {
                error_log( "LazyBookings: Missing Elementor widget file: {$file}" );
            }
        }
        
        // Register widgets only if classes exist
        if ( class_exists( '\LTLB_Elementor_Booking_Form_Widget' ) ) {
            $widgets_manager->register( new \LTLB_Elementor_Booking_Form_Widget() );
        }
        if ( class_exists( '\LTLB_Elementor_Calendar_Widget' ) ) {
            $widgets_manager->register( new \LTLB_Elementor_Calendar_Widget() );
        }
    }
}
