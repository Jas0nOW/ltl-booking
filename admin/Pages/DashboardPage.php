<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_DashboardPage {
	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

		$svc_repo = new LTLB_ServiceRepository();
		$cust_repo = new LTLB_CustomerRepository();
		$appt_repo = new LTLB_AppointmentRepository();
		$res_repo = new LTLB_ResourceRepository();
		$appt_res_repo = class_exists('LTLB_AppointmentResourcesRepository') ? new LTLB_AppointmentResourcesRepository() : null;

		$services = $svc_repo->get_all();
		$customers = $cust_repo->get_all();
		$appointments = $appt_repo->get_all();
		$resources = $res_repo->get_all();

		// last 5 appointments
		$last5 = $appt_repo->get_all();
		usort($last5, function($a,$b){ return strcmp($b['start_at'],$a['start_at']); });
		$last5 = array_slice($last5, 0, 5);

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('LazyBookings Dashboard', 'ltl-bookings'); ?></h1>

			<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
				<div style="padding:12px;border:1px solid #ddd;border-radius:6px;min-width:160px;">
					<strong><?php echo esc_html__('Services', 'ltl-bookings'); ?></strong>
					<div style="font-size:24px;"><?php echo intval(count($services)); ?></div>
				</div>
				<div style="padding:12px;border:1px solid #ddd;border-radius:6px;min-width:160px;">
					<strong><?php echo esc_html__('Customers', 'ltl-bookings'); ?></strong>
					<div style="font-size:24px;"><?php echo intval(count($customers)); ?></div>
				</div>
				<div style="padding:12px;border:1px solid #ddd;border-radius:6px;min-width:160px;">
					<strong><?php echo esc_html__('Appointments', 'ltl-bookings'); ?></strong>
					<div style="font-size:24px;"><?php echo intval(count($appointments)); ?></div>
				</div>
				<div style="padding:12px;border:1px solid #ddd;border-radius:6px;min-width:160px;">
					<strong><?php echo esc_html__('Resources', 'ltl-bookings'); ?></strong>
					<div style="font-size:24px;"><?php echo intval(count($resources)); ?></div>
				</div>
			</div>

			<h2><?php echo esc_html__('Latest appointments', 'ltl-bookings'); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__('ID', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Service', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Start', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Resource', 'ltl-bookings'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $last5 as $a ): ?>
						<tr>
							<td><?php echo intval($a['id']); ?></td>
							<td><?php
								$srepo = new LTLB_ServiceRepository();
								$s = $srepo->get_by_id( intval($a['service_id']) );
								echo esc_html( $s ? $s['name'] : '—' );
							?></td>
							<td><?php echo esc_html( $a['start_at'] ); ?></td>
							<td><?php echo esc_html( $a['status'] ); ?></td>
							<td><?php
								$rid = null;
								if ( $appt_res_repo ) {
									$rid = $appt_res_repo->get_resource_for_appointment( intval($a['id']) );
								}
								if ( $rid ) {
									$r = $res_repo->get_by_id( intval($rid) );
									echo esc_html( $r ? $r['name'] : '—' );
								} else {
									echo '—';
								}
							?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

