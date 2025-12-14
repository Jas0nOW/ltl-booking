<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Role & Capability Manager for LazyBookings
 * 
 * Handles custom caps registration and role profile setup.
 */
class LTLB_Role_Manager {

	/**
	 * Ensure plugin-specific roles exist.
	 */
	public static function register_roles(): void {
		if ( ! function_exists( 'add_role' ) ) {
			return;
		}

		// CEO: can view reports/insights, but cannot manage secrets/settings.
		if ( ! get_role( 'ltlb_ceo' ) ) {
			add_role(
				'ltlb_ceo',
				__( 'LazyBookings CEO', 'ltl-bookings' ),
				[ 'read' => true ]
			);
		}

		// Staff: minimal access placeholder role.
		if ( ! get_role( 'ltlb_staff' ) ) {
			add_role(
				'ltlb_staff',
				__( 'LazyBookings Staff', 'ltl-bookings' ),
				[ 'read' => true ]
			);
		}
	}

	/**
	 * Register all custom capabilities
	 */
	public static function register_capabilities(): void {
		if ( ! function_exists('register_post_type') ) {
			return; // wp not fully loaded
		}

		// Define custom caps
		$caps = [
			'manage_ai_settings' => [
				'label' => __('Manage AI Settings', 'ltl-bookings'),
				'roles' => ['administrator'],
			],
			'manage_ai_secrets' => [
				'label' => __('Manage AI API Keys', 'ltl-bookings'),
				'roles' => ['administrator'],
			],
			'view_ai_reports' => [
				'label' => __('View AI Reports & Insights', 'ltl-bookings'),
				'roles' => ['administrator', 'ltlb_ceo'],
			],
			'approve_ai_drafts' => [
				'label' => __('Approve AI Actions', 'ltl-bookings'),
				'roles' => ['administrator'],
			],
			'manage_staff_roles' => [
				'label' => __('Manage Staff Roles', 'ltl-bookings'),
				'roles' => ['administrator'],
			],
		];

		// Assign caps to roles
		foreach ( $caps as $cap_name => $cap_data ) {
			foreach ( $cap_data['roles'] as $role_name ) {
				$role = get_role( $role_name );
				if ( $role ) {
					$role->add_cap( $cap_name );
				}
			}
		}
	}

	/**
	 * Check if user can perform action
	 */
	public static function user_can( string $cap, int $user_id = 0 ): bool {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		return user_can( $user_id, $cap );
	}

	/**
	 * Get current user profile (superadmin/ceo/mitarbeiter)
	 */
	public static function get_user_profile( int $user_id = 0 ): string {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return 'guest';
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return 'guest';
		}

		// Check role hierarchy
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return 'superadmin';
		}
		if ( in_array( 'ltlb_ceo', (array) $user->roles, true ) ||
			 in_array( 'ceo', (array) $user->roles, true ) ||
		     user_can( $user_id, 'view_ai_reports' ) ) {
			return 'ceo';
		}
		if ( in_array( 'ltlb_staff', (array) $user->roles, true ) ||
			 in_array( 'editor', (array) $user->roles, true ) ) {
			return 'mitarbeiter';
		}

		return 'guest';
	}

	/**
	 * Get allowed menu items for user's profile
	 */
	public static function get_allowed_menu_items( string $profile = '' ): array {
		if ( ! $profile ) {
			$profile = self::get_user_profile();
		}

		$all_items = [
			'ltlb_dashboard' => true,
			'ltlb_appointments' => true,
			'ltlb_calendar' => true,
			'ltlb_customers' => true,
			'ltlb_services' => true,
			'ltlb_resources' => true,
			'ltlb_staff' => true,
			'ltlb_design' => true,
			'ltlb_diagnostics' => true,
			'ltlb_settings' => false, // restricted
			'ltlb_ai' => false, // restricted
		];

		switch ( $profile ) {
			case 'superadmin':
				// All items visible
				return array_map( fn() => true, $all_items );

			case 'ceo':
				// Read-only: dashboards only (reports/finances).
				return [
					'ltlb_dashboard' => true,
					'ltlb_calendar' => false,
					'ltlb_customers' => false,
					'ltlb_appointments' => false,
					'ltlb_design' => false,
					'ltlb_settings' => false,
					'ltlb_ai' => false,
					'ltlb_diagnostics' => false,
				];

			case 'mitarbeiter':
				// No default access (placeholder profile).
				return [
					'ltlb_appointments' => false,
					'ltlb_calendar' => false,
					'ltlb_customers' => false,
					'ltlb_settings' => false,
					'ltlb_ai' => false,
					'ltlb_diagnostics' => false,
				];

			default:
				// Guest: nothing
				return array_map( fn() => false, $all_items );
		}
	}

	/**
	 * Filter admin menu by user profile
	 */
	public static function filter_admin_menu(): void {
		if ( ! is_admin() ) {
			return;
		}

		$profile = self::get_user_profile();
		$allowed = self::get_allowed_menu_items( $profile );

		add_action( 'admin_menu', function() use ( $allowed ) {
			global $menu, $submenu;

			foreach ( $menu as $key => $item ) {
				if ( isset( $item[2] ) ) {
					$page_slug = $item[2];
					if ( isset( $allowed[ $page_slug ] ) && ! $allowed[ $page_slug ] ) {
						unset( $menu[ $key ] );
					}
				}
			}
		}, 999 );
	}
}

