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
		$template_mode = is_array($settings) && isset($settings['template_mode']) ? $settings['template_mode'] : 'service';
		$is_hotel_mode = $template_mode === 'hotel';

		$tabs = [];

        if ($template_mode === 'service') {
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
                    'label' => __( 'Services', 'ltl-bookings' ),
                    'url' => admin_url('admin.php?page=ltlb_services'),
                ],
                'ltlb_resources' => [
                    'label' => __( 'Resources', 'ltl-bookings' ),
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
            ];
        } else { // hotel mode
            $tabs = [
                'ltlb_dashboard' => [
                    'label' => __( 'Dashboard', 'ltl-bookings' ),
                    'url' => admin_url('admin.php?page=ltlb_dashboard'),
                ],
                'ltlb_bookings' => [
                    'label' => __( 'Bookings', 'ltl-bookings' ),
                    'url' => admin_url('admin.php?page=ltlb_appointments'), // reusing appointments page
                ],
                'ltlb_calendar' => [
                    'label' => __( 'Calendar', 'ltl-bookings' ),
                    'url' => admin_url('admin.php?page=ltlb_calendar'),
                ],
                'ltlb_services' => [
                    'label' => __( 'Room Types', 'ltl-bookings' ),
                    'url' => admin_url('admin.php?page=ltlb_services'),
                ],
                'ltlb_resources' => [
                    'label' => __( 'Rooms', 'ltl-bookings' ),
                    'url' => admin_url('admin.php?page=ltlb_resources'),
                ],
                'ltlb_settings' => [
                    'label' => __( 'Settings', 'ltl-bookings' ),
                    'url' => admin_url('admin.php?page=ltlb_settings'),
                ],
            ];
        }

		// Always add design and diagnostics
		$tabs['ltlb_design'] = [
			'label' => __( 'Design', 'ltl-bookings' ),
			'url' => admin_url('admin.php?page=ltlb_design'),
		];
		$tabs['ltlb_diagnostics'] = [
			'label' => __( 'Diagnostics', 'ltl-bookings' ),
			'url' => admin_url('admin.php?page=ltlb_diagnostics'),
		];


		?>
		<div class="ltlb-admin-header">
			<div class="ltlb-admin-header__brand">
				<span class="dashicons dashicons-calendar-alt ltlb-admin-header__icon"></span>
				<div class="ltlb-admin-header__titles">
					<div class="ltlb-admin-header__title"><?php echo esc_html__('LazyBookings', 'ltl-bookings'); ?></div>
					<div class="ltlb-admin-header__subtitle">v<?php echo esc_html(LTLB_VERSION); ?></div>
				</div>
				<?php if ( ! empty( $active_page ) && isset( $tabs[ $active_page ] ) ): ?>
					<nav class="ltlb-breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumbs', 'ltl-bookings'); ?>">
						<a href="<?php echo esc_url( admin_url('admin.php') ); ?>"><?php echo esc_html__('Dashboard', 'ltl-bookings'); ?></a>
						<span class="ltlb-breadcrumbs__sep" aria-hidden="true">/</span>
						<span class="ltlb-breadcrumbs__current"><?php echo esc_html( $tabs[ $active_page ]['label'] ); ?></span>
					</nav>
				<?php endif; ?>
			</div>
			<div class="ltlb-admin-header__main">
				<div class="ltlb-mode-switcher">
				<a href="<?php echo esc_url(add_query_arg(['page' => $_GET['page'], 'ltlb_template_mode' => 'service'])); ?>" class="ltlb-mode-switcher__button <?php echo $template_mode === 'service' ? 'is-active' : ''; ?>">
					<?php echo esc_html__('Appointments', 'ltl-bookings'); ?>
				</a>
				<a href="<?php echo esc_url(add_query_arg(['page' => $_GET['page'], 'ltlb_template_mode' => 'hotel'])); ?>" class="ltlb-mode-switcher__button <?php echo $template_mode === 'hotel' ? 'is-active' : ''; ?>">
						<?php echo esc_html__('Hotel', 'ltl-bookings'); ?>
					</a>
				</div>
				<nav class="ltlb-admin-header__nav">
					<?php foreach ( $tabs as $page_slug => $tab ): ?>
						<a  href="<?php echo esc_url($tab['url']); ?>"
							class="ltlb-admin-header__tab <?php echo $active_page === $page_slug ? 'is-active' : ''; ?>">
							<?php echo esc_html($tab['label']); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>
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
				<script>
				(function(){
					var modeLinks = document.querySelectorAll('.ltlb-admin-header__mode a');
					for (var i = 0; i < modeLinks.length; i++) {
						modeLinks[i].addEventListener('click', function(e) {
							if (this.classList.contains('active')) return;
							if (!confirm(<?php echo wp_json_encode( __( 'Switching modes may hide data specific to the current mode. Continue?', 'ltl-bookings' ) ); ?>)) {
								e.preventDefault();
							}
						});
					}
				})();
				// Global keyboard shortcuts
				document.addEventListener('keydown', function(e) {
					if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
					// S: Focus search if available
					if (e.key === 's' || e.key === 'S') {
						var searchInput = document.querySelector('.ltlb-admin input[type="search"], .ltlb-admin .search-box');
						if (searchInput) {
							e.preventDefault();
							searchInput.focus();
						}
					}
					// N: Click "Add New" button if available
					if (e.key === 'n' || e.key === 'N') {
						var newBtn = document.querySelector('.ltlb-admin .page-title-action');
						if (newBtn) {
							e.preventDefault();
							newBtn.click();
						}
					}
				});
				</script>
			</div>
		</div>
		<?php
	}
}
