<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_CustomersPage {

	private $customer_repo;

	public function __construct() {
		$this->customer_repo = new LTLB_CustomerRepository();
	}

	public function render(): void {
		if ( ! current_user_can('manage_options') ) wp_die( esc_html__('No access', 'ltl-bookings') );
		// Handle save
		if ( isset( $_POST['ltlb_customer_save'] ) ) {
			if ( ! check_admin_referer( 'ltlb_customer_save_action', 'ltlb_customer_nonce' ) ) {
				wp_die( esc_html__('Nonce verification failed', 'ltl-bookings') );
			}

			$data = [];
			$data['email'] = LTLB_Sanitizer::email( $_POST['email'] ?? '' );
			$data['first_name'] = LTLB_Sanitizer::text( $_POST['first_name'] ?? '' );
			$data['last_name'] = LTLB_Sanitizer::text( $_POST['last_name'] ?? '' );
			$data['phone'] = LTLB_Sanitizer::text( $_POST['phone'] ?? '' );
			$data['notes'] = isset( $_POST['notes'] ) ? wp_kses_post( $_POST['notes'] ) : null;

			$res = $this->customer_repo->upsert_by_email( $data );

			$redirect = admin_url( 'admin.php?page=ltlb_customers' );
			if ( $res ) {
				LTLB_Notices::add( __( 'Customer saved.', 'ltl-bookings' ), 'success' );
			} else {
				LTLB_Notices::add( __( 'An error occurred.', 'ltl-bookings' ), 'error' );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$editing = false;
		$customer = null;
		if ( $action === 'edit' && ! empty( $_GET['id'] ) ) {
			$customer = $this->customer_repo->get_by_id( intval( $_GET['id'] ) );
			if ( $customer ) $editing = true;
		}

		$customers = $this->customer_repo->get_all();
		?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_customers'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html__('Customers', 'ltl-bookings'); ?></h1>
            <?php if ( $action !== 'add' && ! $editing ) : ?>
			    <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_customers&action=add') ); ?>" class="page-title-action"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a>
            <?php endif; ?>
            <hr class="wp-header-end">
			
            <p class="description" style="margin-bottom:20px;"><?php echo esc_html__('Manage customer information. Customers are created automatically from bookings.', 'ltl-bookings'); ?></p>

			<?php // Notices are rendered via LTLB_Notices::render() hooked to admin_notices ?>

			<?php if ( $action === 'add' || $editing ) :
				$email = $editing ? $customer['email'] : '';
				$first = $editing ? $customer['first_name'] : '';
				$last = $editing ? $customer['last_name'] : '';
				$phone = $editing ? $customer['phone'] : '';
				$notes = $editing ? $customer['notes'] : '';
				?>
                <div class="ltlb-card" style="max-width:800px;">
                    <h2><?php echo $editing ? esc_html__('Edit Customer', 'ltl-bookings') : esc_html__('Add New Customer', 'ltl-bookings'); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field( 'ltlb_customer_save_action', 'ltlb_customer_nonce' ); ?>
                        <input type="hidden" name="ltlb_customer_save" value="1" />

                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><label for="email"><?php echo esc_html__('Email', 'ltl-bookings'); ?></label></th>
                                    <td><input name="email" id="email" type="email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="first_name"><?php echo esc_html__('First name', 'ltl-bookings'); ?></label></th>
                                    <td><input name="first_name" id="first_name" type="text" value="<?php echo esc_attr( $first ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="last_name"><?php echo esc_html__('Last name', 'ltl-bookings'); ?></label></th>
                                    <td><input name="last_name" id="last_name" type="text" value="<?php echo esc_attr( $last ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="phone"><?php echo esc_html__('Phone', 'ltl-bookings'); ?></label></th>
                                    <td><input name="phone" id="phone" type="text" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="notes"><?php echo esc_html__('Notes', 'ltl-bookings'); ?></label></th>
                                    <td><textarea name="notes" id="notes" class="large-text" rows="5"><?php echo esc_textarea( $notes ); ?></textarea></td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit">
                            <?php submit_button( $editing ? esc_html__('Update Customer', 'ltl-bookings') : esc_html__('Create Customer', 'ltl-bookings'), 'primary', 'submit', false ); ?>
                            <a href="<?php echo admin_url('admin.php?page=ltlb_customers'); ?>" class="button"><?php echo esc_html__('Cancel', 'ltl-bookings'); ?></a>
                        </p>
                    </form>
                </div>
			<?php else: ?>
                <div class="ltlb-card">
                    <?php if ( empty($customers) ) : ?>
                        <p><?php echo esc_html__('No customers found.', 'ltl-bookings'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Email', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Name', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Phone', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $customers as $c ): ?>
                                    <tr>
                                        <td><a href="mailto:<?php echo esc_attr($c['email']); ?>"><?php echo esc_html($c['email']); ?></a></td>
                                        <td>
                                            <strong><a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_customers&action=edit&id='.$c['id']) ); ?>">
                                                <?php echo esc_html( trim($c['first_name'] . ' ' . $c['last_name']) ?: '—' ); ?>
                                            </a></strong>
                                        </td>
                                        <td><?php echo esc_html( $c['phone'] ); ?></td>
                                        <td>
                                            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_customers&action=edit&id='.$c['id']) ); ?>" class="button button-small"><?php echo esc_html__('Edit', 'ltl-bookings'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
			<?php endif; ?>
		</div>

		<?php
	}
}
