<?php
if ( ! defined('ABSPATH') ) exit;

require_once LTLB_PATH . 'Includes/Repository/ServiceRepository.php';
require_once LTLB_PATH . 'Includes/Repository/AppointmentRepository.php';
require_once LTLB_PATH . 'Includes/Repository/ResourceRepository.php';
require_once LTLB_PATH . 'Includes/Repository/AppointmentResourcesRepository.php';
require_once LTLB_PATH . 'Includes/Repository/ServiceResourcesRepository.php';
require_once LTLB_PATH . 'Includes/Util/Time.php';

class Availability {
    /**
     * Compute time slots for a given service and date.
     * Returns array of slots with keys: time, start, end, free_resources_count, resource_ids
     */
    public function compute_time_slots(int $service_id, string $date, int $step = 15): array {
        $service_repo = new LTLB_ServiceRepository();
        $appt_repo = new LTLB_AppointmentRepository();
        $res_repo = new LTLB_ResourceRepository();
        $appt_res_repo = new LTLB_AppointmentResourcesRepository();
        $svc_res_repo = new LTLB_ServiceResourcesRepository();

        $service = $service_repo->get_by_id( $service_id );
        $duration = $service && isset($service['duration_min']) ? intval($service['duration_min']) : 60;

        $tz = LTLB_Time::wp_timezone();
        $day_start = new DateTimeImmutable($date . ' 00:00:00', $tz);

        // default business window 08:00 - 20:00
        $window_start = $day_start->setTime(8,0);
        $window_end = $day_start->setTime(20,0);

        $slots = [];

        // Allowed resources for service
        $allowed_resources = $svc_res_repo->get_resources_for_service($service_id);
        if ( empty($allowed_resources) ) {
            $all = $res_repo->get_all();
            $allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
        }

        $include_pending = get_option('ltlb_pending_blocks', 0) ? true : false;

        $cursor = $window_start;
        while ( $cursor <= $window_end ) {
            $slot_start = $cursor;
            $slot_end = $slot_start->modify('+' . intval($duration) . ' minutes');
            if ( $slot_end > $window_end ) break;

            $start_sql = LTLB_Time::format_wp_datetime( $slot_start );
            $end_sql = LTLB_Time::format_wp_datetime( $slot_end );

            // get blocked counts per resource for this interval
            $blocked = [];
            if ( method_exists($appt_res_repo, 'get_blocked_resources') ) {
                $blocked = $appt_res_repo->get_blocked_resources( $start_sql, $end_sql, $include_pending );
            }

            // compute free resources from allowed list
            $free_ids = [];
            foreach ( $allowed_resources as $rid ) {
                $res = $res_repo->get_by_id( intval($rid) );
                if ( ! $res ) continue;
                $cap = intval($res['capacity'] ?? 1);
                $used = isset($blocked[$rid]) ? intval($blocked[$rid]) : 0;
                if ( $used < $cap ) $free_ids[] = intval($rid);
            }

            $slots[] = [
                'time' => $slot_start->format('H:i'),
                'start' => $start_sql,
                'end' => $end_sql,
                'free_resources_count' => count($free_ids),
                'resource_ids' => $free_ids,
            ];

            $cursor = $cursor->modify('+' . intval($step) . ' minutes');
        }

        return $slots;
    }
}
