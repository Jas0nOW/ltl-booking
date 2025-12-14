<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_CalendarPage {
	public function render(): void {
		if ( ! current_user_can('manage_options') ) {
			wp_die( esc_html__('You do not have permission to view this page.', 'ltl-bookings') );
		}

		?>
		<div class="wrap ltlb-admin">
			<?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_calendar'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Calendar', 'ltl-bookings' ); ?></h1>
			<hr class="wp-header-end">

			<div class="ltlb-card">
				<div class="ltlb-calendar-layout">
					<div class="ltlb-calendar-layout__main">
						<div class="ltlb-calendar-legend" aria-label="<?php echo esc_attr__( 'Legend', 'ltl-bookings' ); ?>">
							<span class="ltlb-calendar-legend__item ltlb-fc-status-confirmed"><?php echo esc_html__( 'Confirmed', 'ltl-bookings' ); ?></span>
							<span class="ltlb-calendar-legend__item ltlb-fc-status-pending"><?php echo esc_html__( 'Pending', 'ltl-bookings' ); ?></span>
							<span class="ltlb-calendar-legend__item ltlb-fc-status-cancelled"><?php echo esc_html__( 'Cancelled', 'ltl-bookings' ); ?></span>
						</div>
						<div id="ltlb-admin-calendar" style="min-height: 700px;"></div>
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
