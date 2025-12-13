<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_ServicesPage {

    private $service_repository;

    public function __construct() {
        $this->service_repository = new LTLB_ServiceRepository();
    }

    /**
     * Render the services page with HTML table
     */
    public function render(): void {
        $services = $this->service_repository->get_all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Services', 'ltl-bookings'); ?></h1>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'ltl-bookings'); ?></th>
                        <th><?php esc_html_e('Name', 'ltl-bookings'); ?></th>
                        <th><?php esc_html_e('Duration (min)', 'ltl-bookings'); ?></th>
                        <th><?php esc_html_e('Price', 'ltl-bookings'); ?></th>
                        <th><?php esc_html_e('Active', 'ltl-bookings'); ?></th>
                        <th><?php esc_html_e('Created', 'ltl-bookings'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $services ) ) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No services found.', 'ltl-bookings'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $services as $service ) : ?>
                            <tr>
                                <td><?php echo esc_html( $service['id'] ); ?></td>
                                <td><?php echo esc_html( $service['name'] ); ?></td>
                                <td><?php echo esc_html( $service['duration_min'] ); ?></td>
                                <td>
                                    <?php 
                                    $price = isset( $service['price_cents'] ) ? $service['price_cents'] / 100 : 0;
                                    $currency = isset( $service['currency'] ) ? $service['currency'] : 'EUR';
                                    echo esc_html( number_format( $price, 2 ) . ' ' . $currency );
                                    ?>
                                </td>
                                <td><?php echo esc_html( $service['is_active'] ? 'Yes' : 'No' ); ?></td>
                                <td><?php echo esc_html( $service['created_at'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
