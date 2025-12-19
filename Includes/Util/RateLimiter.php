<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Simple Rate Limiter for REST API Endpoints
 * 
 * Uses WordPress transients for temporary storage.
 * Implements sliding window rate limiting.
 */
class LTLB_RateLimiter {

    /**
     * Check if rate limit is exceeded for an endpoint
     * 
     * @param string $endpoint Endpoint identifier (e.g., 'create_booking', 'refund')
     * @param int $max_requests Maximum requests allowed in time window
     * @param int $window_seconds Time window in seconds
     * @param string|null $identifier User identifier (defaults to current user ID or IP)
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int]
     */
    public static function check( string $endpoint, int $max_requests = 60, int $window_seconds = 60, ?string $identifier = null ): array {
        
        // Generate unique key for this endpoint + user/IP
        if ( $identifier === null ) {
            $identifier = self::get_default_identifier();
        }
        
        $key = self::get_cache_key( $endpoint, $identifier );
        
        // Get current request count
        $requests = get_transient( $key );
        $requests = is_array( $requests ) ? $requests : [];
        
        // Clean up old requests outside the time window
        $now = time();
        $cutoff = $now - $window_seconds;
        $requests = array_filter( $requests, function( $timestamp ) use ( $cutoff ) {
            return $timestamp > $cutoff;
        } );
        
        // Check if limit exceeded
        $current_count = count( $requests );
        $allowed = $current_count < $max_requests;
        
        if ( $allowed ) {
            // Add this request to the log
            $requests[] = $now;
            set_transient( $key, $requests, $window_seconds + 60 ); // Extra buffer to avoid edge cases
        }
        
        // Calculate retry_after (seconds until oldest request expires)
        $retry_after = 0;
        if ( ! $allowed && ! empty( $requests ) ) {
            $oldest = min( $requests );
            $retry_after = max( 0, ( $oldest + $window_seconds ) - $now );
        }
        
        return [
            'allowed' => $allowed,
            'remaining' => max( 0, $max_requests - $current_count - ( $allowed ? 1 : 0 ) ),
            'retry_after' => $retry_after,
            'limit' => $max_requests,
            'window' => $window_seconds,
        ];
    }

    /**
     * Get default identifier (user ID or IP address)
     */
    private static function get_default_identifier(): string {
        $user_id = get_current_user_id();
        if ( $user_id > 0 ) {
            return 'user_' . $user_id;
        }
        
        // Fallback to IP address for anonymous users
        $ip = self::get_client_ip();
        return 'ip_' . $ip;
    }

    /**
     * Get client IP address (handles proxies)
     */
    private static function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = (string) sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // For X-Forwarded-For, take the first IP
                if ( strpos( (string) $ip, ',' ) !== false ) {
                    $ips = explode( ',', (string) $ip );
                    $ip = trim( (string) $ips[0] );
                }
                return (string) $ip;
            }
        }
        
        return 'unknown';
    }

    /**
     * Generate cache key for rate limit tracking
     */
    private static function get_cache_key( string $endpoint, string $identifier ): string {
        return 'ltlb_ratelimit_' . md5( $endpoint . '_' . $identifier );
    }

    /**
     * Reset rate limit for an endpoint + identifier (admin override)
     */
    public static function reset( string $endpoint, ?string $identifier = null ): bool {
        if ( $identifier === null ) {
            $identifier = self::get_default_identifier();
        }
        
        $key = self::get_cache_key( $endpoint, $identifier );
        return delete_transient( $key );
    }

    /**
     * Apply rate limit to a REST endpoint callback
     * Returns 429 error if limit exceeded, otherwise executes callback
     * 
     * @param callable $callback Original endpoint callback
     * @param string $endpoint_name Endpoint identifier for rate limiting
     * @param int $max_requests Max requests per window
     * @param int $window_seconds Time window in seconds
     * @return callable Wrapped callback with rate limiting
     */
    public static function wrap( callable $callback, string $endpoint_name, int $max_requests = 60, int $window_seconds = 60 ): callable {
        return function( WP_REST_Request $request ) use ( $callback, $endpoint_name, $max_requests, $window_seconds ) {
            
            // Check rate limit
            $check = self::check( $endpoint_name, $max_requests, $window_seconds );
            
            if ( ! $check['allowed'] ) {
                $response = new WP_REST_Response(
                    [
                        'ok' => false,
                        'error' => 'rate_limit_exceeded',
                        'error_code' => 'rate_limit_exceeded',
                        'message' => sprintf(
                            __( 'Rate limit exceeded. Maximum %d requests per %d seconds. Please try again in %d seconds.', 'ltl-bookings' ),
                            $check['limit'],
                            $check['window'],
                            $check['retry_after']
                        ),
                        'data' => [
                            'limit' => $check['limit'],
                            'window' => $check['window'],
                            'retry_after' => $check['retry_after'],
                        ],
                    ],
                    429
                );
                
                // Add rate limit headers
                $response->header( 'X-RateLimit-Limit', (string) $check['limit'] );
                $response->header( 'X-RateLimit-Remaining', '0' );
                $response->header( 'X-RateLimit-Reset', (string) ( time() + $check['retry_after'] ) );
                $response->header( 'Retry-After', (string) $check['retry_after'] );
                
                return $response;
            }
            
            // Execute original callback
            $response = call_user_func( $callback, $request );
            
            // Add rate limit headers to successful responses
            if ( $response instanceof WP_REST_Response ) {
                $response->header( 'X-RateLimit-Limit', (string) $check['limit'] );
                $response->header( 'X-RateLimit-Remaining', (string) $check['remaining'] );
                $response->header( 'X-RateLimit-Reset', (string) ( time() + $check['window'] ) );
            }
            
            return $response;
        };
    }
}
