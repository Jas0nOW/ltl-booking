<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Time {

    public static function wp_timezone(): DateTimeZone {
        // Allow plugin setting to override site timezone for plugin operations
        $plugin_tz = get_option( 'ltlb_timezone', '' );
        if ( ! empty( $plugin_tz ) ) {
            try {
                return new DateTimeZone( $plugin_tz );
            } catch ( Exception $e ) {
                // fallback to WP timezone
            }
        }

        if ( function_exists('wp_timezone') ) {
            return wp_timezone();
        }

        $tz = get_option('timezone_string');
        if ( ! $tz ) {
            return new DateTimeZone( 'UTC' );
        }
        return new DateTimeZone( $tz );
    }

    public static function get_site_timezone_string(): string {
        $tz = get_option('timezone_string');
        if ( $tz ) return $tz;
        return 'UTC';
    }

    /**
     * Create DateTimeImmutable from various formats in site timezone.
     * Accepts 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'.
     */
    public static function create_datetime_immutable( string $datetime ): ?DateTimeImmutable {
        $tz = self::wp_timezone();

        $formats = [ 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' ];
        foreach ( $formats as $fmt ) {
            $dt = DateTimeImmutable::createFromFormat( $fmt, $datetime, $tz );
            if ( $dt !== false ) return $dt->setTimezone( $tz );
        }

        // Try generic parse
        try {
            $dt2 = new DateTimeImmutable( $datetime, $tz );
            return $dt2->setTimezone( $tz );
        } catch ( Exception $e ) {
            return null;
        }
    }

    public static function parse_date_and_time( string $date, string $time ): ?DateTimeImmutable {
        $tz = self::wp_timezone();
        $combined = trim( $date . ' ' . $time );
        // Try common formats
        $fmts = [ 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' ];
        foreach ( $fmts as $fmt ) {
            $dt = DateTimeImmutable::createFromFormat( $fmt, $combined, $tz );
            if ( $dt !== false ) return $dt->setTimezone( $tz );
        }

        try {
            $dt = new DateTimeImmutable( $combined, $tz );
            return $dt->setTimezone( $tz );
        } catch ( Exception $e ) {
            return null;
        }
    }

    public static function format_wp_datetime( DateTimeInterface $dt ): string {
        return $dt->format('Y-m-d H:i:s');
    }

    public static function day_start( DateTimeInterface $dt ): DateTimeImmutable {
        $tz = $dt->getTimezone();
        return new DateTimeImmutable( $dt->format('Y-m-d') . ' 00:00:00', $tz );
    }

    public static function day_end( DateTimeInterface $dt ): DateTimeImmutable {
        $tz = $dt->getTimezone();
        return new DateTimeImmutable( $dt->format('Y-m-d') . ' 23:59:59', $tz );
    }

    /**
     * Generate slot start DateTimeImmutable objects for a given day.
     * $start_hour and $end_hour are integers (24h). $end_hour is exclusive.
     */
    public static function generate_slots_for_day( DateTimeInterface $day, int $start_hour = 9, int $end_hour = 17, int $slot_minutes = 60 ): array {
        $tz = $day->getTimezone();
        $slots = [];
        $current = new DateTimeImmutable( $day->format('Y-m-d') . sprintf(' %02d:00:00', $start_hour), $tz );
        $end = new DateTimeImmutable( $day->format('Y-m-d') . sprintf(' %02d:00:00', $end_hour), $tz );

        while ( $current < $end ) {
            $slots[] = $current;
            $current = $current->modify( '+' . intval($slot_minutes) . ' minutes' );
        }

        return $slots;
    }
}
