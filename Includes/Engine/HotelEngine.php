<?php
if ( ! defined('ABSPATH') ) exit;

require_once LTLB_PATH . 'Includes/Repository/ServiceRepository.php';
require_once LTLB_PATH . 'Includes/Repository/AppointmentRepository.php';
require_once LTLB_PATH . 'Includes/Repository/CustomerRepository.php';
require_once LTLB_PATH . 'Includes/Repository/ResourceRepository.php';
require_once LTLB_PATH . 'Includes/Repository/AppointmentResourcesRepository.php';
require_once LTLB_PATH . 'Includes/Repository/ServiceResourcesRepository.php';
require_once LTLB_PATH . 'Includes/Util/Time.php';

class HotelEngine implements BookingEngineInterface {

    /**
     * Hotel availability check: returns nights, free_resources_count, resource_ids, total_price_cents
     * Input: service_id, checkin (Y-m-d), checkout (Y-m-d), guests (int)
     */
    public function get_hotel_availability( int $service_id, string $checkin_date, string $checkout_date, int $guests ): array {
        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( $service_id );
        
        if ( ! $service ) {
            return [ 'error' => 'Invalid service' ];
        }

        // Calculate nights
        $nights = LTLB_Time::nights_between( $checkin_date, $checkout_date );
        if ( $nights < 1 ) {
            return [ 'error' => 'Invalid dates: checkout must be after checkin' ];
        }

        // Validate nights range
        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];
        $min_nights = (int) ( $ls['hotel_min_nights'] ?? 1 );
        $max_nights = (int) ( $ls['hotel_max_nights'] ?? 30 );

        if ( $nights < $min_nights || $nights > $max_nights ) {
            return [ 'error' => sprintf( 'Invalid nights: must be between %d and %d', $min_nights, $max_nights ) ];
        }

        // Get checkin/checkout times from settings
        $checkin_time = $ls['hotel_checkin_time'] ?? '15:00';
        $checkout_time = $ls['hotel_checkout_time'] ?? '11:00';

        // Create full datetime for range checking
        $checkin_dt = LTLB_Time::combine_date_time( $checkin_date, $checkin_time );
        $checkout_dt = LTLB_Time::combine_date_time( $checkout_date, $checkout_time );

        if ( ! $checkin_dt || ! $checkout_dt ) {
            return [ 'error' => 'Invalid date/time format' ];
        }

        $start_at_sql = LTLB_Time::format_wp_datetime( $checkin_dt );
        $end_at_sql = LTLB_Time::format_wp_datetime( $checkout_dt );

        // Get allowed resources (rooms)
        $svc_res_repo = new LTLB_ServiceResourcesRepository();
        $res_repo = new LTLB_ResourceRepository();
        $appt_res_repo = new LTLB_AppointmentResourcesRepository();

        $allowed_resources = $svc_res_repo->get_resources_for_service( $service_id );
        if ( empty( $allowed_resources ) ) {
            $all = $res_repo->get_all();
            $allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
        }

        // Get blocked/occupied resources for the date range
        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];
        $include_pending = ! empty( $ls['pending_blocks'] );
        $blocked = $appt_res_repo->get_blocked_resources( $start_at_sql, $end_at_sql, $include_pending );

        // Check which rooms are available
        $free_ids = [];
        foreach ( $allowed_resources as $rid ) {
            $r = $res_repo->get_by_id( intval($rid) );
            if ( ! $r ) continue;
            $cap = intval($r['capacity'] ?? 1);
            $used = isset($blocked[$rid]) ? intval($blocked[$rid]) : 0;
            // Available if: $used + $guests <= $cap
            if ( ($used + $guests) <= $cap ) {
                $free_ids[] = intval($rid);
            }
        }

        // Calculate total price
        $price_cents = intval($service['price_cents'] ?? 0);
        $total_price_cents = $price_cents * $nights;

        return [
            'nights' => $nights,
            'free_resources_count' => count($free_ids),
            'resource_ids' => $free_ids,
            'total_price_cents' => $total_price_cents,
        ];
    }

    public function get_time_slots(int $service_id, string $date, array $context = []): array {
        // Hotel mode doesn't use time slots; return empty
        return [];
    }

    public function create_booking(array $payload) {
        return new WP_Error('not_supported', __( 'Use hotel-specific endpoint for hotel bookings.', 'ltl-bookings' ));
    }

    public function validate_payload(array $payload) {
        return new WP_Error('not_supported', __( 'Use validate_hotel_payload for hotel bookings.', 'ltl-bookings' ));
    }

    /**
     * Validate hotel booking payload
     */
    public function validate_hotel_payload( array $payload ): bool|WP_Error {
        if ( empty($payload['service_id']) || empty($payload['checkin']) || empty($payload['checkout']) || empty($payload['email']) || empty($payload['guests']) ) {
            return new WP_Error('missing_fields', __('Please fill all required fields.', 'ltl-bookings'));
        }
        
        $guests = intval($payload['guests']);
        if ( $guests < 1 ) {
            return new WP_Error('invalid_guests', __('Number of guests must be at least 1.', 'ltl-bookings'));
        }

        return true;
    }

    /**
     * Create a hotel booking
     */
    public function create_hotel_booking( array $payload ) {
        // Validate first
        $valid = $this->validate_hotel_payload( $payload );
        if ( is_wp_error($valid) ) {
            return $valid;
        }

        $service_id = intval($payload['service_id']);
        $checkin_date = sanitize_text_field($payload['checkin']);
        $checkout_date = sanitize_text_field($payload['checkout']);
        $guests = intval($payload['guests']);

        // Check availability
        $avail = $this->get_hotel_availability( $service_id, $checkin_date, $checkout_date, $guests );
        if ( isset($avail['error']) ) {
            return new WP_Error('unavailable', $avail['error']);
        }

		$free_ids = isset( $avail['resource_ids'] ) && is_array( $avail['resource_ids'] ) ? array_values( $avail['resource_ids'] ) : [];
		if ( empty( $free_ids ) ) {
			return new WP_Error( 'unavailable', __( 'No rooms available for the selected dates.', 'ltl-bookings' ) );
		}

        $chosen = isset( $payload['resource_id'] ) ? intval( $payload['resource_id'] ) : 0;
        if ( $chosen > 0 && ! in_array( $chosen, $free_ids, true ) ) {
            return new WP_Error( 'unavailable', __( 'Selected room is no longer available.', 'ltl-bookings' ) );
        }

        // Get times from settings
        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];
        $checkin_time = $ls['hotel_checkin_time'] ?? '15:00';
        $checkout_time = $ls['hotel_checkout_time'] ?? '11:00';

        // Create full datetimes
        $checkin_dt = LTLB_Time::combine_date_time( $checkin_date, $checkin_time );
        $checkout_dt = LTLB_Time::combine_date_time( $checkout_date, $checkout_time );

        if ( ! $checkin_dt || ! $checkout_dt ) {
            return new WP_Error('invalid_date', __('Invalid date/time.', 'ltl-bookings'));
        }

        $start_at_sql = LTLB_Time::format_wp_datetime( $checkin_dt );
        $end_at_sql = LTLB_Time::format_wp_datetime( $checkout_dt );

        // Upsert customer
        $customer_repo = new LTLB_CustomerRepository();
        $customer_id = $customer_repo->upsert_by_email( [
            'email'      => $payload['email'],
            'first_name' => $payload['first'] ?? '',
            'last_name'  => $payload['last'] ?? '',
            'phone'      => $payload['phone'] ?? '',
        ] );

        if ( ! $customer_id ) {
            return new WP_Error( 'customer_error', __( 'Unable to save customer.', 'ltl-bookings' ) );
        }

        $default_status = $ls['default_status'] ?? 'pending';

        // Create appointment with seats=guests
        $appointment_repo = new LTLB_AppointmentRepository();
        $appt_id = $appointment_repo->create( [
            'service_id'  => $service_id,
            'customer_id' => $customer_id,
            'start_at'    => $checkin_dt,
            'end_at'      => $checkout_dt,
            'status'      => $default_status,
            'timezone'    => LTLB_Time::get_site_timezone_string(),
            'seats'       => $guests,
			'skip_conflict_check' => true,
        ] );

        if ( is_wp_error( $appt_id ) ) {
            return $appt_id;
        }

        // Assign resource (room)
        $appt_res_repo = new LTLB_AppointmentResourcesRepository();
        if ( $chosen > 0 ) {
            $appt_res_repo->set_resource_for_appointment( intval($appt_id), $chosen );
        } else {
            // Auto-assign first available room computed during availability check
            $rid = intval( $free_ids[0] );
            $appt_res_repo->set_resource_for_appointment( intval($appt_id), $rid );
        }

        // Send notifications
        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( $service_id );
        $customer = $customer_repo->get_by_id( $customer_id );
        if ( class_exists( 'LTLB_Mailer' ) ) {
            LTLB_Mailer::send_booking_notifications( $appt_id, $service ?: [], $customer ?: [], $start_at_sql, $end_at_sql, $default_status, $guests );
        }

        return $appt_id;
    }
}
