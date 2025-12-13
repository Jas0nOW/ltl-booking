<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_AppointmentsPage {

	private $repo;

	public function __construct() {
		$this->repo = new LTLB_AppointmentRepository();
	}

	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

		// Handle status change action via GET + nonce
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'change_status' && ! empty( $_GET['id'] ) && ! empty( $_GET['status'] ) && ! empty( $_GET['nonce'] ) ) {
			$id = intval( $_GET['id'] );
			$status = sanitize_text_field( $_GET['status'] );
			$nonce = sanitize_text_field( $_GET['nonce'] );

			if ( wp_verify_nonce( $nonce, 'ltlb_change_status_' . $id ) ) {
				$ok = $this->repo->update_status( $id, $status );
				$redirect = admin_url( 'admin.php?page=ltlb_appointments' );
				if ( $ok ) {
					LTLB_Notices::add( __( 'Status updated.', 'ltl-bookings' ), 'success' );
				} else {
					LTLB_Notices::add( __( 'An error occurred.', 'ltl-bookings' ), 'error' );
				}
				wp_safe_redirect( $redirect );
				exit;
			} else {
				wp_die( esc_html__('Nonce verification failed', 'ltl-bookings') );
			}
		}

		$filters = [];
		if ( isset($_GET['from']) ) $filters['from'] = sanitize_text_field($_GET['from']);
		if ( isset($_GET['to']) ) $filters['to'] = sanitize_text_field($_GET['to']);
		if ( isset($_GET['status']) ) $filters['status'] = sanitize_text_field($_GET['status']);

		$rows = $this->repo->get_all($filters);

		// Get template mode for display
		$settings = get_option( 'lazy_settings', [] );
		$template_mode = is_array($settings) && isset($settings['template_mode']) ? $settings['template_mode'] : 'service';

		// Load resource repo if needed
		$appt_res_repo = null;
		$res_repo = null;
		if ( class_exists('LTLB_AppointmentResourcesRepository') ) {
			$appt_res_repo = new LTLB_AppointmentResourcesRepository();
			$res_repo = new LTLB_ResourceRepository();
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Appointments', 'ltl-bookings'); ?></h1>

			<form method="get" class="ltlb-filters" style="margin-bottom:12px;">
				<input type="hidden" name="page" value="ltlb_appointments" />
				<label><?php echo esc_html__('From', 'ltl-bookings'); ?> <input type="date" name="from" value="<?php echo esc_attr( $filters['from'] ?? '' ); ?>"></label>
				&nbsp;
				<label><?php echo esc_html__('To', 'ltl-bookings'); ?> <input type="date" name="to" value="<?php echo esc_attr( $filters['to'] ?? '' ); ?>"></label>
				&nbsp;
				<label><?php echo esc_html__('Status', 'ltl-bookings'); ?>
					<select name="status">
						<option value=""><?php echo esc_html__('Any', 'ltl-bookings'); ?></option>
						<option value="pending" <?php selected( $filters['status'] ?? '', 'pending' ); ?>><?php echo esc_html__('Pending', 'ltl-bookings'); ?></option>
						<option value="confirmed" <?php selected( $filters['status'] ?? '', 'confirmed' ); ?>><?php echo esc_html__('Confirmed', 'ltl-bookings'); ?></option>
						<option value="canceled" <?php selected( $filters['status'] ?? '', 'canceled' ); ?>><?php echo esc_html__('Canceled', 'ltl-bookings'); ?></option>
					</select>
				</label>
				&nbsp;
				<?php submit_button( esc_html__('Filter'), 'secondary', '', false ); ?>
			</form>

			<?php // Notices are rendered via LTLB_Notices::render() hooked to admin_notices ?>

			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<?php if ( $template_mode === 'hotel' ): ?>
							<th><?php echo esc_html__('Room Type', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Customer', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Check-in', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Check-out', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Nights', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Guests', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Room', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
						<?php else: ?>
							<th><?php echo esc_html__('Service ID', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Customer ID', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Start', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('End', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Seats', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
							<th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty($rows) ): ?>
						<tr><td colspan="9"><?php echo esc_html__('No appointments', 'ltl-bookings'); ?></td></tr>
					<?php else: foreach($rows as $r): ?>
						<tr>
							<?php if ( $template_mode === 'hotel' ): ?>
								<?php 
									$svc_repo = new LTLB_ServiceRepository();
									$cust_repo = new LTLB_CustomerRepository();
									$svc = $svc_repo->get_by_id( intval($r['service_id']) );
									$cust = $cust_repo->get_by_id( intval($r['customer_id']) );
									$room_name = '—';
									if ( $appt_res_repo && $res_repo ) {
										$res_id = $appt_res_repo->get_resource_for_appointment( intval($r['id']) );
										if ( $res_id ) {
											$res = $res_repo->get_by_id( $res_id );
											if ( $res ) $room_name = $res['name'];
										}
									}
									$nights = LTLB_Time::nights_between( $r['start_at'], $r['end_at'] );
									$cust_name = trim( ($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? '') ) ?: ($cust['email'] ?? '');
								?>
								<td><?php echo esc_html( $svc['name'] ?? $r['service_id'] ); ?></td>
								<td><?php echo esc_html( $cust_name ); ?></td>
								<td><?php echo esc_html( substr($r['start_at'], 0, 10) ); ?></td>
								<td><?php echo esc_html( substr($r['end_at'], 0, 10) ); ?></td>
								<td><?php echo esc_html( $nights ); ?></td>
								<td><?php echo esc_html( $r['seats'] ?? 1 ); ?></td>
								<td><?php echo esc_html( $room_name ); ?></td>
								<td><?php echo esc_html($r['status']); ?></td>
								<td>
									<?php if ( $r['status'] !== 'confirmed' ): ?>
										<?php $nonce = wp_create_nonce( 'ltlb_change_status_' . intval($r['id']) ); ?>
										<a href="<?php echo esc_attr( add_query_arg( [ 'page' => 'ltlb_appointments', 'action' => 'change_status', 'id' => intval($r['id']), 'status' => 'confirmed', 'nonce' => $nonce ], admin_url('admin.php') ) ); ?>"><?php echo esc_html__('Confirm', 'ltl-bookings'); ?></a>
										&nbsp;
									<?php endif; ?>
									<?php if ( $r['status'] !== 'canceled' ): ?>
										<?php $nonce2 = wp_create_nonce( 'ltlb_change_status_' . intval($r['id']) ); ?>
										<a href="<?php echo esc_attr( add_query_arg( [ 'page' => 'ltlb_appointments', 'action' => 'change_status', 'id' => intval($r['id']), 'status' => 'canceled', 'nonce' => $nonce2 ], admin_url('admin.php') ) ); ?>"><?php echo esc_html__('Cancel', 'ltl-bookings'); ?></a>
									<?php endif; ?>
								</td>
							<?php else: ?>
								<td><?php echo esc_html($r['service_id']); ?></td>
								<td><?php echo esc_html($r['customer_id']); ?></td>
								<td><?php echo esc_html($r['start_at']); ?></td>
								<td><?php echo esc_html($r['end_at']); ?></td>
								<td><?php echo esc_html( $r['seats'] ?? 1 ); ?></td>
								<td><?php echo esc_html($r['status']); ?></td>
								<td>
									<?php if ( $r['status'] !== 'confirmed' ): ?>
										<?php $nonce = wp_create_nonce( 'ltlb_change_status_' . intval($r['id']) ); ?>
										<a href="<?php echo esc_attr( add_query_arg( [ 'page' => 'ltlb_appointments', 'action' => 'change_status', 'id' => intval($r['id']), 'status' => 'confirmed', 'nonce' => $nonce ], admin_url('admin.php') ) ); ?>"><?php echo esc_html__('Confirm', 'ltl-bookings'); ?></a>
										&nbsp;
									<?php endif; ?>
									<?php if ( $r['status'] !== 'canceled' ): ?>
										<?php $nonce2 = wp_create_nonce( 'ltlb_change_status_' . intval($r['id']) ); ?>
										<a href="<?php echo esc_attr( add_query_arg( [ 'page' => 'ltlb_appointments', 'action' => 'change_status', 'id' => intval($r['id']), 'status' => 'canceled', 'nonce' => $nonce2 ], admin_url('admin.php') ) ); ?>"><?php echo esc_html__('Cancel', 'ltl-bookings'); ?></a>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

