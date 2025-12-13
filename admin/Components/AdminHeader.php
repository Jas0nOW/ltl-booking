<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_Header {
	public static function render( string $active_page = '' ): void {
		if ( ! current_user_can('manage_options') ) return;

		$current_lang = 'en_US';
		if ( class_exists('LTLB_I18n') ) {
			$current_lang = LTLB_I18n::get_user_admin_locale();
		}

		$settings = get_option('lazy_settings', []);
		$is_hotel = is_array($settings) && isset($settings['template_mode']) && $settings['template_mode'] === 'hotel';

		$tabs = [
			'ltlb_dashboard' => [
				'label' => __( 'Dashboard', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_dashboard'),
			],
			'ltlb_appointments' => [
				'label' => __( 'Appointments', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_appointments'),
			],
			'ltlb_calendar' => [
				'label' => __( 'Calendar', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_calendar'),
			],
			'ltlb_customers' => [
				'label' => __( 'Customers', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_customers'),
			],
			'ltlb_services' => [
				'label' => $is_hotel ? __( 'Room Types', 'ltl-bookings' ) : __( 'Services', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_services'),
			],
			'ltlb_resources' => [
				'label' => $is_hotel ? __( 'Rooms', 'ltl-bookings' ) : __( 'Resources', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_resources'),
			],
			'ltlb_staff' => [
				'label' => __( 'Staff', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_staff'),
			],
			'ltlb_settings' => [
				'label' => __( 'Settings', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_settings'),
			],
			'ltlb_design' => [
				'label' => __( 'Design', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_design'),
			],
			'ltlb_diagnostics' => [
				'label' => __( 'Diagnostics', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_diagnostics'),
			],
			'ltlb_privacy' => [
				'label' => __( 'Privacy', 'ltl-bookings' ),
				'url' => admin_url('admin.php?page=ltlb_privacy'),
			],
		];

		?>
		<div class="ltlb-admin-header">
			<div class="ltlb-admin-header__brand">
				<span class="dashicons dashicons-calendar-alt ltlb-admin-header__icon"></span>
				<div class="ltlb-admin-header__titles">
					<div class="ltlb-admin-header__title">LazyBookings</div>
					<div class="ltlb-admin-header__subtitle"><?php echo esc_html( sprintf( __( 'Version %s', 'ltl-bookings' ), LTLB_VERSION ) ); ?></div>
				</div>
			</div>
			<nav class="ltlb-admin-header__nav" aria-label="<?php echo esc_attr__('LazyBookings Navigation', 'ltl-bookings'); ?>">
				<?php foreach ( $tabs as $slug => $tab ) :
					$is_active = $active_page === $slug;
					?>
					<a class="ltlb-admin-tab <?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tab['url'] ); ?>">
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="ltlb-admin-header__actions">
				<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="ltlb-admin-lang">
					<input type="hidden" name="action" value="ltlb_set_admin_lang" />
					<?php wp_nonce_field( 'ltlb_set_admin_lang' ); ?>
					<label for="ltlb_admin_lang" class="screen-reader-text"><?php echo esc_html__( 'Language', 'ltl-bookings' ); ?></label>
					<select name="ltlb_admin_lang" id="ltlb_admin_lang">
						<option value="en_US" <?php selected( $current_lang, 'en_US' ); ?>><?php echo esc_html__( 'English', 'ltl-bookings' ); ?></option>
						<option value="de_DE" <?php selected( $current_lang, 'de_DE' ); ?>><?php echo esc_html__( 'German', 'ltl-bookings' ); ?></option>
					</select>
					<button type="submit" class="button button-small"><?php echo esc_html__( 'Update', 'ltl-bookings' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}
}
