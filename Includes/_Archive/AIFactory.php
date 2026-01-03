<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * AI Provider Factory
 * 
 * Returns appropriate AI provider instance based on config
 */
class LTLB_AI_Factory {

	/**
	 * Get AI provider instance
	 *
	 * @param string $provider (gemini, etc.)
	 * @return LTLB_AI_Provider|null
	 */
	public static function get_provider( string $provider = '' ): ?LTLB_AI_Provider {
		if ( ! $provider ) {
			$ai_config = get_option( 'lazy_ai_config', [] );
			$provider = $ai_config['provider'] ?? 'gemini';
		}

		// Get API key
		$api_keys = get_option( 'lazy_api_keys', [] );
		if ( ! is_array( $api_keys ) ) {
			$api_keys = [];
		}

		// Get model
		$ai_config = get_option( 'lazy_ai_config', [] );
		$model = $ai_config['model'] ?? 'gemini-2.5-flash';

		switch ( strtolower( $provider ) ) {
			case 'gemini':
				$key = $api_keys['gemini'] ?? '';
				if ( $key && class_exists( 'LTLB_Crypto' ) ) {
					$key = LTLB_Crypto::decrypt_string( (string) $key );
				}
				if ( ! $key ) {
					return null;
				}
				return new LTLB_AI_Gemini( $key, $model );

			default:
				return null;
		}
	}

	/**
	 * Test connection for given provider
	 *
	 * @param string $provider
	 * @param string $api_key (optional, override stored key)
	 * @return array {success, message, timestamp}
	 */
	public static function test_connection( string $provider = '', string $api_key = '', string $model_override = '' ): array {
		if ( ! $provider ) {
			$ai_config = get_option( 'lazy_ai_config', [] );
			$provider = $ai_config['provider'] ?? 'gemini';
		}

		if ( ! $api_key ) {
			$api_keys = get_option( 'lazy_api_keys', [] );
			$api_key = $api_keys[ $provider ] ?? '';
			if ( $api_key && class_exists( 'LTLB_Crypto' ) ) {
				$api_key = LTLB_Crypto::decrypt_string( (string) $api_key );
			}
		}

		if ( ! $api_key ) {
			return [
				'success' => false,
				'message' => __('No API key available', 'ltl-bookings'),
				'timestamp' => current_time('Y-m-d H:i:s'),
			];
		}

		$model = '';
		if ( is_string( $model_override ) && $model_override !== '' ) {
			$model = $model_override;
		} else {
			$ai_config = get_option( 'lazy_ai_config', [] );
			$model = $ai_config['model'] ?? 'gemini-2.5-flash';
		}

		switch ( strtolower( $provider ) ) {
			case 'gemini':
				$gemini = new LTLB_AI_Gemini( $api_key, $model );
				return $gemini->test_connection();

			default:
				return [
					'success' => false,
					'message' => sprintf(
						__('Unknown provider: %s', 'ltl-bookings'),
						$provider
					),
					'timestamp' => current_time('Y-m-d H:i:s'),
				];
		}
	}
}

