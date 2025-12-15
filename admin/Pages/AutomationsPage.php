<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_AutomationsPage {
	public function render(): void {
		if ( ! current_user_can( 'manage_ai_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
		}

		$this->handle_post();
		$rules = LTLB_Automations::get_rules();
		$action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';
		$active_rule_id = isset( $_GET['rule_id'] ) ? sanitize_text_field( (string) $_GET['rule_id'] ) : '';

		?>
		<div class="wrap ltlb-admin">
			<?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_automations'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Automations', 'ltl-bookings' ); ?></h1>
			<hr class="wp-header-end">

			<?php
				if ( $action === 'edit' && $active_rule_id !== '' ) {
					$this->render_edit_rule( $rules, $active_rule_id );
				}
				if ( $action === 'logs' && $active_rule_id !== '' ) {
					$this->render_logs( $active_rule_id );
				}
			?>

			<?php LTLB_Admin_Component::card_start( __( 'Automation Rules', 'ltl-bookings' ) ); ?>
				<p class="description"><?php echo esc_html__( 'Configure scheduled automations like reminders and reports. Execution respects the AI Operating Mode (HITL drafts go to Outbox).', 'ltl-bookings' ); ?></p>

				<form method="post" style="margin:12px 0;">
					<?php wp_nonce_field( 'ltlb_automations_action', 'ltlb_automations_nonce' ); ?>
					<input type="hidden" name="ltlb_automations_do" value="add_defaults">
					<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Add Default Rules', 'ltl-bookings' ); ?></button>
				</form>

				<form method="post" style="margin:12px 0; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
					<?php wp_nonce_field( 'ltlb_automations_action', 'ltlb_automations_nonce' ); ?>
					<input type="hidden" name="ltlb_automations_do" value="add_rule">
					<div>
						<label for="ltlb_add_rule_type" class="ltlb-muted" style="display:block; margin-bottom:4px;"><?php echo esc_html__( 'Type', 'ltl-bookings' ); ?></label>
						<select id="ltlb_add_rule_type" name="type">
							<option value="payment_reminder"><?php echo esc_html__( 'Payment reminder', 'ltl-bookings' ); ?></option>
							<option value="invoice_send"><?php echo esc_html__( 'Invoice', 'ltl-bookings' ); ?></option>
							<option value="overdue_reminder"><?php echo esc_html__( 'Overdue reminder', 'ltl-bookings' ); ?></option>
							<option value="insights_report"><?php echo esc_html__( 'Insights report', 'ltl-bookings' ); ?></option>
						</select>
					</div>
					<div>
						<label for="ltlb_add_rule_name" class="ltlb-muted" style="display:block; margin-bottom:4px;"><?php echo esc_html__( 'Name (optional)', 'ltl-bookings' ); ?></label>
						<input id="ltlb_add_rule_name" class="regular-text" type="text" name="name" value="" placeholder="<?php echo esc_attr__( 'e.g., Weekly report', 'ltl-bookings' ); ?>" />
					</div>
					<button type="submit" class="button button-primary"><?php echo esc_html__( 'Add Rule', 'ltl-bookings' ); ?></button>
				</form>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Name', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Type', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Schedule', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Enabled', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Last Run', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Next Run', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'ltl-bookings' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rules ) ): ?>
							<tr><td colspan="7"><?php echo esc_html__( 'No rules yet. Click “Add Default Rules” to get started.', 'ltl-bookings' ); ?></td></tr>
						<?php else: ?>
							<?php foreach ( $rules as $rule ): ?>
								<?php
									$rid = sanitize_text_field( (string) ( $rule['id'] ?? '' ) );
									$name = sanitize_text_field( (string) ( $rule['name'] ?? '' ) );
									$type = sanitize_key( (string) ( $rule['type'] ?? '' ) );
									$schedule = sanitize_key( (string) ( $rule['schedule'] ?? 'daily' ) );
									$time = sanitize_text_field( (string) ( $rule['time_hhmm'] ?? '09:00' ) );
									$interval_min = isset( $rule['interval_min'] ) ? intval( $rule['interval_min'] ) : 60;
									$minute = isset( $rule['minute'] ) ? intval( $rule['minute'] ) : 0;
									$weekday = isset( $rule['weekday'] ) ? intval( $rule['weekday'] ) : 1;
									$day_of_month = isset( $rule['day_of_month'] ) ? intval( $rule['day_of_month'] ) : 1;
									$enabled = ! empty( $rule['enabled'] );
									$last = ! empty( $rule['last_run_ts'] ) ? date_i18n( get_option('date_format') . ' ' . get_option('time_format'), intval( $rule['last_run_ts'] ) ) : '—';
									$next = ! empty( $rule['next_run_ts'] ) ? date_i18n( get_option('date_format') . ' ' . get_option('time_format'), intval( $rule['next_run_ts'] ) ) : '—';

									$schedule_label = '';
									if ( $schedule === 'minutely' ) {
										$schedule_label = sprintf( __( 'Every %d min', 'ltl-bookings' ), max( 1, $interval_min ) );
									} elseif ( $schedule === 'hourly' ) {
										$schedule_label = sprintf( __( 'Hourly @ :%02d', 'ltl-bookings' ), max( 0, min( 59, $minute ) ) );
									} elseif ( $schedule === 'weekly' ) {
										$days = [ 1 => __( 'Mon', 'ltl-bookings' ), 2 => __( 'Tue', 'ltl-bookings' ), 3 => __( 'Wed', 'ltl-bookings' ), 4 => __( 'Thu', 'ltl-bookings' ), 5 => __( 'Fri', 'ltl-bookings' ), 6 => __( 'Sat', 'ltl-bookings' ), 7 => __( 'Sun', 'ltl-bookings' ) ];
										$schedule_label = sprintf( __( 'Weekly (%s) @ %s', 'ltl-bookings' ), $days[ max( 1, min( 7, $weekday ) ) ] ?? __( 'Mon', 'ltl-bookings' ), $time );
									} elseif ( $schedule === 'monthly' ) {
										$schedule_label = sprintf( __( 'Monthly (%d) @ %s', 'ltl-bookings' ), max( 1, min( 28, $day_of_month ) ), $time );
									} else {
										$schedule_label = sprintf( __( 'Daily @ %s', 'ltl-bookings' ), $time );
									}
									$edit_url = admin_url( 'admin.php?page=ltlb_automations&action=edit&rule_id=' . rawurlencode( $rid ) );
									$logs_url = admin_url( 'admin.php?page=ltlb_automations&action=logs&rule_id=' . rawurlencode( $rid ) );
								?>
								<tr>
									<td><?php echo esc_html( $name ); ?></td>
									<td><?php echo esc_html( $type ); ?></td>
									<td><?php echo esc_html( $schedule_label ); ?></td>
									<td><?php echo $enabled ? esc_html__( 'Yes', 'ltl-bookings' ) : esc_html__( 'No', 'ltl-bookings' ); ?></td>
									<td><?php echo esc_html( $last ); ?></td>
									<td><?php echo esc_html( $next ); ?></td>
									<td>
										<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit', 'ltl-bookings' ); ?></a>
										<a class="button button-small" style="margin-left:6px;" href="<?php echo esc_url( $logs_url ); ?>"><?php echo esc_html__( 'Logs', 'ltl-bookings' ); ?></a>
										<form method="post" style="display:inline-block; margin-right:6px;">
											<?php wp_nonce_field( 'ltlb_automations_action', 'ltlb_automations_nonce' ); ?>
											<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rid ); ?>">
											<input type="hidden" name="ltlb_automations_do" value="toggle">
											<button class="button button-small" type="submit"><?php echo $enabled ? esc_html__( 'Disable', 'ltl-bookings' ) : esc_html__( 'Enable', 'ltl-bookings' ); ?></button>
										</form>
										<form method="post" style="display:inline-block;">
											<?php wp_nonce_field( 'ltlb_automations_action', 'ltlb_automations_nonce' ); ?>
											<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rid ); ?>">
											<input type="hidden" name="ltlb_automations_do" value="run_now">
											<button class="button button-small" type="submit"><?php echo esc_html__( 'Run Now', 'ltl-bookings' ); ?></button>
										</form>
										<form method="post" style="display:inline-block; margin-left:6px;">
											<?php wp_nonce_field( 'ltlb_automations_action', 'ltlb_automations_nonce' ); ?>
											<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rid ); ?>">
											<input type="hidden" name="ltlb_automations_do" value="delete">
											<button class="button button-small" type="submit" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'ltl-bookings' ) ); ?>')"><?php echo esc_html__( 'Delete', 'ltl-bookings' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php LTLB_Admin_Component::card_end(); ?>
		</div>
		<?php
	}

	private function render_edit_rule( array $rules, string $rule_id ): void {
		$current = null;
		foreach ( $rules as $r ) {
			if ( (string) ( $r['id'] ?? '' ) === $rule_id ) {
				$current = $r;
				break;
			}
		}
		if ( ! is_array( $current ) ) {
			return;
		}

		$templates = get_option( 'lazy_reply_templates', [] );
		if ( ! is_array( $templates ) ) $templates = [];

		$name = sanitize_text_field( (string) ( $current['name'] ?? '' ) );
		$type = sanitize_key( (string) ( $current['type'] ?? '' ) );
		$enabled = ! empty( $current['enabled'] );
		$schedule = sanitize_key( (string) ( $current['schedule'] ?? 'daily' ) );
		$mode = sanitize_key( (string) ( $current['mode'] ?? 'inherit' ) );
		$time = sanitize_text_field( (string) ( $current['time_hhmm'] ?? '09:00' ) );
		$interval_min = isset( $current['interval_min'] ) ? intval( $current['interval_min'] ) : 60;
		$minute = isset( $current['minute'] ) ? intval( $current['minute'] ) : 0;
		$weekday = isset( $current['weekday'] ) ? intval( $current['weekday'] ) : 1;
		$day_of_month = isset( $current['day_of_month'] ) ? intval( $current['day_of_month'] ) : 1;
		$limit = isset( $current['limit'] ) ? intval( $current['limit'] ) : 50;
		$days_before = isset( $current['days_before'] ) ? intval( $current['days_before'] ) : 2;
		$template_id = sanitize_text_field( (string) ( $current['template_id'] ?? '' ) );
		$back = admin_url( 'admin.php?page=ltlb_automations' );
		?>
		<?php LTLB_Admin_Component::card_start( __( 'Edit Rule', 'ltl-bookings' ) ); ?>
			<form method="post">
				<?php wp_nonce_field( 'ltlb_automations_action', 'ltlb_automations_nonce' ); ?>
				<input type="hidden" name="ltlb_automations_do" value="save_rule">
				<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule_id ); ?>">
				<table class="form-table"><tbody>
					<tr>
						<th><label for="ltlb_rule_name"><?php echo esc_html__( 'Name', 'ltl-bookings' ); ?></label></th>
						<td><input id="ltlb_rule_name" class="regular-text" type="text" name="name" value="<?php echo esc_attr( $name ); ?>"></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Type', 'ltl-bookings' ); ?></th>
						<td><code><?php echo esc_html( $type ); ?></code></td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ltl-bookings' ); ?></th>
						<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $enabled ); ?>> <?php echo esc_html__( 'Active', 'ltl-bookings' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="ltlb_rule_mode"><?php echo esc_html__( 'Execution Mode', 'ltl-bookings' ); ?></label></th>
						<td>
							<select name="mode" id="ltlb_rule_mode">
								<option value="inherit" <?php selected( $mode, 'inherit' ); ?>><?php echo esc_html__( 'Inherit global setting', 'ltl-bookings' ); ?></option>
								<option value="hitl" <?php selected( $mode, 'hitl' ); ?>><?php echo esc_html__( 'Human-in-the-Loop (draft to Outbox)', 'ltl-bookings' ); ?></option>
								<option value="auto" <?php selected( $mode, 'auto' ); ?>><?php echo esc_html__( 'Autonomous (approve + execute)', 'ltl-bookings' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="ltlb_rule_schedule"><?php echo esc_html__( 'Schedule', 'ltl-bookings' ); ?></label></th>
						<td>
							<select name="schedule" id="ltlb_rule_schedule">
								<option value="minutely" <?php selected( $schedule, 'minutely' ); ?>><?php echo esc_html__( 'Minutely (every N minutes)', 'ltl-bookings' ); ?></option>
								<option value="hourly" <?php selected( $schedule, 'hourly' ); ?>><?php echo esc_html__( 'Hourly', 'ltl-bookings' ); ?></option>
								<option value="daily" <?php selected( $schedule, 'daily' ); ?>><?php echo esc_html__( 'Daily', 'ltl-bookings' ); ?></option>
								<option value="weekly" <?php selected( $schedule, 'weekly' ); ?>><?php echo esc_html__( 'Weekly', 'ltl-bookings' ); ?></option>
								<option value="monthly" <?php selected( $schedule, 'monthly' ); ?>><?php echo esc_html__( 'Monthly', 'ltl-bookings' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Schedule Details', 'ltl-bookings' ); ?></th>
						<td>
							<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
								<label><?php echo esc_html__( 'Interval (min)', 'ltl-bookings' ); ?> <input class="small-text" type="number" min="1" max="1440" name="interval_min" value="<?php echo esc_attr( (string) $interval_min ); ?>"></label>
								<label><?php echo esc_html__( 'Minute', 'ltl-bookings' ); ?> <input class="small-text" type="number" min="0" max="59" name="minute" value="<?php echo esc_attr( (string) $minute ); ?>"></label>
								<label><?php echo esc_html__( 'Time (HH:MM)', 'ltl-bookings' ); ?> <input class="small-text" type="text" name="time_hhmm" value="<?php echo esc_attr( $time ); ?>"></label>
								<label><?php echo esc_html__( 'Weekday', 'ltl-bookings' ); ?>
									<select name="weekday">
										<option value="1" <?php selected( $weekday, 1 ); ?>><?php echo esc_html__( 'Mon', 'ltl-bookings' ); ?></option>
										<option value="2" <?php selected( $weekday, 2 ); ?>><?php echo esc_html__( 'Tue', 'ltl-bookings' ); ?></option>
										<option value="3" <?php selected( $weekday, 3 ); ?>><?php echo esc_html__( 'Wed', 'ltl-bookings' ); ?></option>
										<option value="4" <?php selected( $weekday, 4 ); ?>><?php echo esc_html__( 'Thu', 'ltl-bookings' ); ?></option>
										<option value="5" <?php selected( $weekday, 5 ); ?>><?php echo esc_html__( 'Fri', 'ltl-bookings' ); ?></option>
										<option value="6" <?php selected( $weekday, 6 ); ?>><?php echo esc_html__( 'Sat', 'ltl-bookings' ); ?></option>
										<option value="7" <?php selected( $weekday, 7 ); ?>><?php echo esc_html__( 'Sun', 'ltl-bookings' ); ?></option>
									</select>
								</label>
								<label><?php echo esc_html__( 'Day (1-28)', 'ltl-bookings' ); ?> <input class="small-text" type="number" min="1" max="28" name="day_of_month" value="<?php echo esc_attr( (string) $day_of_month ); ?>"></label>
							</div>
						</td>
					</tr>

					<?php if ( in_array( $type, [ 'payment_reminder', 'invoice_send' ], true ) ) : ?>
					<tr>
						<th><label for="ltlb_rule_days_before"><?php echo esc_html__( 'Days before', 'ltl-bookings' ); ?></label></th>
						<td><input id="ltlb_rule_days_before" class="small-text" type="number" min="0" max="365" name="days_before" value="<?php echo esc_attr( (string) $days_before ); ?>"></td>
					</tr>
					<?php endif; ?>

					<?php if ( in_array( $type, [ 'payment_reminder', 'invoice_send', 'overdue_reminder' ], true ) ) : ?>
					<tr>
						<th><label for="ltlb_rule_template"><?php echo esc_html__( 'Template', 'ltl-bookings' ); ?></label></th>
						<td>
							<select name="template_id" id="ltlb_rule_template">
								<option value="" <?php selected( $template_id, '' ); ?>><?php echo esc_html__( 'None (fallback)', 'ltl-bookings' ); ?></option>
								<?php foreach ( $templates as $tpl ) :
									$tid = sanitize_text_field( (string) ( $tpl['id'] ?? '' ) );
									$tname = sanitize_text_field( (string) ( $tpl['name'] ?? '' ) );
									if ( $tid === '' ) continue;
								?>
									<option value="<?php echo esc_attr( $tid ); ?>" <?php selected( $template_id, $tid ); ?>><?php echo esc_html( $tname ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="ltlb_rule_limit"><?php echo esc_html__( 'Limit per run', 'ltl-bookings' ); ?></label></th>
						<td><input id="ltlb_rule_limit" class="small-text" type="number" min="1" max="200" name="limit" value="<?php echo esc_attr( (string) $limit ); ?>"></td>
					</tr>
					<?php endif; ?>
				</tbody></table>
				<p>
					<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Rule', 'ltl-bookings' ); ?></button>
					<a class="button" href="<?php echo esc_url( $back ); ?>"><?php echo esc_html__( 'Close', 'ltl-bookings' ); ?></a>
				</p>
			</form>
		<?php LTLB_Admin_Component::card_end(); ?>
		<?php
	}

	private function render_logs( string $rule_id ): void {
		$logs = class_exists( 'LTLB_Automations' ) ? LTLB_Automations::get_logs( $rule_id, 50 ) : [];
		?>
		<?php LTLB_Admin_Component::card_start( __( 'Logs', 'ltl-bookings' ) ); ?>
			<?php if ( empty( $logs ) ) : ?>
				<p class="ltlb-muted" style="margin:0;"><?php echo esc_html__( 'No logs yet for this rule.', 'ltl-bookings' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Time', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Result', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Message', 'ltl-bookings' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $row ) :
							$ts = isset( $row['ts'] ) ? intval( $row['ts'] ) : 0;
							$ok = ! empty( $row['success'] );
							$msg = sanitize_text_field( (string) ( $row['message'] ?? '' ) );
						?>
							<tr>
								<td><?php echo esc_html( $ts > 0 ? date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $ts ) : '—' ); ?></td>
								<td><?php echo $ok ? esc_html__( 'Success', 'ltl-bookings' ) : esc_html__( 'Failed', 'ltl-bookings' ); ?></td>
								<td><?php echo esc_html( $msg ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php LTLB_Admin_Component::card_end(); ?>
		<?php
	}

	private function handle_post(): void {
		if ( empty( $_POST['ltlb_automations_do'] ) ) {
			return;
		}
		if ( ! check_admin_referer( 'ltlb_automations_action', 'ltlb_automations_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
		}

		$do = sanitize_key( (string) $_POST['ltlb_automations_do'] );
		$rules = LTLB_Automations::get_rules();

		if ( $do === 'add_rule' ) {
			$type = sanitize_key( (string) ( $_POST['type'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $_POST['name'] ?? '' ) );
			if ( ! in_array( $type, [ 'payment_reminder', 'invoice_send', 'overdue_reminder', 'insights_report' ], true ) ) {
				LTLB_Notices::add( __( 'Invalid rule type.', 'ltl-bookings' ), 'error' );
				wp_safe_redirect( admin_url( 'admin.php?page=ltlb_automations' ) );
				exit;
			}

			$rule_id = wp_generate_uuid4();
			$base = [
				'id' => $rule_id,
				'name' => $name !== '' ? $name : ucfirst( str_replace( '_', ' ', $type ) ),
				'type' => $type,
				'enabled' => 1,
				'mode' => 'inherit',
				'schedule' => 'daily',
				'time_hhmm' => '09:00',
				'last_run_ts' => 0,
				'next_run_ts' => 0,
			];

			if ( $type === 'payment_reminder' ) {
				$base['name'] = $name !== '' ? $name : __( 'Payment reminders (unpaid bookings)', 'ltl-bookings' );
				$base['days_before'] = 2;
				$base['template_id'] = 'payment_reminder_default';
				$base['limit'] = 50;
			}
			if ( $type === 'invoice_send' ) {
				$base['name'] = $name !== '' ? $name : __( 'Invoices (unpaid bookings)', 'ltl-bookings' );
				$base['days_before'] = 0;
				$base['template_id'] = 'invoice_default';
				$base['limit'] = 50;
				$base['time_hhmm'] = '09:05';
			}
			if ( $type === 'overdue_reminder' ) {
				$base['name'] = $name !== '' ? $name : __( 'Overdue reminders (past bookings, unpaid)', 'ltl-bookings' );
				$base['template_id'] = 'overdue_reminder_default';
				$base['limit'] = 50;
				$base['time_hhmm'] = '09:10';
			}
			if ( $type === 'insights_report' ) {
				$base['name'] = $name !== '' ? $name : __( 'Weekly outlook report (next 7 days)', 'ltl-bookings' );
				$base['schedule'] = 'weekly';
				$base['time_hhmm'] = '08:00';
				$base['weekday'] = 1;
			}

			$rules[] = $base;
			LTLB_Automations::save_rules( $rules );
			LTLB_Notices::add( __( 'Rule added.', 'ltl-bookings' ), 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_automations&action=edit&rule_id=' . rawurlencode( $rule_id ) ) );
			exit;
		}

		if ( $do === 'add_defaults' ) {
			$existing_types = [];
			foreach ( $rules as $r ) {
				$existing_types[] = sanitize_key( (string) ( $r['type'] ?? '' ) );
			}

			if ( ! in_array( 'payment_reminder', $existing_types, true ) ) {
				$rules[] = [
					'id' => wp_generate_uuid4(),
					'name' => __( 'Payment reminders (unpaid bookings)', 'ltl-bookings' ),
					'type' => 'payment_reminder',
					'enabled' => 1,
					'mode' => 'inherit',
					'schedule' => 'daily',
					'time_hhmm' => '09:00',
					'days_before' => 2,
					'template_id' => 'payment_reminder_default',
					'limit' => 50,
					'last_run_ts' => 0,
					'next_run_ts' => 0,
				];
			}
			if ( ! in_array( 'invoice_send', $existing_types, true ) ) {
				$rules[] = [
					'id' => wp_generate_uuid4(),
					'name' => __( 'Invoices (unpaid bookings)', 'ltl-bookings' ),
					'type' => 'invoice_send',
					'enabled' => 1,
					'mode' => 'inherit',
					'schedule' => 'daily',
					'time_hhmm' => '09:05',
					'days_before' => 0,
					'template_id' => 'invoice_default',
					'limit' => 50,
					'last_run_ts' => 0,
					'next_run_ts' => 0,
				];
			}
			if ( ! in_array( 'overdue_reminder', $existing_types, true ) ) {
				$rules[] = [
					'id' => wp_generate_uuid4(),
					'name' => __( 'Overdue reminders (past bookings, unpaid)', 'ltl-bookings' ),
					'type' => 'overdue_reminder',
					'enabled' => 1,
					'mode' => 'inherit',
					'schedule' => 'daily',
					'time_hhmm' => '09:10',
					'template_id' => 'overdue_reminder_default',
					'limit' => 50,
					'last_run_ts' => 0,
					'next_run_ts' => 0,
				];
			}
			if ( ! in_array( 'insights_report', $existing_types, true ) ) {
				$rules[] = [
					'id' => wp_generate_uuid4(),
					'name' => __( 'Weekly outlook report (next 7 days)', 'ltl-bookings' ),
					'type' => 'insights_report',
					'enabled' => 1,
					'mode' => 'inherit',
					'schedule' => 'weekly',
					'time_hhmm' => '08:00',
					'weekday' => 1,
					'last_run_ts' => 0,
					'next_run_ts' => 0,
				];
			}

			LTLB_Automations::save_rules( $rules );
			LTLB_Notices::add( __( 'Default rules added.', 'ltl-bookings' ), 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_automations' ) );
			exit;
		}

		$rule_id = sanitize_text_field( (string) ( $_POST['rule_id'] ?? '' ) );
		if ( $rule_id === '' ) {
			LTLB_Notices::add( __( 'Invalid rule.', 'ltl-bookings' ), 'error' );
			return;
		}

		if ( $do === 'delete' ) {
			$before = count( $rules );
			$rules = array_values( array_filter( $rules, static function( $r ) use ( $rule_id ) {
				return is_array( $r ) && (string) ( $r['id'] ?? '' ) !== $rule_id;
			} ) );
			if ( count( $rules ) === $before ) {
				LTLB_Notices::add( __( 'Rule not found.', 'ltl-bookings' ), 'error' );
				return;
			}
			LTLB_Automations::save_rules( $rules );
			LTLB_Notices::add( __( 'Rule deleted.', 'ltl-bookings' ), 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_automations' ) );
			exit;
		}

		foreach ( $rules as &$rule ) {
			if ( (string) ( $rule['id'] ?? '' ) !== $rule_id ) continue;

			if ( $do === 'toggle' ) {
				$rule['enabled'] = empty( $rule['enabled'] ) ? 1 : 0;
				LTLB_Automations::save_rules( $rules );
				LTLB_Notices::add( __( 'Rule updated.', 'ltl-bookings' ), 'success' );
				wp_safe_redirect( admin_url( 'admin.php?page=ltlb_automations' ) );
				exit;
			}

			if ( $do === 'run_now' ) {
				// Public trigger: call cron runner once; it will run due rules. Force due for this one.
				$rule['next_run_ts'] = 0;
				LTLB_Automations::save_rules( $rules );
				LTLB_Automations::run_due_rules();
				LTLB_Notices::add( __( 'Run triggered. Check Outbox for drafts.', 'ltl-bookings' ), 'success' );
				wp_safe_redirect( admin_url( 'admin.php?page=ltlb_automations' ) );
				exit;
			}

			if ( $do === 'save_rule' ) {
				$rule['name'] = sanitize_text_field( (string) ( $_POST['name'] ?? ( $rule['name'] ?? '' ) ) );
				$rule['enabled'] = empty( $_POST['enabled'] ) ? 0 : 1;
				$rule['mode'] = sanitize_key( (string) ( $_POST['mode'] ?? ( $rule['mode'] ?? 'inherit' ) ) );
				$rule['schedule'] = sanitize_key( (string) ( $_POST['schedule'] ?? ( $rule['schedule'] ?? 'daily' ) ) );
				$rule['time_hhmm'] = sanitize_text_field( (string) ( $_POST['time_hhmm'] ?? ( $rule['time_hhmm'] ?? '09:00' ) ) );
				$rule['interval_min'] = isset( $_POST['interval_min'] ) ? max( 1, min( 1440, intval( $_POST['interval_min'] ) ) ) : ( $rule['interval_min'] ?? 60 );
				$rule['minute'] = isset( $_POST['minute'] ) ? max( 0, min( 59, intval( $_POST['minute'] ) ) ) : ( $rule['minute'] ?? 0 );
				$rule['weekday'] = isset( $_POST['weekday'] ) ? max( 1, min( 7, intval( $_POST['weekday'] ) ) ) : ( $rule['weekday'] ?? 1 );
				$rule['day_of_month'] = isset( $_POST['day_of_month'] ) ? max( 1, min( 28, intval( $_POST['day_of_month'] ) ) ) : ( $rule['day_of_month'] ?? 1 );

				if ( isset( $_POST['days_before'] ) ) {
					$rule['days_before'] = max( 0, min( 365, intval( $_POST['days_before'] ) ) );
				}
				if ( isset( $_POST['limit'] ) ) {
					$rule['limit'] = max( 1, min( 200, intval( $_POST['limit'] ) ) );
				}
				if ( isset( $_POST['template_id'] ) ) {
					$rule['template_id'] = sanitize_text_field( (string) $_POST['template_id'] );
				}

				$rule['next_run_ts'] = 0;
				LTLB_Automations::save_rules( $rules );
				LTLB_Notices::add( __( 'Rule saved.', 'ltl-bookings' ), 'success' );
				wp_safe_redirect( admin_url( 'admin.php?page=ltlb_automations' ) );
				exit;
			}
		}

		LTLB_Notices::add( __( 'Rule not found.', 'ltl-bookings' ), 'error' );
	}
}
