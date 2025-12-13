<?php
if ( ! defined('ABSPATH') ) exit;

interface BookingEngineInterface {
    /**
     * Return time slots for service (or equivalent) for a given date.
     * Should return same structure as previous compute_time_slots for compatibility.
     *
     * @param int $service_id
     * @param string $date YYYY-MM-DD
     * @param array $context optional
     * @return array
     */
    public function get_time_slots(int $service_id, string $date, array $context = []): array;

    /**
     * Create a booking using engine-specific payload. Return appointment ID or WP_Error.
     *
     * @param array $payload
     * @return int|WP_Error
     */
    public function create_booking(array $payload);

    /**
     * Optional validation hook; return true or WP_Error.
     * @param array $payload
     * @return bool|WP_Error
     */
    public function validate_payload(array $payload);
}
