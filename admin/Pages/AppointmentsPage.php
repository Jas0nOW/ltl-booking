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
						<th><?php echo esc_html__('Service ID', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Customer ID', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Resource', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Start', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('End', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty($rows) ): ?>
						<tr><td colspan="7"><?php echo esc_html__('No appointments', 'ltl-bookings'); ?></td></tr>
					<?php else: foreach($rows as $r): ?>
						<tr>
							<td><?php echo esc_html($r['service_id']); ?></td>
							<td><?php echo esc_html($r['customer_id']); ?></td>
							<?php
								// get resource name for appointment
								$apptResRepo = new LTLB_AppointmentResourcesRepository();
								$resRepo = new LTLB_ResourceRepository();
								$resId = $apptResRepo->get_resource_for_appointment( intval($r['id']) );
								$resName = '—';
								if ( $resId ) {
									$res = $resRepo->get_by_id( intval($resId) );
									if ( $res ) $resName = sanitize_text_field( $res['name'] );
								}
							?>
							<td><?php echo esc_html( $resName ); ?></td>
							<td><?php echo esc_html($r['start_at']); ?></td>
							<td><?php echo esc_html($r['end_at']); ?></td>
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
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

