<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * WP-CLI Seed Command
 * 
 * Seeds demo data for development/testing.
 * Only available when WP_DEBUG is true or enable_dev_tools setting is enabled.
 */
class LTLB_CLI_SeedCommand {

    /**
     * Seed demo data.
     *
     * ## OPTIONS
     *
     * [--mode=<mode>]
     * : Type of seed data to create: service or hotel
     * ---
     * default: service
     * options:
     *   - service
     *   - hotel
     * ---
     *
     * ## EXAMPLES
     *
     *     wp ltlb seed
     *     wp ltlb seed --mode=service
     *     wp ltlb seed --mode=hotel
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        if ( ! class_exists('WP_CLI') ) {
            return;
        }

        // Check dev tools gate
        $settings = get_option('lazy_settings', []);
        $dev_tools_enabled = ( defined('WP_DEBUG') && WP_DEBUG ) || ! empty( $settings['enable_dev_tools'] );
        
        if ( ! $dev_tools_enabled ) {
            WP_CLI::error( 'Seed command is only available when WP_DEBUG is true or enable_dev_tools setting is enabled.' );
            return;
        }

        $mode = $assoc_args['mode'] ?? 'service';
        
        if ( ! in_array( $mode, ['service', 'hotel'], true ) ) {
            WP_CLI::error( 'Invalid mode. Use: service or hotel' );
            return;
        }

        WP_CLI::line( "Seeding demo data (mode: {$mode})..." );

        try {
            if ( $mode === 'service' ) {
                $this->seed_service_mode();
            } else {
                $this->seed_hotel_mode();
            }
            
            WP_CLI::success( 'Demo data seeded successfully.' );
        } catch ( Exception $e ) {
            WP_CLI::error( 'Seed failed: ' . $e->getMessage() );
        }
    }

    private function seed_service_mode(): void {
        global $wpdb;
        
        // Create service
        $service_repo = new LTLB_ServiceRepository();
        $service_id = $service_repo->create([
            'name' => 'Demo Yoga Class',
            'description' => 'A relaxing yoga session for all levels',
            'duration_min' => 60,
            'buffer_before_min' => 10,
            'buffer_after_min' => 10,
            'price_cents' => 2500,
            'currency' => 'EUR',
            'is_active' => 1
        ]);
        WP_CLI::line( "Created service: Demo Yoga Class (ID: {$service_id})" );

        // Create 2 resources
        $resource_repo = new LTLB_ResourceRepository();
        $resource1_id = $resource_repo->create([
            'name' => 'Studio A',
            'capacity' => 10,
            'is_active' => 1
        ]);
        $resource2_id = $resource_repo->create([
            'name' => 'Studio B',
            'capacity' => 8,
            'is_active' => 1
        ]);
        WP_CLI::line( "Created resources: Studio A (ID: {$resource1_id}), Studio B (ID: {$resource2_id})" );

        // Map resources to service
        $service_resources_repo = new LTLB_ServiceResourcesRepository();
        $service_resources_repo->add_resource_to_service( $service_id, $resource1_id );
        $service_resources_repo->add_resource_to_service( $service_id, $resource2_id );
        WP_CLI::line( "Mapped resources to service" );

        // Create 2 staff users with working hours
        $staff_user1 = wp_create_user( 'demo_staff_1', wp_generate_password(), 'staff1@demo.local' );
        if ( ! is_wp_error( $staff_user1 ) ) {
            $user1 = new WP_User( $staff_user1 );
            $user1->set_role( 'ltlb_staff' );
            
            // Add working hours (Mon-Fri 9-17)
            $staff_hours_repo = new LTLB_StaffHoursRepository();
            for ( $day = 1; $day <= 5; $day++ ) {
                $staff_hours_repo->create([
                    'user_id' => $staff_user1,
                    'weekday' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'is_active' => 1
                ]);
            }
            WP_CLI::line( "Created staff user: demo_staff_1 (ID: {$staff_user1}) with Mon-Fri 9-17 hours" );
        }

        $staff_user2 = wp_create_user( 'demo_staff_2', wp_generate_password(), 'staff2@demo.local' );
        if ( ! is_wp_error( $staff_user2 ) ) {
            $user2 = new WP_User( $staff_user2 );
            $user2->set_role( 'ltlb_staff' );
            
            // Add working hours (Tue-Sat 10-18)
            $staff_hours_repo = new LTLB_StaffHoursRepository();
            for ( $day = 2; $day <= 6; $day++ ) {
                $staff_hours_repo->create([
                    'user_id' => $staff_user2,
                    'weekday' => $day,
                    'start_time' => '10:00:00',
                    'end_time' => '18:00:00',
                    'is_active' => 1
                ]);
            }
            WP_CLI::line( "Created staff user: demo_staff_2 (ID: {$staff_user2}) with Tue-Sat 10-18 hours" );
        }

        // Create a demo customer
        $customer_repo = new LTLB_CustomerRepository();
        $customer_id = $customer_repo->create([
            'email' => 'demo@customer.local',
            'first_name' => 'Demo',
            'last_name' => 'Customer',
            'phone' => '+1234567890'
        ]);
        WP_CLI::line( "Created customer: Demo Customer (ID: {$customer_id})" );
    }

    private function seed_hotel_mode(): void {
        global $wpdb;
        
        // Create room type (service)
        $service_repo = new LTLB_ServiceRepository();
        $room_type_id = $service_repo->create([
            'name' => 'Demo Double Room',
            'description' => 'Comfortable double room with sea view',
            'duration_min' => 1440, // 24 hours (not used in hotel mode)
            'buffer_before_min' => 0,
            'buffer_after_min' => 0,
            'price_cents' => 12000, // 120.00 EUR per night
            'currency' => 'EUR',
            'is_active' => 1
        ]);
        WP_CLI::line( "Created room type: Demo Double Room (ID: {$room_type_id})" );

        // Create 2 rooms (resources)
        $resource_repo = new LTLB_ResourceRepository();
        $room1_id = $resource_repo->create([
            'name' => 'Room 101',
            'capacity' => 2,
            'is_active' => 1
        ]);
        $room2_id = $resource_repo->create([
            'name' => 'Room 102',
            'capacity' => 2,
            'is_active' => 1
        ]);
        WP_CLI::line( "Created rooms: Room 101 (ID: {$room1_id}), Room 102 (ID: {$room2_id})" );

        // Map rooms to room type
        $service_resources_repo = new LTLB_ServiceResourcesRepository();
        $service_resources_repo->add_resource_to_service( $room_type_id, $room1_id );
        $service_resources_repo->add_resource_to_service( $room_type_id, $room2_id );
        WP_CLI::line( "Mapped rooms to room type" );

        // Set hotel mode settings if not already set
        $settings = get_option('lazy_settings', []);
        if ( empty( $settings['template_mode'] ) || $settings['template_mode'] !== 'hotel' ) {
            $settings['template_mode'] = 'hotel';
            $settings['hotel_checkin_time'] = '15:00';
            $settings['hotel_checkout_time'] = '11:00';
            $settings['hotel_min_nights'] = 1;
            $settings['hotel_max_nights'] = 30;
            update_option('lazy_settings', $settings);
            WP_CLI::line( "Updated settings to hotel mode" );
        }

        // Create a demo customer
        $customer_repo = new LTLB_CustomerRepository();
        $customer_id = $customer_repo->create([
            'email' => 'hotel.guest@demo.local',
            'first_name' => 'Hotel',
            'last_name' => 'Guest',
            'phone' => '+9876543210'
        ]);
        WP_CLI::line( "Created customer: Hotel Guest (ID: {$customer_id})" );
    }
}
