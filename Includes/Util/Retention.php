<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LTLB_Retention {
	public static function run( bool $manual = false ): array {
		global $wpdb;

		$settings = get_option( 'lazy_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$deleted_appointments = 0;
		$anonymized_customers = 0;

		$delete_cancelled_days = isset( $settings['retention_delete_canceled_days'] ) ? intval( $settings['retention_delete_canceled_days'] ) : 0;
		$anonymize_after_days = isset( $settings['retention_anonymize_after_days'] ) ? intval( $settings['retention_anonymize_after_days'] ) : 0;

		$now_mysql = current_time( 'mysql' );
		$now_dt = class_exists( 'LTLB_Time' ) ? LTLB_Time::create_datetime_immutable( $now_mysql ) : null;
		if ( ! $now_dt ) {
			$now_dt = new DateTimeImmutable( $now_mysql );
		}

		if ( $delete_cancelled_days > 0 ) {
			$cutoff = $now_dt->modify( '-' . $delete_cancelled_days . ' days' )->format( 'Y-m-d H:i:s' );
			$table = $wpdb->prefix . 'lazy_appointments';
			$deleted_appointments = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE status = %s AND end_at < %s",
					'cancelled',
					$cutoff
				)
			);
		}

		if ( $anonymize_after_days > 0 ) {
			$cutoff = $now_dt->modify( '-' . $anonymize_after_days . ' days' )->format( 'Y-m-d H:i:s' );
			$customers_table = $wpdb->prefix . 'lazy_customers';
			$appointments_table = $wpdb->prefix . 'lazy_appointments';

			$customer_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT c.id
					 FROM {$customers_table} c
					 JOIN {$appointments_table} a ON a.customer_id = c.id
					 GROUP BY c.id
					 HAVING MAX(a.end_at) < %s",
					$cutoff
				)
			);

			if ( is_array( $customer_ids ) ) {
				foreach ( $customer_ids as $customer_id ) {
					$customer_id = intval( $customer_id );
					if ( $customer_id <= 0 ) continue;

					$email = 'anon+' . $customer_id . '@example.invalid';
					$updated = $wpdb->update(
						$customers_table,
						[
							'first_name' => 'Anonymized',
							'last_name' => '',
							'email' => $email,
							'phone' => '',
							'notes' => '',
							'updated_at' => $now_dt->format( 'Y-m-d H:i:s' ),
						],
						[ 'id' => $customer_id ],
						[ '%s', '%s', '%s', '%s', '%s', '%s' ],
						[ '%d' ]
					);
					if ( $updated !== false && $updated > 0 ) {
						$anonymized_customers++;
					}
				}
			}
		}

		if ( class_exists( 'LTLB_Logger' ) && method_exists( 'LTLB_Logger', 'info' ) ) {
			LTLB_Logger::info( 'Retention cleanup completed', [
				'manual' => $manual,
				'deleted_appointments' => $deleted_appointments,
				'anonymized_customers' => $anonymized_customers,
			] );
		}

		return [
			'deleted_appointments' => $deleted_appointments,
			'anonymized_customers' => $anonymized_customers,
		];
	}
}

