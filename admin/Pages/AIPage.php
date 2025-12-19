<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_AIPage {

	public function render(): void {
		if ( ! current_user_can('manage_ai_settings') ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
		}

		// Handle save
		if ( isset( $_POST['ltlb_ai_save'] ) ) {
			if ( ! check_admin_referer( 'ltlb_ai_save_action', 'ltlb_ai_nonce' ) ) {
				wp_die( esc_html__('Security check failed', 'ltl-bookings') );
			}

			$ai_config = get_option( 'lazy_ai_config', [] );
			if ( ! is_array( $ai_config ) ) $ai_config = [];

			$ai_config['enabled'] = isset( $_POST['ai_enabled'] ) ? 1 : 0;
			$ai_config['provider'] = sanitize_text_field( $_POST['ai_provider'] ?? 'gemini' );
			$ai_config['model'] = sanitize_text_field( $_POST['ai_model'] ?? 'gemini-2.5-flash' );
			$ai_config['operating_mode'] = sanitize_text_field( $_POST['ai_operating_mode'] ?? 'human-in-the-loop' );

			update_option( 'lazy_ai_config', $ai_config );

			// Handle API Keys (only if user has manage_ai_secrets cap)
			if ( current_user_can('manage_ai_secrets') ) {
				$api_keys = get_option( 'lazy_api_keys', [] );
				if ( ! is_array( $api_keys ) ) $api_keys = [];

				$gemini_key = sanitize_text_field( $_POST['gemini_api_key'] ?? '' );
				if ( $gemini_key ) {
					// Basic validation: alphanumeric + dashes/underscores
					if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $gemini_key ) ) {
						$api_keys['gemini'] = class_exists( 'LTLB_Crypto' ) ? LTLB_Crypto::encrypt_string( $gemini_key ) : $gemini_key;
					}
				}

				// Keep secrets out of autoload.
				update_option( 'lazy_api_keys', $api_keys, false );
			}

			// Business Context
			$business_context = get_option( 'lazy_business_context', [] );
			if ( ! is_array( $business_context ) ) $business_context = [];

			$business_context['brand_name'] = sanitize_text_field( $_POST['brand_name'] ?? '' );
			$business_context['brand_voice'] = sanitize_textarea_field( $_POST['brand_voice'] ?? '' );
			$business_context['faq'] = wp_kses_post( $_POST['faq'] ?? '' );
			$business_context['policies'] = wp_kses_post( $_POST['policies'] ?? '' );
			$business_context['invoice_terms'] = wp_kses_post( $_POST['invoice_terms'] ?? '' );
			$business_context['contact_info'] = sanitize_text_field( $_POST['contact_info'] ?? '' );

			// Send-control toggles
			$business_context['send_brand_name'] = isset( $_POST['send_brand_name'] ) ? 1 : 0;
			$business_context['send_brand_voice'] = isset( $_POST['send_brand_voice'] ) ? 1 : 0;
			$business_context['send_faq'] = isset( $_POST['send_faq'] ) ? 1 : 0;
			$business_context['send_policies'] = isset( $_POST['send_policies'] ) ? 1 : 0;
			$business_context['send_invoice_terms'] = isset( $_POST['send_invoice_terms'] ) ? 1 : 0;
			$business_context['send_contact_info'] = isset( $_POST['send_contact_info'] ) ? 1 : 0;

			update_option( 'lazy_business_context', $business_context );

			LTLB_Notices::add( __('AI Settings saved.', 'ltl-bookings'), 'success' );
			wp_safe_redirect( admin_url('admin.php?page=ltlb_ai') );
			exit;
		}

		// Load current settings
		$ai_config = get_option( 'lazy_ai_config', [] );
		if ( ! is_array( $ai_config ) ) $ai_config = [];

		$ai_enabled = $ai_config['enabled'] ?? 0;
		$ai_provider = $ai_config['provider'] ?? 'gemini';
		$ai_model = $ai_config['model'] ?? 'gemini-2.5-flash';
		$ai_mode = $ai_config['operating_mode'] ?? 'human-in-the-loop';

		$api_keys = get_option( 'lazy_api_keys', [] );
		if ( ! is_array( $api_keys ) ) $api_keys = [];
			$has_gemini_key = current_user_can('manage_ai_secrets') && ! empty( $api_keys['gemini'] ?? '' );
			$gemini_key = '';

		$business_context = get_option( 'lazy_business_context', [] );
		if ( ! is_array( $business_context ) ) $business_context = [];
		?>

		<div class="wrap ltlb-admin">
			<?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_ai'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__('AI & Automations', 'ltl-bookings'); ?></h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['message'] ) && $_GET['message'] === 'saved' ): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__('Settings saved.', 'ltl-bookings'); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" class="ltlb-ai-form">
				<?php wp_nonce_field( 'ltlb_ai_save_action', 'ltlb_ai_nonce' ); ?>
				<input type="hidden" name="ltlb_ai_save" value="1">

				<!-- AI MASTER SWITCH -->
				<?php LTLB_Admin_Component::card_start(__('Enable AI Features', 'ltl-bookings')); ?>
					<table class="form-table">
						<tbody>
							<tr>
								<th><label for="ai_enabled"><?php echo esc_html__('AI Enabled', 'ltl-bookings'); ?></label></th>
								<td>
									<input type="checkbox" name="ai_enabled" id="ai_enabled" value="1" <?php checked($ai_enabled); ?>>
									<label for="ai_enabled"><?php echo esc_html__('Activate AI-powered automations', 'ltl-bookings'); ?></label>
									<p class="description"><?php echo esc_html__('Disable to turn off all AI features.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				<?php LTLB_Admin_Component::card_end(); ?>

				<!-- PROVIDER & MODEL -->
				<?php LTLB_Admin_Component::card_start(__('AI Provider', 'ltl-bookings')); ?>
					<table class="form-table">
						<tbody>
							<tr>
								<th><label for="ai_provider"><?php echo esc_html__('Provider', 'ltl-bookings'); ?></label></th>
								<td>
									<select name="ai_provider" id="ai_provider" aria-describedby="ltlb-provider-desc">
										<option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Google Gemini</option>
									</select>
									<p class="description" id="ltlb-provider-desc"><?php echo esc_html__('AI service provider for content generation.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="ai_model"><?php echo esc_html__('Model', 'ltl-bookings'); ?></label></th>
								<td>
									<select name="ai_model" id="ai_model" aria-describedby="ltlb-model-desc">
										<option value="gemini-2.5-flash" <?php selected($ai_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash (Fast)</option>
										<option value="gemini-3.0-pro-preview" <?php selected($ai_model, 'gemini-3.0-pro-preview'); ?>>Gemini 3.0 Pro Preview (Advanced)</option>
									</select>
									<p class="description" id="ltlb-model-desc"><?php echo esc_html__('Flash is faster, Pro is more accurate.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				<?php LTLB_Admin_Component::card_end(); ?>

				<!-- API KEY (SECRETS) -->
				<?php if ( current_user_can('manage_ai_secrets') ) : ?>
					<?php LTLB_Admin_Component::card_start(__('API Keys', 'ltl-bookings')); ?>
						<table class="form-table">
							<tbody>
								<tr>
									<th><label for="gemini_api_key"><?php echo esc_html__('Gemini API Key', 'ltl-bookings'); ?></label></th>
									<td>
										<input type="password" name="gemini_api_key" id="gemini_api_key" value="" class="regular-text" aria-describedby="ltlb-key-desc">
										<p class="description" id="ltlb-key-desc">
											<?php if ( $has_gemini_key ) : ?>
												<?php echo esc_html__('A key is stored. Leave blank to keep the existing key.', 'ltl-bookings'); ?>
												<br>
											<?php endif; ?>
											<?php echo esc_html__('Get your key from', 'ltl-bookings'); ?>
											<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>.
											<?php echo esc_html__('Never share this key.', 'ltl-bookings'); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><?php echo esc_html__('Test Connection', 'ltl-bookings'); ?></th>
									<td>
										<button type="button" class="ltlb-btn ltlb-btn--secondary" id="ltlb-test-connection" aria-describedby="ltlb-test-desc">
											<?php echo esc_html__('Test Connection', 'ltl-bookings'); ?>
										</button>
										<span id="ltlb-test-status" role="status" aria-live="polite"></span>
										<p class="description" id="ltlb-test-desc"><?php echo esc_html__('Verify your API key works.', 'ltl-bookings'); ?></p>
									</td>
								</tr>
							</tbody>
						</table>
					<?php LTLB_Admin_Component::card_end(); ?>
				<?php endif; ?>

				<!-- OPERATING MODE -->
				<?php LTLB_Admin_Component::card_start(__('Operating Mode', 'ltl-bookings')); ?>
					<table class="form-table">
						<tbody>
							<tr>
								<th><?php echo esc_html__('Mode', 'ltl-bookings'); ?></th>
								<td>
									<label>
										<input type="radio" name="ai_operating_mode" value="autonomous" <?php checked($ai_mode, 'autonomous'); ?>>
										<strong><?php echo esc_html__('Autonomous', 'ltl-bookings'); ?></strong> — <?php echo esc_html__('AI executes actions automatically', 'ltl-bookings'); ?>
									</label>
									<br><br>
									<label>
										<input type="radio" name="ai_operating_mode" value="human-in-the-loop" <?php checked($ai_mode, 'human-in-the-loop'); ?>>
										<strong><?php echo esc_html__('Human-in-the-Loop', 'ltl-bookings'); ?></strong> — <?php echo esc_html__('AI drafts appear in Outbox for approval', 'ltl-bookings'); ?>
									</label>
									<p class="description"><?php echo esc_html__('HITL is safer for production. Autonomous is faster.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				<?php LTLB_Admin_Component::card_end(); ?>

				<!-- BUSINESS CONTEXT -->
				<?php LTLB_Admin_Component::card_start(__('Business Context', 'ltl-bookings')); ?>
					<p class="description" style="margin-bottom:15px;">
						<?php echo esc_html__('Information AI uses to personalize responses. Toggle "Send to AI" to control what data is shared.', 'ltl-bookings'); ?>
					</p>
					<table class="form-table">
						<tbody>
							<tr>
								<th><label for="brand_name"><?php echo esc_html__('Brand Name', 'ltl-bookings'); ?></label></th>
								<td>
									<input type="text" name="brand_name" id="brand_name" value="<?php echo esc_attr($business_context['brand_name'] ?? ''); ?>" class="regular-text" aria-describedby="ltlb-brand-desc">
									<label style="display:block; margin-top:5px;">
										<input type="checkbox" name="send_brand_name" value="1" <?php checked($business_context['send_brand_name'] ?? 0); ?>>
										<?php echo esc_html__('Send to AI', 'ltl-bookings'); ?>
									</label>
									<p class="description" id="ltlb-brand-desc"><?php echo esc_html__('Your business name.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="brand_voice"><?php echo esc_html__('Brand Voice', 'ltl-bookings'); ?></label></th>
								<td>
									<textarea name="brand_voice" id="brand_voice" rows="3" class="large-text" aria-describedby="ltlb-voice-desc"><?php echo esc_textarea($business_context['brand_voice'] ?? ''); ?></textarea>
									<label style="display:block; margin-top:5px;">
										<input type="checkbox" name="send_brand_voice" value="1" <?php checked($business_context['send_brand_voice'] ?? 1); ?>>
										<?php echo esc_html__('Send to AI', 'ltl-bookings'); ?>
									</label>
									<p class="description" id="ltlb-voice-desc"><?php echo esc_html__('Tone and style (e.g., "professional", "friendly", "luxury").', 'ltl-bookings'); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="faq"><?php echo esc_html__('FAQs', 'ltl-bookings'); ?></label></th>
								<td>
									<textarea name="faq" id="faq" rows="3" class="large-text" aria-describedby="ltlb-faq-desc"><?php echo esc_textarea($business_context['faq'] ?? ''); ?></textarea>
									<label style="display:block; margin-top:5px;">
										<input type="checkbox" name="send_faq" value="1" <?php checked($business_context['send_faq'] ?? 1); ?>>
										<?php echo esc_html__('Send to AI', 'ltl-bookings'); ?>
									</label>
									<p class="description" id="ltlb-faq-desc"><?php echo esc_html__('Common questions and answers.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="policies"><?php echo esc_html__('Policies', 'ltl-bookings'); ?></label></th>
								<td>
									<textarea name="policies" id="policies" rows="3" class="large-text" aria-describedby="ltlb-policies-desc"><?php echo esc_textarea($business_context['policies'] ?? ''); ?></textarea>
									<label style="display:block; margin-top:5px;">
										<input type="checkbox" name="send_policies" value="1" <?php checked($business_context['send_policies'] ?? 1); ?>>
										<?php echo esc_html__('Send to AI', 'ltl-bookings'); ?>
									</label>
									<p class="description" id="ltlb-policies-desc"><?php echo esc_html__('Cancellation, refund, and booking policies.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="invoice_terms"><?php echo esc_html__('Invoice Terms', 'ltl-bookings'); ?></label></th>
								<td>
									<textarea name="invoice_terms" id="invoice_terms" rows="2" class="large-text" aria-describedby="ltlb-invoice-desc"><?php echo esc_textarea($business_context['invoice_terms'] ?? ''); ?></textarea>
									<label style="display:block; margin-top:5px;">
										<input type="checkbox" name="send_invoice_terms" value="1" <?php checked($business_context['send_invoice_terms'] ?? 1); ?>>
										<?php echo esc_html__('Send to AI', 'ltl-bookings'); ?>
									</label>
									<p class="description" id="ltlb-invoice-desc"><?php echo esc_html__('Payment terms, tax ID, legal info.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="contact_info"><?php echo esc_html__('Contact Info', 'ltl-bookings'); ?></label></th>
								<td>
									<input type="text" name="contact_info" id="contact_info" value="<?php echo esc_attr($business_context['contact_info'] ?? ''); ?>" class="regular-text" aria-describedby="ltlb-contact-desc" placeholder="<?php echo esc_attr__( 'mail@example.com, +1-555-0000', 'ltl-bookings' ); ?>">
									<label style="display:block; margin-top:5px;">
										<input type="checkbox" name="send_contact_info" value="1" <?php checked($business_context['send_contact_info'] ?? 0); ?>>
										<?php echo esc_html__('Send to AI', 'ltl-bookings'); ?>
									</label>
									<p class="description" id="ltlb-contact-desc"><?php echo esc_html__('Email, phone, or other public contact details.', 'ltl-bookings'); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				<?php LTLB_Admin_Component::card_end(); ?>

				<p class="submit" style="margin-top:20px;">
					<?php submit_button( esc_html__('Save AI Settings', 'ltl-bookings'), 'primary', 'ltlb_ai_save_button', false ); ?>
				</p>
			</form>
		</div>

		<?php
	}
}

