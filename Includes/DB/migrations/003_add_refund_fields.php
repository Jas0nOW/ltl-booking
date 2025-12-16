<?php
/**
 * Migration 003: Add refund tracking fields to appointments table
 * 
 * Adds:
 * - refund_status: Tracks refund state (none, pending, partial, full, failed)
 * - refund_amount_cents: Amount refunded (can be partial)
 * - refunded_at: Timestamp of successful refund
 * - refund_ref: External refund reference (Stripe refund ID, PayPal refund ID)
 * - refund_reason: Optional reason for refund
 */

if ( ! defined('ABSPATH') ) exit;

return [
    'up' => function() {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';
        
        // Check if columns already exist (idempotent)
        $columns = $wpdb->get_col( "DESCRIBE {$table}" );
        $has_refund_status = in_array( 'refund_status', $columns );
        
        if ( ! $has_refund_status ) {
            $wpdb->query( "
                ALTER TABLE {$table}
                ADD COLUMN refund_status VARCHAR(20) NOT NULL DEFAULT 'none' AFTER paid_at,
                ADD COLUMN refund_amount_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER refund_status,
                ADD COLUMN refunded_at DATETIME NULL AFTER refund_amount_cents,
                ADD COLUMN refund_ref VARCHAR(190) NULL AFTER refunded_at,
                ADD COLUMN refund_reason TEXT NULL AFTER refund_ref,
                ADD INDEX refund_status (refund_status)
            " );
        }
        
        return true;
    },
    
    'down' => function() {
        global $wpdb;
        $table = $wpdb->prefix . 'lazy_appointments';
        
        $wpdb->query( "
            ALTER TABLE {$table}
            DROP COLUMN IF EXISTS refund_status,
            DROP COLUMN IF EXISTS refund_amount_cents,
            DROP COLUMN IF EXISTS refunded_at,
            DROP COLUMN IF EXISTS refund_ref,
            DROP COLUMN IF EXISTS refund_reason
        " );
        
        return true;
    },
];
