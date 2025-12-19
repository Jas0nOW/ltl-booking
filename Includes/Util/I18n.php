<?php
/**
 * LTLB Internationalization (i18n) Handler
 * 
 * Provides locale switching for both admin (per-user) and frontend (per-visitor).
 * Uses WordPress standard .mo files for translations - no custom dictionary needed.
 * 
 * @package LTL_Bookings
 * @since 2.0.0
 */
if ( ! defined('ABSPATH') ) exit;

class LTLB_I18n {
	public const TEXT_DOMAIN = 'ltl-bookings';
	public const USER_META_KEY = 'ltlb_admin_lang';
	public const FRONTEND_LANG_COOKIE = 'ltlb_frontend_lang';

	/** @var array Supported locales with labels */
	public const SUPPORTED_LOCALES = [
		'en_US' => [ 'name' => 'English', 'short' => 'EN', 'flag' => '🇬🇧' ],
		'de_DE' => [ 'name' => 'Deutsch', 'short' => 'DE', 'flag' => '🇩🇪' ],
		'es_ES' => [ 'name' => 'Español', 'short' => 'ES', 'flag' => '🇪🇸' ],
	];

	/** @var string|null Cached current locale */
	private static ?string $current_locale = null;

	/**
	 * Initialize i18n hooks
	 */
	public static function init(): void {
		// Early locale filter - BEFORE textdomain is loaded
		add_filter( 'locale', [ __CLASS__, 'filter_locale' ], 1 );
		
		// Reload textdomain when locale changes
		add_action( 'init', [ __CLASS__, 'maybe_reload_textdomain' ], 1 );
		
		// Frontend language switch AJAX handler
		add_action( 'wp_ajax_ltlb_set_frontend_lang', [ __CLASS__, 'ajax_set_frontend_lang' ] );
		add_action( 'wp_ajax_nopriv_ltlb_set_frontend_lang', [ __CLASS__, 'ajax_set_frontend_lang' ] );
		
		// Admin language switch AJAX handler
		add_action( 'wp_ajax_ltlb_set_admin_lang', [ __CLASS__, 'ajax_set_admin_lang' ] );
	}

	/**
	 * Get supported locales
	 */
	public static function get_supported_locales(): array {
		return self::SUPPORTED_LOCALES;
	}

	/**
	 * Check if a locale is supported
	 */
	public static function is_supported_locale( string $locale ): bool {
		return isset( self::SUPPORTED_LOCALES[ $locale ] );
	}

	/**
	 * Filter WordPress locale based on context
	 */
	public static function filter_locale( string $locale ): string {
		if ( self::$current_locale !== null ) {
			return self::$current_locale;
		}

		$new_locale = $locale;

		// Admin context: use per-user preference
		if ( is_admin() && ! wp_doing_ajax() ) {
			$user_locale = self::get_user_admin_locale();
			if ( $user_locale && self::is_supported_locale( $user_locale ) ) {
				$new_locale = $user_locale;
			}
		}
		// Frontend/AJAX context: use cookie preference
		else {
			$cookie_locale = self::get_cookie_locale();
			if ( $cookie_locale && self::is_supported_locale( $cookie_locale ) ) {
				$new_locale = $cookie_locale;
			}
		}

		self::$current_locale = $new_locale;
		return $new_locale;
	}

	/**
	 * Reload textdomain with correct locale
	 */
	public static function maybe_reload_textdomain(): void {
		$locale = self::get_current_locale();
		
		// Unload and reload our textdomain with the correct locale
		unload_textdomain( self::TEXT_DOMAIN );
		
		// Try to load from plugin languages folder
		$mo_file = LTLB_PATH . 'languages/' . $locale . '.mo';
		if ( file_exists( $mo_file ) ) {
			load_textdomain( self::TEXT_DOMAIN, $mo_file );
		} else {
			// Fallback to standard WordPress location
			load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( LTLB_PATH . 'ltl-booking.php' ) ) . '/languages' );
		}
	}

	/**
	 * Get current effective locale
	 */
	public static function get_current_locale(): string {
		if ( self::$current_locale !== null ) {
			return self::$current_locale;
		}
		return get_locale();
	}

	/**
	 * Get locale from cookie
	 */
	public static function get_cookie_locale(): ?string {
		if ( ! isset( $_COOKIE[ self::FRONTEND_LANG_COOKIE ] ) ) {
			return null;
		}
		$locale = sanitize_text_field( wp_unslash( $_COOKIE[ self::FRONTEND_LANG_COOKIE ] ) );
		return self::is_supported_locale( $locale ) ? $locale : null;
	}

	/**
	 * Get user's preferred admin locale
	 */
	public static function get_user_admin_locale( ?int $user_id = null ): ?string {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return null;
		}

		$locale = get_user_meta( $user_id, self::USER_META_KEY, true );
		if ( ! is_string( $locale ) || ! trim( $locale ) ) {
			return null;
		}
		$locale = trim( $locale );
		return self::is_supported_locale( $locale ) ? $locale : null;
	}

	/**
	 * Set user's preferred admin locale
	 */
	public static function set_user_admin_locale( int $user_id, string $locale ): bool {
		if ( ! self::is_supported_locale( $locale ) ) {
			return false;
		}
		update_user_meta( $user_id, self::USER_META_KEY, $locale );
		self::$current_locale = null;
		return true;
	}

	/**
	 * Get frontend language (from cookie or site default)
	 */
	public static function get_frontend_locale(): string {
		$cookie_locale = self::get_cookie_locale();
		if ( $cookie_locale ) {
			return $cookie_locale;
		}
		return get_locale();
	}

	/**
	 * Set frontend language cookie
	 */
	public static function set_frontend_locale( string $locale ): bool {
		if ( ! self::is_supported_locale( $locale ) ) {
			return false;
		}
		
		$secure = is_ssl();
		$cookie_path = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		
		setcookie( 
			self::FRONTEND_LANG_COOKIE, 
			$locale, 
			[
				'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
				'path'     => $cookie_path,
				'domain'   => $cookie_domain,
				'secure'   => $secure,
				'httponly' => false,
				'samesite' => 'Lax',
			]
		);
		
		$_COOKIE[ self::FRONTEND_LANG_COOKIE ] = $locale;
		self::$current_locale = null;
		
		return true;
	}

	/**
	 * AJAX handler for frontend language switch
	 */
	public static function ajax_set_frontend_lang(): void {
		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';
		
		if ( ! self::is_supported_locale( $locale ) ) {
			wp_send_json_error( [ 'message' => 'Invalid locale' ], 400 );
			return;
		}
		
		self::set_frontend_locale( $locale );
		wp_send_json_success( [ 'locale' => $locale ] );
	}

	/**
	 * AJAX handler for admin language switch
	 */
	public static function ajax_set_admin_lang(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Not logged in' ], 401 );
			return;
		}

		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';
		
		if ( ! self::is_supported_locale( $locale ) ) {
			wp_send_json_error( [ 'message' => 'Invalid locale' ], 400 );
			return;
		}
		
		$user_id = get_current_user_id();
		self::set_user_admin_locale( $user_id, $locale );
		wp_send_json_success( [ 'locale' => $locale ] );
	}

	/**
	 * Check if current request is an LTLB admin page
	 */
	public static function is_ltlb_admin_page_request(): bool {
		if ( ! is_admin() ) {
			return false;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! is_string( $page ) || ! $page ) {
			return false;
		}
		return strpos( $page, 'ltlb_' ) === 0;
	}
}