<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Booking Mode Helper
 * 
 * Manages booking mode (Service vs Hotel) and provides mode-aware utilities.
 * Ensures clean separation between Hotel and Service entities.
 */
class LTLB_BookingMode {

    const MODE_SERVICE = 'service';
    const MODE_HOTEL = 'hotel';
    const OPTION_KEY = 'ltlb_booking_mode';

    /**
     * Get current booking mode
     */
    public static function get_current_mode(): string {
        $mode = get_option( self::OPTION_KEY, self::MODE_SERVICE );
        return in_array( $mode, [ self::MODE_SERVICE, self::MODE_HOTEL ], true ) ? $mode : self::MODE_SERVICE;
    }

    /**
     * Set booking mode
     */
    public static function set_mode( string $mode ): bool {
        if ( ! in_array( $mode, [ self::MODE_SERVICE, self::MODE_HOTEL ], true ) ) {
            return false;
        }
        
        return update_option( self::OPTION_KEY, $mode );
    }

    /**
     * Check if current mode is Service
     */
    public static function is_service_mode(): bool {
        return self::get_current_mode() === self::MODE_SERVICE;
    }

    /**
     * Check if current mode is Hotel
     */
    public static function is_hotel_mode(): bool {
        return self::get_current_mode() === self::MODE_HOTEL;
    }

    /**
     * Get mode-specific entity name
     * 
     * @param string $entity Entity type: 'booking', 'item', 'resource'
     * @return string Localized entity name
     */
    public static function get_entity_name( string $entity ): string {
        $mode = self::get_current_mode();
        
        $names = [
            'booking' => [
                self::MODE_SERVICE => __( 'Appointment', 'ltl-bookings' ),
                self::MODE_HOTEL => __( 'Booking', 'ltl-bookings' ),
            ],
            'bookings' => [
                self::MODE_SERVICE => __( 'Appointments', 'ltl-bookings' ),
                self::MODE_HOTEL => __( 'Bookings', 'ltl-bookings' ),
            ],
            'item' => [
                self::MODE_SERVICE => __( 'Service', 'ltl-bookings' ),
                self::MODE_HOTEL => __( 'Room Type', 'ltl-bookings' ),
            ],
            'items' => [
                self::MODE_SERVICE => __( 'Services', 'ltl-bookings' ),
                self::MODE_HOTEL => __( 'Room Types', 'ltl-bookings' ),
            ],
            'resource' => [
                self::MODE_SERVICE => __( 'Resource', 'ltl-bookings' ),
                self::MODE_HOTEL => __( 'Room', 'ltl-bookings' ),
            ],
            'resources' => [
                self::MODE_SERVICE => __( 'Resources', 'ltl-bookings' ),
                self::MODE_HOTEL => __( 'Rooms', 'ltl-bookings' ),
            ],
            'staff' => [
                self::MODE_SERVICE => __( 'Staff', 'ltl-bookings' ),
                self::MODE_HOTEL => __( 'Staff', 'ltl-bookings' ), // Same for both
            ],
        ];
        
        return $names[ $entity ][ $mode ] ?? ucfirst( $entity );
    }

    /**
     * Get mode-specific menu configuration
     */
    public static function get_menu_config(): array {
        $mode = self::get_current_mode();
        
        $service_menu = [
            'dashboard' => [ 'label' => __( 'Dashboard', 'ltl-bookings' ), 'visible' => true ],
            'calendar' => [ 'label' => __( 'Calendar', 'ltl-bookings' ), 'visible' => true ],
            'appointments' => [ 'label' => __( 'Appointments', 'ltl-bookings' ), 'visible' => true ],
            'services' => [ 'label' => __( 'Services', 'ltl-bookings' ), 'visible' => true ],
            'staff' => [ 'label' => __( 'Staff', 'ltl-bookings' ), 'visible' => true ],
            'resources' => [ 'label' => __( 'Resources', 'ltl-bookings' ), 'visible' => true ],
            'customers' => [ 'label' => __( 'Customers', 'ltl-bookings' ), 'visible' => true ],
            'room_types' => [ 'label' => __( 'Room Types', 'ltl-bookings' ), 'visible' => false ],
            'rooms' => [ 'label' => __( 'Rooms', 'ltl-bookings' ), 'visible' => false ],
            'settings' => [ 'label' => __( 'Settings', 'ltl-bookings' ), 'visible' => true ],
        ];
        
        $hotel_menu = [
            'dashboard' => [ 'label' => __( 'Dashboard', 'ltl-bookings' ), 'visible' => true ],
            'calendar' => [ 'label' => __( 'Calendar', 'ltl-bookings' ), 'visible' => true ],
            'appointments' => [ 'label' => __( 'Bookings', 'ltl-bookings' ), 'visible' => true ],
            'services' => [ 'label' => __( 'Services', 'ltl-bookings' ), 'visible' => false ],
            'staff' => [ 'label' => __( 'Staff', 'ltl-bookings' ), 'visible' => true ],
            'resources' => [ 'label' => __( 'Resources', 'ltl-bookings' ), 'visible' => false ],
            'customers' => [ 'label' => __( 'Guests', 'ltl-bookings' ), 'visible' => true ],
            'room_types' => [ 'label' => __( 'Room Types', 'ltl-bookings' ), 'visible' => true ],
            'rooms' => [ 'label' => __( 'Rooms', 'ltl-bookings' ), 'visible' => true ],
            'settings' => [ 'label' => __( 'Settings', 'ltl-bookings' ), 'visible' => true ],
        ];
        
        return $mode === self::MODE_HOTEL ? $hotel_menu : $service_menu;
    }

    /**
     * Get mode-specific settings fields
     * 
     * @return array Fields visible for current mode
     */
    public static function get_mode_settings(): array {
        $mode = self::get_current_mode();
        
        $service_settings = [
            'service_duration_step',
            'service_buffer_time',
            'service_cancellation_window',
            'staff_assignment_required',
            'resource_booking_enabled',
            'group_bookings_enabled',
        ];
        
        $hotel_settings = [
            'hotel_checkin_time',
            'hotel_checkout_time',
            'hotel_min_nights',
            'hotel_max_nights',
            'hotel_cancellation_policy',
            'hotel_deposit_percentage',
            'occupancy_rules',
        ];
        
        $shared_settings = [
            'working_hours_start',
            'working_hours_end',
            'timezone',
            'currency',
            'payment_methods',
            'notification_emails',
            'gdpr_compliance',
        ];
        
        $mode_specific = $mode === self::MODE_HOTEL ? $hotel_settings : $service_settings;
        
        return array_merge( $shared_settings, $mode_specific );
    }

    /**
     * Check if a feature is available in current mode
     */
    public static function is_feature_available( string $feature ): bool {
        $mode = self::get_current_mode();
        
        $service_features = [
            'staff_schedules',
            'service_durations',
            'resource_booking',
            'group_bookings',
            'parallel_appointments',
        ];
        
        $hotel_features = [
            'room_types',
            'rate_plans',
            'occupancy_management',
            'multi_night_bookings',
            'room_amenities',
        ];
        
        $shared_features = [
            'calendar',
            'customers',
            'payments',
            'notifications',
            'reports',
            'exports',
        ];
        
        if ( in_array( $feature, $shared_features, true ) ) {
            return true;
        }
        
        if ( $mode === self::MODE_SERVICE ) {
            return in_array( $feature, $service_features, true );
        }
        
        if ( $mode === self::MODE_HOTEL ) {
            return in_array( $feature, $hotel_features, true );
        }
        
        return false;
    }

    /**
     * Get repository for mode-specific entity
     * 
     * @param string $entity_type 'item' or 'resource'
     * @return object Repository instance
     */
    public static function get_repository( string $entity_type ) {
        $mode = self::get_current_mode();
        
        if ( $entity_type === 'item' ) {
            return $mode === self::MODE_HOTEL 
                ? new LTLB_RoomTypeRepository() 
                : new LTLB_ServiceRepository();
        }
        
        if ( $entity_type === 'resource' ) {
            return $mode === self::MODE_HOTEL 
                ? new LTLB_RoomRepository() 
                : new LTLB_ResourceRepository();
        }
        
        throw new InvalidArgumentException( "Unknown entity type: {$entity_type}" );
    }
}
