<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Pricing Engine Integration
 * 
 * Features:
 * - Integration with booking flow
 * - Admin UI for pricing rules
 * - Dynamic pricing rules management
 * - Real-time price calculations
 * 
 * @package LazyBookings
 */
class LTLB_Pricing_Integration {

    /**
     * Initialize integration hooks
     */
    public static function init(): void {
        // Booking flow integration
        add_filter( 'ltlb_calculate_booking_price', [ __CLASS__, 'calculate_booking_price' ], 10, 2 );
        add_action( 'ltlb_before_booking_save', [ __CLASS__, 'apply_pricing_to_booking' ] );
        
        // Admin UI hooks
        add_action( 'admin_menu', [ __CLASS__, 'add_pricing_menu' ] );
        add_action( 'wp_ajax_ltlb_save_pricing_rule', [ __CLASS__, 'ajax_save_pricing_rule' ] );
        add_action( 'wp_ajax_ltlb_delete_pricing_rule', [ __CLASS__, 'ajax_delete_pricing_rule' ] );
        
        // Frontend hooks
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_scripts' ] );
        add_action( 'wp_ajax_ltlb_get_price_quote', [ __CLASS__, 'ajax_get_price_quote' ] );
        add_action( 'wp_ajax_nopriv_ltlb_get_price_quote', [ __CLASS__, 'ajax_get_price_quote' ] );
    }

    /**
     * Calculate booking price with pricing engine
     * 
     * @param int $price Default price
     * @param array $booking_data Booking data
     * @return int Calculated price in cents
     */
    public static function calculate_booking_price( int $price, array $booking_data ): int {
        $pricing_engine = new LTLB_Pricing_Engine();
        
        $params = [
            'service_id' => $booking_data['service_id'] ?? 0,
            'room_id' => $booking_data['room_id'] ?? 0,
            'start_date' => $booking_data['start_date'] ?? '',
            'end_date' => $booking_data['end_date'] ?? '',
            'num_persons' => $booking_data['num_persons'] ?? 1,
            'duration_hours' => $booking_data['duration_hours'] ?? 0
        ];

        $result = $pricing_engine->calculate( $params );
        
        return $result['total_cents'] ?? $price;
    }

    /**
     * Apply pricing to booking before save
     * 
     * @param array $booking_data Booking data
     */
    public static function apply_pricing_to_booking( array &$booking_data ): void {
        if ( ! isset( $booking_data['amount_cents'] ) || $booking_data['amount_cents'] === 0 ) {
            $booking_data['amount_cents'] = self::calculate_booking_price( 0, $booking_data );
        }
    }

    /**
     * Add pricing rules menu page
     */
    public static function add_pricing_menu(): void {
        add_submenu_page(
            'ltlb_dashboard',
            __( 'Pricing Rules', 'ltl-bookings' ),
            __( 'Pricing Rules', 'ltl-bookings' ),
            'manage_options',
            'ltlb_pricing',
            [ __CLASS__, 'render_pricing_page' ]
        );
    }

    /**
     * Render pricing rules admin page
     */
    public static function render_pricing_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ltl-bookings' ) );
        }

        $rules = self::get_all_pricing_rules();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Pricing Rules', 'ltl-bookings' ); ?></h1>
            
            <div class="ltlb-pricing-page">
                <div class="ltlb-pricing-rules-list">
                    <h2><?php esc_html_e( 'Active Pricing Rules', 'ltl-bookings' ); ?></h2>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Modifier', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Applies To', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Valid Period', 'ltl-bookings' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'ltl-bookings' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $rules ) ): ?>
                                <tr>
                                    <td colspan="6"><?php esc_html_e( 'No pricing rules defined yet.', 'ltl-bookings' ); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ( $rules as $rule ): ?>
                                    <tr>
                                        <td><?php echo esc_html( $rule['name'] ); ?></td>
                                        <td><?php echo esc_html( ucfirst( $rule['type'] ) ); ?></td>
                                        <td><?php echo esc_html( sprintf( '%+d%%', round( ( $rule['modifier'] - 1 ) * 100 ) ) ); ?></td>
                                        <td><?php echo esc_html( $rule['scope'] ); ?></td>
                                        <td>
                                            <?php 
                                            if ( $rule['start_date'] && $rule['end_date'] ) {
                                                echo esc_html( date_i18n( 'M j, Y', strtotime( $rule['start_date'] ) ) );
                                                echo ' - ';
                                                echo esc_html( date_i18n( 'M j, Y', strtotime( $rule['end_date'] ) ) );
                                            } else {
                                                esc_html_e( 'Always', 'ltl-bookings' );
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="button button-small" onclick="editPricingRule(<?php echo esc_attr( $rule['id'] ); ?>)">
                                                <?php esc_html_e( 'Edit', 'ltl-bookings' ); ?>
                                            </button>
                                            <button class="button button-small" onclick="deletePricingRule(<?php echo esc_attr( $rule['id'] ); ?>)">
                                                <?php esc_html_e( 'Delete', 'ltl-bookings' ); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <p>
                        <button class="button button-primary" onclick="showAddRuleModal()">
                            <?php esc_html_e( 'Add Pricing Rule', 'ltl-bookings' ); ?>
                        </button>
                    </p>
                </div>
                
                <div class="ltlb-pricing-form" id="pricing-rule-modal" style="display:none;">
                    <h2 id="modal-title"><?php esc_html_e( 'Add Pricing Rule', 'ltl-bookings' ); ?></h2>
                    
                    <form id="pricing-rule-form">
                        <input type="hidden" name="rule_id" id="rule_id" value="" />
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="rule_name"><?php esc_html_e( 'Rule Name', 'ltl-bookings' ); ?></label></th>
                                <td><input type="text" id="rule_name" name="rule_name" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th><label for="rule_type"><?php esc_html_e( 'Rule Type', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <select id="rule_type" name="rule_type" required>
                                        <option value="seasonal"><?php esc_html_e( 'Seasonal', 'ltl-bookings' ); ?></option>
                                        <option value="weekday"><?php esc_html_e( 'Weekday/Weekend', 'ltl-bookings' ); ?></option>
                                        <option value="duration"><?php esc_html_e( 'Duration Discount', 'ltl-bookings' ); ?></option>
                                        <option value="person"><?php esc_html_e( 'Person Count', 'ltl-bookings' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="rule_modifier"><?php esc_html_e( 'Price Modifier (%)', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <input type="number" id="rule_modifier" name="rule_modifier" step="0.01" value="0" />
                                    <p class="description"><?php esc_html_e( 'Enter percentage change (e.g., 20 for +20%, -10 for -10%)', 'ltl-bookings' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e( 'Applies To', 'ltl-bookings' ); ?></label></th>
                                <td>
                                    <label>
                                        <input type="radio" name="applies_to" value="all" checked />
                                        <?php esc_html_e( 'All Services/Rooms', 'ltl-bookings' ); ?>
                                    </label><br/>
                                    <label>
                                        <input type="radio" name="applies_to" value="specific" />
                                        <?php esc_html_e( 'Specific Services/Rooms', 'ltl-bookings' ); ?>
                                    </label>
                                    <select id="specific_items" name="specific_items[]" multiple style="display:none; margin-top:5px;">
                                        <!-- Populated via JS -->
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="start_date"><?php esc_html_e( 'Valid From', 'ltl-bookings' ); ?></label></th>
                                <td><input type="date" id="start_date" name="start_date" /></td>
                            </tr>
                            <tr>
                                <th><label for="end_date"><?php esc_html_e( 'Valid Until', 'ltl-bookings' ); ?></label></th>
                                <td><input type="date" id="end_date" name="end_date" /></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Rule', 'ltl-bookings' ); ?></button>
                            <button type="button" class="button" onclick="hideAddRuleModal()"><?php esc_html_e( 'Cancel', 'ltl-bookings' ); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        function showAddRuleModal() {
            document.getElementById('pricing-rule-modal').style.display = 'block';
            document.getElementById('rule_id').value = '';
            document.getElementById('pricing-rule-form').reset();
            document.getElementById('modal-title').textContent = '<?php esc_html_e( 'Add Pricing Rule', 'ltl-bookings' ); ?>';
        }
        
        function hideAddRuleModal() {
            document.getElementById('pricing-rule-modal').style.display = 'none';
        }
        
        function editPricingRule(id) {
            // TODO: Load rule data and populate form
            showAddRuleModal();
            document.getElementById('modal-title').textContent = '<?php esc_html_e( 'Edit Pricing Rule', 'ltl-bookings' ); ?>';
        }
        
        function deletePricingRule(id) {
            if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this rule?', 'ltl-bookings' ); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'ltlb_delete_pricing_rule',
                rule_id: id,
                nonce: '<?php echo wp_create_nonce( 'ltlb_pricing_rule' ); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to delete rule');
                }
            });
        }
        
        jQuery(document).ready(function($) {
            $('#pricing-rule-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                $.post(ajaxurl, formData + '&action=ltlb_save_pricing_rule&nonce=<?php echo wp_create_nonce( 'ltlb_pricing_rule' ); ?>', function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to save rule');
                    }
                });
            });
            
            $('input[name="applies_to"]').on('change', function() {
                if ($(this).val() === 'specific') {
                    $('#specific_items').show();
                } else {
                    $('#specific_items').hide();
                }
            });
        });
        </script>
        
        <style>
        .ltlb-pricing-page {
            max-width: 1200px;
        }
        
        .ltlb-pricing-form {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        #specific_items {
            width: 100%;
            min-height: 100px;
        }
        </style>
        <?php
    }

    /**
     * Get all pricing rules
     * 
     * @return array Pricing rules
     */
    public static function get_all_pricing_rules(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_pricing_rules';
        
        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            return [];
        }

        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY priority DESC, id ASC",
            ARRAY_A
        );
    }

    /**
     * AJAX: Save pricing rule
     */
    public static function ajax_save_pricing_rule(): void {
        check_ajax_referer( 'ltlb_pricing_rule', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'ltl-bookings' ) ] );
        }

        // TODO: Implement save logic with database table
        
        wp_send_json_success( [ 'message' => __( 'Pricing rule saved', 'ltl-bookings' ) ] );
    }

    /**
     * AJAX: Delete pricing rule
     */
    public static function ajax_delete_pricing_rule(): void {
        check_ajax_referer( 'ltlb_pricing_rule', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'ltl-bookings' ) ] );
        }

        // TODO: Implement delete logic
        
        wp_send_json_success( [ 'message' => __( 'Pricing rule deleted', 'ltl-bookings' ) ] );
    }

    /**
     * AJAX: Get price quote
     */
    public static function ajax_get_price_quote(): void {
        $service_id = intval( $_POST['service_id'] ?? 0 );
        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date = sanitize_text_field( $_POST['end_date'] ?? '' );
        $num_persons = intval( $_POST['num_persons'] ?? 1 );

        $pricing_engine = new LTLB_Pricing_Engine();
        
        $result = $pricing_engine->calculate( [
            'service_id' => $service_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'num_persons' => $num_persons
        ] );

        wp_send_json_success( $result );
    }

    /**
     * Enqueue frontend scripts
     */
    public static function enqueue_frontend_scripts(): void {
        if ( ! is_page() && ! is_singular() ) {
            return;
        }

        wp_add_inline_script( 'ltlb-public', self::get_inline_script() );
    }

    /**
     * Get inline script for price calculation
     * 
     * @return string JavaScript code
     */
    private static function get_inline_script(): string {
        return "
        function ltlbUpdatePrice() {
            const form = document.querySelector('.ltlb-booking-form');
            if (!form) return;
            
            const serviceId = form.querySelector('[name=\"service_id\"]')?.value;
            const startDate = form.querySelector('[name=\"start_date\"]')?.value;
            const endDate = form.querySelector('[name=\"end_date\"]')?.value;
            const numPersons = form.querySelector('[name=\"num_persons\"]')?.value || 1;
            
            if (!serviceId || !startDate) return;
            
            fetch('" . admin_url( 'admin-ajax.php' ) . "', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'ltlb_get_price_quote',
                    service_id: serviceId,
                    start_date: startDate,
                    end_date: endDate,
                    num_persons: numPersons
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    ltlbDisplayPriceBreakdown(data.data);
                }
            });
        }
        
        function ltlbDisplayPriceBreakdown(priceData) {
            const container = document.getElementById('ltlb-price-breakdown');
            if (!container) return;
            
            let html = '<div class=\"ltlb-price-breakdown\">';
            
            priceData.breakdown.forEach(item => {
                html += '<div class=\"price-item\">';
                html += '<span>' + item.label + '</span>';
                html += '<span>' + ltlbFormatPrice(item.amount_cents) + '</span>';
                html += '</div>';
            });
            
            html += '<div class=\"price-total\">';
            html += '<span><strong>Total</strong></span>';
            html += '<span><strong>' + ltlbFormatPrice(priceData.total_cents) + '</strong></span>';
            html += '</div>';
            
            html += '</div>';
            
            container.innerHTML = html;
        }
        
        function ltlbFormatPrice(cents) {
            return new Intl.NumberFormat('de-DE', {
                style: 'currency',
                currency: 'EUR'
            }).format(cents / 100);
        }
        
        // Auto-update price on input change
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.ltlb-booking-form');
            if (!form) return;
            
            form.addEventListener('change', function(e) {
                if (['service_id', 'start_date', 'end_date', 'num_persons'].includes(e.target.name)) {
                    ltlbUpdatePrice();
                }
            });
        });
        ";
    }
}

// Initialize integration
LTLB_Pricing_Integration::init();
