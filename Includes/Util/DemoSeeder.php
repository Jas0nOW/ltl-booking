<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Demo Data Seeder
 * 
 * Industry presets with sample data for quick start.
 */
class LTLB_DemoSeeder {

    public static function seed_demo_data( string $preset = 'salon' ): array {
        global $wpdb;
        
        $result = [
            'services' => 0,
            'resources' => 0,
            'customers' => 0,
        ];

        // Choose preset
        switch ( $preset ) {
            case 'hotel':
                $result = self::seed_hotel_preset();
                break;
            case 'yoga':
                $result = self::seed_yoga_preset();
                break;
            case 'salon':
            default:
                $result = self::seed_salon_preset();
                break;
        }

        return $result;
    }

    private static function seed_salon_preset(): array {
        $service_repo = new LTLB_ServiceRepository();
        $resource_repo = new LTLB_ResourceRepository();
        $customer_repo = new LTLB_CustomerRepository();

        // Services
        $services = [
            ['name' => __('Haircut', 'ltl-bookings'), 'duration_min' => 45, 'price_cents' => 3500, 'description' => __('Professional haircut and styling', 'ltl-bookings')],
            ['name' => __('Hair Coloring', 'ltl-bookings'), 'duration_min' => 120, 'price_cents' => 8500, 'description' => __('Full hair coloring service', 'ltl-bookings')],
            ['name' => __('Manicure', 'ltl-bookings'), 'duration_min' => 30, 'price_cents' => 2500, 'description' => __('Classic manicure', 'ltl-bookings')],
            ['name' => __('Pedicure', 'ltl-bookings'), 'duration_min' => 45, 'price_cents' => 3500, 'description' => __('Relaxing pedicure', 'ltl-bookings')],
        ];

        $service_count = 0;
        foreach ( $services as $s ) {
            $service_repo->create( $s );
            $service_count++;
        }

        // Resources (Staff)
        $resources = [
            ['name' => 'Anna Schmidt', 'type' => 'staff', 'capacity' => 1],
            ['name' => 'Maria Mueller', 'type' => 'staff', 'capacity' => 1],
        ];

        $resource_count = 0;
        foreach ( $resources as $r ) {
            $resource_repo->create( $r );
            $resource_count++;
        }

        // Demo customers
        $customers = [
            ['email' => 'demo.customer1@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'phone' => '+49 123 456789'],
            ['email' => 'demo.customer2@example.com', 'first_name' => 'Jane', 'last_name' => 'Smith', 'phone' => '+49 987 654321'],
        ];

        $customer_count = 0;
        foreach ( $customers as $c ) {
            $customer_repo->upsert_by_email( $c );
            $customer_count++;
        }

        return [
            'services' => $service_count,
            'resources' => $resource_count,
            'customers' => $customer_count,
        ];
    }

    private static function seed_yoga_preset(): array {
        $service_repo = new LTLB_ServiceRepository();
        $resource_repo = new LTLB_ResourceRepository();

        $services = [
            ['name' => __('Hatha Yoga', 'ltl-bookings'), 'duration_min' => 60, 'price_cents' => 2000, 'description' => __('Beginner-friendly yoga class', 'ltl-bookings')],
            ['name' => __('Vinyasa Flow', 'ltl-bookings'), 'duration_min' => 75, 'price_cents' => 2500, 'description' => __('Dynamic flowing yoga', 'ltl-bookings')],
            ['name' => __('Yin Yoga', 'ltl-bookings'), 'duration_min' => 90, 'price_cents' => 2800, 'description' => __('Slow-paced, meditative yoga', 'ltl-bookings')],
            ['name' => __('Private Session', 'ltl-bookings'), 'duration_min' => 60, 'price_cents' => 8000, 'description' => __('One-on-one yoga instruction', 'ltl-bookings')],
        ];

        $service_count = 0;
        foreach ( $services as $s ) {
            $service_repo->create( $s );
            $service_count++;
        }

        $resources = [
            ['name' => 'Studio Room 1', 'type' => 'room', 'capacity' => 12],
            ['name' => 'Studio Room 2', 'type' => 'room', 'capacity' => 8],
        ];

        $resource_count = 0;
        foreach ( $resources as $r ) {
            $resource_repo->create( $r );
            $resource_count++;
        }

        return ['services' => $service_count, 'resources' => $resource_count, 'customers' => 0];
    }

    private static function seed_hotel_preset(): array {
        $service_repo = new LTLB_ServiceRepository();
        $resource_repo = new LTLB_ResourceRepository();

        $services = [
            ['name' => __('Standard Room', 'ltl-bookings'), 'price_cents' => 9000, 'description' => __('Comfortable room with queen bed', 'ltl-bookings'), 'max_adults' => 2, 'max_children' => 1],
            ['name' => __('Deluxe Room', 'ltl-bookings'), 'price_cents' => 14000, 'description' => __('Spacious room with king bed and balcony', 'ltl-bookings'), 'max_adults' => 2, 'max_children' => 2],
            ['name' => __('Suite', 'ltl-bookings'), 'price_cents' => 25000, 'description' => __('Luxury suite with separate living area', 'ltl-bookings'), 'max_adults' => 4, 'max_children' => 2],
        ];

        $service_count = 0;
        foreach ( $services as $s ) {
            $service_repo->create( $s );
            $service_count++;
        }

        $resources = [
            ['name' => 'Room 101', 'type' => 'room', 'capacity' => 1],
            ['name' => 'Room 102', 'type' => 'room', 'capacity' => 1],
            ['name' => 'Room 201', 'type' => 'room', 'capacity' => 1],
            ['name' => 'Suite 301', 'type' => 'room', 'capacity' => 1],
        ];

        $resource_count = 0;
        foreach ( $resources as $r ) {
            $resource_repo->create( $r );
            $resource_count++;
        }

        return ['services' => $service_count, 'resources' => $resource_count, 'customers' => 0];
    }
}
