<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Premium Agency-Level Admin Header
 * 
 * Renders the main navigation header with:
 * - Brand identity with logo
 * - Mode switcher (Appointments/Hotel)
 * - Primary navigation tabs
 * - Language selector
 * - User quick actions
 * 
 * @since 1.0.0
 * @since 3.0.0 Agency-level redesign with premium styling
 */
class LTLB_Admin_Header {

	/**
	 * Navigation icons mapping for each page
	 */
	private static array $nav_icons = [
		'ltlb_dashboard'    => 'dashicons-analytics',
		'ltlb_appointments' => 'dashicons-calendar',
		'ltlb_bookings'     => 'dashicons-calendar',
		'ltlb_calendar'     => 'dashicons-calendar-alt',
		'ltlb_customers'    => 'dashicons-groups',
		'ltlb_services'     => 'dashicons-clipboard',
		'ltlb_resources'    => 'dashicons-building',
		'ltlb_staff'        => 'dashicons-id',
		'ltlb_settings'     => 'dashicons-admin-settings',
		'ltlb_design'       => 'dashicons-art',
		'ltlb_diagnostics'  => 'dashicons-sos',
		'ltlb_ai'           => 'dashicons-admin-generic',
	];

	/**
	 * Get the navigation tabs based on template mode
	 */
	private static function get_navigation_tabs( string $template_mode ): array {
		$base_tabs = [];

		if ( $template_mode === 'service' ) {
			$base_tabs = [
				'ltlb_dashboard' => [
					'label' => __( 'Dashboard', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_dashboard'),
					'group' => 'main',
				],
				'ltlb_appointments' => [
					'label' => __( 'Appointments', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_appointments'),
					'group' => 'main',
				],
				'ltlb_calendar' => [
					'label' => __( 'Calendar', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_calendar'),
					'group' => 'main',
				],
				'ltlb_customers' => [
					'label' => __( 'Customers', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_customers'),
					'group' => 'main',
				],
				'ltlb_services' => [
					'label' => __( 'Services', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_services'),
					'group' => 'resources',
				],
				'ltlb_resources' => [
					'label' => __( 'Resources', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_resources'),
					'group' => 'resources',
				],
				'ltlb_staff' => [
					'label' => __( 'Staff', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_staff'),
					'group' => 'resources',
				],
			];
		} else {
			$base_tabs = [
				'ltlb_dashboard' => [
					'label' => __( 'Dashboard', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_dashboard'),
					'group' => 'main',
				],
				'ltlb_bookings' => [
					'label' => __( 'Bookings', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_appointments'),
					'group' => 'main',
				],
				'ltlb_calendar' => [
					'label' => __( 'Calendar', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_calendar'),
					'group' => 'main',
				],
				'ltlb_services' => [
					'label' => __( 'Room Types', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_services'),
					'group' => 'resources',
				],
				'ltlb_resources' => [
					'label' => __( 'Rooms', 'ltl-bookings' ),
					'url'   => admin_url('admin.php?page=ltlb_resources'),
					'group' => 'resources',
				],
			];
		}

		// Settings tab (always present)
		$base_tabs['ltlb_settings'] = [
			'label' => __( 'Settings', 'ltl-bookings' ),
			'url'   => admin_url('admin.php?page=ltlb_settings'),
			'group' => 'system',
		];

		return $base_tabs;
	}

	/**
	 * Render the premium admin header
	 */
	public static function render( string $active_page = '' ): void {
		if ( ! current_user_can('manage_options') && ! current_user_can('view_ai_reports') ) {
			return;
		}

		$current_lang = 'en_US';
		if ( class_exists('LTLB_I18n') ) {
			$current_lang = LTLB_I18n::get_user_admin_locale() ?? LTLB_I18n::get_current_locale();
		}

		$settings = get_option('lazy_settings', []);
		$template_mode = is_array($settings) && isset($settings['template_mode']) ? $settings['template_mode'] : 'service';
		$tabs = self::get_navigation_tabs( $template_mode );

		// Get current user for avatar
		$current_user = wp_get_current_user();
		$avatar_url = get_avatar_url( $current_user->ID, [ 'size' => 32 ] );

		// Safe page retrieval
		$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'ltlb_dashboard';
		?>
		<header class="ltlb-header" role="banner">
			<!-- Top Bar with Brand and Actions -->
			<div class="ltlb-header__top">
				<div class="ltlb-header__brand">
					<div class="ltlb-header__logo">
						<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
							<rect width="28" height="28" rx="6" fill="currentColor" class="ltlb-logo-bg"/>
							<path d="M7 9h14M7 14h10M7 19h14" stroke="white" stroke-width="2" stroke-linecap="round"/>
							<circle cx="21" cy="14" r="3" fill="#FFD700"/>
						</svg>
					</div>
					<div class="ltlb-header__brand-text">
						<span class="ltlb-header__title">LazyBookings</span>
						<span class="ltlb-header__version"><?php echo esc_html__( 'Pro', 'ltl-bookings' ); ?> v<?php echo esc_html(LTLB_VERSION); ?></span>
					</div>
				</div>

				<!-- Mode Switcher -->
				<div class="ltlb-header__mode-switch" role="tablist" aria-label="<?php echo esc_attr__( 'Booking Mode', 'ltl-bookings' ); ?>">
					<a href="<?php echo esc_url(add_query_arg(['page' => $current_page, 'ltlb_template_mode' => 'service'])); ?>" 
					   class="ltlb-mode-switch__btn <?php echo $template_mode === 'service' ? 'is-active' : ''; ?>"
					   role="tab"
					   aria-selected="<?php echo $template_mode === 'service' ? 'true' : 'false'; ?>">
						<span class="dashicons dashicons-clock" aria-hidden="true"></span>
						<?php echo esc_html__('Appointments', 'ltl-bookings'); ?>
					</a>
					<a href="<?php echo esc_url(add_query_arg(['page' => $current_page, 'ltlb_template_mode' => 'hotel'])); ?>" 
					   class="ltlb-mode-switch__btn <?php echo $template_mode === 'hotel' ? 'is-active' : ''; ?>"
					   role="tab"
					   aria-selected="<?php echo $template_mode === 'hotel' ? 'true' : 'false'; ?>">
						<span class="dashicons dashicons-building" aria-hidden="true"></span>
						<?php echo esc_html__('Hotel', 'ltl-bookings'); ?>
					</a>
				</div>

				<!-- Actions Bar -->
				<div class="ltlb-header__actions">
					<!-- Quick Add Button -->
					<?php if ( current_user_can('manage_options') ): ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments&action=add')); ?>" 
					   class="ltlb-header__quick-add"
					   title="<?php echo esc_attr__( 'Quick Add', 'ltl-bookings' ); ?>">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php echo esc_html__( 'Add New', 'ltl-bookings' ); ?></span>
					</a>
					<?php endif; ?>

					<!-- Language Selector -->
					<div class="ltlb-header__lang">
						<label for="ltlb_admin_lang" class="screen-reader-text"><?php echo esc_html__( 'Language', 'ltl-bookings' ); ?></label>
						<select name="ltlb_admin_lang" id="ltlb_admin_lang" class="ltlb-header__lang-select" data-ltlb-admin-lang>
							<option value="en_US" <?php selected( $current_lang, 'en_US' ); ?>>🇬🇧 EN</option>
							<option value="de_DE" <?php selected( $current_lang, 'de_DE' ); ?>>🇩🇪 DE</option>
							<option value="es_ES" <?php selected( $current_lang, 'es_ES' ); ?>>🇪🇸 ES</option>
						</select>
					</div>

					<!-- User Menu -->
					<div class="ltlb-header__user">
						<button type="button" class="ltlb-header__user-btn" aria-expanded="false" aria-haspopup="true">
							<img src="<?php echo esc_url($avatar_url); ?>" alt="" class="ltlb-header__avatar" />
							<span class="ltlb-header__user-name"><?php echo esc_html($current_user->display_name); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
						</button>
						<div class="ltlb-header__user-dropdown" role="menu" hidden>
							<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_settings')); ?>" class="ltlb-header__dropdown-item" role="menuitem">
								<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
								<?php echo esc_html__( 'Settings', 'ltl-bookings' ); ?>
							</a>
							<a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_diagnostics')); ?>" class="ltlb-header__dropdown-item" role="menuitem">
								<span class="dashicons dashicons-sos" aria-hidden="true"></span>
								<?php echo esc_html__( 'Diagnostics', 'ltl-bookings' ); ?>
							</a>
							<hr class="ltlb-header__dropdown-sep" />
							<a href="https://docs.lazybookings.com" target="_blank" rel="noopener" class="ltlb-header__dropdown-item" role="menuitem">
								<span class="dashicons dashicons-book" aria-hidden="true"></span>
								<?php echo esc_html__( 'Documentation', 'ltl-bookings' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>

			<!-- Primary Navigation -->
			<nav class="ltlb-header__nav" role="navigation" aria-label="<?php echo esc_attr__( 'Primary Navigation', 'ltl-bookings' ); ?>">
				<ul class="ltlb-nav" role="menubar">
					<?php foreach ( $tabs as $page_slug => $tab ): 
						$is_active = ( $active_page === $page_slug ) || 
						             ( $page_slug === 'ltlb_bookings' && $active_page === 'ltlb_appointments' );
						$icon = self::$nav_icons[$page_slug] ?? 'dashicons-admin-page';
					?>
					<li class="ltlb-nav__item" role="none">
						<a href="<?php echo esc_url($tab['url']); ?>"
						   class="ltlb-nav__link <?php echo $is_active ? 'is-active' : ''; ?>"
						   role="menuitem"
						   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
							<span class="dashicons <?php echo esc_attr($icon); ?> ltlb-nav__icon" aria-hidden="true"></span>
							<span class="ltlb-nav__label"><?php echo esc_html($tab['label']); ?></span>
							<?php if ( $is_active ): ?>
							<span class="ltlb-nav__indicator" aria-hidden="true"></span>
							<?php endif; ?>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</header>

		<script>
		(function() {
			'use strict';
			
			// User dropdown toggle
			var userBtn = document.querySelector('.ltlb-header__user-btn');
			var userDropdown = document.querySelector('.ltlb-header__user-dropdown');
			
			if (userBtn && userDropdown) {
				userBtn.addEventListener('click', function(e) {
					e.stopPropagation();
					var isExpanded = this.getAttribute('aria-expanded') === 'true';
					this.setAttribute('aria-expanded', !isExpanded);
					userDropdown.hidden = isExpanded;
				});
				
				document.addEventListener('click', function(e) {
					if (!e.target.closest('.ltlb-header__user')) {
						userBtn.setAttribute('aria-expanded', 'false');
						userDropdown.hidden = true;
					}
				});
				
				document.addEventListener('keydown', function(e) {
					if (e.key === 'Escape' && userBtn.getAttribute('aria-expanded') === 'true') {
						userBtn.setAttribute('aria-expanded', 'false');
						userDropdown.hidden = true;
						userBtn.focus();
					}
				});
			}

			// Admin Language Selector
			var langSelect = document.querySelector('[data-ltlb-admin-lang]');
			if (langSelect) {
				var adminAjax = (typeof ajaxurl !== 'undefined' && ajaxurl)
					? ajaxurl
					: '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';

				console.log('[LTLB] Language selector found, AJAX URL:', adminAjax);

				langSelect.addEventListener('change', function() {
					var locale = this.value;
					console.log('[LTLB] Changing language to:', locale);
					
					var xhr = new XMLHttpRequest();
					xhr.open('POST', adminAjax, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.onload = function() {
						console.log('[LTLB] AJAX response:', xhr.status, xhr.responseText);
						// Reload to apply new language on any 2xx
						if (xhr.status >= 200 && xhr.status < 300) {
							window.location.reload();
						} else {
							console.error('Language change failed:', xhr.status, xhr.responseText);
							alert('<?php echo esc_js( __( 'Could not change language. Please try again.', 'ltl-bookings' ) ); ?>');
						}
					};
					xhr.onerror = function() {
						console.error('AJAX error changing language');
						alert('<?php echo esc_js( __( 'Network error. Please try again.', 'ltl-bookings' ) ); ?>');
					};
					xhr.send('action=ltlb_set_admin_lang&locale=' + encodeURIComponent(locale));
				});
			} else {
				console.warn('[LTLB] Language selector not found!');
			}

			// Keyboard shortcuts
			document.addEventListener('keydown', function(e) {
				if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
				
				if ((e.key === 's' || e.key === 'S') && !e.ctrlKey && !e.metaKey) {
					var searchInput = document.querySelector('.ltlb-admin input[type="search"], .ltlb-admin .search-box input');
					if (searchInput) {
						e.preventDefault();
						searchInput.focus();
					}
				}
				
				// N: New appointment/booking
				if ((e.key === 'n' || e.key === 'N') && !e.ctrlKey && !e.metaKey) {
					var addBtn = document.querySelector('.ltlb-header__quick-add');
					if (addBtn) {
						e.preventDefault();
						addBtn.click();
					}
				}
				
				// ?: Show keyboard shortcuts
				if (e.key === '?' && e.shiftKey) {
					e.preventDefault();
					alert(<?php echo wp_json_encode( __( "Keyboard Shortcuts:\n\nS - Focus search\nN - New appointment\n? - Show this help", 'ltl-bookings' ) ); ?>);
				}
			});
		})();
		</script>
		<?php
	}
}
