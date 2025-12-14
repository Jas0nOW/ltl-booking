<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Finance {
	public static function get_settings(): array {
		$settings = get_option( 'lazy_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		return $settings;
	}

	public static function template_mode(): string {
		$settings = self::get_settings();
		$mode = isset( $settings['template_mode'] ) ? (string) $settings['template_mode'] : 'service';
		return $mode === 'hotel' ? 'hotel' : 'service';
	}

	public static function profit_margin_percent(): int {
		$settings = self::get_settings();
		$p = isset( $settings['profit_margin_percent'] ) ? intval( $settings['profit_margin_percent'] ) : 100;
		return max( 0, min( 100, $p ) );
	}

	public static function hotel_fee_percent(): int {
		$settings = self::get_settings();
		$p = isset( $settings['hotel_fee_percent'] ) ? intval( $settings['hotel_fee_percent'] ) : 0;
		return max( 0, min( 100, $p ) );
	}

	public static function hotel_fee_fixed_cents(): int {
		$settings = self::get_settings();
		$c = isset( $settings['hotel_fee_fixed_cents'] ) ? intval( $settings['hotel_fee_fixed_cents'] ) : 0;
		return max( 0, $c );
	}

	public static function format_money_from_cents( int $cents, string $currency_symbol = '€' ): string {
		$amount = floatval( $cents ) / 100.0;
		return $currency_symbol . number_format( $amount, 2 );
	}

	/**
	 * Hotel finance totals for confirmed bookings.
	 *
	 * Returns cents to keep everything deterministic.
	 */
	public static function hotel_financials_cents( string $start_date, string $end_date ): array {
		global $wpdb;
		$appt_table = $wpdb->prefix . 'lazy_appointments';
		$ar_table = $wpdb->prefix . 'lazy_appointment_resources';
		$res_table = $wpdb->prefix . 'lazy_resources';

		$fee_percent = self::hotel_fee_percent();
		$fee_fixed_cents = self::hotel_fee_fixed_cents();

		// Avoid double counting revenue/fees when joining appointment_resources by grouping per appointment.
		$sql = "
			SELECT
				SUM(t.revenue_cents) AS revenue_cents,
				SUM(t.fees_cents) AS fees_cents,
				SUM(t.room_costs_cents) AS room_costs_cents
			FROM (
				SELECT
					a.id AS appointment_id,
					COALESCE(a.amount_cents, 0) AS revenue_cents,
					(ROUND(COALESCE(a.amount_cents, 0) * %f / 100) + %d) AS fees_cents,
					(DATEDIFF(DATE(a.end_at), DATE(a.start_at)) * COALESCE(SUM(r.cost_per_night_cents), 0)) AS room_costs_cents
				FROM {$appt_table} a
				LEFT JOIN {$ar_table} ar ON ar.appointment_id = a.id
				LEFT JOIN {$res_table} r ON r.id = ar.resource_id
				WHERE DATE(a.start_at) BETWEEN %s AND %s
					AND a.status = 'confirmed'
				GROUP BY a.id
			) t
		";

		$row = $wpdb->get_row( $wpdb->prepare( $sql, floatval( $fee_percent ), intval( $fee_fixed_cents ), $start_date, $end_date ), ARRAY_A );
		$revenue_cents = $row ? intval( $row['revenue_cents'] ?? 0 ) : 0;
		$fees_cents = $row ? intval( $row['fees_cents'] ?? 0 ) : 0;
		$room_costs_cents = $row ? intval( $row['room_costs_cents'] ?? 0 ) : 0;
		$gross_profit_cents = $revenue_cents - $fees_cents - $room_costs_cents;

		return [
			'revenue_cents' => $revenue_cents,
			'fees_cents' => $fees_cents,
			'room_costs_cents' => $room_costs_cents,
			'gross_profit_cents' => $gross_profit_cents,
		];
	}
}
