<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_DashboardPage {
	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

		global $wpdb;

		$tbl = function($name) use ($wpdb) { return $wpdb->prefix . 'lazy_' . $name; };

		$counts = [];
		$names = ['services','customers','appointments','resources','service_resources','appointment_resources','staff_hours','staff_exceptions'];
		foreach ( $names as $n ) {
			$table = $tbl($n);
			$counts[$n] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		// last 5 appointments
		$appts_table = $tbl('appointments');
		$last_appts = $wpdb->get_results( "SELECT * FROM {$appts_table} ORDER BY start_at DESC LIMIT 5", ARRAY_A );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('LazyBookings Status', 'ltl-bookings'); ?></h1>
			<style>
			.ltlb-status-grid{display:flex;gap:18px;flex-wrap:wrap;margin-bottom:18px}
			.ltlb-status-card{background:#fff;border:1px solid #e9e9e9;padding:14px;border-radius:8px;min-width:160px;box-shadow:0 1px 0 rgba(0,0,0,0.03)}
			.ltlb-status-card .ltlb-count{font-size:20px;margin-top:6px;color:#222}
			.ltlb-status-card a{color:inherit;text-decoration:none}
			</style>

			<div class="ltlb-status-grid">
				<?php foreach ( $counts as $key => $val ): ?>
					<?php $label = ucwords( str_replace('_',' ', $key ) );
					$admin_link = '';
					switch ( $key ) {
						case 'services': $admin_link = admin_url('admin.php?page=ltlb_services'); break;
						case 'customers': $admin_link = admin_url('admin.php?page=ltlb_customers'); break;
						case 'appointments': $admin_link = admin_url('admin.php?page=ltlb_appointments'); break;
						case 'resources': $admin_link = admin_url('admin.php?page=ltlb_resources'); break;
					}
					?>
					<div class="ltlb-status-card">
						<strong><?php echo esc_html( $label ); ?></strong>
						<div class="ltlb-count">
							<?php if ( $admin_link ): ?><a href="<?php echo esc_url($admin_link); ?>"><?php echo intval($val); ?></a><?php else: echo intval($val); endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<h2><?php echo esc_html__('Last 5 Appointments', 'ltl-bookings'); ?></h2>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__('ID', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Service', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Start', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Staff', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Resource', 'ltl-bookings'); ?></th>
						<th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty($last_appts) ): ?>
						<tr><td colspan="6"><?php echo esc_html__('No appointments', 'ltl-bookings'); ?></td></tr>
					<?php else: ?>
						<?php
						$svcRepo = new LTLB_ServiceRepository();
						$staffRepo = null; // using WP user data
						$apptResRepo = new LTLB_AppointmentResourcesRepository();
						$resRepo = new LTLB_ResourceRepository();
						foreach ( $last_appts as $a ): 
							$service = $svcRepo->get_by_id( intval($a['service_id']) );
							$service_name = $service ? sanitize_text_field($service['name']) : '';
							$staff_name = '';
							if ( ! empty($a['staff_user_id']) ) {
								$user = get_user_by('ID', intval($a['staff_user_id']));
								if ( $user ) $staff_name = esc_html( $user->display_name );
							}
							$rid = $apptResRepo->get_resource_for_appointment( intval($a['id']) );
							$res_name = '—';
							if ( $rid ) {
								$r = $resRepo->get_by_id( intval($rid) );
								if ( $r ) $res_name = sanitize_text_field( $r['name'] );
							}
						?>
						<tr>
							<td><?php echo intval($a['id']); ?></td>
							<td><?php echo esc_html($service_name); ?></td>
							<td><?php echo esc_html($a['start_at']); ?></td>
							<td><?php echo esc_html($staff_name); ?></td>
							<td><?php echo esc_html($res_name); ?></td>
							<td><?php echo esc_html($a['status']); ?></td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

