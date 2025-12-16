<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_CalendarPage {
	public function render(): void {
		if ( ! current_user_can('view_bookings') && ! current_user_can('manage_bookings') ) {
			wp_die( esc_html__('You do not have permission to view this page.', 'ltl-bookings') );
		}

		$settings = get_option( 'lazy_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$template_mode = isset( $settings['template_mode'] ) ? (string) $settings['template_mode'] : 'service';
		$is_hotel_mode = ( $template_mode === 'hotel' );

		?>
		<div class="wrap ltlb-admin<?php echo $is_hotel_mode ? ' ltlb-admin--hotel' : ''; ?>">
			<?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_calendar'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Calendar', 'ltl-bookings' ); ?></h1>
			<hr class="wp-header-end">

			<div class="ltlb-card">
				<div class="ltlb-calendar-layout">
					<?php if ( $is_hotel_mode ): ?>
						<div class="ltlb-calendar-layout__rooms" aria-label="<?php echo esc_attr__( 'Rooms', 'ltl-bookings' ); ?>">
							<div class="ltlb-calendar-rooms__header">
								<strong><?php echo esc_html__( 'Rooms', 'ltl-bookings' ); ?></strong>
								<div class="ltlb-muted"><?php echo esc_html__( 'Room Types', 'ltl-bookings' ); ?></div>
								<button type="button" id="ltlb-auto-sort-rooms" class="button button-small ltlb-auto-sort-button" style="margin-top: 8px;" title="<?php echo esc_attr__( 'Automatically sort rooms by name', 'ltl-bookings' ); ?>">
									<span class="dashicons dashicons-sort"></span>
									<?php echo esc_html__( 'Auto-Sort', 'ltl-bookings' ); ?>
								</button>
							</div>
							<div id="ltlb-admin-calendar-rooms" class="ltlb-calendar-rooms" role="list"></div>
						</div>
					<?php endif; ?>
					<div class="ltlb-calendar-layout__main">
					<div class="ltlb-calendar-legend-wrapper">
						<button type="button" class="ltlb-calendar-legend-toggle" aria-expanded="true" aria-controls="ltlb-calendar-legend-items" aria-label="<?php echo esc_attr__( 'Show or hide status legend', 'ltl-bookings' ); ?>">
							<span class="dashicons dashicons-info" aria-hidden="true"></span>
							<?php echo esc_html__( 'Legend', 'ltl-bookings' ); ?>
							<span class="dashicons dashicons-arrow-down-alt2 ltlb-legend-toggle-icon" aria-hidden="true"></span>
						</button>
							<div id="ltlb-calendar-legend-items" class="ltlb-calendar-legend" aria-label="<?php echo esc_attr__( 'Status Legend', 'ltl-bookings' ); ?>">
								<span class="ltlb-calendar-legend__item ltlb-fc-status-confirmed">
									<input type="color" class="ltlb-calendar-legend__color" data-ltlb-status="confirmed" value="#2271b1" aria-label="<?php echo esc_attr__( 'Change color for Confirmed', 'ltl-bookings' ); ?>" title="<?php echo esc_attr__( 'Change color', 'ltl-bookings' ); ?>" />
									<?php echo esc_html__( 'Confirmed', 'ltl-bookings' ); ?>
								</span>
								<span class="ltlb-calendar-legend__item ltlb-fc-status-pending">
									<input type="color" class="ltlb-calendar-legend__color" data-ltlb-status="pending" value="#2271b1" aria-label="<?php echo esc_attr__( 'Change color for Pending', 'ltl-bookings' ); ?>" title="<?php echo esc_attr__( 'Change color', 'ltl-bookings' ); ?>" />
									<?php echo esc_html__( 'Pending', 'ltl-bookings' ); ?>
								</span>
								<span class="ltlb-calendar-legend__item ltlb-fc-status-cancelled">
									<input type="color" class="ltlb-calendar-legend__color" data-ltlb-status="cancelled" value="#ccd0d4" aria-label="<?php echo esc_attr__( 'Change color for Cancelled', 'ltl-bookings' ); ?>" title="<?php echo esc_attr__( 'Change color', 'ltl-bookings' ); ?>" />
									<?php echo esc_html__( 'Cancelled', 'ltl-bookings' ); ?>
								</span>
							</div>
						</div>
				<div id="ltlb-admin-calendar-loading" class="ltlb-calendar-loading" role="status" aria-live="polite">
					<span class="spinner is-active"></span>
					<span class="screen-reader-text"><?php echo esc_html__( 'Loading calendar… This may take a few seconds.', 'ltl-bookings' ); ?></span>
				</div>
				<div id="ltlb-admin-calendar" style="min-height: <?php echo $is_hotel_mode ? '0' : '700'; ?>px;" hidden></div>
				<?php if ( $is_hotel_mode ): ?>
					<div id="ltlb-admin-hotel-rack" class="ltlb-hotel-rack" hidden></div>
				<?php endif; ?>
					</div>
					<div class="ltlb-calendar-layout__side">
						<div id="ltlb-admin-calendar-details-empty" class="ltlb-calendar-details-empty">
							<p class="ltlb-muted" style="margin:0;"><?php echo esc_html__( 'Tip: Click an appointment to view and edit details.', 'ltl-bookings' ); ?></p>
						</div>
						<div id="ltlb-admin-calendar-details" class="ltlb-calendar-details" hidden></div>
						<div id="ltlb-admin-calendar-live" class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true"></div>
						<div id="ltlb-admin-calendar-live-assertive" class="screen-reader-text" role="alert" aria-live="assertive" aria-atomic="true"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
