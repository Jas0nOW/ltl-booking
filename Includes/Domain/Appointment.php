<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LTLB_Appointment {
	public const STATUS_PENDING = 'pending';
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_CANCELLED = 'cancelled';

	public static function allowed_statuses(): array {
		return [
			self::STATUS_PENDING,
			self::STATUS_CONFIRMED,
			self::STATUS_CANCELLED,
		];
	}
}

