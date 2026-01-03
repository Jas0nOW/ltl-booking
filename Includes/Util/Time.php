<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Centralized timezone-safe date/time helpers.
 * 
 * Storage convention: appointments are stored as UTC MySQL DATETIME strings.
 * Conversion happens only at boundaries (UI / emails / exports).
 */
class LTLB_Time {

    public static function utc_timezone(): DateTimeZone {
        return new DateTimeZone( 'UTC' );
    }

    public static function wp_timezone(): DateTimeZone {
        // Allow plugin setting to override site timezone for plugin operations
        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) $ls = [];
        $plugin_tz = $ls['timezone'] ?? '';
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
     */
    public static function create_datetime_immutable( string $datetime ): ?DateTimeImmutable {
        $tz = self::wp_timezone();

        $formats = [ 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d' ];
        foreach ( $formats as $fmt ) {
            $dt = DateTimeImmutable::createFromFormat( $fmt, $datetime, $tz );
            if ( $dt !== false ) return $dt->setTimezone( $tz );
        }

        try {
            $dt2 = new DateTimeImmutable( $datetime, $tz );
            return $dt2->setTimezone( $tz );
        } catch ( Exception $e ) {
            return null;
        }
    }

    /**
     * Parse a MySQL DATETIME string that is known to be UTC.
     */
    public static function parse_utc_mysql( string $mysql_datetime ): ?DateTimeImmutable {
        $mysql_datetime = trim( $mysql_datetime );
        if ( $mysql_datetime === '' ) return null;

        $tz = self::utc_timezone();
        $dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql_datetime, $tz );
        if ( $dt !== false ) {
            return $dt->setTimezone( $tz );
        }

        try {
            $dt2 = new DateTimeImmutable( $mysql_datetime, $tz );
            return $dt2->setTimezone( $tz );
        } catch ( Exception $e ) {
            return null;
        }
    }

    /**
     * Format a DateTimeInterface as a UTC MySQL DATETIME string.
     */
    public static function format_utc_mysql( DateTimeInterface $dt ): string {
        $utc = self::utc_timezone();
        return ( new DateTimeImmutable( $dt->format( DATE_ATOM ) ) )
            ->setTimezone( $utc )
            ->format( 'Y-m-d H:i:s' );
    }

    /**
     * Normalize a date/time value to a UTC MySQL DATETIME string.
     */
    public static function normalize_to_utc_mysql( $value, ?string $local_tz_string = null ): ?string {
        if ( $value instanceof DateTimeInterface ) {
            return self::format_utc_mysql( $value );
        }
        if ( ! is_string( $value ) ) {
            return null;
        }

        $raw = trim( $value );
        if ( $raw === '' ) return null;

        if ( preg_match( '/[zZ]$|[+-]\d{2}:?\d{2}$/', $raw ) ) {
            try {
                $dt = new DateTimeImmutable( $raw );
                return self::format_utc_mysql( $dt );
            } catch ( Exception $e ) {
                return null;
            }
        }

        try {
            $local_tz = $local_tz_string ? new DateTimeZone( $local_tz_string ) : self::wp_timezone();
        } catch ( Exception $e ) {
            $local_tz = self::wp_timezone();
        }

        $dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $raw, $local_tz );
        if ( $dt === false ) $dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $raw, $local_tz );
        if ( $dt === false ) $dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $raw, $local_tz );

        if ( $dt === false ) {
            try {
                $dt = new DateTimeImmutable( $raw, $local_tz );
            } catch ( Exception $e ) {
                return null;
            }
        }

        return self::format_utc_mysql( $dt );
    }

    /**
     * Convert a UTC MySQL DATETIME string into a DateTimeImmutable in the given timezone.
     */
    public static function utc_mysql_to_local_dt( string $mysql_datetime_utc, ?string $tz_string = null ): ?DateTimeImmutable {
        $utc_dt = self::parse_utc_mysql( $mysql_datetime_utc );
        if ( ! $utc_dt ) return null;

        try {
            $tz = $tz_string ? new DateTimeZone( $tz_string ) : self::wp_timezone();
        } catch ( Exception $e ) {
            $tz = self::wp_timezone();
        }

        return $utc_dt->setTimezone( $tz );
    }

    /**
     * Render a UTC MySQL DATETIME string in WP/site timezone for display.
     */
    public static function format_local_display_from_utc_mysql( string $mysql_datetime_utc, string $format, ?string $tz_string = null ): string {
        $dt = self::utc_mysql_to_local_dt( $mysql_datetime_utc, $tz_string );
        if ( ! $dt ) return '';

        if ( function_exists( 'wp_date' ) ) {
            return wp_date( $format, $dt->getTimestamp(), $dt->getTimezone() );
        }

        return $dt->format( $format );
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

    public static function nights_between( string $checkin_date, string $checkout_date ): int {
        $tz = self::wp_timezone();
        try {
            $checkin = DateTimeImmutable::createFromFormat( 'Y-m-d', $checkin_date, $tz );
            $checkout = DateTimeImmutable::createFromFormat( 'Y-m-d', $checkout_date, $tz );
            if ( $checkin === false || $checkout === false ) return 0;
            return $checkout->diff( $checkin )->days;
        } catch ( Exception $e ) {
            return 0;
        }
    }
}
