<?php
if ( ! defined('ABSPATH') ) exit;

require_once LTLB_PATH . 'Includes/Repository/ServiceRepository.php';
require_once LTLB_PATH . 'Includes/Repository/AppointmentRepository.php';
require_once LTLB_PATH . 'Includes/Repository/ResourceRepository.php';
require_once LTLB_PATH . 'Includes/Repository/AppointmentResourcesRepository.php';
require_once LTLB_PATH . 'Includes/Repository/ServiceResourcesRepository.php';
require_once LTLB_PATH . 'Includes/Repository/StaffHoursRepository.php';
require_once LTLB_PATH . 'Includes/Repository/StaffExceptionsRepository.php';
require_once LTLB_PATH . 'Includes/Util/Time.php';

class Availability {
    /**
     * Compute availability for a given service and date.
     * Historical endpoint compatibility: returns an object wrapper.
     *
     * Current implementation returns discrete time slots under `slots`.
     */
    public function compute_availability(int $service_id, string $date, int $step = 15): array {
        return [
            'slots' => $this->compute_time_slots( $service_id, $date, $step ),
        ];
    }

    /**
     * Compute time slots for a given service and date.
     * Returns array of slots with keys: time, start, end, free_resources_count, resource_ids, spots_left
     * For group services: spots_left = min available seats across all free resources
     * Now also checks staff availability via StaffHours and StaffExceptions.
     */
    public function compute_time_slots(int $service_id, string $date, int $step = 15): array {
        $service_repo = new LTLB_ServiceRepository();
        $appt_repo = new LTLB_AppointmentRepository();
        $res_repo = new LTLB_ResourceRepository();
        $appt_res_repo = new LTLB_AppointmentResourcesRepository();
        $svc_res_repo = new LTLB_ServiceResourcesRepository();
        $staff_hours_repo = new StaffHoursRepository();
        $staff_exceptions_repo = new StaffExceptionsRepository();

        $service = $service_repo->get_by_id( $service_id );
        $duration = $service && isset($service['duration_min']) ? intval($service['duration_min']) : 60;

        $tz = LTLB_Time::wp_timezone();
        $day_start = new DateTimeImmutable($date . ' 00:00:00', $tz);

        // Block bookings in the past (server-side). For today, filter out slots earlier than now.
        $now = new DateTimeImmutable('now', $tz);
        $today_start = $now->setTime(0, 0, 0);
        if ( $day_start < $today_start ) {
            return [];
        }
        $is_today = ( $day_start->format('Y-m-d') === $today_start->format('Y-m-d') );

        // Get working hours from settings (default fallback)
        $settings = get_option('lazy_settings', []);
        $default_start = isset($settings['working_hours_start']) ? intval($settings['working_hours_start']) : 9;
        $default_end = isset($settings['working_hours_end']) ? intval($settings['working_hours_end']) : 17;
        
        // Check if service has a staff member assigned
        $staff_user_id = isset($service['staff_user_id']) ? intval($service['staff_user_id']) : 0;
        
        // Get staff-specific hours if available
        $window_start = $day_start->setTime($default_start, 0);
        $window_end = $day_start->setTime($default_end, 0);

        // Service-specific availability limits (optional)
        if ( $service ) {
            $weekday = intval($day_start->format('w')); // 0=Sunday, 6=Saturday

            $allowed_days_raw = isset($service['available_weekdays']) ? trim((string)$service['available_weekdays']) : '';
            if ( $allowed_days_raw !== '' ) {
                $allowed_days = [];
                $parts = preg_split('/\s*,\s*/', $allowed_days_raw);
                foreach ( $parts as $p ) {
                    if ( $p === '' || $p === null ) continue;
                    $d = intval($p);
                    if ( $d < 0 || $d > 6 ) continue;
                    $allowed_days[] = $d;
                }
                $allowed_days = array_values(array_unique($allowed_days));
                if ( ! in_array( $weekday, $allowed_days, true ) ) {
                    return [];
                }
            }

            $svc_start = isset($service['available_start_time']) ? trim((string)$service['available_start_time']) : '';
            $svc_end = isset($service['available_end_time']) ? trim((string)$service['available_end_time']) : '';
            if ( $svc_start !== '' && $svc_end !== '' ) {
                // Normalize TIME columns (HH:MM or HH:MM:SS)
                if ( preg_match('/^(\d{2}):(\d{2})/', $svc_start, $m1) && preg_match('/^(\d{2}):(\d{2})/', $svc_end, $m2) ) {
                    $service_window_start = $day_start->setTime(intval($m1[1]), intval($m1[2]));
                    $service_window_end = $day_start->setTime(intval($m2[1]), intval($m2[2]));

                    // Intersect with current window
                    if ( $service_window_start > $window_start ) {
                        $window_start = $service_window_start;
                    }
                    if ( $service_window_end < $window_end ) {
                        $window_end = $service_window_end;
                    }

                    if ( $window_end <= $window_start ) {
                        return [];
                    }
                }
            }
        }
        
        if ($staff_user_id > 0) {
            // Check for staff exceptions (off days or special hours)
            $exceptions = $staff_exceptions_repo->get_range($staff_user_id, $date, $date);
            if (!empty($exceptions)) {
                $exception = $exceptions[0];
                if (!empty($exception['is_off_day'])) {
                    // Staff is off this day - no slots available
                    return [];
                }
                // Use exception hours if provided
                if (!empty($exception['start_time']) && !empty($exception['end_time'])) {
                    $parts_start = explode(':', $exception['start_time']);
                    $parts_end = explode(':', $exception['end_time']);
                    $window_start = $day_start->setTime(intval($parts_start[0]), intval($parts_start[1]));
                    $window_end = $day_start->setTime(intval($parts_end[0]), intval($parts_end[1]));
                }
            } else {
                // Check weekly hours
                $weekday = intval($day_start->format('w')); // 0=Sunday, 6=Saturday
                $weekly_hours = $staff_hours_repo->get_weekly($staff_user_id);
                foreach ($weekly_hours as $wh) {
                    if (intval($wh['weekday']) === $weekday && !empty($wh['is_active'])) {
                        $parts_start = explode(':', $wh['start_time']);
                        $parts_end = explode(':', $wh['end_time']);
                        $window_start = $day_start->setTime(intval($parts_start[0]), intval($parts_start[1]));
                        $window_end = $day_start->setTime(intval($parts_end[0]), intval($parts_end[1]));
                        break;
                    }
                }
            }
        }

        // If staff window got applied after service window, re-apply intersection with service window
        if ( $service ) {
            $svc_start = isset($service['available_start_time']) ? trim((string)$service['available_start_time']) : '';
            $svc_end = isset($service['available_end_time']) ? trim((string)$service['available_end_time']) : '';
            if ( $svc_start !== '' && $svc_end !== '' ) {
                if ( preg_match('/^(\d{2}):(\d{2})/', $svc_start, $m1) && preg_match('/^(\d{2}):(\d{2})/', $svc_end, $m2) ) {
                    $service_window_start = $day_start->setTime(intval($m1[1]), intval($m1[2]));
                    $service_window_end = $day_start->setTime(intval($m2[1]), intval($m2[2]));
                    if ( $service_window_start > $window_start ) {
                        $window_start = $service_window_start;
                    }
                    if ( $service_window_end < $window_end ) {
                        $window_end = $service_window_end;
                    }
                    if ( $window_end <= $window_start ) {
                        return [];
                    }
                }
            }
        }

        $slots = [];

        $availability_mode = $service && isset($service['availability_mode']) ? sanitize_key((string)$service['availability_mode']) : 'window';
        if ( ! in_array($availability_mode, ['window','fixed'], true) ) {
            $availability_mode = 'window';
        }

        $fixed_times_for_day = [];
        if ( $availability_mode === 'fixed' && $service ) {
            $weekday = intval($day_start->format('w')); // 0=Sunday, 6=Saturday
            $raw = isset($service['fixed_weekly_slots']) ? (string)$service['fixed_weekly_slots'] : '';
            if ( $raw !== '' ) {
                $decoded = json_decode($raw, true);
                if ( is_array($decoded) ) {
                    foreach ( $decoded as $row ) {
                        if ( ! is_array($row) ) continue;
                        if ( ! isset($row['weekday'], $row['time']) ) continue;
                        $w = intval($row['weekday']);
                        $t = trim((string)$row['time']);
                        if ( $w !== $weekday ) continue;
                        if ( ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ) continue;
                        $fixed_times_for_day[] = $t;
                    }
                }
            }
            $fixed_times_for_day = array_values(array_unique($fixed_times_for_day));
            sort($fixed_times_for_day);

            // If fixed mode is selected but no fixed times match this weekday, there are no slots.
            if ( empty($fixed_times_for_day) ) {
                return [];
            }
        }

        // Allowed resources for service
        $allowed_resources = $svc_res_repo->get_resources_for_service($service_id);
        if ( empty($allowed_resources) ) {
            $all = $res_repo->get_all();
            $allowed_resources = array_map(function($r){ return intval($r['id']); }, $all );
        }

        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];
        $include_pending = ! empty( $ls['pending_blocks'] );

        if ( $availability_mode === 'fixed' ) {
            foreach ( $fixed_times_for_day as $t ) {
                if ( ! preg_match('/^(\d{2}):(\d{2})$/', $t, $m) ) continue;
                $slot_start = $day_start->setTime(intval($m[1]), intval($m[2]));
                $slot_end = $slot_start->modify('+' . intval($duration) . ' minutes');

                if ( $is_today && $slot_start < $now ) continue;

                // Must fit within effective window (global/staff and optional service window)
                if ( $slot_start < $window_start ) continue;
                if ( $slot_end > $window_end ) continue;

                $start_sql = LTLB_Time::format_wp_datetime( $slot_start );
                $end_sql = LTLB_Time::format_wp_datetime( $slot_end );

                $blocked = [];
                if ( method_exists($appt_res_repo, 'get_blocked_resources') ) {
                    $blocked = $appt_res_repo->get_blocked_resources( $start_sql, $end_sql, $include_pending );
                }

                $free_ids = [];
                $min_available = PHP_INT_MAX;
                foreach ( $allowed_resources as $rid ) {
                    $res = $res_repo->get_by_id( intval($rid) );
                    if ( ! $res ) continue;
                    $cap = intval($res['capacity'] ?? 1);
                    $used = isset($blocked[$rid]) ? intval($blocked[$rid]) : 0;
                    $available = max(0, $cap - $used);
                    if ( $available > 0 ) {
                        $free_ids[] = intval($rid);
                        $min_available = min($min_available, $available);
                    }
                }

                $spots_left = ($min_available === PHP_INT_MAX) ? 0 : $min_available;

                $slots[] = [
                    'time' => $slot_start->format('H:i'),
                    'start' => $start_sql,
                    'end' => $end_sql,
                    'free_resources_count' => count($free_ids),
                    'resource_ids' => $free_ids,
                    'spots_left' => $spots_left,
                ];
            }

            return $slots;
        }

        $cursor = $window_start;
        while ( $cursor <= $window_end ) {
            $slot_start = $cursor;
            $slot_end = $slot_start->modify('+' . intval($duration) . ' minutes');
            if ( $slot_end > $window_end ) break;

            if ( $is_today && $slot_start < $now ) {
                $cursor = $cursor->modify('+' . intval($step) . ' minutes');
                continue;
            }

            $start_sql = LTLB_Time::format_wp_datetime( $slot_start );
            $end_sql = LTLB_Time::format_wp_datetime( $slot_end );

            // get blocked counts per resource for this interval
            $blocked = [];
            if ( method_exists($appt_res_repo, 'get_blocked_resources') ) {
                $blocked = $appt_res_repo->get_blocked_resources( $start_sql, $end_sql, $include_pending );
            }

            // compute free resources from allowed list
            $free_ids = [];
            $min_available = PHP_INT_MAX; // for group services
            foreach ( $allowed_resources as $rid ) {
                $res = $res_repo->get_by_id( intval($rid) );
                if ( ! $res ) continue;
                $cap = intval($res['capacity'] ?? 1);
                $used = isset($blocked[$rid]) ? intval($blocked[$rid]) : 0;
                $available = max(0, $cap - $used);
                if ( $available > 0 ) {
                    $free_ids[] = intval($rid);
                    $min_available = min($min_available, $available);
                }
            }

            // spots_left = minimum available seats across all free resources (for group bookings)
            $spots_left = ($min_available === PHP_INT_MAX) ? 0 : $min_available;

            $slots[] = [
                'time' => $slot_start->format('H:i'),
                'start' => $start_sql,
                'end' => $end_sql,
                'free_resources_count' => count($free_ids),
                'resource_ids' => $free_ids,
                'spots_left' => $spots_left,
            ];

            $cursor = $cursor->modify('+' . intval($step) . ' minutes');
        }

        return $slots;
    }
}
