<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Crypto {
	private const PREFIX = 'ltlb:enc:v1:';
	private const CIPHER = 'aes-256-gcm';
	private const IV_BYTES = 12;
	private const TAG_BYTES = 16;
	private const AAD = 'ltl-bookings:lazy_api_keys';

	public static function is_encrypted( $value ): bool {
		return is_string( $value ) && strpos( $value, self::PREFIX ) === 0;
	}

	public static function encrypt_string( string $plaintext ): string {
		$plaintext = (string) $plaintext;
		if ( $plaintext === '' ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return $plaintext;
		}

		try {
			$iv = random_bytes( self::IV_BYTES );
		} catch ( Exception $e ) {
			return $plaintext;
		}

		$tag = '';
		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			self::get_key_bytes(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			self::AAD,
			self::TAG_BYTES
		);

		if ( $ciphertext === false || ! is_string( $tag ) || strlen( $tag ) !== self::TAG_BYTES ) {
			return $plaintext;
		}

		$payload = base64_encode( $iv . $tag . $ciphertext );
		if ( ! is_string( $payload ) || $payload === '' ) {
			return $plaintext;
		}

		return self::PREFIX . $payload;
	}

	public static function decrypt_string( string $value ): string {
		$value = (string) $value;
		if ( $value === '' ) {
			return '';
		}

		if ( ! self::is_encrypted( $value ) ) {
			return $value;
		}

		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$payload_b64 = substr( $value, strlen( self::PREFIX ) );
		$raw = base64_decode( $payload_b64, true );
		if ( $raw === false ) {
			return '';
		}

		$min_len = self::IV_BYTES + self::TAG_BYTES + 1;
		if ( strlen( $raw ) < $min_len ) {
			return '';
		}

		$iv = substr( $raw, 0, self::IV_BYTES );
		$tag = substr( $raw, self::IV_BYTES, self::TAG_BYTES );
		$ciphertext = substr( $raw, self::IV_BYTES + self::TAG_BYTES );

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			self::get_key_bytes(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			self::AAD
		);

		if ( $plaintext === false ) {
			if ( class_exists( 'LTLB_Logger' ) ) {
				LTLB_Logger::log_error( 'Failed to decrypt API key. Check that WP salts have not changed.' );
			}
			return '';
		}

		return (string) $plaintext;
	}

	private static function get_key_bytes(): string {
		$material = '';
		if ( function_exists( 'wp_salt' ) ) {
			$material = (string) wp_salt( 'auth' );
		}

		if ( $material === '' && defined( 'AUTH_KEY' ) ) {
			$material = (string) AUTH_KEY;
		}

		if ( $material === '' ) {
			$material = (string) site_url();
		}

		return hash( 'sha256', $material, true );
	}
}
