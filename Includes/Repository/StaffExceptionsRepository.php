<?php

class StaffExceptionsRepository {
    public function get_range($user_id, $from, $to) {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_staff_exceptions';
        $user_id = (int) $user_id;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, date, is_off_day, start_time, end_time, note FROM $table WHERE user_id = %d AND date BETWEEN %s AND %s ORDER BY date ASC",
                $user_id, $from, $to
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    public function create($user_id, $date, $is_off_day, $start_time, $end_time, $note) {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_staff_exceptions';
        $user_id = (int) $user_id;
        $is_off_day = !empty($is_off_day) ? 1 : 0;

        $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'date' => $date,
                'is_off_day' => $is_off_day,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'note' => $note
            ],
            ['%d','%s','%d','%s','%s','%s']
        );

        return $wpdb->insert_id ?: false;
    }

    public function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_staff_exceptions';
        $id = (int) $id;

        return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
    }
}
