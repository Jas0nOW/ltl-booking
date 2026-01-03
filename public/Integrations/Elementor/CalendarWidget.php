<?php
if ( ! defined('ABSPATH') ) exit;

// Prevent fatal error if Elementor is not loaded
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
    return;
}

class LTLB_Elementor_Calendar_Widget extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'ltlb_calendar';
    }

    public function get_title(): string {
        return __( 'Booking Calendar', 'ltl-bookings' );
    }

    public function get_icon(): string {
        return 'eicon-calendar';
    }

    public function get_categories(): array {
        return [ 'ltlb' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Settings', 'ltl-bookings' ),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'view', [
            'label' => __( 'View Mode', 'ltl-bookings' ),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'month',
            'options' => [
                'month' => __( 'Month View', 'ltl-bookings' ),
                'week' => __( 'Week View', 'ltl-bookings' ),
            ],
        ] );

        $this->add_control( 'service_id', [
            'label' => __( 'Filter by Service (optional)', 'ltl-bookings' ),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 0,
            'min' => 0,
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        
        $atts = [
            'view' => sanitize_key( $settings['view'] ?? 'month' ),
        ];
        
        if ( ! empty( $settings['service_id'] ) ) {
            $atts['service'] = intval( $settings['service_id'] );
        }

        if ( class_exists('LTLB_Shortcodes') && method_exists('LTLB_Shortcodes', 'render_lazy_book_calendar') ) {
            echo LTLB_Shortcodes::render_lazy_book_calendar( $atts );
        } else {
            echo '<p>' . esc_html__( 'LazyBookings plugin required', 'ltl-bookings' ) . '</p>';
        }
    }
}
