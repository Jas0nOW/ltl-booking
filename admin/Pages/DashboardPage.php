<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_DashboardPage {
	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('LazyBookings Dashboard', 'ltl-bookings'); ?></h1>
			<p><?php echo esc_html__('Welcome to LazyBookings.', 'ltl-bookings'); ?></p>
		</div>
		<?php
	}
}

