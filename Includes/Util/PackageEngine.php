<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Package Engine
 * 
 * Manages service packages (5er-Karten, 10er-Karten, subscriptions).
 * Tracks usage, expiration, and remaining credits.
 */
class LTLB_PackageEngine {

    /**
     * Create a package
     */
    public static function create( int $customer_id, int $service_id, array $config ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_packages';

        $defaults = [
            'credits_total' => 10,
            'credits_remaining' => 10,
            'validity_days' => 365,
            'price' => 0,
            'discount_percent' => 0,
        ];

        $config = wp_parse_args( $config, $defaults );

        $wpdb->insert( $table, [
            'customer_id' => $customer_id,
            'service_id' => $service_id,
            'credits_total' => $config['credits_total'],
            'credits_remaining' => $config['credits_remaining'],
            'price' => $config['price'],
            'discount_percent' => $config['discount_percent'],
            'expires_at' => date( 'Y-m-d H:i:s', strtotime( '+' . $config['validity_days'] . ' days' ) ),
            'status' => 'active',
            'created_at' => current_time( 'mysql' ),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Use a credit from package
     */
    public static function use_credit( int $package_id, int $appointment_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_packages';

        $package = self::get( $package_id );

        if ( ! $package || $package->status !== 'active' ) {
            return false;
        }

        if ( $package->credits_remaining <= 0 ) {
            return false;
        }

        if ( strtotime( $package->expires_at ) < time() ) {
            self::expire( $package_id );
            return false;
        }

        // Decrement credit
        $new_remaining = $package->credits_remaining - 1;

        $wpdb->update( $table, [
            'credits_remaining' => $new_remaining,
            'last_used_at' => current_time( 'mysql' ),
        ], [ 'id' => $package_id ] );

        // Log usage
        $wpdb->insert( $wpdb->prefix . 'ltlb_package_usage', [
            'package_id' => $package_id,
            'appointment_id' => $appointment_id,
            'credits_used' => 1,
            'credits_remaining_after' => $new_remaining,
            'used_at' => current_time( 'mysql' ),
        ]);

        // Auto-deactivate if depleted
        if ( $new_remaining === 0 ) {
            $wpdb->update( $table, [ 'status' => 'depleted' ], [ 'id' => $package_id ] );
        }

        return true;
    }

    /**
     * Refund a credit (e.g., after cancellation)
     */
    public static function refund_credit( int $package_id, int $appointment_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_packages';

        $package = self::get( $package_id );

        if ( ! $package ) {
            return false;
        }

        // Can't refund more than total
        if ( $package->credits_remaining >= $package->credits_total ) {
            return false;
        }

        $new_remaining = $package->credits_remaining + 1;

        $wpdb->update( $table, [
            'credits_remaining' => $new_remaining,
            'status' => 'active', // Reactivate if was depleted
        ], [ 'id' => $package_id ] );

        // Log refund
        $wpdb->insert( $wpdb->prefix . 'ltlb_package_usage', [
            'package_id' => $package_id,
            'appointment_id' => $appointment_id,
            'credits_used' => -1, // Negative = refund
            'credits_remaining_after' => $new_remaining,
            'used_at' => current_time( 'mysql' ),
        ]);

        return true;
    }

    /**
     * Get package details
     */
    public static function get( int $package_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_packages';

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $package_id
        ));
    }

    /**
     * Get active packages for customer
     */
    public static function get_by_customer( int $customer_id, string $status = 'active' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_packages';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE customer_id = %d 
             AND status = %s 
             AND expires_at > NOW()
             ORDER BY expires_at ASC",
            $customer_id,
            $status
        ));
    }

    /**
     * Get usage history for package
     */
    public static function get_usage_history( int $package_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_package_usage';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE package_id = %d ORDER BY used_at DESC",
            $package_id
        ));
    }

    /**
     * Expire a package
     */
    public static function expire( int $package_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_packages';

        return (bool) $wpdb->update( $table, [
            'status' => 'expired',
        ], [ 'id' => $package_id ] );
    }

    /**
     * Expire all packages past their validity date (cron job)
     */
    public static function expire_old_packages(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_packages';

        $wpdb->query(
            "UPDATE {$table} 
             SET status = 'expired' 
             WHERE status = 'active' 
             AND expires_at < NOW()"
        );
    }

    /**
     * Cancel a package and issue refund
     */
    public static function cancel( int $package_id, string $reason = '' ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_packages';

        return (bool) $wpdb->update( $table, [
            'status' => 'cancelled',
            'cancelled_at' => current_time( 'mysql' ),
            'cancel_reason' => $reason,
        ], [ 'id' => $package_id ] );
    }

    /**
     * Get package statistics
     */
    public static function get_stats( int $package_id ): array {
        $package = self::get( $package_id );

        if ( ! $package ) {
            return [];
        }

        $used = $package->credits_total - $package->credits_remaining;
        $usage_percent = $package->credits_total > 0 ? round( ( $used / $package->credits_total ) * 100 ) : 0;

        $days_until_expiry = max( 0, round( ( strtotime( $package->expires_at ) - time() ) / DAY_IN_SECONDS ) );

        return [
            'credits_used' => $used,
            'credits_remaining' => $package->credits_remaining,
            'usage_percent' => $usage_percent,
            'days_until_expiry' => $days_until_expiry,
            'is_expired' => strtotime( $package->expires_at ) < time(),
            'is_active' => $package->status === 'active',
        ];
    }

    /**
     * Common package presets
     */
    public static function get_presets(): array {
        return [
            '5er_karte' => [
                'name' => __('5er Card', 'ltl-bookings'),
                'credits_total' => 5,
                'validity_days' => 180,
                'discount_percent' => 10,
            ],
            '10er_karte' => [
                'name' => __('10er Card', 'ltl-bookings'),
                'credits_total' => 10,
                'validity_days' => 365,
                'discount_percent' => 15,
            ],
            'monthly' => [
                'name' => __('Monthly Subscription', 'ltl-bookings'),
                'credits_total' => 4,
                'validity_days' => 30,
                'discount_percent' => 20,
            ],
        ];
    }
}
