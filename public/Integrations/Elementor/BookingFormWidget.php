<?php
if ( ! defined('ABSPATH') ) exit;

// Prevent fatal error if Elementor is not loaded
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
    return;
}

class LTLB_Elementor_Booking_Form_Widget extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'ltlb_booking_form';
    }

    public function get_title(): string {
        return __( 'Booking Form', 'ltl-bookings' );
    }

    public function get_icon(): string {
        return 'eicon-form-horizontal';
    }

    public function get_categories(): array {
        return [ 'ltlb' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Settings', 'ltl-bookings' ),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'mode', [
            'label' => __( 'Mode', 'ltl-bookings' ),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'wizard',
            'options' => [
                'wizard' => __( 'Wizard', 'ltl-bookings' ),
                'calendar' => __( 'Calendar', 'ltl-bookings' ),
            ],
        ] );

        $this->add_control( 'service_id', [
            'label' => __( 'Preselect Service (optional)', 'ltl-bookings' ),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 0,
            'min' => 0,
        ] );

        $this->add_control( 'title', [
            'label' => __( 'Title', 'ltl-bookings' ),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'placeholder' => __( 'Optional title', 'ltl-bookings' ),
        ] );

        $this->add_control( 'subtitle', [
            'label' => __( 'Subtitle', 'ltl-bookings' ),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'placeholder' => __( 'Optional subtitle', 'ltl-bookings' ),
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        
        $atts = [
            'mode' => sanitize_key( $settings['mode'] ?? 'wizard' ),
        ];
        
        if ( ! empty( $settings['service_id'] ) ) {
            $atts['service'] = intval( $settings['service_id'] );
        }
        
        if ( ! empty( $settings['title'] ) ) {
            $atts['title'] = sanitize_text_field( $settings['title'] );
        }
        
        if ( ! empty( $settings['subtitle'] ) ) {
            $atts['subtitle'] = sanitize_text_field( $settings['subtitle'] );
        }

        if ( class_exists('LTLB_Shortcodes') && method_exists('LTLB_Shortcodes', 'render_lazy_book') ) {
            echo LTLB_Shortcodes::render_lazy_book( $atts );
        } else {
            echo '<p>' . esc_html__( 'LazyBookings plugin required', 'ltl-bookings' ) . '</p>';
        }
    }
}
