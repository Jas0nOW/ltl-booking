<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_ReplyTemplatesPage {
	public function render(): void {
		if ( ! current_user_can( 'manage_ai_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
		}

		$this->handle_post();
		$templates = get_option( 'lazy_reply_templates', [] );
		if ( ! is_array( $templates ) ) $templates = [];

		$action = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';
		$id = isset( $_GET['id'] ) ? sanitize_text_field( (string) $_GET['id'] ) : '';

		if ( $action === 'edit' && $id !== '' ) {
			$this->render_edit( $templates, $id );
			return;
		}
		if ( $action === 'add' ) {
			$this->render_add();
			return;
		}

		?>
		<div class="wrap ltlb-admin">
			<?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_reply_templates'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Reply Templates', 'ltl-bookings' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=ltlb_reply_templates&action=add' ) ); ?>"><?php echo esc_html__( 'Add New', 'ltl-bookings' ); ?></a>
			<hr class="wp-header-end">

			<?php LTLB_Admin_Component::card_start( __( 'Templates', 'ltl-bookings' ) ); ?>
				<p class="description"><?php echo esc_html__( 'Reusable subjects and bodies for emails (automations, AI drafts, and manual replies).', 'ltl-bookings' ); ?></p>

				<form method="post" style="margin:12px 0;">
					<?php wp_nonce_field( 'ltlb_templates_action', 'ltlb_templates_nonce' ); ?>
					<input type="hidden" name="ltlb_templates_do" value="add_default">
					<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Load default templates', 'ltl-bookings' ); ?></button>
				</form>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Name', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Subject', 'ltl-bookings' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'ltl-bookings' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $templates ) ): ?>
							<tr><td colspan="3"><?php echo esc_html__( 'No templates yet. Click "Add New" to create your first template.', 'ltl-bookings' ); ?></td></tr>
						<?php else: ?>
							<?php foreach ( $templates as $tpl ): ?>
								<?php
									$tid = sanitize_text_field( (string) ( $tpl['id'] ?? '' ) );
									$name = sanitize_text_field( (string) ( $tpl['name'] ?? '' ) );
									$subject = sanitize_text_field( (string) ( $tpl['subject'] ?? '' ) );
									$edit_url = admin_url( 'admin.php?page=ltlb_reply_templates&action=edit&id=' . rawurlencode( $tid ) );
								?>
								<tr>
									<td><?php echo esc_html( $name ); ?></td>
									<td><?php echo esc_html( $subject ); ?></td>
									<td>
										<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'Edit', 'ltl-bookings' ); ?></a>
										<form method="post" style="display:inline-block; margin-left:6px;">
											<?php wp_nonce_field( 'ltlb_templates_action', 'ltlb_templates_nonce' ); ?>
											<input type="hidden" name="ltlb_templates_do" value="delete">
											<input type="hidden" name="id" value="<?php echo esc_attr( $tid ); ?>">
											<button type="submit" class="button button-small"><?php echo esc_html__( 'Delete', 'ltl-bookings' ); ?></button>
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

	private function handle_post(): void {
		if ( empty( $_POST['ltlb_templates_do'] ) ) return;
		if ( ! check_admin_referer( 'ltlb_templates_action', 'ltlb_templates_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'ltl-bookings' ) );
		}

		$templates = get_option( 'lazy_reply_templates', [] );
		if ( ! is_array( $templates ) ) $templates = [];
		$do = sanitize_key( (string) $_POST['ltlb_templates_do'] );

		if ( $do === 'add_default' ) {
			$existing_ids = [];
			foreach ( $templates as $tpl ) {
				$existing_ids[] = (string) ( $tpl['id'] ?? '' );
			}
			if ( ! in_array( 'payment_reminder_default', $existing_ids, true ) ) {
				$templates[] = [
					'id' => 'payment_reminder_default',
					'name' => __( 'Payment Reminder (Default)', 'ltl-bookings' ),
					'subject' => __( 'Payment reminder: {service_name}', 'ltl-bookings' ),
					'body' => __( "Hello {first_name},\n\nThis is a reminder that your booking is awaiting payment.\n\nService: {service_name}\nDate: {start_time}\nAmount: {amount}\n\nThank you!", 'ltl-bookings' ),
				];
			}
			if ( ! in_array( 'invoice_default', $existing_ids, true ) ) {
				$templates[] = [
					'id' => 'invoice_default',
					'name' => __( 'Invoice (Default)', 'ltl-bookings' ),
					'subject' => __( 'Invoice for booking #{booking_id}', 'ltl-bookings' ),
					'body' => __( "Hello {first_name},\n\nHere is your invoice for booking #{booking_id}.\n\nService: {service_name}\nDate: {start_time}\nAmount: {amount}\n\nThank you!", 'ltl-bookings' ),
				];
			}
			if ( ! in_array( 'overdue_reminder_default', $existing_ids, true ) ) {
				$templates[] = [
					'id' => 'overdue_reminder_default',
					'name' => __( 'Overdue Reminder (Default)', 'ltl-bookings' ),
					'subject' => __( 'Overdue payment for booking #{booking_id}', 'ltl-bookings' ),
					'body' => __( "Hello {first_name},\n\nOur records show an overdue payment for booking #{booking_id}.\n\nService: {service_name}\nDate: {start_time}\nAmount: {amount}\n\nIf you have already paid, please ignore this message. Thank you!", 'ltl-bookings' ),
				];
			}

			// Two business-tone templates (used to influence AI drafts and manual replies).
			if ( ! in_array( 'biz_friendly_concierge', $existing_ids, true ) ) {
				$templates[] = [
					'id' => 'biz_friendly_concierge',
					'name' => __( 'Business: Friendly Concierge', 'ltl-bookings' ),
					'subject' => __( 'Your booking: {service_name}', 'ltl-bookings' ),
					'body' => __( "Hello {first_name},\n\nThanks for your booking! Here are the details:\n\nService: {service_name}\nDate: {start_time}\nAmount: {amount}\n\nIf you have any questions, just reply to this email and we’ll be happy to help.\n\nWarm regards,", 'ltl-bookings' ),
				];
			}
			if ( ! in_array( 'biz_short_professional', $existing_ids, true ) ) {
				$templates[] = [
					'id' => 'biz_short_professional',
					'name' => __( 'Business: Short & Professional', 'ltl-bookings' ),
					'subject' => __( '{service_name} — booking confirmation', 'ltl-bookings' ),
					'body' => __( "Hello {first_name},\n\nYour booking is confirmed.\n\nService: {service_name}\nDate: {start_time}\nAmount: {amount}\n\nBest regards,", 'ltl-bookings' ),
				];
			}
			update_option( 'lazy_reply_templates', array_values( $templates ) );
			LTLB_Notices::add( __( 'Default templates added.', 'ltl-bookings' ), 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_reply_templates' ) );
			exit;
		}

		if ( $do === 'delete' ) {
			$id = sanitize_text_field( (string) ( $_POST['id'] ?? '' ) );
			$templates = array_values( array_filter( $templates, function( $tpl ) use ( $id ) {
				return (string) ( $tpl['id'] ?? '' ) !== $id;
			} ) );
			update_option( 'lazy_reply_templates', $templates );
			LTLB_Notices::add( __( 'Template deleted.', 'ltl-bookings' ), 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_reply_templates' ) );
			exit;
		}

		if ( $do === 'save' ) {
			$id = sanitize_text_field( (string) ( $_POST['id'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $_POST['name'] ?? '' ) );
			$subject = sanitize_text_field( (string) ( $_POST['subject'] ?? '' ) );
			$body = sanitize_textarea_field( (string) ( $_POST['body'] ?? '' ) );
			if ( $id === '' ) {
				$id = wp_generate_uuid4();
			}
			if ( $name === '' ) {
				LTLB_Notices::add( __( 'Name is required.', 'ltl-bookings' ), 'error' );
				return;
			}
			$found = false;
			foreach ( $templates as &$tpl ) {
				if ( (string) ( $tpl['id'] ?? '' ) === $id ) {
					$tpl['name'] = $name;
					$tpl['subject'] = $subject;
					$tpl['body'] = $body;
					$found = true;
				}
			}
			if ( ! $found ) {
				$templates[] = [ 'id' => $id, 'name' => $name, 'subject' => $subject, 'body' => $body ];
			}
			update_option( 'lazy_reply_templates', array_values( $templates ) );
			LTLB_Notices::add( __( 'Template saved.', 'ltl-bookings' ), 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_reply_templates' ) );
			exit;
		}
	}

	private function render_add(): void {
		$this->render_form( [ 'id' => '', 'name' => '', 'subject' => '', 'body' => '' ], __( 'Add Template', 'ltl-bookings' ) );
	}

	private function render_edit( array $templates, string $id ): void {
		$current = null;
		foreach ( $templates as $tpl ) {
			if ( (string) ( $tpl['id'] ?? '' ) === $id ) {
				$current = $tpl;
				break;
			}
		}
		if ( ! is_array( $current ) ) {
			LTLB_Notices::add( __( 'Template not found.', 'ltl-bookings' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=ltlb_reply_templates' ) );
			exit;
		}
		$this->render_form( $current, __( 'Edit Template', 'ltl-bookings' ) );
	}

	private function render_form( array $tpl, string $title ): void {
		$back = admin_url( 'admin.php?page=ltlb_reply_templates' );
		?>
		<div class="wrap ltlb-admin">
			<?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_reply_templates'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $back ); ?>"><?php echo esc_html__( 'Back', 'ltl-bookings' ); ?></a>
			<hr class="wp-header-end">
			<?php LTLB_Admin_Component::card_start( __( 'Template', 'ltl-bookings' ) ); ?>
				<form method="post">
					<?php wp_nonce_field( 'ltlb_templates_action', 'ltlb_templates_nonce' ); ?>
					<input type="hidden" name="ltlb_templates_do" value="save">
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) ( $tpl['id'] ?? '' ) ); ?>">
					<table class="form-table"><tbody>
						<tr>
							<th><label for="name"><?php echo esc_html__( 'Name', 'ltl-bookings' ); ?></label></th>
							<td><input class="regular-text" type="text" name="name" id="name" value="<?php echo esc_attr( (string) ( $tpl['name'] ?? '' ) ); ?>"></td>
						</tr>
						<tr>
							<th><label for="subject"><?php echo esc_html__( 'Subject', 'ltl-bookings' ); ?></label></th>
							<td><input class="large-text" type="text" name="subject" id="subject" value="<?php echo esc_attr( (string) ( $tpl['subject'] ?? '' ) ); ?>"></td>
						</tr>
						<tr>
							<th><label for="body"><?php echo esc_html__( 'Body', 'ltl-bookings' ); ?></label></th>
							<td>
								<textarea class="large-text" rows="10" name="body" id="body"><?php echo esc_textarea( (string) ( $tpl['body'] ?? '' ) ); ?></textarea>
								<p class="description"><?php echo esc_html__( 'You can use placeholders: {first_name}, {service_name}, {start_time}, {amount}, {booking_id}.', 'ltl-bookings' ); ?></p>
							</td>
						</tr>
					</tbody></table>
					<p><button type="submit" class="button button-primary"><?php echo esc_html__( 'Save', 'ltl-bookings' ); ?></button></p>
				</form>
			<?php LTLB_Admin_Component::card_end(); ?>
		</div>
		<?php
	}
}
