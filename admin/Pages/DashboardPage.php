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
        <div class="wrap ltlb-admin ltlb-admin--dashboard">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_dashboard'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__('LazyBookings Dashboard', 'ltl-bookings'); ?></h1>
            <hr class="wp-header-end">

            <div class="ltlb-dashboard-stats">
                <div class="ltlb-stat-card">
                    <div class="ltlb-stat-icon dashicons dashicons-calendar-alt"></div>
                    <div class="ltlb-stat-content">
                        <span class="ltlb-stat-label"><?php echo esc_html__('Appointments', 'ltl-bookings'); ?></span>
                        <span class="ltlb-stat-value"><?php echo intval(count($appointments)); ?></span>
                    </div>
                </div>
                <div class="ltlb-stat-card">
                    <div class="ltlb-stat-icon dashicons dashicons-admin-users"></div>
                    <div class="ltlb-stat-content">
                        <span class="ltlb-stat-label"><?php echo esc_html__('Customers', 'ltl-bookings'); ?></span>
                        <span class="ltlb-stat-value"><?php echo intval(count($customers)); ?></span>
                    </div>
                </div>
                <div class="ltlb-stat-card">
                    <div class="ltlb-stat-icon dashicons dashicons-list-view"></div>
                    <div class="ltlb-stat-content">
                        <span class="ltlb-stat-label"><?php echo esc_html__('Services', 'ltl-bookings'); ?></span>
                        <span class="ltlb-stat-value"><?php echo intval(count($services)); ?></span>
                    </div>
                </div>
                <div class="ltlb-stat-card">
                    <div class="ltlb-stat-icon dashicons dashicons-building"></div>
                    <div class="ltlb-stat-content">
                        <span class="ltlb-stat-label"><?php echo esc_html__('Resources', 'ltl-bookings'); ?></span>
                        <span class="ltlb-stat-value"><?php echo intval(count($resources)); ?></span>
                    </div>
                </div>
            </div>

            <div class="ltlb-card" style="margin-top:20px;">
                <h2 style="margin-top:0;border-bottom:1px solid #eee;padding-bottom:15px;"><?php echo esc_html__('Latest Appointments', 'ltl-bookings'); ?></h2>
                
                <?php if ( empty($last5) ) : ?>
                    <p><?php echo esc_html__('No appointments found.', 'ltl-bookings'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('ID', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Service', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Customer', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Start', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
                                <th><?php echo esc_html__('Resource', 'ltl-bookings'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $last5 as $a ): 
                                $cust = $cust_repo->get_by_id( intval($a['customer_id']) );
                                $cust_name = $cust ? $cust['first_name'] . ' ' . $cust['last_name'] : '—';
                            ?>
                                <tr>
                                    <td>#<?php echo intval($a['id']); ?></td>
                                    <td><?php
                                        $srepo = new LTLB_ServiceRepository();
                                        $s = $srepo->get_by_id( intval($a['service_id']) );
                                        echo esc_html( $s ? $s['name'] : '—' );
                                    ?></td>
                                    <td><?php echo esc_html( $cust_name ); ?></td>
                                    <td><?php echo esc_html( $a['start_at'] ); ?></td>
                                    <td>
                                        <?php
                                        $status_label = $a['status'];
                                        if ( $a['status'] === 'confirmed' ) $status_label = __( 'Confirmed', 'ltl-bookings' );
                                        if ( $a['status'] === 'pending' ) $status_label = __( 'Pending', 'ltl-bookings' );
                                        if ( $a['status'] === 'cancelled' ) $status_label = __( 'Cancelled', 'ltl-bookings' );
                                        ?>
                                        <span class="ltlb-status-badge status-<?php echo esc_attr($a['status']); ?>"><?php echo esc_html( $status_label ); ?></span>
                                    </td>
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
                <?php endif; ?>
            </div>
		</div>


		<?php
	}
}

