<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Sanitizer {

	public static function text(?string $value): string {
		if ( $value === null ) return '';
		return sanitize_text_field( $value );
	}

	public static function int($value): int {
		return intval( $value );
	}

	public static function money_cents($value): int {
		if ( $value === null || $value === '' ) return 0;
		// Accept strings like 12.34 or integers
		$float = filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
		return (int) round( floatval( $float ) * 100 );
	}

	public static function email(?string $value): string {
		if ( $value === null ) return '';
		return sanitize_email( $value );
	}

	public static function datetime(?string $value): string {
		if ( empty( $value ) ) return '';
		// Let WP handle formatting elsewhere; basic sanitize
		return sanitize_text_field( $value );
	}
}

