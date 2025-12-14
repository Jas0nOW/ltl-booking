<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_OutboxPage {

	public function render(): void {
		if ( ! current_user_can( 'approve_ai_drafts' ) ) {
			wp_die( esc_html__( 'No access', 'ltl-bookings' ) );
		}

		$this->handle_post_actions();

		$action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( $action === 'view' && $id > 0 ) {
			$this->render_detail( $id );
			return;
		}

		$this->render_list();
	}

	private function handle_post_actions(): void {
		if ( empty( $_POST['ltlb_outbox_do'] ) ) {
			return;
		}
		if ( ! check_admin_referer( 'ltlb_outbox_action', 'ltlb_outbox_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
		}

		$do = sanitize_key( (string) ( $_POST['ltlb_outbox_do'] ?? '' ) );
		$id = intval( $_POST['id'] ?? 0 );
		if ( $id <= 0 ) {
			LTLB_Notices::add( __( 'Invalid item.', 'ltl-bookings' ), 'error' );
			return;
		}

		if ( $do === 'reject' ) {
			$ok = LTLB_AI_Outbox::reject( $id );
			LTLB_Notices::add( $ok ? __( 'Draft rejected.', 'ltl-bookings' ) : __( 'Could not reject draft.', 'ltl-bookings' ), $ok ? 'success' : 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_outbox' ) );
			exit;
		}

		if ( $do === 'approve_execute' ) {
			$res = LTLB_AI_Outbox::approve_and_execute( $id );
			LTLB_Notices::add( $res['message'] ?? __( 'Done.', 'ltl-bookings' ), ( $res['success'] ?? false ) ? 'success' : 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_outbox&action=view&id=' . $id ) );
			exit;
		}

		if ( $do === 'execute' ) {
			$res = LTLB_AI_Outbox::execute( $id );
			LTLB_Notices::add( $res['message'] ?? __( 'Done.', 'ltl-bookings' ), ( $res['success'] ?? false ) ? 'success' : 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_outbox&action=view&id=' . $id ) );
			exit;
		}

		if ( $do === 'save_email' ) {
			$row = LTLB_AI_Outbox::get_action( $id );
			if ( ! $row ) {
				LTLB_Notices::add( __( 'Draft not found.', 'ltl-bookings' ), 'error' );
				return;
			}

			$to = sanitize_email( (string) ( $_POST['to'] ?? '' ) );
			$subject = sanitize_text_field( (string) ( $_POST['subject'] ?? '' ) );
			$body = wp_kses_post( (string) ( $_POST['body'] ?? '' ) );
			$notes = sanitize_textarea_field( (string) ( $_POST['notes'] ?? '' ) );

			$meta_raw = $row['metadata'] ?? '';
			$metadata = [];
			if ( is_string( $meta_raw ) && $meta_raw !== '' ) {
				$decoded = json_decode( $meta_raw, true );
				if ( is_array( $decoded ) ) {
					$metadata = $decoded;
				}
			}
			$metadata['to'] = $to;
			$metadata['subject'] = $subject;
			$metadata['body'] = $body;

			$ok = LTLB_AI_Outbox::update_action_metadata( $id, $metadata, $notes );
			LTLB_Notices::add( $ok ? __( 'Draft updated.', 'ltl-bookings' ) : __( 'Could not update draft.', 'ltl-bookings' ), $ok ? 'success' : 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_outbox&action=view&id=' . $id ) );
			exit;
		}
	}

	private function render_list(): void {
		$status = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : 'draft';
		$allowed = [ 'draft', 'approved', 'executed', 'rejected', 'failed', 'all' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'draft';
		}

		$rows = LTLB_AI_Outbox::list_actions( $status, 200 );
		?>
		<div class="wrap ltlb-admin">
			<?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_outbox'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Outbox', 'ltl-bookings' ); ?></h1>
			<hr class="wp-header-end">

			<?php LTLB_Admin_Component::card_start( __( 'Draft Center', 'ltl-bookings' ) ); ?>
				<p class="description"><?php echo esc_html__( 'Review, edit, approve, and execute AI-assisted actions.', 'ltl-bookings' ); ?></p>
				<p>
					<?php
					$base = admin_url( 'admin.php?page=ltlb_outbox' );
					foreach ( $allowed as $s ) {
						$label = ucfirst( $s );
						$url = add_query_arg( [ 'status' => $s ], $base );
						$is_current = ( $status === $s );
						echo $is_current ? '<strong>' . esc_html( $label ) . '</strong> ' : '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
					}
					?>
				</p>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'ID', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Type', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Created', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'User', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'ltl-bookings' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ): ?>
							<tr><td colspan="6"><?php echo esc_html__( 'No items found.', 'ltl-bookings' ); ?></td></tr>
						<?php else: ?>
							<?php foreach ( $rows as $row ): ?>
								<?php
									$id = intval( $row['id'] ?? 0 );
									$type = sanitize_text_field( (string) ( $row['action_type'] ?? '' ) );
									$st = sanitize_text_field( (string) ( $row['status'] ?? '' ) );
									$created = sanitize_text_field( (string) ( $row['created_at'] ?? '' ) );
									$user_id = intval( $row['user_id'] ?? 0 );
									$user = $user_id ? get_user_by( 'id', $user_id ) : null;
									$user_label = $user ? ( $user->display_name . ' (#' . $user_id . ')' ) : ( $user_id ? '#' . $user_id : '-' );
									$view_url = admin_url( 'admin.php?page=ltlb_outbox&action=view&id=' . $id );
								?>
								<tr>
									<td><?php echo esc_html( (string) $id ); ?></td>
									<td><?php echo esc_html( $type ); ?></td>
									<td><?php echo esc_html( $st ); ?></td>
									<td><?php echo esc_html( $created ); ?></td>
									<td><?php echo esc_html( $user_label ); ?></td>
									<td><a class="button button-small" href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html__( 'View', 'ltl-bookings' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php LTLB_Admin_Component::card_end(); ?>
		</div>
		<?php
	}

	private function render_detail( int $id ): void {
		$row = LTLB_AI_Outbox::get_action( $id );
		if ( ! $row ) {
			LTLB_Notices::add( __( 'Draft not found.', 'ltl-bookings' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_outbox' ) );
			exit;
		}

		$type = sanitize_text_field( (string) ( $row['action_type'] ?? '' ) );
		$status = sanitize_text_field( (string) ( $row['status'] ?? '' ) );
		$ai_input = (string) ( $row['ai_input'] ?? '' );
		$ai_output = (string) ( $row['ai_output'] ?? '' );
		$notes = (string) ( $row['notes'] ?? '' );

		$meta_raw = $row['metadata'] ?? '';
		$metadata = [];
		if ( is_string( $meta_raw ) && $meta_raw !== '' ) {
			$decoded = json_decode( $meta_raw, true );
			if ( is_array( $decoded ) ) {
				$metadata = $decoded;
			}
		}

		$back_url = admin_url( 'admin.php?page=ltlb_outbox' );
		?>
		<div class="wrap ltlb-admin">
			<?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_outbox'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Outbox Item', 'ltl-bookings' ); ?> #<?php echo esc_html( (string) $id ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $back_url ); ?>"><?php echo esc_html__( 'Back to Outbox', 'ltl-bookings' ); ?></a>
			<hr class="wp-header-end">

			<?php LTLB_Admin_Component::card_start( __( 'Overview', 'ltl-bookings' ) ); ?>
				<table class="form-table"><tbody>
					<tr><th><?php echo esc_html__( 'Type', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $type ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Status', 'ltl-bookings' ); ?></th><td><?php echo esc_html( $status ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Created', 'ltl-bookings' ); ?></th><td><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Updated', 'ltl-bookings' ); ?></th><td><?php echo esc_html( (string) ( $row['updated_at'] ?? '' ) ); ?></td></tr>
				</tbody></table>
			<?php LTLB_Admin_Component::card_end(); ?>

			<?php if ( $type === 'email' ): ?>
				<?php
					$to = sanitize_email( (string) ( $metadata['to'] ?? '' ) );
					$subject = sanitize_text_field( (string) ( $metadata['subject'] ?? '' ) );
					$body = (string) ( $metadata['body'] ?? '' );
				?>
				<?php LTLB_Admin_Component::card_start( __( 'Email Draft', 'ltl-bookings' ) ); ?>
					<form method="post">
						<?php wp_nonce_field( 'ltlb_outbox_action', 'ltlb_outbox_nonce' ); ?>
						<input type="hidden" name="ltlb_outbox_do" value="save_email">
						<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">

						<table class="form-table"><tbody>
							<tr>
								<th><label for="to"><?php echo esc_html__( 'To', 'ltl-bookings' ); ?></label></th>
								<td><input type="email" class="regular-text" name="to" id="to" value="<?php echo esc_attr( $to ); ?>"></td>
							</tr>
							<tr>
								<th><label for="subject"><?php echo esc_html__( 'Subject', 'ltl-bookings' ); ?></label></th>
								<td><input type="text" class="large-text" name="subject" id="subject" value="<?php echo esc_attr( $subject ); ?>"></td>
							</tr>
							<tr>
								<th><label for="body"><?php echo esc_html__( 'Body', 'ltl-bookings' ); ?></label></th>
								<td><textarea name="body" id="body" rows="10" class="large-text code"><?php echo esc_textarea( $body ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="notes"><?php echo esc_html__( 'Notes', 'ltl-bookings' ); ?></label></th>
								<td><textarea name="notes" id="notes" rows="3" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea></td>
							</tr>
						</tbody></table>

						<p>
							<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Draft', 'ltl-bookings' ); ?></button>
						</p>
					</form>
				<?php LTLB_Admin_Component::card_end(); ?>
			<?php endif; ?>

			<?php LTLB_Admin_Component::card_start( __( 'AI Input / Output', 'ltl-bookings' ) ); ?>
				<h3><?php echo esc_html__( 'AI Input', 'ltl-bookings' ); ?></h3>
				<textarea class="large-text code" rows="6" readonly><?php echo esc_textarea( $ai_input ); ?></textarea>
				<h3><?php echo esc_html__( 'AI Output', 'ltl-bookings' ); ?></h3>
				<textarea class="large-text code" rows="10" readonly><?php echo esc_textarea( $ai_output ); ?></textarea>
			<?php LTLB_Admin_Component::card_end(); ?>

			<?php LTLB_Admin_Component::card_start( __( 'Actions', 'ltl-bookings' ) ); ?>
				<?php
					$is_hitl = class_exists( 'LTLB_AI_Outbox' ) ? LTLB_AI_Outbox::is_hitl_mode() : true;
					$row_status = is_string( $row['status'] ?? null ) ? $row['status'] : '';
				?>
				<form method="post" style="display:inline-block; margin-right:8px;">
					<?php wp_nonce_field( 'ltlb_outbox_action', 'ltlb_outbox_nonce' ); ?>
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
					<input type="hidden" name="ltlb_outbox_do" value="reject">
					<button type="submit" class="button"><?php echo esc_html__( 'Reject', 'ltl-bookings' ); ?></button>
				</form>

				<form method="post" style="display:inline-block; margin-right:8px;">
					<?php wp_nonce_field( 'ltlb_outbox_action', 'ltlb_outbox_nonce' ); ?>
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
					<input type="hidden" name="ltlb_outbox_do" value="approve_execute">
					<button type="submit" class="button button-primary"><?php echo esc_html__( 'Approve & Execute', 'ltl-bookings' ); ?></button>
				</form>

				<?php if ( ! $is_hitl || $row_status !== 'draft' ) : ?>
					<form method="post" style="display:inline-block;">
						<?php wp_nonce_field( 'ltlb_outbox_action', 'ltlb_outbox_nonce' ); ?>
						<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
						<input type="hidden" name="ltlb_outbox_do" value="execute">
						<button type="submit" class="button"><?php echo esc_html__( 'Execute', 'ltl-bookings' ); ?></button>
					</form>
				<?php endif; ?>

				<p class="description" style="margin-top:10px;">
					<?php echo esc_html__( 'Approve & Execute will mark the item approved and run it. Execute runs it as-is (useful for already approved drafts).', 'ltl-bookings' ); ?>
				</p>
			<?php LTLB_Admin_Component::card_end(); ?>
		</div>
		<?php
	}
}
