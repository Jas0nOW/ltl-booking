<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_AppointmentsPage {
	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );

		$appointment_repo = new LTLB_AppointmentRepository();
		$service_repo = new LTLB_ServiceRepository();
		$customer_repo = new LTLB_CustomerRepository();

		// Handle bulk actions
		if (isset($_POST['action']) && $_POST['action'] !== '-1' && isset($_POST['appointment_ids']) && !empty($_POST['appointment_ids'])) {
			if (!check_admin_referer('ltlb_appointments_bulk_action')) {
				wp_die('Security check failed');
			}
			$action = sanitize_text_field($_POST['action']);
			$ids = array_map('intval', $_POST['appointment_ids']);

			if ($action === 'delete') {
				// To be implemented if needed
			} else if (strpos($action, 'set_status_') === 0) {
				$status = str_replace('set_status_', '', $action);
				if (in_array($status, ['pending', 'confirmed', 'cancelled'])) {
					$appointment_repo->update_status_bulk($ids, $status);
					LTLB_Notices::add(count($ids) . ' ' . __('appointments updated.', 'ltl-bookings'), 'success');
				}
			}
			wp_safe_redirect(remove_query_arg(['action', 'paged'], wp_get_referer()));
			exit;
		}

		// Get filters from URL
		$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
		$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
		$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
		$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
		$customer_search = isset($_GET['customer_search']) ? sanitize_text_field($_GET['customer_search']) : '';

		$filters = [];
		if (!empty($date_from)) $filters['from'] = $date_from . ' 00:00:00';
		if (!empty($date_to)) $filters['to'] = $date_to . ' 23:59:59';
		if (!empty($status)) $filters['status'] = $status;
		if ($service_id > 0) $filters['service_id'] = $service_id;
		if (!empty($customer_search)) $filters['customer_search'] = $customer_search;

		$per_page = 20;
		$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		$offset = ($current_page - 1) * $per_page;

		$total_appointments = $appointment_repo->get_count($filters);
		$appointments = $appointment_repo->get_all(array_merge($filters, ['limit' => $per_page, 'offset' => $offset]));

		$all_services = $service_repo->get_all();
		?>
        <div class="wrap ltlb-admin">
            <?php LTLB_Admin_Header::render('ltlb_appointments'); ?>
            <h1 class="wp-heading-inline"><?php echo esc_html__( 'Appointments', 'ltl-bookings' ); ?></h1>
            <hr class="wp-header-end">
            
            <form method="post">
                <?php LTLB_Admin_Component::card_start(''); ?>
                    <div class="ltlb-table-toolbar">
                        <div class="ltlb-table-toolbar__bulk-actions">
                            <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action' ); ?></label>
                            <select name="action" id="bulk-action-selector-top">
                                <option value="-1"><?php esc_html_e( 'Bulk Actions' ); ?></option>
                                <option value="set_status_confirmed"><?php esc_html_e( 'Change status to confirmed' ); ?></option>
                                <option value="set_status_pending"><?php esc_html_e( 'Change status to pending' ); ?></option>
                                <option value="set_status_cancelled"><?php esc_html_e( 'Change status to cancelled' ); ?></option>
                            </select>
                            <?php submit_button( esc_html__( 'Apply' ), 'action', '', false ); ?>
                        </div>
                        <form method="get">
                            <input type="hidden" name="page" value="ltlb_appointments">
                            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                            <select name="status">
                                <option value=""><?php echo esc_html__( 'All Statuses', 'ltl-bookings' ); ?></option>
                                <option value="pending" <?php selected($status, 'pending'); ?>><?php echo esc_html__( 'Pending', 'ltl-bookings' ); ?></option>
                                <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php echo esc_html__( 'Confirmed', 'ltl-bookings' ); ?></option>
                                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php echo esc_html__( 'Cancelled', 'ltl-bookings' ); ?></option>
                            </select>
                            <button type="submit" class="button"><?php echo esc_html__( 'Filter', 'ltl-bookings' ); ?></button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ltlb_appointments')); ?>" class="button"><?php echo esc_html__('Reset', 'ltl-bookings'); ?></a>
                        </form>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td id="cb" class="manage-column column-cb check-column">
                                    <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All' ); ?></label>
                                    <input id="cb-select-all-1" type="checkbox">
                                </td>
                                <th scope="col" class="manage-column"><?php echo esc_html__( 'Customer', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column"><?php echo esc_html__( 'Service', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column"><?php echo esc_html__( 'Start', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column"><?php echo esc_html__( 'End', 'ltl-bookings' ); ?></th>
                                <th scope="col" class="manage-column"><?php echo esc_html__( 'Status', 'ltl-bookings' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="6">
                                        <?php 
                                        LTLB_Admin_Component::empty_state(
                                            __('No Appointments Found', 'ltl-bookings'),
                                            __('There are no appointments matching your current filters.', 'ltl-bookings'),
                                            __('Clear Filters', 'ltl-bookings'),
                                            admin_url('admin.php?page=ltlb_appointments'),
                                            'dashicons-calendar-alt'
                                        ); 
                                        ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): 
                                    $customer = $customer_repo->get_by_id($appointment['customer_id']);
                                    $service = $service_repo->get_by_id($appointment['service_id']);
                                ?>
                                    <tr>
                                        <th scope="row" class="check-column">
                                            <input type="checkbox" name="appointment_ids[]" value="<?php echo esc_attr( $appointment['id'] ); ?>">
                                        </th>
                                        <td><?php echo esc_html($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                        <td><?php echo esc_html($service['name']); ?></td>
                                        <td><?php echo esc_html($appointment['start_at']); ?></td>
                                        <td><?php echo esc_html($appointment['end_at']); ?></td>
                                        <td><span class="ltlb-status-badge status-<?php echo esc_attr($appointment['status']); ?>"><?php echo esc_html(ucfirst($appointment['status'])); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php LTLB_Admin_Component::pagination($total_appointments, $per_page); ?>
                <?php LTLB_Admin_Component::card_end(); ?>
                <?php wp_nonce_field('ltlb_appointments_bulk_action'); ?>
            </form>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('cb-select-all-1');
                const checkboxes = document.querySelectorAll('input[name="appointment_ids[]"]');
                if (selectAll) {
                    selectAll.addEventListener('change', function(e) {
                        checkboxes.forEach(cb => cb.checked = e.target.checked);
                    });
                }
            });
        </script>
		<?php
	}
}

