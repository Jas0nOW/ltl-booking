<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Gutenberg Blocks Registration
 * 
 * Native WordPress blocks for booking forms, calendar, and portals.
 */
class LTLB_Gutenberg_Blocks {

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register_blocks' ] );
        add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_editor_assets' ] );
    }

    public static function register_blocks(): void {
        // Register booking form block
        register_block_type( 'ltlb/booking-form', [
            'render_callback' => [ __CLASS__, 'render_booking_form' ],
            'attributes' => [
                'mode' => [
                    'type' => 'string',
                    'default' => 'wizard',
                ],
                'serviceId' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'title' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'subtitle' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ] );

        // Register calendar block
        register_block_type( 'ltlb/calendar', [
            'render_callback' => [ __CLASS__, 'render_calendar' ],
            'attributes' => [
                'viewMode' => [
                    'type' => 'string',
                    'default' => 'month',
                ],
                'serviceId' => [
                    'type' => 'number',
                    'default' => 0,
                ],
            ],
        ] );

        // Register customer portal block
        register_block_type( 'ltlb/customer-portal', [
            'render_callback' => [ __CLASS__, 'render_customer_portal' ],
            'attributes' => [
                'showUpcoming' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showHistory' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
        ] );
    }

    public static function render_booking_form( array $attributes ): string {
        $mode = sanitize_key( $attributes['mode'] ?? 'wizard' );
        $service_id = intval( $attributes['serviceId'] ?? 0 );
        $title = sanitize_text_field( $attributes['title'] ?? '' );
        $subtitle = sanitize_text_field( $attributes['subtitle'] ?? '' );

        $atts = [
            'mode' => $mode,
        ];
        
        if ( $service_id > 0 ) {
            $atts['service'] = $service_id;
        }
        
        if ( $title !== '' ) {
            $atts['title'] = $title;
        }
        
        if ( $subtitle !== '' ) {
            $atts['subtitle'] = $subtitle;
        }

        if ( class_exists('LTLB_Shortcodes') && method_exists('LTLB_Shortcodes', 'render_lazy_book') ) {
            return LTLB_Shortcodes::render_lazy_book( $atts );
        }
        
        return '<p>' . esc_html__( 'Booking form block (LazyBookings plugin required)', 'ltl-bookings' ) . '</p>';
    }

    public static function render_calendar( array $attributes ): string {
        $view_mode = sanitize_key( $attributes['viewMode'] ?? 'month' );
        $service_id = intval( $attributes['serviceId'] ?? 0 );

        $atts = [
            'view' => $view_mode,
        ];
        
        if ( $service_id > 0 ) {
            $atts['service'] = $service_id;
        }

        if ( class_exists('LTLB_Shortcodes') && method_exists('LTLB_Shortcodes', 'render_lazy_book_calendar') ) {
            return LTLB_Shortcodes::render_lazy_book_calendar( $atts );
        }
        
        return '<p>' . esc_html__( 'Calendar block (LazyBookings plugin required)', 'ltl-bookings' ) . '</p>';
    }

    public static function render_customer_portal( array $attributes ): string {
        $show_upcoming = ! empty( $attributes['showUpcoming'] );
        $show_history = ! empty( $attributes['showHistory'] );

        // Customer portal shortcode (if exists) or basic implementation
        $output = '<div class="ltlb-customer-portal-block">';
        
        if ( ! is_user_logged_in() ) {
            $output .= '<p>' . esc_html__( 'Please log in to view your bookings.', 'ltl-bookings' ) . '</p>';
            $output .= wp_login_form( [ 'echo' => false ] );
        } else {
            $current_user = wp_get_current_user();
            $email = $current_user->user_email;
            
            if ( class_exists('LTLB_CustomerRepository') ) {
                $customer_repo = new LTLB_CustomerRepository();
                $customer = $customer_repo->get_by_email( $email );
                
                if ( $customer ) {
                    $appointment_repo = new LTLB_AppointmentRepository();
                    $appointments = $appointment_repo->get_by_customer_id( intval( $customer['id'] ) );
                    
                    if ( $show_upcoming ) {
                        $output .= '<h3>' . esc_html__( 'Upcoming Bookings', 'ltl-bookings' ) . '</h3>';
                        $upcoming = array_filter( $appointments, function( $appt ) {
                            return in_array( $appt['status'], ['pending', 'confirmed'], true ) && strtotime( $appt['start_at'] ) > time();
                        } );
                        
                        if ( empty( $upcoming ) ) {
                            $output .= '<p>' . esc_html__( 'No upcoming bookings.', 'ltl-bookings' ) . '</p>';
                        } else {
                            $output .= '<ul class="ltlb-portal-list">';
                            foreach ( $upcoming as $appt ) {
                                $output .= '<li>';
                                $output .= '<strong>' . esc_html( $appt['service_name'] ?? 'Service' ) . '</strong><br>';
                                $output .= esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), strtotime( $appt['start_at'] ) ) );
                                $output .= ' - ' . esc_html( ucfirst( $appt['status'] ) );
                                $output .= '</li>';
                            }
                            $output .= '</ul>';
                        }
                    }
                    
                    if ( $show_history ) {
                        $output .= '<h3>' . esc_html__( 'Booking History', 'ltl-bookings' ) . '</h3>';
                        $past = array_filter( $appointments, function( $appt ) {
                            return strtotime( $appt['start_at'] ) <= time();
                        } );
                        
                        if ( empty( $past ) ) {
                            $output .= '<p>' . esc_html__( 'No past bookings.', 'ltl-bookings' ) . '</p>';
                        } else {
                            $output .= '<ul class="ltlb-portal-list">';
                            foreach ( array_slice( $past, 0, 10 ) as $appt ) {
                                $output .= '<li>';
                                $output .= '<strong>' . esc_html( $appt['service_name'] ?? 'Service' ) . '</strong><br>';
                                $output .= esc_html( date_i18n( get_option('date_format'), strtotime( $appt['start_at'] ) ) );
                                $output .= ' - ' . esc_html( ucfirst( $appt['status'] ) );
                                $output .= '</li>';
                            }
                            $output .= '</ul>';
                        }
                    }
                } else {
                    $output .= '<p>' . esc_html__( 'No customer account found for your email.', 'ltl-bookings' ) . '</p>';
                }
            }
        }
        
        $output .= '</div>';
        return $output;
    }

    public static function enqueue_editor_assets(): void {
        // Inline block editor config for LazyBookings blocks
        $inline_js = "
        (function() {
            var el = wp.element.createElement;
            var registerBlockType = wp.blocks.registerBlockType;
            var InspectorControls = wp.blockEditor.InspectorControls;
            var PanelBody = wp.components.PanelBody;
            var TextControl = wp.components.TextControl;
            var SelectControl = wp.components.SelectControl;
            var ToggleControl = wp.components.ToggleControl;

            registerBlockType('ltlb/booking-form', {
                title: 'LazyBookings: Booking Form',
                icon: 'calendar-alt',
                category: 'widgets',
                attributes: {
                    mode: { type: 'string', default: 'wizard' },
                    serviceId: { type: 'number', default: 0 },
                    title: { type: 'string', default: '' },
                    subtitle: { type: 'string', default: '' }
                },
                edit: function(props) {
                    return el('div', { className: 'ltlb-block-placeholder' },
                        el(InspectorControls, {},
                            el(PanelBody, { title: 'Settings' },
                                el(SelectControl, {
                                    label: 'Mode',
                                    value: props.attributes.mode,
                                    options: [
                                        { label: 'Wizard', value: 'wizard' },
                                        { label: 'Calendar', value: 'calendar' }
                                    ],
                                    onChange: function(val) { props.setAttributes({ mode: val }); }
                                }),
                                el(TextControl, {
                                    label: 'Service ID (optional)',
                                    value: props.attributes.serviceId,
                                    onChange: function(val) { props.setAttributes({ serviceId: parseInt(val) || 0 }); }
                                }),
                                el(TextControl, {
                                    label: 'Title',
                                    value: props.attributes.title,
                                    onChange: function(val) { props.setAttributes({ title: val }); }
                                }),
                                el(TextControl, {
                                    label: 'Subtitle',
                                    value: props.attributes.subtitle,
                                    onChange: function(val) { props.setAttributes({ subtitle: val }); }
                                })
                            )
                        ),
                        el('p', { style: { padding: '20px', background: '#f0f0f0', border: '1px dashed #ccc' } }, '📅 LazyBookings Booking Form')
                    );
                },
                save: function() { return null; }
            });

            registerBlockType('ltlb/calendar', {
                title: 'LazyBookings: Calendar',
                icon: 'calendar',
                category: 'widgets',
                attributes: {
                    viewMode: { type: 'string', default: 'month' },
                    serviceId: { type: 'number', default: 0 }
                },
                edit: function(props) {
                    return el('div', { className: 'ltlb-block-placeholder' },
                        el(InspectorControls, {},
                            el(PanelBody, { title: 'Settings' },
                                el(SelectControl, {
                                    label: 'View Mode',
                                    value: props.attributes.viewMode,
                                    options: [
                                        { label: 'Month', value: 'month' },
                                        { label: 'Week', value: 'week' }
                                    ],
                                    onChange: function(val) { props.setAttributes({ viewMode: val }); }
                                }),
                                el(TextControl, {
                                    label: 'Service ID Filter (optional)',
                                    value: props.attributes.serviceId,
                                    onChange: function(val) { props.setAttributes({ serviceId: parseInt(val) || 0 }); }
                                })
                            )
                        ),
                        el('p', { style: { padding: '20px', background: '#f0f0f0', border: '1px dashed #ccc' } }, '📆 LazyBookings Calendar')
                    );
                },
                save: function() { return null; }
            });

            registerBlockType('ltlb/customer-portal', {
                title: 'LazyBookings: Customer Portal',
                icon: 'admin-users',
                category: 'widgets',
                attributes: {
                    showUpcoming: { type: 'boolean', default: true },
                    showHistory: { type: 'boolean', default: true }
                },
                edit: function(props) {
                    return el('div', { className: 'ltlb-block-placeholder' },
                        el(InspectorControls, {},
                            el(PanelBody, { title: 'Settings' },
                                el(ToggleControl, {
                                    label: 'Show Upcoming Bookings',
                                    checked: props.attributes.showUpcoming,
                                    onChange: function(val) { props.setAttributes({ showUpcoming: val }); }
                                }),
                                el(ToggleControl, {
                                    label: 'Show Booking History',
                                    checked: props.attributes.showHistory,
                                    onChange: function(val) { props.setAttributes({ showHistory: val }); }
                                })
                            )
                        ),
                        el('p', { style: { padding: '20px', background: '#f0f0f0', border: '1px dashed #ccc' } }, '👤 LazyBookings Customer Portal')
                    );
                },
                save: function() { return null; }
            });
        })();
        ";
        
        wp_add_inline_script( 'wp-blocks', $inline_js );
    }
}
