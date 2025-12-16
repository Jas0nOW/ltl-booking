<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Notification Queue System
 * 
 * Provides robust email/SMS notifications with:
 * - Queue management (pending, sent, failed)
 * - Automatic retry with exponential backoff
 * - Status tracking in database
 * - Integration with WordPress Action Scheduler (if available)
 */
class LTLB_NotificationQueue {

    /**
     * Queue a notification for sending
     * 
     * @param string $type Type: 'email' or 'sms'
     * @param string $recipient Email address or phone number
     * @param string $subject Subject line (email only)
     * @param string $message Message body
     * @param array $metadata Additional metadata (appointment_id, etc.)
     * @return int|false Queue ID or false on failure
     */
    public static function queue( string $type, string $recipient, string $subject, string $message, array $metadata = [] ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_notification_queue';
        
        $result = $wpdb->insert(
            $table,
            [
                'type' => sanitize_key( $type ),
                'recipient' => sanitize_text_field( $recipient ),
                'subject' => sanitize_text_field( $subject ),
                'message' => wp_kses_post( $message ),
                'metadata' => wp_json_encode( $metadata ),
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );
        
        if ( $result ) {
            $queue_id = $wpdb->insert_id;
            
            // Schedule immediate processing via Action Scheduler (if available)
            if ( function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action( time(), 'ltlb_process_notification', [ 'queue_id' => $queue_id ] );
            } else {
                // Fallback: process immediately in same request
                self::process_notification( $queue_id );
            }
            
            return $queue_id;
        }
        
        return false;
    }

    /**
     * Process a queued notification
     * 
     * @param int $queue_id Queue ID
     * @return bool Success
     */
    public static function process_notification( int $queue_id ): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltlb_notification_queue';
        
        // Get notification
        $notification = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $queue_id ),
            ARRAY_A
        );
        
        if ( ! $notification ) {
            return false;
        }
        
        // Skip if already sent or failed permanently
        if ( in_array( $notification['status'], [ 'sent', 'failed_permanent' ], true ) ) {
            return false;
        }
        
        // Increment attempts
        $attempts = intval( $notification['attempts'] ) + 1;
        $wpdb->update(
            $table,
            [ 'attempts' => $attempts, 'last_attempt_at' => current_time( 'mysql' ) ],
            [ 'id' => $queue_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );
        
        // Try to send
        $success = false;
        $error_message = '';
        
        try {
            if ( $notification['type'] === 'email' ) {
                $success = self::send_email(
                    $notification['recipient'],
                    $notification['subject'],
                    $notification['message']
                );
                if ( ! $success ) {
                    $error_message = 'Email sending failed (wp_mail returned false)';
                }
            } elseif ( $notification['type'] === 'sms' ) {
                $success = self::send_sms(
                    $notification['recipient'],
                    $notification['message']
                );
                if ( ! $success ) {
                    $error_message = 'SMS sending failed';
                }
            }
        } catch ( Exception $e ) {
            $error_message = $e->getMessage();
        }
        
        if ( $success ) {
            // Mark as sent
            $wpdb->update(
                $table,
                [
                    'status' => 'sent',
                    'sent_at' => current_time( 'mysql' ),
                    'error_message' => null,
                ],
                [ 'id' => $queue_id ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
            
            // Log success
            if ( class_exists( 'LTLB_Logger' ) ) {
                LTLB_Logger::info( "Notification sent: ID={$queue_id}, type={$notification['type']}, recipient={$notification['recipient']}" );
            }
            
            return true;
        } else {
            // Failed - schedule retry with exponential backoff
            $max_attempts = 5;
            
            if ( $attempts >= $max_attempts ) {
                // Permanent failure
                $wpdb->update(
                    $table,
                    [
                        'status' => 'failed_permanent',
                        'error_message' => $error_message,
                    ],
                    [ 'id' => $queue_id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
                
                // Log permanent failure
                if ( class_exists( 'LTLB_Logger' ) ) {
                    LTLB_Logger::error( "Notification failed permanently: ID={$queue_id}, error={$error_message}" );
                }
            } else {
                // Temporary failure - schedule retry
                $wpdb->update(
                    $table,
                    [
                        'status' => 'failed_retry',
                        'error_message' => $error_message,
                    ],
                    [ 'id' => $queue_id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
                
                // Schedule retry with exponential backoff: 1min, 5min, 15min, 1h, 4h
                $retry_delays = [ 60, 300, 900, 3600, 14400 ];
                $delay = $retry_delays[ $attempts - 1 ] ?? 3600;
                
                if ( function_exists( 'as_schedule_single_action' ) ) {
                    as_schedule_single_action( time() + $delay, 'ltlb_process_notification', [ 'queue_id' => $queue_id ] );
                }
                
                // Log retry
                if ( class_exists( 'LTLB_Logger' ) ) {
                    LTLB_Logger::warning( "Notification failed (attempt {$attempts}/{$max_attempts}), retrying in {$delay}s: ID={$queue_id}, error={$error_message}" );
                }
            }
            
            return false;
        }
    }

    /**
     * Send email notification
     */
    private static function send_email( string $to, string $subject, string $message ): bool {
        // Use LTLB_Mailer if available, otherwise wp_mail
        if ( class_exists( 'LTLB_Mailer' ) && method_exists( 'LTLB_Mailer', 'send' ) ) {
            return LTLB_Mailer::send( $to, $subject, $message );
        }
        
        return wp_mail( $to, $subject, $message );
    }

    /**
     * Send SMS notification (placeholder for SMS provider integration)
     */
    private static function send_sms( string $to, string $message ): bool {
        // TODO: Integrate SMS provider (Twilio, Vonage, etc.)
        // For now, just log and return false
        if ( class_exists( 'LTLB_Logger' ) ) {
            LTLB_Logger::info( "SMS sending not implemented yet: to={$to}, message={$message}" );
        }
        
        return false;
    }

    /**
     * Get queue statistics
     */
    public static function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_notification_queue';
        
        $stats = [
            'pending' => 0,
            'sent' => 0,
            'failed_retry' => 0,
            'failed_permanent' => 0,
            'total' => 0,
        ];
        
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
            ARRAY_A
        );
        
        foreach ( $results as $row ) {
            $status = $row['status'];
            $count = intval( $row['count'] );
            if ( isset( $stats[ $status ] ) ) {
                $stats[ $status ] = $count;
            }
            $stats['total'] += $count;
        }
        
        return $stats;
    }

    /**
     * Clean up old sent notifications (older than 30 days)
     */
    public static function cleanup_old(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_notification_queue';
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE status = 'sent' AND sent_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
            )
        );
        
        return intval( $result );
    }

    /**
     * Retry all failed notifications
     */
    public static function retry_failed(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_notification_queue';
        
        $failed = $wpdb->get_col(
            "SELECT id FROM {$table} WHERE status IN ('failed_retry', 'failed_permanent') AND attempts < 5"
        );
        
        $count = 0;
        foreach ( $failed as $queue_id ) {
            if ( function_exists( 'as_schedule_single_action' ) ) {
                as_schedule_single_action( time(), 'ltlb_process_notification', [ 'queue_id' => $queue_id ] );
            } else {
                self::process_notification( intval( $queue_id ) );
            }
            $count++;
        }
        
        return $count;
    }
}
