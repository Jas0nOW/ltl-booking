<?php
/**
 * Template: Booking Wizard
 * 
 * Variables available:
 * $services (array)
 * $is_hotel_mode (bool)
 */
if ( ! defined('ABSPATH') ) exit;
?>
<div class="ltlb-booking">
    <a href="#ltlb-form-start" class="ltlb-skip-link screen-reader-text"><?php echo esc_html__('Skip to booking form', 'ltl-bookings'); ?></a>
    
    <form method="post" aria-label="<?php echo esc_attr__('Booking form', 'ltl-bookings'); ?>" class="ltlb-wizard-form">
        <?php wp_nonce_field( 'ltlb_book_action', 'ltlb_book_nonce' ); ?>
        
        <!-- Honeypot -->
        <div style="display:none;" aria-hidden="true" tabindex="-1">
            <label>Leave this empty<input type="text" name="ltlb_hp" value=""></label>
        </div>
        
        <span id="ltlb-form-start"></span>
        
        <h3 class="ltlb-wizard-title">
            <?php echo $is_hotel_mode ? esc_html__( 'Book a room', 'ltl-bookings' ) : esc_html__( 'Book a service', 'ltl-bookings' ); ?>
        </h3>

        <?php if ( $is_hotel_mode ) : ?>
            <div id="ltlb-price-preview" class="ltlb-price-preview" style="display:none;">
                <p class="ltlb-price-label"><?php echo esc_html__('Price estimate:', 'ltl-bookings'); ?></p>
                <p class="ltlb-price-value"><strong id="ltlb-price-amount">—</strong> <span id="ltlb-price-breakdown"></span></p>
            </div>
        <?php endif; ?>

        <!-- Step 1: Service/Room Selection -->
        <fieldset class="ltlb-step">
            <legend><?php echo $is_hotel_mode ? esc_html__('Room Type', 'ltl-bookings') : esc_html__('Service', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="required">*</span></legend>
            
            <div class="ltlb-form-group">
                <select name="service_id" class="ltlb-service-select" required aria-required="true" data-price-cents="">
                    <option value=""><?php echo $is_hotel_mode ? esc_html__('Select a room type', 'ltl-bookings') : esc_html__('Select a service', 'ltl-bookings'); ?></option>
                    <?php foreach ( $services as $s ): ?>
                        <option value="<?php echo esc_attr( $s['id'] ); ?>" data-price="<?php echo esc_attr( $s['price_cents'] ?? 0 ); ?>">
                            <?php echo esc_html( $s['name'] ); ?>
                            <?php if ( isset($s['price_cents']) && $s['price_cents'] > 0 ) : ?> 
                                — <?php echo number_format($s['price_cents']/100, 2) . ' ' . ($s['currency'] ?? 'EUR'); ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ( $is_hotel_mode ) : ?>
                    <span class="ltlb-field-hint"><?php echo esc_html__('Price shown is per night', 'ltl-bookings'); ?></span>
                <?php endif; ?>
            </div>
        </fieldset>

        <!-- Step 2: Date & Time -->
        <?php if ( $is_hotel_mode ) : ?>
            <fieldset class="ltlb-step">
                <legend><?php echo esc_html__('Dates', 'ltl-bookings'); ?></legend>
                <div class="ltlb-form-row">
                    <div class="ltlb-form-group">
                        <label for="ltlb-checkin">
                            <?php echo esc_html__('Check-in', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                        </label>
                        <input type="date" id="ltlb-checkin" name="checkin" required aria-required="true">
                    </div>
                    <div class="ltlb-form-group">
                        <label for="ltlb-checkout">
                            <?php echo esc_html__('Check-out', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                        </label>
                        <input type="date" id="ltlb-checkout" name="checkout" required aria-required="true">
                    </div>
                </div>
                <div class="ltlb-form-group">
                    <label for="ltlb-guests">
                        <?php echo esc_html__('Guests', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                    </label>
                    <input type="number" id="ltlb-guests" name="guests" min="1" value="1" required aria-required="true">
                </div>
            </fieldset>
        <?php else : ?>
            <fieldset class="ltlb-step">
                <legend><?php echo esc_html__('Date & Time', 'ltl-bookings'); ?></legend>
                <div class="ltlb-form-group">
                    <label for="ltlb-date">
                        <?php echo esc_html__('Date', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                    </label>
                    <input type="date" id="ltlb-date" name="date" required aria-required="true">
                </div>
                <div class="ltlb-form-group">
                    <label for="ltlb-time-slot">
                        <?php echo esc_html__('Time', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                    </label>
                    <select id="ltlb-time-slot" name="time_slot" required aria-required="true">
                        <option value=""><?php echo esc_html__('Select date first', 'ltl-bookings'); ?></option>
                    </select>
                    <span class="ltlb-field-hint"><?php echo esc_html__('Available time slots will load after selecting a date', 'ltl-bookings'); ?></span>
                </div>
            </fieldset>
        <?php endif; ?>

        <!-- Step 3: Resource (Optional) -->
        <fieldset class="ltlb-step" id="ltlb-resource-step" style="display:none;">
            <legend><?php echo $is_hotel_mode ? esc_html__('Room Preference', 'ltl-bookings') : esc_html__('Resource', 'ltl-bookings'); ?></legend>
            <div class="ltlb-form-group">
                <select id="ltlb-resource-select" name="resource_id">
                    <option value=""><?php echo esc_html__('Any available', 'ltl-bookings'); ?></option>
                </select>
                <span class="ltlb-field-hint" id="ltlb-resource-hint">
                    <?php echo $is_hotel_mode ? esc_html__('Multiple rooms available for your dates', 'ltl-bookings') : esc_html__('Multiple resources available for this time', 'ltl-bookings'); ?>
                </span>
            </div>
        </fieldset>

        <!-- Step 4: User Details -->
        <fieldset class="ltlb-step">
            <legend><?php echo esc_html__('Your details', 'ltl-bookings'); ?></legend>
            <div class="ltlb-form-group">
                <label for="ltlb-email">
                    <?php echo esc_html__('Email', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                </label>
                <input type="email" id="ltlb-email" name="email" required aria-required="true" placeholder="you@example.com" autocomplete="email">
            </div>
            <div class="ltlb-form-row">
                <div class="ltlb-form-group">
                    <label for="ltlb-first-name"><?php echo esc_html__('First name', 'ltl-bookings'); ?></label>
                    <input type="text" id="ltlb-first-name" name="first_name" autocomplete="given-name">
                </div>
                <div class="ltlb-form-group">
                    <label for="ltlb-last-name"><?php echo esc_html__('Last name', 'ltl-bookings'); ?></label>
                    <input type="text" id="ltlb-last-name" name="last_name" autocomplete="family-name">
                </div>
            </div>
            <div class="ltlb-form-group">
                <label for="ltlb-phone"><?php echo esc_html__('Phone', 'ltl-bookings'); ?></label>
                <input type="tel" id="ltlb-phone" name="phone" placeholder="+1234567890" autocomplete="tel">
            </div>
        </fieldset>

        <div class="ltlb-form-actions">
            <?php submit_button( esc_html__('Complete Booking', 'ltl-bookings'), 'primary', 'ltlb_book_submit', false, ['aria-label' => esc_attr__('Submit booking form', 'ltl-bookings')] ); ?>
        </div>
    </form>
</div>
