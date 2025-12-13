<?php
if ( ! defined('ABSPATH') ) exit;

class Availability {
    /**
     * Compute availability for a given service on a given date.
     * Returns array keyed by staff user ID with arrays of available intervals [ ['start' => 'HH:MM:SS','end' => 'HH:MM:SS'], ... ]
     *
     * @param int $service_id
     * @param string $date YYYY-MM-DD
     * @return array
     */
    public function compute_availability(int $service_id, string $date): array {
        // load repositories
        $service_repo = new LTLB_ServiceRepository();
        $hours_repo = new StaffHoursRepository();
        $exceptions_repo = new StaffExceptionsRepository();
        $appt_repo = new LTLB_AppointmentRepository();
        $service_resources_repo = new LTLB_ServiceResourcesRepository();
        $resource_repo = new LTLB_ResourceRepository();
        $appt_resource_repo = new LTLB_AppointmentResourcesRepository();

        $service = $service_repo->get_by_id( $service_id );
        if ( ! $service ) return [];

        $duration_min = intval( $service['duration_min'] ?? 60 );
        $buffer_before = intval( $service['buffer_before_min'] ?? 0 );
        $buffer_after = intval( $service['buffer_after_min'] ?? 0 );

        $weekday = (int) date('w', strtotime($date)); // 0 (Sunday) - 6

        $result = [];

        $staff_users = get_users( ['role' => 'ltlb_staff'] );
        foreach ( $staff_users as $u ) {
            $user_id = intval( $u->ID );

            // get weekly hours
            $weekly = $hours_repo->get_weekly( $user_id );
            $found = null;
            foreach ( $weekly as $w ) {
                if ( intval($w['weekday']) === $weekday ) {
                    $found = $w;
                    break;
                }
            }
            if ( ! $found || empty( $found['is_active'] ) ) {
                // no working hours
                $result[$user_id] = [];
                continue;
            }

            $work_start = $found['start_time'];
            $work_end = $found['end_time'];

            // check exceptions for that date
            $exceptions = $exceptions_repo->get_range( $user_id, $date, $date );
            if ( ! empty( $exceptions ) ) {
                $ex = $exceptions[0];
                if ( ! empty( $ex['is_off_day'] ) ) {
                    $result[$user_id] = [];
                    continue;
                }
                // override times if provided
                if ( ! empty( $ex['start_time'] ) ) $work_start = $ex['start_time'];
                if ( ! empty( $ex['end_time'] ) ) $work_end = $ex['end_time'];
            }

            // get appointments for this staff on the date
            $from = $date . ' 00:00:00';
            $to = $date . ' 23:59:59';
            $appts = $appt_repo->get_all( [ 'from' => $from, 'to' => $to, 'service_id' => $service_id ] );

            // build occupied intervals for this staff
            $occupied = [];
            foreach ( $appts as $a ) {
                if ( intval($a['staff_user_id']) !== $user_id ) continue;
                $s = $a['start_at'];
                $e = $a['end_at'];
                if ( empty($s) || empty($e) ) continue;
                // expand by buffers
                $s_dt = new DateTime( $s );
                $e_dt = new DateTime( $e );
                if ( $buffer_before > 0 ) $s_dt->sub( new DateInterval('PT' . $buffer_before . 'M') );
                if ( $buffer_after > 0 ) $e_dt->add( new DateInterval('PT' . $buffer_after . 'M') );
                $occupied[] = [ 'start' => $s_dt->format('H:i:s'), 'end' => $e_dt->format('H:i:s') ];
            }

            // merge occupied intervals
            usort( $occupied, function($a,$b){ return strcmp($a['start'],$b['start']); } );
            $merged = [];
            foreach ( $occupied as $int ) {
                if ( empty($merged) ) { $merged[] = $int; continue; }
                $last = &$merged[count($merged)-1];
                if ( $int['start'] <= $last['end'] ) {
                    // overlap
                    if ( $int['end'] > $last['end'] ) $last['end'] = $int['end'];
                } else {
                    $merged[] = $int;
                }
            }

            // compute free intervals between work_start..work_end excluding merged occupied
            $free = [];
            $cursor = $work_start;
            foreach ( $merged as $m ) {
                if ( $m['start'] > $cursor ) {
                    $free[] = [ 'start' => $cursor, 'end' => $m['start'] ];
                }
                if ( $m['end'] > $cursor ) $cursor = $m['end'];
            }
            if ( $cursor < $work_end ) {
                $free[] = [ 'start' => $cursor, 'end' => $work_end ];
            }
            // filter free intervals shorter than service duration
            $valid = [];
            // determine allowed resources for this service
            $allowed_resources = $service_resources_repo->get_resources_for_service( $service_id );
            if ( empty( $allowed_resources ) ) {
                // allow any active resource
                $all = $resource_repo->get_all();
                $allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
            }

            // should pending appointments block? (option)
            $include_pending = get_option('ltlb_pending_blocks', 0) ? true : false;

            foreach ( $free as $f ) {
                $s_dt = DateTime::createFromFormat('H:i:s', $f['start']);
                $e_dt = DateTime::createFromFormat('H:i:s', $f['end']);
                if ( ! $s_dt || ! $e_dt ) continue;
                $diff = ( $e_dt->getTimestamp() - $s_dt->getTimestamp() ) / 60;
                if ( $diff < $duration_min ) continue;

                // Check resource occupancy conservatively over the whole interval: any appointment overlapping any part
                // will be counted as occupying that resource. If at least one allowed resource has available capacity
                // (capacity > overlapping_count) we keep the interval.
                $interval_start_dt = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $f['start']);
                $interval_end_dt = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $f['end']);
                if ( ! $interval_start_dt || ! $interval_end_dt ) {
                    $valid[] = $f; // can't analyze, keep it
                    continue;
                }

                $blocked_counts = $appt_resource_repo->get_blocked_resources( $interval_start_dt->format('Y-m-d H:i:s'), $interval_end_dt->format('Y-m-d H:i:s'), $include_pending );

                $has_available = false;
                foreach ( $allowed_resources as $rid ) {
                    $res = $resource_repo->get_by_id( intval($rid) );
                    if ( ! $res ) continue;
                    $capacity = intval( $res['capacity'] ?? 1 );
                    $used = isset( $blocked_counts[ $rid ] ) ? intval( $blocked_counts[ $rid ] ) : 0;
                    if ( $used < $capacity ) {
                        $has_available = true;
                        break;
                    }
                }

                if ( $has_available ) {
                    $valid[] = $f;
                }
            }

            $result[$user_id] = $valid;
        }

        return $result;
    }

    /**
     * Compute time slots (start datetimes) for each staff user where the full service duration fits.
     * Returns array keyed by staff user ID with arrays of slots: [ ['start' => 'YYYY-MM-DD HH:MM:SS','end' => 'YYYY-MM-DD HH:MM:SS'], ... ]
     *
     * @param int $service_id
     * @param string $date
     * @param int $step_min step size for candidate starts (default 15)
     * @return array
     */
    public function compute_time_slots(int $service_id, string $date, int $step_min = 15): array {
        $avail = $this->compute_availability($service_id, $date);
        $service_repo = new LTLB_ServiceRepository();
        $service = $service_repo->get_by_id( $service_id );
        if ( ! $service ) return [];
        $duration_min = intval( $service['duration_min'] ?? 60 );

        $service_resources_repo = new LTLB_ServiceResourcesRepository();
        $resource_repo = new LTLB_ResourceRepository();
        $appt_resource_repo = new LTLB_AppointmentResourcesRepository();
        $include_pending = get_option('ltlb_pending_blocks', 0) ? true : false;

        $slots_by_user = [];
        foreach ( $avail as $user_id => $intervals ) {
            $slots_by_user[$user_id] = [];
            foreach ( $intervals as $int ) {
                // int['start'] and ['end'] are H:i:s
                $cursor = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $int['start']);
                $interval_end = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $int['end']);
                if ( ! $cursor || ! $interval_end ) continue;

                // iterate by step_min and add slots where full duration fits
                while ( true ) {
                        $slot_end = clone $cursor;
                        $slot_end->add( new DateInterval('PT' . $duration_min . 'M') );
                        if ( $slot_end > $interval_end ) break;

                        // determine allowed resources for this service
                        $allowed_resources = $service_resources_repo->get_resources_for_service( $service_id );
                        if ( empty( $allowed_resources ) ) {
                            $all = $resource_repo->get_all();
                            $allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
                        }

                        // compute blocked counts for this specific slot interval
                        $blocked_counts = $appt_resource_repo->get_blocked_resources( $cursor->format('Y-m-d H:i:s'), $slot_end->format('Y-m-d H:i:s'), $include_pending );

                        $available_ids = [];
                        foreach ( $allowed_resources as $rid ) {
                            $res = $resource_repo->get_by_id( intval($rid) );
                            if ( ! $res ) continue;
                            $capacity = intval( $res['capacity'] ?? 1 );
                            $used = isset( $blocked_counts[ $rid ] ) ? intval( $blocked_counts[ $rid ] ) : 0;
                            if ( $used < $capacity ) {
                                $available_ids[] = intval($rid);
                            }
                        }

                        $slots_by_user[$user_id][] = [
                            'start' => $cursor->format('Y-m-d H:i:s'),
                            'end' => $slot_end->format('Y-m-d H:i:s'),
                            'free_resources_count' => count($available_ids),
                            'resource_ids' => $available_ids,
                        ];

                        $cursor->add( new DateInterval('PT' . $step_min . 'M') );
                }
            }
        }

        return $slots_by_user;
    }
}
