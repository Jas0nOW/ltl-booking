<?php
/**
 * Template: Booking Wizard
 * 
 * Variables available:
 * $services (array)
 * $is_hotel_mode (bool)
 */
if ( ! defined('ABSPATH') ) exit;

$start_mode = isset( $start_mode ) ? strval( $start_mode ) : 'wizard';
if ( $start_mode !== 'calendar' ) {
    $start_mode = 'wizard';
}

$prefill_service_id = isset( $prefill_service_id ) ? intval( $prefill_service_id ) : 0;

$prefill_service_exists = false;
if ( $prefill_service_id > 0 && is_array( $services ?? null ) ) {
    foreach ( $services as $s ) {
        if ( intval( $s['id'] ?? 0 ) === $prefill_service_id ) {
            $prefill_service_exists = true;
            break;
        }
    }
}

// With the JS stepper, we always keep steps available so the user can go back.
$hide_service_step = false;
$prefill_date = isset( $prefill_date ) ? (string) $prefill_date : '';
$prefill_time = isset( $prefill_time ) ? (string) $prefill_time : '';
$prefill_checkin = isset( $prefill_checkin ) ? (string) $prefill_checkin : '';
$prefill_checkout = isset( $prefill_checkout ) ? (string) $prefill_checkout : '';
$prefill_guests = isset( $prefill_guests ) ? max( 1, intval( $prefill_guests ) ) : 1;
?>
<div class="ltlb-booking<?php echo $start_mode === 'calendar' ? ' ltlb-start-calendar' : ''; ?>" role="region" aria-label="<?php echo esc_attr__( 'Booking Wizard', 'ltl-bookings' ); ?>" data-ltlb-start-mode="<?php echo esc_attr( $start_mode ); ?>" data-ltlb-prefill-service="<?php echo esc_attr( $prefill_service_id ); ?>" data-ltlb-prefill-date="<?php echo esc_attr( $prefill_date ); ?>" data-ltlb-prefill-time="<?php echo esc_attr( $prefill_time ); ?>" data-ltlb-prefill-checkin="<?php echo esc_attr( $prefill_checkin ); ?>" data-ltlb-prefill-checkout="<?php echo esc_attr( $prefill_checkout ); ?>" data-ltlb-prefill-guests="<?php echo esc_attr( $prefill_guests ); ?>">
    <a href="#ltlb-form-start" class="ltlb-skip-link screen-reader-text"><?php echo esc_html__( 'Skip to booking form', 'ltl-bookings' ); ?></a>
    
    <form method="post" aria-label="<?php echo esc_attr__( 'Booking form', 'ltl-bookings' ); ?>" class="ltlb-wizard-form" novalidate>
        <?php wp_nonce_field( 'ltlb_book_action', 'ltlb_book_nonce' ); ?>
        
        <div style="display:none;" aria-hidden="true" tabindex="-1">
            <label><?php echo esc_html__( 'Leave this field empty', 'ltl-bookings' ); ?><input type="text" name="ltlb_hp" value=""></label>
        </div>
        
        <span id="ltlb-form-start"></span>
	
        <h3 class="ltlb-wizard-title">
            <?php echo $is_hotel_mode ? esc_html__( 'Book a room', 'ltl-bookings' ) : esc_html__( 'Book a service', 'ltl-bookings' ); ?>
        </h3>

        <?php if ( $is_hotel_mode ) : ?>
            <div id="ltlb-price-preview" class="ltlb-price-preview" style="display:none;">
                <p class="ltlb-price-label"><?php echo esc_html__( 'Estimated price:', 'ltl-bookings' ); ?></p>
                <p class="ltlb-price-value"><strong id="ltlb-price-amount">—</strong> <span id="ltlb-price-breakdown"></span></p>
            </div>
        <?php endif; ?>

        <div class="ltlb-step-indicator" data-ltlb-step-indicator role="status" aria-live="polite">
            <span class="ltlb-step-indicator__count" data-ltlb-step-count></span>
            <span class="ltlb-step-indicator__title" data-ltlb-step-title></span>
        </div>

        <div class="ltlb-stepper" data-ltlb-stepper>
            <!-- Step 1: Service/Room Selection -->
            <fieldset class="ltlb-step ltlb-step-panel" aria-labelledby="ltlb-step-service" data-ltlb-step="service">
                <legend id="ltlb-step-service"><?php echo $is_hotel_mode ? esc_html__( 'Room type', 'ltl-bookings' ) : esc_html__( 'Service', 'ltl-bookings' ); ?><abbr class="ltlb-required" title="<?php echo esc_attr__( 'required', 'ltl-bookings' ); ?>">*</abbr></legend>
                
                <div class="ltlb-form-group">
                    <select name="service_id" class="ltlb-service-select ltlb-input" required aria-required="true" data-price-cents="">
                        <option value=""><?php echo $is_hotel_mode ? esc_html__( 'Select room type', 'ltl-bookings' ) : esc_html__( 'Select service', 'ltl-bookings' ); ?></option>
                        <?php foreach ( $services as $s ): ?>
							<?php $sid = intval( $s['id'] ); ?>
                            <option value="<?php echo esc_attr( $sid ); ?>" data-price="<?php echo esc_attr( $s['price_cents'] ?? 0 ); ?>"<?php echo ( $prefill_service_id && $sid === $prefill_service_id ) ? ' selected' : ''; ?>>
                                <?php echo esc_html( $s['name'] ); ?>
                                <?php if ( isset($s['price_cents']) && $s['price_cents'] > 0 ) : ?> 
                                    — <?php echo number_format($s['price_cents']/100, 2) . ' ' . ($s['currency'] ?? 'EUR'); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( $is_hotel_mode ) : ?>
                        <span class="ltlb-field-hint"><?php echo esc_html__( 'Price is per night', 'ltl-bookings' ); ?></span>
                    <?php endif; ?>
                </div>

                <div class="ltlb-step-nav">
                    <button type="button" class="button-secondary" data-ltlb-back disabled><?php echo esc_html__( 'Back', 'ltl-bookings' ); ?></button>
                    <button type="button" class="button-primary" data-ltlb-next><?php echo esc_html__( 'Next', 'ltl-bookings' ); ?></button>
                </div>
            </fieldset>

            <!-- Step 2: Date & Time -->
            <?php if ( $is_hotel_mode ) : ?>
                <fieldset class="ltlb-step ltlb-step-panel" aria-labelledby="ltlb-step-dates" data-ltlb-step="datetime">
                    <legend id="ltlb-step-dates"><?php echo esc_html__( 'Dates', 'ltl-bookings' ); ?></legend>
                    <div class="ltlb-form-row">
                        <div class="ltlb-form-group">
                            <label for="ltlb-checkin">
                                <?php echo esc_html__( 'Check-in', 'ltl-bookings' ); ?><abbr class="ltlb-required" title="<?php echo esc_attr__( 'required', 'ltl-bookings' ); ?>">*</abbr>
                            </label>
                            <input type="date" id="ltlb-checkin" name="checkin" required aria-required="true" class="ltlb-input">
                        </div>
                        <div class="ltlb-form-group">
                            <label for="ltlb-checkout">
                                <?php echo esc_html__( 'Check-out', 'ltl-bookings' ); ?><abbr class="ltlb-required" title="<?php echo esc_attr__( 'required', 'ltl-bookings' ); ?>">*</abbr>
                            </label>
                            <input type="date" id="ltlb-checkout" name="checkout" required aria-required="true" class="ltlb-input">
                        </div>
                    </div>
                    <div class="ltlb-form-group">
                        <label for="ltlb-guests">
                            <?php echo esc_html__( 'Guests', 'ltl-bookings' ); ?><abbr class="ltlb-required" title="<?php echo esc_attr__( 'required', 'ltl-bookings' ); ?>">*</abbr>
                        </label>
                        <input type="number" id="ltlb-guests" name="guests" min="1" value="1" required aria-required="true" class="ltlb-input">
                    </div>

                    <div class="ltlb-step-nav">
                        <button type="button" class="button-secondary" data-ltlb-back><?php echo esc_html__( 'Back', 'ltl-bookings' ); ?></button>
                        <button type="button" class="button-primary" data-ltlb-next><?php echo esc_html__( 'Next', 'ltl-bookings' ); ?></button>
                    </div>
                </fieldset>
            <?php else : ?>
                <fieldset class="ltlb-step ltlb-step-panel" aria-labelledby="ltlb-step-datetime" data-ltlb-step="datetime">
                    <legend id="ltlb-step-datetime"><?php echo esc_html__( 'Date & Time', 'ltl-bookings' ); ?></legend>
                    <div class="ltlb-form-group">
                        <label for="ltlb-date">
                            <?php echo esc_html__( 'Date', 'ltl-bookings' ); ?><abbr class="ltlb-required" title="<?php echo esc_attr__( 'required', 'ltl-bookings' ); ?>">*</abbr>
                        </label>
                        <input type="date" id="ltlb-date" name="date" required aria-required="true" class="ltlb-input">
                    </div>
                    <div class="ltlb-form-group">
                        <label for="ltlb-time-slot">
                            <?php echo esc_html__( 'Time', 'ltl-bookings' ); ?><abbr class="ltlb-required" title="<?php echo esc_attr__( 'required', 'ltl-bookings' ); ?>">*</abbr>
                        </label>
                        <select id="ltlb-time-slot" name="time_slot" required aria-required="true" class="ltlb-input">
                            <option value=""><?php echo esc_html__( 'Select a date first', 'ltl-bookings' ); ?></option>
                        </select>
                        <span class="ltlb-field-hint"><?php echo esc_html__( 'Available times load after you select a date', 'ltl-bookings' ); ?></span>
                    </div>

                    <div class="ltlb-step-nav">
                        <button type="button" class="button-secondary" data-ltlb-back><?php echo esc_html__( 'Back', 'ltl-bookings' ); ?></button>
                        <button type="button" class="button-primary" data-ltlb-next><?php echo esc_html__( 'Next', 'ltl-bookings' ); ?></button>
                    </div>
                </fieldset>
            <?php endif; ?>

            <!-- Step 3: Resource (Optional) -->
            <fieldset class="ltlb-step ltlb-step-panel" id="ltlb-resource-step" aria-labelledby="ltlb-step-resource" style="display:none;" data-ltlb-step="resource">
                <legend id="ltlb-step-resource"><?php echo $is_hotel_mode ? esc_html__( 'Room preference', 'ltl-bookings' ) : esc_html__( 'Resource', 'ltl-bookings' ); ?></legend>
                <div class="ltlb-form-group">
                    <select id="ltlb-resource-select" name="resource_id" class="ltlb-input">
                        <option value=""><?php echo esc_html__( 'Any', 'ltl-bookings' ); ?></option>
                    </select>
                    <span class="ltlb-field-hint" id="ltlb-resource-hint">
                        <?php echo $is_hotel_mode ? esc_html__( 'Multiple rooms are available for your dates', 'ltl-bookings' ) : esc_html__( 'Multiple resources are available for this time', 'ltl-bookings' ); ?>
                    </span>
                </div>

                <div class="ltlb-step-nav">
                    <button type="button" class="button-secondary" data-ltlb-back><?php echo esc_html__( 'Back', 'ltl-bookings' ); ?></button>
                    <button type="button" class="button-primary" data-ltlb-next><?php echo esc_html__( 'Next', 'ltl-bookings' ); ?></button>
                </div>
            </fieldset>

            <!-- Step 4: User Details + Submit -->
            <fieldset class="ltlb-step ltlb-step-panel" aria-labelledby="ltlb-step-details" data-ltlb-step="details">
                <legend id="ltlb-step-details"><?php echo esc_html__( 'Your details', 'ltl-bookings' ); ?></legend>
                <div class="ltlb-form-group">
                    <label for="ltlb-email">
                        <?php echo esc_html__( 'Email', 'ltl-bookings' ); ?><abbr class="ltlb-required" title="<?php echo esc_attr__( 'required', 'ltl-bookings' ); ?>">*</abbr>
                    </label>
					<input type="email" id="ltlb-email" name="email" required aria-required="true" autocomplete="email" class="ltlb-input">
                </div>
                <div class="ltlb-form-row">
                    <div class="ltlb-form-group">
                        <label for="ltlb-first-name"><?php echo esc_html__( 'First name', 'ltl-bookings' ); ?></label>
						<input type="text" id="ltlb-first-name" name="first_name" autocomplete="given-name" class="ltlb-input">
                    </div>
                    <div class="ltlb-form-group">
                        <label for="ltlb-last-name"><?php echo esc_html__( 'Last name', 'ltl-bookings' ); ?></label>
						<input type="text" id="ltlb-last-name" name="last_name" autocomplete="family-name" class="ltlb-input">
                    </div>
                </div>
                <div class="ltlb-form-group">
                    <label for="ltlb-phone"><?php echo esc_html__( 'Phone', 'ltl-bookings' ); ?></label>
					<input type="tel" id="ltlb-phone" name="phone" autocomplete="tel" class="ltlb-input">
                </div>

                <?php
                $ltlb_payment_methods = isset( $ltlb_payment_methods ) && is_array( $ltlb_payment_methods ) ? $ltlb_payment_methods : [];
                $ltlb_default_payment_method = isset( $ltlb_default_payment_method ) ? sanitize_key( (string) $ltlb_default_payment_method ) : '';
                if ( empty( $ltlb_default_payment_method ) ) {
                    $ltlb_default_payment_method = ! empty( $ltlb_payment_methods ) ? (string) $ltlb_payment_methods[0] : '';
                }
                $labels = [
                    'stripe_card' => __( 'Pay online (card)', 'ltl-bookings' ),
                    'cash' => __( 'Pay on site (cash)', 'ltl-bookings' ),
                    'pos_card' => __( 'Pay on site (card / POS)', 'ltl-bookings' ),
                    'invoice' => __( 'Company invoice', 'ltl-bookings' ),
                    'paypal' => __( 'PayPal', 'ltl-bookings' ),
                    'klarna' => __( 'Klarna', 'ltl-bookings' ),
                ];
                ?>
                <?php if ( ! empty( $ltlb_payment_methods ) ) : ?>
                    <fieldset class="ltlb-form-group" aria-labelledby="ltlb-payment-method-label">
                        <legend id="ltlb-payment-method-label"><?php echo esc_html__( 'Payment method', 'ltl-bookings' ); ?></legend>
                        <div class="ltlb-radio-group" data-ltlb-payment-methods>
                            <?php foreach ( $ltlb_payment_methods as $m ) :
                                $m = sanitize_key( (string) $m );
                                if ( $m === '' ) continue;
                                $label = $labels[ $m ] ?? ucfirst( str_replace( '_', ' ', $m ) );
                                $checked = ( $m === $ltlb_default_payment_method ) ? ' checked' : '';
                            ?>
                                <label style="display:block;margin:6px 0;">
                                    <input type="radio" name="payment_method" value="<?php echo esc_attr( $m ); ?>"<?php echo $checked; ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="ltlb-form-group" data-ltlb-invoice-fields style="display:none;">
                        <label for="ltlb-company-name"><?php echo esc_html__( 'Company name', 'ltl-bookings' ); ?></label>
                        <input type="text" id="ltlb-company-name" name="company_name" class="ltlb-input" autocomplete="organization">
                        <span class="ltlb-field-hint"><?php echo esc_html__( 'Required for company invoices.', 'ltl-bookings' ); ?></span>
                    </div>
                    <div class="ltlb-form-group" data-ltlb-invoice-fields style="display:none;">
                        <label for="ltlb-company-vat"><?php echo esc_html__( 'VAT / Tax ID', 'ltl-bookings' ); ?></label>
                        <input type="text" id="ltlb-company-vat" name="company_vat" class="ltlb-input" autocomplete="off">
                    </div>
                <?php endif; ?>

                <div class="ltlb-step-nav">
                    <button type="button" class="button-secondary" data-ltlb-back><?php echo esc_html__( 'Back', 'ltl-bookings' ); ?></button>
                    <?php submit_button( esc_html__( 'Confirm booking', 'ltl-bookings' ), 'primary', 'ltlb_book_submit', false, [ 'aria-label' => esc_attr__( 'Submit booking form', 'ltl-bookings' ) ] ); ?>
                </div>
            </fieldset>
        </div>
    </form>
</div>
