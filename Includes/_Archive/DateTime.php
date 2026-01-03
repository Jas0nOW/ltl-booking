<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Centralized timezone-safe date/time helpers.
 *
 * Storage convention (P0): appointments are stored as UTC MySQL DATETIME strings.
 * Conversion happens only at boundaries (UI / emails / exports).
 */
class LTLB_Time {

    public static function utc_timezone(): DateTimeZone {
        return new DateTimeZone( 'UTC' );
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
            // Fallback for ISO inputs.
            $dt2 = new DateTimeImmutable( $mysql_datetime, $tz );
            return $dt2->setTimezone( $tz );
        } catch ( Exception $e ) {
            return null;
        }
    }

    /**
     * Convert a DateTimeInterface to a UTC MySQL DATETIME string.
     */
    public static function format_utc_mysql( DateTimeInterface $dt ): string {
        $utc = self::utc_timezone();
        return ( new DateTimeImmutable( $dt->format( DATE_ATOM ) ) )
            ->setTimezone( $utc )
            ->format( 'Y-m-d H:i:s' );
    }

    /**
     * Normalize a date/time value to a UTC MySQL DATETIME string.
     *
     * - If value is a DateTimeInterface: converted to UTC.
     * - If value is a string without timezone offset: interpreted in $local_tz_string.
     * - If value is an ISO string with offset/Z: parsed as-is.
     */
    public static function normalize_to_utc_mysql( $value, string $local_tz_string ): ?string {
        if ( $value instanceof DateTimeInterface ) {
            return self::format_utc_mysql( $value );
        }
        if ( ! is_string( $value ) ) {
            return null;
        }

        $raw = trim( $value );
        if ( $raw === '' ) return null;

        // ISO with timezone info: parse directly.
        if ( preg_match( '/[zZ]$|[+-]\d{2}:?\d{2}$/', $raw ) ) {
            try {
                $dt = new DateTimeImmutable( $raw );
                return self::format_utc_mysql( $dt );
            } catch ( Exception $e ) {
                return null;
            }
        }

        try {
            $local_tz = new DateTimeZone( $local_tz_string );
        } catch ( Exception $e ) {
            $local_tz = LTLB_Time::wp_timezone();
        }

        $dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $raw, $local_tz );
        if ( $dt === false ) {
            $dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $raw, $local_tz );
        }
        if ( $dt === false ) {
            $dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $raw, $local_tz );
        }

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
    public static function utc_mysql_to_local_dt( string $mysql_datetime_utc, string $tz_string ): ?DateTimeImmutable {
        $utc_dt = self::parse_utc_mysql( $mysql_datetime_utc );
        if ( ! $utc_dt ) return null;

        try {
            $tz = new DateTimeZone( $tz_string );
        } catch ( Exception $e ) {
            $tz = LTLB_Time::wp_timezone();
        }

        return $utc_dt->setTimezone( $tz );
    }

    /**
     * Render a UTC MySQL DATETIME string in WP/site timezone for display.
     */
    public static function format_local_display_from_utc_mysql( string $mysql_datetime_utc, string $format, ?string $tz_string = null ): string {
        $tz_string = $tz_string ?: LTLB_Time::get_site_timezone_string();
        $dt = self::utc_mysql_to_local_dt( $mysql_datetime_utc, $tz_string );
        if ( ! $dt ) return '';

        // wp_date respects locale and site settings.
        if ( function_exists( 'wp_date' ) ) {
            return wp_date( $format, $dt->getTimestamp(), $dt->getTimezone() );
        }

        return $dt->format( $format );
    }
}

