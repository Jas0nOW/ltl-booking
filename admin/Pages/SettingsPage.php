<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_SettingsPage {

	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

		// Handle save
		if ( isset( $_POST['ltlb_settings_save'] ) ) {
			if ( ! check_admin_referer( 'ltlb_settings_save_action', 'ltlb_settings_nonce' ) ) {
				wp_die( esc_html__('Nonce verification failed', 'ltl-bookings') );
			}

			$start = isset( $_POST['working_hours_start'] ) ? intval( $_POST['working_hours_start'] ) : 9;
			$end = isset( $_POST['working_hours_end'] ) ? intval( $_POST['working_hours_end'] ) : 17;
			$slot = isset( $_POST['slot_size_minutes'] ) ? intval( $_POST['slot_size_minutes'] ) : 60;
			$tz = isset( $_POST['ltlb_timezone'] ) ? sanitize_text_field( $_POST['ltlb_timezone'] ) : '';
			$default_status = isset( $_POST['default_status'] ) ? sanitize_text_field( $_POST['default_status'] ) : 'pending';
			$pending_blocks = isset( $_POST['pending_blocks'] ) ? 1 : 0;

			update_option( 'ltlb_working_hours_start', $start );
			update_option( 'ltlb_working_hours_end', $end );
			update_option( 'ltlb_slot_minutes', $slot );
			update_option( 'ltlb_timezone', $tz );
			update_option( 'ltlb_default_status', $default_status );
			update_option( 'ltlb_pending_blocks', $pending_blocks );

			$redirect = add_query_arg( 'message', 'saved', admin_url( 'admin.php?page=ltlb_settings' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		$start = (int) get_option( 'ltlb_working_hours_start', 9 );
		$end = (int) get_option( 'ltlb_working_hours_end', 17 );
		$slot = (int) get_option( 'ltlb_slot_minutes', 60 );
		$tz = get_option( 'ltlb_timezone', '' );
		$default_status = get_option( 'ltlb_default_status', 'pending' );
		$pending_blocks = get_option( 'ltlb_pending_blocks', 0 );

		$timezones = timezone_identifiers_list();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('LazyBookings Settings', 'ltl-bookings'); ?></h1>

			<?php if ( isset( $_GET['message'] ) && $_GET['message'] === 'saved' ) : ?>
				<div id="message" class="updated notice is-dismissible"><p><?php echo esc_html__('Settings saved.', 'ltl-bookings'); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'ltlb_settings_save_action', 'ltlb_settings_nonce' ); ?>
				<input type="hidden" name="ltlb_settings_save" value="1">

				<table class="form-table">
					<tbody>
						<tr>
							<th><label for="working_hours_start"><?php echo esc_html__('Working hours start (hour)', 'ltl-bookings'); ?></label></th>
							<td><input name="working_hours_start" id="working_hours_start" type="number" value="<?php echo esc_attr( $start ); ?>" class="small-text"> (0-23)</td>
						</tr>
						<tr>
							<th><label for="working_hours_end"><?php echo esc_html__('Working hours end (hour)', 'ltl-bookings'); ?></label></th>
							<td><input name="working_hours_end" id="working_hours_end" type="number" value="<?php echo esc_attr( $end ); ?>" class="small-text"> (exclusive, 1-24)</td>
						</tr>
						<tr>
							<th><label for="slot_size_minutes"><?php echo esc_html__('Slot size (minutes)', 'ltl-bookings'); ?></label></th>
							<td><input name="slot_size_minutes" id="slot_size_minutes" type="number" value="<?php echo esc_attr( $slot ); ?>" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="ltlb_timezone"><?php echo esc_html__('Timezone (optional)', 'ltl-bookings'); ?></label></th>
							<td>
								<select name="ltlb_timezone" id="ltlb_timezone">
									<option value=""><?php echo esc_html__('Use site timezone', 'ltl-bookings'); ?></option>
									<?php foreach ( $timezones as $tzid ): ?>
										<option value="<?php echo esc_attr( $tzid ); ?>" <?php selected( $tz, $tzid ); ?>><?php echo esc_html( $tzid ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="default_status"><?php echo esc_html__('Default appointment status', 'ltl-bookings'); ?></label></th>
							<td>
								<select name="default_status" id="default_status">
									<option value="pending" <?php selected( $default_status, 'pending' ); ?>><?php echo esc_html__('Pending', 'ltl-bookings'); ?></option>
									<option value="confirmed" <?php selected( $default_status, 'confirmed' ); ?>><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php echo esc_html__('Pending blocks slots', 'ltl-bookings'); ?></th>
							<td><label><input name="pending_blocks" type="checkbox" value="1" <?php checked( $pending_blocks ); ?>> <?php echo esc_html__('Treat pending appointments as blocking slots', 'ltl-bookings'); ?></label></td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( esc_html__('Save Settings', 'ltl-bookings') ); ?>
			</form>
		</div>
		<?php
	}
}

