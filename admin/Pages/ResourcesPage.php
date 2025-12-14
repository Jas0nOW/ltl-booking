<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_ResourcesPage {
    private $resource_repository;

    public function __construct() {
        $this->resource_repository = new LTLB_ResourceRepository();
    }

    public function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have permission to view this page.', 'ltl-bookings') );
        }
        
        // Context-aware labels
        $settings = get_option('lazy_settings', []);
        $is_hotel = isset($settings['template_mode']) && $settings['template_mode'] === 'hotel';
        $label_singular = $is_hotel ? __('Room', 'ltl-bookings') : __('Resource', 'ltl-bookings');
		$label_plural = $is_hotel ? __('Rooms', 'ltl-bookings') : __('Resources', 'ltl-bookings');
		$services_label = $is_hotel ? __('Room Types', 'ltl-bookings') : __('Services', 'ltl-bookings');
        // Handle form submissions
        if ( isset( $_POST['ltlb_resource_save'] ) ) {
            if ( ! check_admin_referer( 'ltlb_resource_save_action', 'ltlb_resource_nonce' ) ) {
                wp_die( esc_html__('Security check failed', 'ltl-bookings') );
            }

            $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
            $data = [];
            $data['name'] = LTLB_Sanitizer::text( $_POST['name'] ?? '' );
            $data['description'] = isset( $_POST['description'] ) ? wp_kses_post( $_POST['description'] ) : null;
            $data['capacity'] = LTLB_Sanitizer::int( $_POST['capacity'] ?? 1 );
            $data['is_active'] = isset( $_POST['is_active'] ) ? 1 : 0;

            if ( $id > 0 ) {
                $ok = $this->resource_repository->update( $id, $data );
            } else {
                $ok = $this->resource_repository->create( $data );
            }

            $redirect = admin_url( 'admin.php?page=ltlb_resources' );
            if ( $ok ) {
                LTLB_Notices::add( __( 'Saved.', 'ltl-bookings' ), 'success' );
            } else {
                LTLB_Notices::add( __( 'An error occurred.', 'ltl-bookings' ), 'error' );
            }
            wp_safe_redirect( $redirect );
            exit;
        }

        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $editing = false;
        $resource = null;
        if ( $action === 'edit' && ! empty( $_GET['id'] ) ) {
            $resource = $this->resource_repository->get_by_id( intval( $_GET['id'] ) );
            if ( $resource ) $editing = true;
        }

        $resources = $this->resource_repository->get_all();
        ?>
        <div class="wrap ltlb-admin">
            <?php if ( class_exists('LTLB_Admin_Header') ) { LTLB_Admin_Header::render('ltlb_resources'); } ?>
            <h1 class="wp-heading-inline"><?php echo esc_html($label_plural); ?></h1>
            <?php if ( $action !== 'add' && ! $editing ) : ?>
                <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_resources&action=add') ); ?>" class="page-title-action"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a>
            <?php endif; ?>
            <hr class="wp-header-end">
            
            <p class="description" style="margin-bottom:20px;">
                <?php echo $is_hotel ? esc_html__('Rooms are the bookable units (e.g. Room 101, Room 102). Link them to room types to control availability.', 'ltl-bookings') : esc_html__('Resources are rooms, equipment, or capacities. Link them to services to control availability.', 'ltl-bookings'); ?>
                <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_services') ); ?>"><?php echo esc_html( sprintf( __( 'Manage %s', 'ltl-bookings' ), $services_label ) ); ?></a>
            </p>

            <?php if ( $action === 'add' || $editing ) :
                $form_id = $editing ? intval( $resource['id'] ) : 0;
                $name = $editing ? $resource['name'] : '';
                $description = $editing ? $resource['description'] : '';
                $capacity = $editing ? $resource['capacity'] : 1;
                $is_active = $editing ? ( ! empty( $resource['is_active'] ) ) : true;
                ?>
                <div class="ltlb-card" style="max-width:800px;">
                    <h2><?php echo $editing ? sprintf(esc_html__('Edit %s', 'ltl-bookings'), $label_singular) : sprintf(esc_html__('Add New %s', 'ltl-bookings'), $label_singular); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field( 'ltlb_resource_save_action', 'ltlb_resource_nonce' ); ?>
                        <input type="hidden" name="ltlb_resource_save" value="1" />
                        <input type="hidden" name="id" value="<?php echo esc_attr( $form_id ); ?>" />

                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th><label for="name"><?php echo esc_html__('Name', 'ltl-bookings'); ?></label></th>
                                    <td><input name="name" id="name" type="text" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="description"><?php echo esc_html__('Description', 'ltl-bookings'); ?></label></th>
                                    <td><textarea name="description" id="description" class="large-text" rows="5"><?php echo esc_textarea( $description ); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label for="capacity"><?php echo esc_html__('Capacity', 'ltl-bookings'); ?></label></th>
                                    <td>
                                        <input name="capacity" id="capacity" type="number" value="<?php echo esc_attr( $capacity ); ?>" class="small-text" required min="1">
                                        <p class="description"><?php echo esc_html__('How many concurrent bookings can this resource cover?', 'ltl-bookings'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Active', 'ltl-bookings'); ?></th>
                                    <td><label><input name="is_active" type="checkbox" value="1" <?php checked( $is_active ); ?>> <?php echo esc_html__('Yes', 'ltl-bookings'); ?></label></td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit">
                            <?php
                            $submit_label = $editing ? esc_html__( 'Update', 'ltl-bookings' ) : esc_html__( 'Create', 'ltl-bookings' );
                            submit_button( $submit_label, 'primary', 'submit', false );
                            ?>
                            <a href="<?php echo admin_url('admin.php?page=ltlb_resources'); ?>" class="button"><?php echo esc_html__('Cancel', 'ltl-bookings'); ?></a>
                        </p>
                    </form>
                </div>
            <?php else : ?>
                <div class="ltlb-card">
                    <?php if ( empty($resources) ) : ?>
                        <p>
                            <?php
                            echo $is_hotel
                                ? esc_html__( 'No rooms found.', 'ltl-bookings' )
                                : esc_html__( 'No resources found.', 'ltl-bookings' );
                            ?>
                        </p>
                        <p>
                            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_resources&action=add') ); ?>" class="button button-primary">
                                <?php
                                echo sprintf( esc_html__( 'Add New %s', 'ltl-bookings' ), $label_singular );
                                ?>
                            </a>
                        </p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Name', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Capacity', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Status', 'ltl-bookings'); ?></th>
                                    <th><?php echo esc_html__('Actions', 'ltl-bookings'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $resources as $r ): ?>
                                    <tr>
                                        <td>
                                            <strong><a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_resources&action=edit&id='.$r['id']) ); ?>">
                                                <?php echo esc_html( $r['name'] ); ?>
                                            </a></strong>
                                            <?php if ( ! empty($r['description']) ) : ?>
                                                <p class="description" style="margin:5px 0 0;"><?php echo esc_html( wp_trim_words($r['description'], 10) ); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo intval( $r['capacity'] ); ?></td>
                                        <td>
                                            <?php if ( ! empty($r['is_active']) ) : ?>
                                                <span class="ltlb-status-badge status-active"><?php echo esc_html__('Active', 'ltl-bookings'); ?></span>
                                            <?php else : ?>
                                                <span class="ltlb-status-badge status-inactive"><?php echo esc_html__('Inactive', 'ltl-bookings'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_attr( admin_url('admin.php?page=ltlb_resources&action=edit&id='.$r['id']) ); ?>" class="button button-small"><?php echo esc_html__('Edit', 'ltl-bookings'); ?></a>
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
