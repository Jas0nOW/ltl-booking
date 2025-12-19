<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_CustomersPage {

	private $customer_repo;

	public function __construct() {
		$this->customer_repo = new LTLB_CustomerRepository();
	}

	public function render(): void {
        if ( ! current_user_can( 'view_customers' ) && ! current_user_can( 'manage_customers' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }
        $settings = get_option( 'lazy_settings', [] );
        $template_mode = is_array( $settings ) && isset( $settings['template_mode'] ) ? $settings['template_mode'] : 'service';
        $is_hotel_mode = $template_mode === 'hotel';
        $page_title = $is_hotel_mode ? __( 'Guests', 'ltl-bookings' ) : __( 'Customers', 'ltl-bookings' );
        $item_singular = $is_hotel_mode ? __( 'Guest', 'ltl-bookings' ) : __( 'Customer', 'ltl-bookings' );
        $item_plural = $page_title;
		// Handle save
		if ( isset( $_POST['ltlb_customer_save'] ) ) {
			if ( ! check_admin_referer( 'ltlb_customer_save_action', 'ltlb_customer_nonce' ) ) {
                wp_die( esc_html__('Security check failed', 'ltl-bookings') );
			}
			if ( ! current_user_can( 'manage_customers' ) ) {
				wp_die( esc_html__( 'You do not have permission to edit customers.', 'ltl-bookings' ) );
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
                LTLB_Notices::add( $is_hotel_mode ? __( 'Guest saved.', 'ltl-bookings' ) : __( 'Customer saved.', 'ltl-bookings' ), 'success' );
			} else {
                LTLB_Notices::add( $is_hotel_mode ? __( 'Could not save guest. Please try again.', 'ltl-bookings' ) : __( 'Could not save customer. Please try again.', 'ltl-bookings' ), 'error' );
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

		// Pagination
		$per_page = isset($_GET['per_page']) ? max(20, intval($_GET['per_page'])) : 20;
		$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		$offset = ($current_page - 1) * $per_page;
		
		$total_customers = $this->customer_repo->get_count();
		$customers = $this->customer_repo->get_all($per_page, $offset);
		?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_customers'); } ?>
			<h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>
            <?php if ( $action !== 'add' && ! $editing ) : ?>
                <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_customers&action=add') ); ?>" class="ltlb-btn ltlb-btn--small ltlb-btn--primary"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a>
                <?php if ( !empty($customers) ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=ltlb_customers&action=export_csv'), 'ltlb_export_customers' ) ); ?>" class="ltlb-btn ltlb-btn--small ltlb-btn--secondary">
                        <span class="dashicons dashicons-download" style="vertical-align:middle;" aria-hidden="true"></span>
                        <?php echo esc_html__('Export CSV', 'ltl-bookings'); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <hr class="wp-header-end">
			
            <p class="description" style="margin-bottom:20px;"><?php echo esc_html( $is_hotel_mode ? __( 'Guests are created automatically from bookings. You can also add them manually.', 'ltl-bookings' ) : __( 'Customers are created automatically from bookings. You can also add them manually.', 'ltl-bookings' ) ); ?></p>

			<?php // Notices are rendered via LTLB_Notices::render() hooked to admin_notices ?>

			<?php if ( $action === 'add' || $editing ) :
				$email = $editing ? $customer['email'] : '';
				$first = $editing ? $customer['first_name'] : '';
				$last = $editing ? $customer['last_name'] : '';
				$phone = $editing ? $customer['phone'] : '';
				$notes = $editing ? $customer['notes'] : '';
				?>
                <div class="ltlb-card" style="max-width:800px;">
                    <h2>
                        <?php
                        echo esc_html(
                            $editing
                                ? ( $is_hotel_mode ? __( 'Edit Guest', 'ltl-bookings' ) : __( 'Edit Customer', 'ltl-bookings' ) )
                                : ( $is_hotel_mode ? __( 'Add New Guest', 'ltl-bookings' ) : __( 'Add New Customer', 'ltl-bookings' ) )
                        );
                        ?>
                    </h2>
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
						<th><label for="first_name"><?php echo esc_html__( 'First name', 'ltl-bookings' ); ?></label></th>
                                    <td><input name="first_name" id="first_name" type="text" value="<?php echo esc_attr( $first ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
						<th><label for="last_name"><?php echo esc_html__( 'Last name', 'ltl-bookings' ); ?></label></th>
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
                        <?php
                        submit_button(
                            $editing
                                ? ( $is_hotel_mode ? __( 'Update Guest', 'ltl-bookings' ) : __( 'Update Customer', 'ltl-bookings' ) )
                                : ( $is_hotel_mode ? __( 'Create Guest', 'ltl-bookings' ) : __( 'Create Customer', 'ltl-bookings' ) ),
                            'primary',
                            'submit',
                            false
                        );
                        ?>
						<a href="<?php echo admin_url('admin.php?page=ltlb_customers'); ?>" class="ltlb-btn ltlb-btn--secondary"><?php echo esc_html__('Cancel', 'ltl-bookings'); ?></a>
                        </p>
                    </form>
                </div>
			<?php else: ?>
                <div class="ltlb-card">
                    <?php if ( empty($customers) ) : ?>
						<?php
						LTLB_Admin_Component::empty_state(
							$is_hotel_mode ? __( 'No Guests Yet', 'ltl-bookings' ) : __( 'No Customers Yet', 'ltl-bookings' ),
							$is_hotel_mode 
								? __( 'Guests are created automatically when bookings are made, or you can add them manually.', 'ltl-bookings' )
								: __( 'Customers are created automatically from bookings, or you can add them manually.', 'ltl-bookings' ),
							$is_hotel_mode ? __( 'Add First Guest', 'ltl-bookings' ) : __( 'Add First Customer', 'ltl-bookings' ),
							admin_url('admin.php?page=ltlb_customers&action=add'),
							'dashicons-groups'
						);

                        $create_page_url = admin_url( 'post-new.php?post_type=page' );
                        echo '<p class="description" style="margin-top:12px;">' . wp_kses(
                            sprintf(
                                /* translators: 1: link to create new page in WP admin, 2: shortcode */
                                __( 'Tip: %1$s (shortcode: %2$s) and place a test booking to see it working end-to-end.', 'ltl-bookings' ),
                                '<a href="' . esc_url( $create_page_url ) . '">' . esc_html__( 'Create a booking page', 'ltl-bookings' ) . '</a>',
                                '<code>[lazy_book]</code>'
                            ),
                            [ 'a' => [ 'href' => true ], 'code' => [] ]
                        ) . '</p>';
						?>
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
									<a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_customers&action=edit&id='.$c['id']) ); ?>" class="ltlb-btn ltlb-btn--small"><?php echo esc_html__('Edit', 'ltl-bookings'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php LTLB_Admin_Component::pagination($total_customers, $per_page); ?>
                    <?php endif; ?>
                </div>
			<?php endif; ?>
		</div>

		<?php
	}
}
