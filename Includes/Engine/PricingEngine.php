<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Pricing Engine
 * 
 * Calculates prices based on:
 * - Base price (service/room)
 * - Seasonal rules
 * - Weekday/weekend modifiers
 * - Duration discounts
 * - Person count
 * - Taxes and fees
 * 
 * @package LazyBookings
 */
class LTLB_Pricing_Engine {

    /**
     * Calculate final price for a booking
     * 
     * @param array $params Pricing parameters
     * @return array Price breakdown
     */
    public function calculate( array $params ): array {
        $service_id = $params['service_id'] ?? 0;
        $room_id = $params['room_id'] ?? 0;
        $start_date = $params['start_date'] ?? '';
        $end_date = $params['end_date'] ?? '';
        $num_persons = max( 1, intval( $params['num_persons'] ?? 1 ) );
        $duration_hours = max( 0, floatval( $params['duration_hours'] ?? 0 ) );

        // Get base price
        $base_price_cents = $this->get_base_price( $service_id, $room_id );
        
        if ( $base_price_cents === 0 ) {
            return [
                'base_price_cents' => 0,
                'subtotal_cents' => 0,
                'tax_cents' => 0,
                'total_cents' => 0,
                'breakdown' => [],
                'error' => 'No base price found'
            ];
        }

        $breakdown = [];
        $subtotal_cents = $base_price_cents;
        
        $breakdown[] = [
            'label' => __( 'Base Price', 'ltl-bookings' ),
            'amount_cents' => $base_price_cents,
            'type' => 'base'
        ];

        // Apply seasonal modifiers
        if ( ! empty( $start_date ) ) {
            $seasonal_modifier = $this->get_seasonal_modifier( $start_date, $service_id, $room_id );
            if ( $seasonal_modifier !== 1.0 ) {
                $seasonal_adjustment = round( $base_price_cents * ( $seasonal_modifier - 1 ) );
                $subtotal_cents += $seasonal_adjustment;
                $breakdown[] = [
                    'label' => sprintf( __( 'Seasonal Adjustment (%+d%%)', 'ltl-bookings' ), round( ( $seasonal_modifier - 1 ) * 100 ) ),
                    'amount_cents' => $seasonal_adjustment,
                    'type' => 'seasonal'
                ];
            }
        }

        // Apply weekday/weekend modifiers
        if ( ! empty( $start_date ) ) {
            $day_modifier = $this->get_weekday_modifier( $start_date, $service_id, $room_id );
            if ( $day_modifier !== 1.0 ) {
                $day_adjustment = round( $subtotal_cents * ( $day_modifier - 1 ) );
                $subtotal_cents += $day_adjustment;
                $breakdown[] = [
                    'label' => sprintf( __( 'Weekday/Weekend Adjustment (%+d%%)', 'ltl-bookings' ), round( ( $day_modifier - 1 ) * 100 ) ),
                    'amount_cents' => $day_adjustment,
                    'type' => 'weekday'
                ];
            }
        }

        // Apply person count modifier (if applicable)
        if ( $num_persons > 1 ) {
            $person_modifier = $this->get_person_modifier( $num_persons, $service_id, $room_id );
            if ( $person_modifier !== 1.0 ) {
                $person_adjustment = round( $base_price_cents * ( $person_modifier - 1 ) * $num_persons );
                $subtotal_cents += $person_adjustment;
                $breakdown[] = [
                    'label' => sprintf( __( 'Additional Persons (%d)', 'ltl-bookings' ), $num_persons - 1 ),
                    'amount_cents' => $person_adjustment,
                    'type' => 'persons'
                ];
            }
        }

        // Apply duration discounts
        if ( $duration_hours > 0 ) {
            $duration_modifier = $this->get_duration_modifier( $duration_hours, $service_id, $room_id );
            if ( $duration_modifier !== 1.0 ) {
                $duration_adjustment = round( $subtotal_cents * ( $duration_modifier - 1 ) );
                $subtotal_cents += $duration_adjustment;
                $breakdown[] = [
                    'label' => sprintf( __( 'Duration Discount (%+d%%)', 'ltl-bookings' ), round( ( $duration_modifier - 1 ) * 100 ) ),
                    'amount_cents' => $duration_adjustment,
                    'type' => 'duration'
                ];
            }
        }

        // Calculate taxes
        $tax_rate = $this->get_tax_rate( $service_id, $room_id );
        $tax_cents = round( $subtotal_cents * $tax_rate );
        
        if ( $tax_cents > 0 ) {
            $breakdown[] = [
                'label' => sprintf( __( 'Tax (%.1f%%)', 'ltl-bookings' ), $tax_rate * 100 ),
                'amount_cents' => $tax_cents,
                'type' => 'tax'
            ];
        }

        $total_cents = $subtotal_cents + $tax_cents;

        return [
            'base_price_cents' => $base_price_cents,
            'subtotal_cents' => $subtotal_cents,
            'tax_cents' => $tax_cents,
            'total_cents' => $total_cents,
            'breakdown' => $breakdown,
            'currency' => $this->get_currency()
        ];
    }

    /**
     * Get base price for service or room
     * 
     * @param int $service_id
     * @param int $room_id
     * @return int Price in cents
     */
    private function get_base_price( int $service_id, int $room_id ): int {
        global $wpdb;

        if ( $service_id > 0 ) {
            $table = $wpdb->prefix . 'ltlb_services';
            $price = $wpdb->get_var( $wpdb->prepare(
                "SELECT price_cents FROM $table WHERE id = %d",
                $service_id
            ) );
            return max( 0, intval( $price ) );
        }

        if ( $room_id > 0 ) {
            $table = $wpdb->prefix . 'ltlb_resources';
            $price = $wpdb->get_var( $wpdb->prepare(
                "SELECT price_cents FROM $table WHERE id = %d AND type = 'room'",
                $room_id
            ) );
            return max( 0, intval( $price ) );
        }

        return 0;
    }

    /**
     * Get seasonal modifier (1.0 = no change, >1 = higher price, <1 = lower price)
     * 
     * @param string $date Date in Y-m-d format
     * @param int $service_id
     * @param int $room_id
     * @return float Modifier
     */
    private function get_seasonal_modifier( string $date, int $service_id, int $room_id ): float {
        // TODO: Load from pricing rules table
        // For now, return default (no modifier)
        
        // Example logic:
        // - Summer (June-August): +20%
        // - Winter (December-February): -10%
        // - Rest: no change
        
        $month = intval( date( 'n', strtotime( $date ) ) );
        
        if ( in_array( $month, [6, 7, 8], true ) ) {
            return 1.20; // +20% summer
        }
        
        if ( in_array( $month, [12, 1, 2], true ) ) {
            return 0.90; // -10% winter
        }
        
        return 1.0;
    }

    /**
     * Get weekday modifier
     * 
     * @param string $date
     * @param int $service_id
     * @param int $room_id
     * @return float
     */
    private function get_weekday_modifier( string $date, int $service_id, int $room_id ): float {
        // TODO: Load from pricing rules
        
        $day_of_week = intval( date( 'N', strtotime( $date ) ) ); // 1 = Monday, 7 = Sunday
        
        // Weekend (Saturday/Sunday) +10%
        if ( in_array( $day_of_week, [6, 7], true ) ) {
            return 1.10;
        }
        
        return 1.0;
    }

    /**
     * Get person count modifier
     * 
     * @param int $num_persons
     * @param int $service_id
     * @param int $room_id
     * @return float
     */
    private function get_person_modifier( int $num_persons, int $service_id, int $room_id ): float {
        // TODO: Load from pricing rules
        
        // Example: +50€ per additional person (assuming base is for 2 people)
        if ( $num_persons > 2 ) {
            return 1.0 + ( 0.5 * ( $num_persons - 2 ) );
        }
        
        return 1.0;
    }

    /**
     * Get duration modifier (for longer stays/bookings)
     * 
     * @param float $hours
     * @param int $service_id
     * @param int $room_id
     * @return float
     */
    private function get_duration_modifier( float $hours, int $service_id, int $room_id ): float {
        // TODO: Load from pricing rules
        
        // Example: 7+ days = -10%, 14+ days = -15%
        $days = $hours / 24;
        
        if ( $days >= 14 ) {
            return 0.85; // -15%
        }
        
        if ( $days >= 7 ) {
            return 0.90; // -10%
        }
        
        return 1.0;
    }

    /**
     * Get tax rate
     * 
     * @param int $service_id
     * @param int $room_id
     * @return float Tax rate (0.19 = 19%)
     */
    private function get_tax_rate( int $service_id, int $room_id ): float {
        $settings = get_option( 'lazy_settings', [] );
        
        // Default tax rate from settings
        $default_rate = isset( $settings['tax_rate'] ) ? floatval( $settings['tax_rate'] ) : 0.19;
        
        // TODO: Support service/room-specific tax rates
        
        return max( 0, $default_rate );
    }

    /**
     * Get currency code
     * 
     * @return string
     */
    private function get_currency(): string {
        $settings = get_option( 'lazy_settings', [] );
        return $settings['default_currency'] ?? 'EUR';
    }

    /**
     * Format price for display
     * 
     * @param int $cents
     * @param string $currency
     * @return string
     */
    public static function format_price( int $cents, string $currency = 'EUR' ): string {
        $amount = $cents / 100;
        
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
        ];
        
        $symbol = $symbols[ $currency ] ?? $currency;
        
        return number_format( $amount, 2, ',', '.' ) . ' ' . $symbol;
    }
}
