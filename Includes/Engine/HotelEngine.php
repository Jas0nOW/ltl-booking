<?php
if ( ! defined('ABSPATH') ) exit;

class HotelEngine implements BookingEngineInterface {
    public function get_time_slots(int $service_id, string $date, array $context = []): array {
        // Hotel mode not implemented yet
        return [];
    }

    public function create_booking(array $payload) {
        return new WP_Error('not_supported', __( 'Hotel booking is not supported yet.', 'ltl-bookings' ));
    }

    public function validate_payload(array $payload) {
        return new WP_Error('not_supported', __( 'Hotel mode not implemented.', 'ltl-bookings' ));
    }
}
