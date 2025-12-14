<?php

class StaffHoursRepository {
    public function get_weekly($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_staff_hours';
        $user_id = (int) $user_id;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, weekday, start_time, end_time, is_active FROM $table WHERE user_id = %d ORDER BY weekday ASC",
                $user_id
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    public function save_weekly($user_id, $rows) {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_staff_hours';
        $user_id = (int) $user_id;

        // Remove existing entries for the user
        $wpdb->delete($table, ['user_id' => $user_id], ['%d']);

        // Insert provided rows. Expect $rows as array of arrays with keys: weekday, start_time, end_time, is_active
        foreach ($rows as $r) {
            $weekday = isset($r['weekday']) ? (int) $r['weekday'] : 0;
            $start_time = isset($r['start_time']) ? $r['start_time'] : '00:00:00';
            $end_time = isset($r['end_time']) ? $r['end_time'] : '00:00:00';
            $is_active = !empty($r['is_active']) ? 1 : 0;

            $wpdb->insert(
                $table,
                [
                    'user_id' => $user_id,
                    'weekday' => $weekday,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'is_active' => $is_active
                ],
                ['%d','%d','%s','%s','%d']
            );
        }

        return true;
    }
}

if ( ! class_exists( 'LTLB_StaffHoursRepository' ) ) {
    class LTLB_StaffHoursRepository extends StaffHoursRepository {}
}
