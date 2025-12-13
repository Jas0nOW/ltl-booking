<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Admin_ServicesPage {

    private $service_repository;

    public function __construct() {
        $this->service_repository = new LTLB_ServiceRepository();
    }

    public function render(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_die( esc_html__('You do not have permission to view this page.', 'ltl-bookings') );
        }

        $services = $this->service_repository->get_all();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Services', 'ltl-bookings'); ?> <a href="#" class="page-title-action"><?php echo esc_html__('Add New', 'ltl-bookings'); ?></a></h1>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Name', 'ltl-bookings'); ?></th>
                        <th><?php echo esc_html__('Duration (min)', 'ltl-bookings'); ?></th>
                        <th><?php echo esc_html__('Price', 'ltl-bookings'); ?></th>
                        <th><?php echo esc_html__('Active', 'ltl-bookings'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $services ) ) : ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html__('No services yet', 'ltl-bookings'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $services as $service ) : ?>
                            <tr>
                                <td><?php echo esc_html( $service['name'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $service['duration_min'] ?? '' ); ?></td>
                                <td>
                                    <?php
                                    $price = 0;
                                    if ( isset( $service['price_cents'] ) ) {
                                        $price = $service['price_cents'] / 100;
                                    } elseif ( isset( $service['price'] ) ) {
                                        $price = $service['price'];
                                    }
                                    $currency = isset( $service['currency'] ) ? $service['currency'] : 'EUR';
                                    echo esc_html( number_format( (float) $price, 2 ) . ' ' . $currency );
                                    ?>
                                </td>
                                <td><?php echo esc_html( ! empty( $service['is_active'] ) ? 'Yes' : 'No' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
