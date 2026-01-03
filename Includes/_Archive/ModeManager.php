<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Mode Isolation Manager
 * 
 * Features:
 * - Separate settings per mode (hotel vs service)
 * - Mode-specific data filtering
 * - Safe mode switching
 * - Export/Import per mode
 * 
 * @package LazyBookings
 */
class LTLB_Mode_Manager {

    private const MODE_SERVICE = 'service';
    private const MODE_HOTEL = 'hotel';

    /**
     * Get current active mode
     * 
     * @return string Current mode
     */
    public static function get_current_mode(): string {
        $settings = get_option( 'lazy_settings', [] );
        $mode = $settings['template_mode'] ?? self::MODE_SERVICE;
        
        return in_array( $mode, [ self::MODE_SERVICE, self::MODE_HOTEL ], true ) 
            ? $mode 
            : self::MODE_SERVICE;
    }

    /**
     * Switch mode
     * 
     * @param string $new_mode New mode to switch to
     * @return bool|WP_Error Success or error
     */
    public static function switch_mode( string $new_mode ) {
        if ( ! in_array( $new_mode, [ self::MODE_SERVICE, self::MODE_HOTEL ], true ) ) {
            return new WP_Error( 'invalid_mode', __( 'Invalid mode specified', 'ltl-bookings' ) );
        }

        $settings = get_option( 'lazy_settings', [] );
        $old_mode = $settings['template_mode'] ?? self::MODE_SERVICE;

        if ( $old_mode === $new_mode ) {
            return true; // Already in this mode
        }

        // Apply mode-specific defaults
        $defaults = self::get_mode_defaults( $new_mode );
        $settings = array_merge( $settings, $defaults );
        $settings['template_mode'] = $new_mode;

        update_option( 'lazy_settings', $settings );

        // Trigger mode switch action
        do_action( 'ltlb_mode_switched', $new_mode, $old_mode );

        return true;
    }

    /**
     * Get mode-specific default settings
     * 
     * @param string $mode Mode identifier
     * @return array Default settings
     */
    public static function get_mode_defaults( string $mode ): array {
        $defaults = [
            self::MODE_SERVICE => [
                'working_hours_start' => 9,
                'working_hours_end' => 17,
                'slot_size_minutes' => 60,
                'default_duration' => 60,
                'enable_resources' => 0,
                'enable_multi_day' => 0
            ],
            self::MODE_HOTEL => [
                'working_hours_start' => 14, // Check-in
                'working_hours_end' => 11, // Check-out
                'slot_size_minutes' => 1440, // Full day
                'default_duration' => 1440,
                'enable_resources' => 1,
                'enable_multi_day' => 1
            ]
        ];

        return $defaults[ $mode ] ?? $defaults[ self::MODE_SERVICE ];
    }

    /**
     * Get mode-specific settings
     * 
     * @param string|null $mode Optional mode (defaults to current)
     * @return array Mode settings
     */
    public static function get_mode_settings( ?string $mode = null ): array {
        if ( ! $mode ) {
            $mode = self::get_current_mode();
        }

        $all_settings = get_option( 'lazy_settings', [] );
        $mode_key = 'mode_' . $mode . '_settings';

        return $all_settings[ $mode_key ] ?? [];
    }

    /**
     * Update mode-specific settings
     * 
     * @param array $settings Settings to update
     * @param string|null $mode Optional mode (defaults to current)
     * @return bool Success
     */
    public static function update_mode_settings( array $settings, ?string $mode = null ): bool {
        if ( ! $mode ) {
            $mode = self::get_current_mode();
        }

        $all_settings = get_option( 'lazy_settings', [] );
        $mode_key = 'mode_' . $mode . '_settings';
        
        $all_settings[ $mode_key ] = array_merge( 
            $all_settings[ $mode_key ] ?? [], 
            $settings 
        );

        return update_option( 'lazy_settings', $all_settings );
    }

    /**
     * Get services/rooms filtered by current mode
     * 
     * @return array Filtered services
     */
    public static function get_mode_services(): array {
        global $wpdb;

        $mode = self::get_current_mode();
        $table = $wpdb->prefix . 'ltlb_services';

        if ( $mode === self::MODE_HOTEL ) {
            // Hotel mode: only items with beds_type (rooms)
            $services = $wpdb->get_results(
                "SELECT * FROM $table 
                 WHERE is_active = 1 
                 AND beds_type IS NOT NULL 
                 AND beds_type != ''
                 ORDER BY name ASC",
                ARRAY_A
            );
        } else {
            // Service mode: items without beds_type or explicitly marked as service
            $services = $wpdb->get_results(
                "SELECT * FROM $table 
                 WHERE is_active = 1 
                 AND (beds_type IS NULL OR beds_type = '')
                 ORDER BY name ASC",
                ARRAY_A
            );
        }

        return $services;
    }

    /**
     * Check if feature is enabled in current mode
     * 
     * @param string $feature Feature identifier
     * @return bool Enabled status
     */
    public static function is_feature_enabled( string $feature ): bool {
        $mode = self::get_current_mode();

        $mode_features = [
            self::MODE_SERVICE => [
                'staff_assignment' => true,
                'group_bookings' => true,
                'recurring_slots' => true,
                'multi_day' => false,
                'room_types' => false,
                'bed_configuration' => false,
                'check_in_out' => false
            ],
            self::MODE_HOTEL => [
                'staff_assignment' => false,
                'group_bookings' => false,
                'recurring_slots' => false,
                'multi_day' => true,
                'room_types' => true,
                'bed_configuration' => true,
                'check_in_out' => true
            ]
        ];

        return $mode_features[ $mode ][ $feature ] ?? false;
    }

    /**
     * Get mode-specific field visibility rules
     * 
     * @return array Visibility rules
     */
    public static function get_field_visibility(): array {
        return [
            self::MODE_SERVICE => [
                'visible' => [
                    'staff_id',
                    'duration_min',
                    'buffer_before_min',
                    'buffer_after_min',
                    'is_group',
                    'max_seats_per_booking',
                    'recurring_slots'
                ],
                'hidden' => [
                    'beds_type',
                    'amenities',
                    'max_adults',
                    'max_children',
                    'room_number'
                ]
            ],
            self::MODE_HOTEL => [
                'visible' => [
                    'beds_type',
                    'amenities',
                    'max_adults',
                    'max_children',
                    'room_number',
                    'floor_number'
                ],
                'hidden' => [
                    'staff_id',
                    'buffer_before_min',
                    'buffer_after_min',
                    'is_group',
                    'recurring_slots'
                ]
            ]
        ];
    }

    /**
     * Check if field should be visible in current mode
     * 
     * @param string $field_name Field identifier
     * @return bool Visibility
     */
    public static function is_field_visible( string $field_name ): bool {
        $mode = self::get_current_mode();
        $visibility = self::get_field_visibility();

        if ( in_array( $field_name, $visibility[ $mode ]['visible'], true ) ) {
            return true;
        }

        if ( in_array( $field_name, $visibility[ $mode ]['hidden'], true ) ) {
            return false;
        }

        // Default: visible
        return true;
    }

    /**
     * Get mode-specific labels
     * 
     * @param string $key Label key
     * @return string Translated label
     */
    public static function get_label( string $key ): string {
        $mode = self::get_current_mode();

        $labels = [
            self::MODE_SERVICE => [
                'item' => __( 'Service', 'ltl-bookings' ),
                'items' => __( 'Services', 'ltl-bookings' ),
                'booking' => __( 'Appointment', 'ltl-bookings' ),
                'bookings' => __( 'Appointments', 'ltl-bookings' ),
                'calendar' => __( 'Schedule', 'ltl-bookings' ),
                'add_item' => __( 'Add Service', 'ltl-bookings' ),
                'edit_item' => __( 'Edit Service', 'ltl-bookings' )
            ],
            self::MODE_HOTEL => [
                'item' => __( 'Room', 'ltl-bookings' ),
                'items' => __( 'Rooms', 'ltl-bookings' ),
                'booking' => __( 'Reservation', 'ltl-bookings' ),
                'bookings' => __( 'Reservations', 'ltl-bookings' ),
                'calendar' => __( 'Room Calendar', 'ltl-bookings' ),
                'add_item' => __( 'Add Room', 'ltl-bookings' ),
                'edit_item' => __( 'Edit Room', 'ltl-bookings' )
            ]
        ];

        return $labels[ $mode ][ $key ] ?? $key;
    }

    /**
     * Export mode-specific data
     * 
     * @param string|null $mode Optional mode (defaults to current)
     * @return array Export data
     */
    public static function export_mode_data( ?string $mode = null ): array {
        if ( ! $mode ) {
            $mode = self::get_current_mode();
        }

        global $wpdb;

        $export = [
            'mode' => $mode,
            'settings' => self::get_mode_settings( $mode ),
            'services' => [],
            'appointments' => []
        ];

        // Export services
        $services_table = $wpdb->prefix . 'ltlb_services';
        
        if ( $mode === self::MODE_HOTEL ) {
            $export['services'] = $wpdb->get_results(
                "SELECT * FROM $services_table WHERE beds_type IS NOT NULL AND beds_type != ''",
                ARRAY_A
            );
        } else {
            $export['services'] = $wpdb->get_results(
                "SELECT * FROM $services_table WHERE beds_type IS NULL OR beds_type = ''",
                ARRAY_A
            );
        }

        // Export appointments for these services
        if ( ! empty( $export['services'] ) ) {
            $service_ids = array_column( $export['services'], 'id' );
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            
            $appointments_table = $wpdb->prefix . 'ltlb_appointments';
            $export['appointments'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $appointments_table WHERE service_id IN ($placeholders)",
                    $service_ids
                ),
                ARRAY_A
            );
        }

        return $export;
    }

    /**
     * Import mode-specific data
     * 
     * @param array $import_data Import data
     * @return array Import results
     */
    public static function import_mode_data( array $import_data ): array {
        global $wpdb;

        $results = [
            'services_imported' => 0,
            'appointments_imported' => 0,
            'errors' => []
        ];

        $mode = $import_data['mode'] ?? self::MODE_SERVICE;

        // Import settings
        if ( ! empty( $import_data['settings'] ) ) {
            self::update_mode_settings( $import_data['settings'], $mode );
        }

        // Import services
        $services_table = $wpdb->prefix . 'ltlb_services';
        $id_map = [];

        foreach ( $import_data['services'] ?? [] as $service ) {
            $old_id = $service['id'];
            unset( $service['id'] );
            
            $service['created_at'] = current_time( 'mysql' );
            $service['updated_at'] = current_time( 'mysql' );

            $result = $wpdb->insert( $services_table, $service );
            
            if ( $result ) {
                $new_id = $wpdb->insert_id;
                $id_map[ $old_id ] = $new_id;
                $results['services_imported']++;
            } else {
                $results['errors'][] = 'Failed to import service: ' . $service['name'];
            }
        }

        // Import appointments with mapped service IDs
        $appointments_table = $wpdb->prefix . 'ltlb_appointments';

        foreach ( $import_data['appointments'] ?? [] as $appointment ) {
            unset( $appointment['id'] );
            
            // Map service ID
            if ( isset( $id_map[ $appointment['service_id'] ] ) ) {
                $appointment['service_id'] = $id_map[ $appointment['service_id'] ];
            } else {
                continue; // Skip if service not imported
            }

            $appointment['created_at'] = current_time( 'mysql' );
            $appointment['updated_at'] = current_time( 'mysql' );

            $result = $wpdb->insert( $appointments_table, $appointment );
            
            if ( $result ) {
                $results['appointments_imported']++;
            }
        }

        return $results;
    }

    /**
     * Validate mode consistency
     * 
     * @return array Validation results
     */
    public static function validate_mode_consistency(): array {
        global $wpdb;

        $issues = [];
        $mode = self::get_current_mode();
        $services_table = $wpdb->prefix . 'ltlb_services';

        if ( $mode === self::MODE_HOTEL ) {
            // Check for services without beds_type
            $count = $wpdb->get_var(
                "SELECT COUNT(*) FROM $services_table 
                 WHERE is_active = 1 
                 AND (beds_type IS NULL OR beds_type = '')"
            );

            if ( $count > 0 ) {
                $issues[] = sprintf(
                    __( '%d services are missing room configuration (beds_type)', 'ltl-bookings' ),
                    $count
                );
            }
        } else {
            // Check for rooms in service mode
            $count = $wpdb->get_var(
                "SELECT COUNT(*) FROM $services_table 
                 WHERE is_active = 1 
                 AND beds_type IS NOT NULL 
                 AND beds_type != ''"
            );

            if ( $count > 0 ) {
                $issues[] = sprintf(
                    __( '%d rooms found in service mode (should be in hotel mode)', 'ltl-bookings' ),
                    $count
                );
            }
        }

        return [
            'valid' => empty( $issues ),
            'issues' => $issues
        ];
    }
}
