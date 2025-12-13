<?php
if ( ! defined('ABSPATH') ) exit;

require_once LTLB_PATH . 'Includes/Util/Availability.php';
require_once LTLB_PATH . 'Includes/Repository/AppointmentResourcesRepository.php';
require_once LTLB_PATH . 'Includes/Repository/ServiceResourcesRepository.php';
require_once LTLB_PATH . 'Includes/Repository/ResourceRepository.php';
require_once LTLB_PATH . 'Includes/Repository/AppointmentRepository.php';
require_once LTLB_PATH . 'Includes/Repository/CustomerRepository.php';

class ServiceEngine implements BookingEngineInterface {
    public function get_time_slots(int $service_id, string $date, array $context = []): array {
        $avail = new Availability();
        return $avail->compute_time_slots( $service_id, $date, intval($context['step'] ?? 15) );
    }

    public function validate_payload(array $payload) {
        // basic validation
        if ( empty($payload['service_id']) || empty($payload['date']) || empty($payload['time']) || empty($payload['email']) ) {
            return new WP_Error('missing_fields', __('Please fill the required fields.', 'ltl-bookings'));
        }
        return true;
    }

    public function create_booking(array $payload) {
        // This mirrors the existing Shortcodes::_create_appointment_from_submission logic
        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( $payload['service_id'] );
        $duration = $service && isset( $service['duration_min'] ) ? intval( $service['duration_min'] ) : 60;

        $start_dt = LTLB_Time::parse_date_and_time( $payload['date'], $payload['time'] );
        if ( ! $start_dt ) {
            return new WP_Error( 'invalid_date', __( 'Invalid date/time.', 'ltl-bookings' ) );
        }

        $end_dt = $start_dt->modify( '+' . intval( $duration ) . ' minutes' );

        $start_at_sql = LTLB_Time::format_wp_datetime( $start_dt );
        $end_at_sql = LTLB_Time::format_wp_datetime( $end_dt );

        // upsert customer
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

        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];
        $default_status = $ls['default_status'] ?? 'pending';

        $appointment_repo = new LTLB_AppointmentRepository();
        $appt_id = $appointment_repo->create( [
            'service_id'  => $payload['service_id'],
            'customer_id' => $customer_id,
            'start_at'    => $start_dt,
            'end_at'      => $end_dt,
            'status'      => $default_status,
            'timezone'    => LTLB_Time::get_site_timezone_string(),
        ] );

        if ( is_wp_error( $appt_id ) ) {
            return $appt_id;
        }

        // resource assignment
        $service_resources_repo = new LTLB_ServiceResourcesRepository();
        $resource_repo = new LTLB_ResourceRepository();
        $appt_resource_repo = new LTLB_AppointmentResourcesRepository();

        $allowed_resources = $service_resources_repo->get_resources_for_service( intval( $payload['service_id'] ) );
        if ( empty( $allowed_resources ) ) {
            $all = $resource_repo->get_all();
            $allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
        }

        $include_pending = get_option('ltlb_pending_blocks', 0) ? true : false;
        $blocked_counts = $appt_resource_repo->get_blocked_resources( $start_at_sql, $end_at_sql, $include_pending );

        $chosen = isset($payload['resource_id']) ? intval($payload['resource_id']) : 0;
        if ( $chosen > 0 && in_array($chosen, $allowed_resources, true) ) {
            $res = $resource_repo->get_by_id( $chosen );
            if ( $res ) {
                $cap = intval($res['capacity'] ?? 1);
                $used = isset($blocked_counts[$chosen]) ? intval($blocked_counts[$chosen]) : 0;
                if ( $used < $cap ) {
                    $appt_resource_repo->set_resource_for_appointment( intval($appt_id), $chosen );
                }
            }
        } else {
            foreach ( $allowed_resources as $rid ) {
                $res = $resource_repo->get_by_id( intval($rid) );
                if ( ! $res ) continue;
                $capacity = intval( $res['capacity'] ?? 1 );
                $used = isset( $blocked_counts[ $rid ] ) ? intval( $blocked_counts[ $rid ] ) : 0;
                if ( $used < $capacity ) {
                    $appt_resource_repo->set_resource_for_appointment( intval($appt_id), intval($rid) );
                    break;
                }
            }
        }

        // Send notifications as before
        $service = $service_repo->get_by_id( $payload['service_id'] );
        $customer = $customer_repo->get_by_id( $customer_id );
        if ( class_exists( 'LTLB_Mailer' ) ) {
            LTLB_Mailer::send_booking_notifications( $appt_id, $service ?: [], $customer ?: [], $start_at_sql, $end_at_sql, $default_status );
        }

        return $appt_id;
    }
}
