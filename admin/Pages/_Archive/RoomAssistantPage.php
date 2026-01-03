<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_RoomAssistantPage {
	public function render(): void {
		if ( ! current_user_can( 'approve_ai_drafts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
		}

		$settings = get_option( 'lazy_settings', [] );
		if ( ! is_array( $settings ) ) $settings = [];
		$include_pending = ! empty( $settings['pending_blocks'] );

		// Handle propose/execute action.
		if ( isset( $_POST['ltlb_room_assistant_do'] ) && $_POST['ltlb_room_assistant_do'] === 'propose' ) {
			if ( ! check_admin_referer( 'ltlb_room_assistant_action', 'ltlb_room_assistant_nonce' ) ) {
				wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
			}

			$appointment_id = isset( $_POST['appointment_id'] ) ? intval( $_POST['appointment_id'] ) : 0;
			$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;
			$notes = isset( $_POST['notes'] ) ? sanitize_text_field( (string) $_POST['notes'] ) : '';

			$res = class_exists( 'LTLB_AI_Outbox' )
				? LTLB_AI_Outbox::queue_or_execute_assign_room( $appointment_id, $resource_id, $notes )
				: [ 'success' => false, 'message' => __( 'Outbox not available.', 'ltl-bookings' ) ];

			if ( $res['success'] ?? false ) {
				$msg = $res['message'] ?? __( 'Room assignment queued.', 'ltl-bookings' );
				if ( isset( $res['id'] ) ) {
					$msg .= ' ' . sprintf(
						__( 'View in Outbox: #%d', 'ltl-bookings' ),
						intval( $res['id'] )
					);
				}
				LTLB_Notices::add( $msg, 'success' );
			} else {
				LTLB_Notices::add( $res['message'] ?? __( 'Could not queue room assignment.', 'ltl-bookings' ), 'error' );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_room_assistant' ) );
			exit;
		}

		$days = isset( $_GET['days'] ) ? max( 1, min( 30, intval( $_GET['days'] ) ) ) : 14;
		$from = date( 'Y-m-d 00:00:00' );
		$to = date( 'Y-m-d 23:59:59', strtotime( '+' . $days . ' days' ) );

		$rows = $this->get_unassigned_bookings( $from, $to );

		$service_repo = new LTLB_ServiceRepository();
		$customer_repo = new LTLB_CustomerRepository();
		$resource_repo = new LTLB_ResourceRepository();
		$svc_res_repo = new LTLB_ServiceResourcesRepository();
		$appt_res_repo = new LTLB_AppointmentResourcesRepository();

		?>
		<div class="wrap ltlb-admin">
			<?php if ( class_exists( 'LTLB_Admin_Header' ) ) { LTLB_Admin_Header::render( 'ltlb_dashboard' ); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Smart Room Assistant', 'ltl-bookings' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Review upcoming unassigned bookings and propose room assignments. In Human-in-the-Loop mode, proposals create drafts in the Outbox.', 'ltl-bookings' ); ?></p>
			<hr class="wp-header-end">

			<?php LTLB_Admin_Component::card_start( __( 'Scope', 'ltl-bookings' ) ); ?>
				<form method="get" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
					<input type="hidden" name="page" value="ltlb_room_assistant" />
					<div>
						<label for="ltlb_room_assistant_days" class="ltlb-muted" style="display:block; margin-bottom:4px;">
							<?php echo esc_html__( 'Days ahead', 'ltl-bookings' ); ?>
						</label>
						<input id="ltlb_room_assistant_days" type="number" class="small-text" min="1" max="30" name="days" value="<?php echo esc_attr( (string) $days ); ?>" />
					</div>
					<button type="submit" class="ltlb-btn ltlb-btn--secondary"><?php echo esc_html__( 'Update', 'ltl-bookings' ); ?></button>
					<a class="ltlb-btn ltlb-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_room_assistant' ) ); ?>"><?php echo esc_html__( 'Reset', 'ltl-bookings' ); ?></a>
				</form>
			<?php LTLB_Admin_Component::card_end(); ?>

			<?php LTLB_Admin_Component::card_start( __( 'Unassigned Bookings', 'ltl-bookings' ) ); ?>
				<?php if ( empty( $rows ) ) : ?>
					<?php
						LTLB_Admin_Component::empty_state(
							__( 'All Set', 'ltl-bookings' ),
							__( 'No upcoming unassigned bookings found in the selected range.', 'ltl-bookings' ),
							__( 'View Calendar', 'ltl-bookings' ),
							admin_url( 'admin.php?page=ltlb_calendar' ),
							'dashicons-yes-alt'
						);
					?>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Check-in', 'ltl-bookings' ); ?></th>
								<th><?php echo esc_html__( 'Check-out', 'ltl-bookings' ); ?></th>
								<th><?php echo esc_html__( 'Guest', 'ltl-bookings' ); ?></th>
								<th><?php echo esc_html__( 'Room Type', 'ltl-bookings' ); ?></th>
								<th><?php echo esc_html__( 'Guests', 'ltl-bookings' ); ?></th>
								<th><?php echo esc_html__( 'Suggestion', 'ltl-bookings' ); ?></th>
								<th><?php echo esc_html__( 'Action', 'ltl-bookings' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $appt ) :
								$appt_id = intval( $appt['id'] );
								$service = $service_repo->get_by_id( intval( $appt['service_id'] ) );
								$customer = $customer_repo->get_by_id( intval( $appt['customer_id'] ) );
								$guest_name = $customer ? trim( (string) ( $customer['first_name'] ?? '' ) . ' ' . (string) ( $customer['last_name'] ?? '' ) ) : '—';
								$guests = max( 1, intval( $appt['seats'] ?? 1 ) );

								$appt_tz = (string) ( $appt['timezone'] ?? '' );
								if ( $appt_tz === '' ) {
									$appt_tz = LTLB_Time::get_site_timezone_string();
								}
								$start_display = LTLB_Time::format_local_display_from_utc_mysql( (string) ( $appt['start_at'] ?? '' ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $appt_tz );
								$end_display = LTLB_Time::format_local_display_from_utc_mysql( (string) ( $appt['end_at'] ?? '' ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $appt_tz );

								$suggestion = $this->suggest_room( $appt, $guests, $include_pending, $svc_res_repo, $resource_repo, $appt_res_repo );
								$fit_options = $suggestion['options'] ?? [];
								$suggested_id = isset( $suggestion['resource_id'] ) ? intval( $suggestion['resource_id'] ) : 0;
								$suggested_name = $suggested_id > 0 ? ( ( $resource_repo->get_by_id( $suggested_id )['name'] ?? '' ) ) : '';
							?>
							<tr>
								<td><?php echo esc_html( $start_display ); ?></td>
								<td><?php echo esc_html( $end_display ); ?></td>
								<td><?php echo esc_html( $guest_name ?: '—' ); ?></td>
								<td><?php echo esc_html( $service ? (string) $service['name'] : '—' ); ?></td>
								<td><?php echo esc_html( (string) $guests ); ?></td>
								<td>
									<?php if ( $suggested_id > 0 ) : ?>
										<strong><?php echo esc_html( $suggested_name ); ?></strong>
										<div class="ltlb-muted" style="margin-top:4px;">
											<?php echo esc_html( $suggestion['reason'] ?? '' ); ?>
										</div>
									<?php else : ?>
										<span class="ltlb-muted"><?php echo esc_html__( 'No available room found', 'ltl-bookings' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $fit_options ) ) : ?>
										<form method="post" style="display:flex; gap:8px; align-items:center;">
											<?php wp_nonce_field( 'ltlb_room_assistant_action', 'ltlb_room_assistant_nonce' ); ?>
											<input type="hidden" name="ltlb_room_assistant_do" value="propose" />
											<input type="hidden" name="appointment_id" value="<?php echo esc_attr( (string) $appt_id ); ?>" />
											<select name="resource_id">
												<?php foreach ( $fit_options as $opt ) : ?>
													<option value="<?php echo esc_attr( (string) intval( $opt['id'] ) ); ?>" <?php selected( intval( $opt['id'] ), $suggested_id ); ?>>
														<?php echo esc_html( (string) $opt['name'] ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<input type="text" name="notes" class="regular-text" placeholder="<?php echo esc_attr__( 'Notes (optional)', 'ltl-bookings' ); ?>" />
											<button type="submit" class="ltlb-btn ltlb-btn--primary"><?php echo esc_html__( 'Propose', 'ltl-bookings' ); ?></button>
										</form>
									<?php else : ?>
										<span class="ltlb-muted"><?php echo esc_html__( 'No action available', 'ltl-bookings' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php LTLB_Admin_Component::card_end(); ?>
		</div>
		<?php
	}

	private function get_unassigned_bookings( string $from, string $to ): array {
		global $wpdb;
		$appt_table = $wpdb->prefix . 'lazy_appointments';
		$ar_table = $wpdb->prefix . 'lazy_appointment_resources';

		// Unassigned bookings that overlap the range and are not cancelled.
		$sql = "SELECT a.*
			FROM {$appt_table} a
			LEFT JOIN {$ar_table} ar ON ar.appointment_id = a.id
			WHERE ar.appointment_id IS NULL
			AND a.status IN ('pending','confirmed')
			AND a.start_at < %s
			AND a.end_at > %s
			ORDER BY a.start_at ASC
			LIMIT 50";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $to, $from ), ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	private function suggest_room(
		array $appointment,
		int $guests,
		bool $include_pending,
		LTLB_ServiceResourcesRepository $svc_res_repo,
		LTLB_ResourceRepository $resource_repo,
		LTLB_AppointmentResourcesRepository $appt_res_repo
	): array {
		$service_id = intval( $appointment['service_id'] ?? 0 );
		$start_at = isset( $appointment['start_at'] ) ? (string) $appointment['start_at'] : '';
		$end_at = isset( $appointment['end_at'] ) ? (string) $appointment['end_at'] : '';
		if ( $service_id <= 0 || $start_at === '' || $end_at === '' ) {
			return [];
		}

		$allowed = $svc_res_repo->get_resources_for_service( $service_id );
		if ( empty( $allowed ) ) {
			$all = $resource_repo->get_all();
			$allowed = array_map( static function ( $r ) {
				return intval( $r['id'] ?? 0 );
			}, $all );
		}

		$blocked = $appt_res_repo->get_blocked_resources( $start_at, $end_at, $include_pending );

		$candidates = [];
		foreach ( $allowed as $rid ) {
			$rid = intval( $rid );
			if ( $rid <= 0 ) continue;
			$r = $resource_repo->get_by_id( $rid );
			if ( ! $r ) continue;

			$capacity = intval( $r['capacity'] ?? 1 );
			$used = isset( $blocked[ $rid ] ) ? intval( $blocked[ $rid ] ) : 0;
			$available = max( 0, $capacity - $used );
			if ( $available < $guests ) continue;

			$leftover = max( 0, $available - $guests );
			$candidates[] = [
				'id' => $rid,
				'name' => (string) ( $r['name'] ?? '' ),
				'leftover' => $leftover,
				'available' => $available,
			];
		}

		if ( empty( $candidates ) ) {
			return [];
		}

		usort( $candidates, static function ( $a, $b ) {
			if ( $a['leftover'] === $b['leftover'] ) {
				return strcmp( (string) $a['name'], (string) $b['name'] );
			}
			return $a['leftover'] < $b['leftover'] ? -1 : 1;
		} );

		$best = $candidates[0];
		$reason = sprintf(
			__( 'Best fit: %d guests, %d available, %d leftover.', 'ltl-bookings' ),
			$guests,
			intval( $best['available'] ),
			intval( $best['leftover'] )
		);

		$options = array_map( static function ( $c ) {
			return [ 'id' => intval( $c['id'] ), 'name' => (string) $c['name'] ];
		}, $candidates );

		return [
			'resource_id' => intval( $best['id'] ),
			'reason' => $reason,
			'options' => $options,
		];
	}
}

