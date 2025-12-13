<?php
if (!defined('ABSPATH')) exit;

/**
 * MySQL Named Lock Manager for double-booking protection.
 * 
 * Uses GET_LOCK() / RELEASE_LOCK() to provide atomic locking around booking creation.
 * Timeout is small (2-3s) with graceful fallback.
 * 
 * Limitations:
 * - Named locks are session-based (connection-based)
 * - Won't persist across different DB connections
 * - Not available on all MySQL configurations
 * - Falls back gracefully if GET_LOCK fails
 */
class LTLB_LockManager {

    /**
     * Timeout for GET_LOCK in seconds.
     */
    const LOCK_TIMEOUT = 3;

    /**
     * Acquire a named lock for a specific resource/time slot.
     * 
     * @param string $lock_key Unique identifier (e.g., "service_1_2025-12-20_09:00")
     * @return bool True if lock acquired, false otherwise
     */
    public static function acquire(string $lock_key): bool {
        global $wpdb;

        // Sanitize lock key (max 64 chars for MySQL)
        $lock_key = 'ltlb_' . substr(md5($lock_key), 0, 50);

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT GET_LOCK(%s, %d)",
            $lock_key,
            self::LOCK_TIMEOUT
        ));

        // GET_LOCK returns:
        // 1 = lock acquired
        // 0 = timeout
        // NULL = error (lock unavailable on this MySQL version)
        return ($result === '1');
    }

    /**
     * Release a previously acquired lock.
     * 
     * @param string $lock_key Same key used in acquire()
     * @return bool True if released, false otherwise
     */
    public static function release(string $lock_key): bool {
        global $wpdb;

        $lock_key = 'ltlb_' . substr(md5($lock_key), 0, 50);

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT RELEASE_LOCK(%s)",
            $lock_key
        ));

        // RELEASE_LOCK returns:
        // 1 = lock released
        // 0 = lock not held by this thread
        // NULL = lock does not exist
        return ($result === '1');
    }

    /**
     * Build a lock key for service booking.
     * 
     * @param int $service_id
     * @param string $start_at Datetime string
     * @param int|null $resource_id Optional resource ID
     * @return string
     */
    public static function build_service_lock_key(int $service_id, string $start_at, ?int $resource_id = null): string {
        $key = "service_{$service_id}_{$start_at}";
        if ($resource_id) {
            $key .= "_resource_{$resource_id}";
        }
        return $key;
    }

    /**
     * Build a lock key for hotel booking.
     * 
     * @param int $service_id Room type ID
     * @param string $checkin Check-in date
     * @param string $checkout Check-out date
     * @param int|null $resource_id Optional room ID
     * @return string
     */
    public static function build_hotel_lock_key(int $service_id, string $checkin, string $checkout, ?int $resource_id = null): string {
        $key = "hotel_{$service_id}_{$checkin}_{$checkout}";
        if ($resource_id) {
            $key .= "_room_{$resource_id}";
        }
        return $key;
    }

    /**
     * Execute a callback with lock protection.
     * 
     * @param string $lock_key
     * @param callable $callback
     * @return mixed Result of callback, or false if lock acquisition failed
     */
    public static function with_lock(string $lock_key, callable $callback) {
        $acquired = self::acquire($lock_key);

        if (!$acquired) {
            // Lock timeout - another booking is in progress
            return false;
        }

        try {
            return $callback();
        } finally {
            self::release($lock_key);
        }
    }
}
