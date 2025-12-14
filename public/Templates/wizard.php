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
?>
<div class="ltlb-booking<?php echo $start_mode === 'calendar' ? ' ltlb-start-calendar' : ''; ?>" role="region" aria-label="<?php echo esc_attr__('Buchungsassistent', 'ltl-bookings'); ?>" data-ltlb-start-mode="<?php echo esc_attr( $start_mode ); ?>" data-ltlb-prefill-service="<?php echo esc_attr( $prefill_service_id ); ?>">
    <a href="#ltlb-form-start" class="ltlb-skip-link screen-reader-text"><?php echo esc_html__('Zum Buchungsformular springen', 'ltl-bookings'); ?></a>
    
    <form method="post" aria-label="<?php echo esc_attr__('Buchungsformular', 'ltl-bookings'); ?>" class="ltlb-wizard-form" novalidate>
        <?php wp_nonce_field( 'ltlb_book_action', 'ltlb_book_nonce' ); ?>
        
        <div style="display:none;" aria-hidden="true" tabindex="-1">
            <label>Bitte leer lassen<input type="text" name="ltlb_hp" value=""></label>
        </div>
        
        <span id="ltlb-form-start"></span>
	
        <h3 class="ltlb-wizard-title">
            <?php echo $is_hotel_mode ? esc_html__( 'Zimmer buchen', 'ltl-bookings' ) : esc_html__( 'Leistung buchen', 'ltl-bookings' ); ?>
        </h3>

        <?php if ( $is_hotel_mode ) : ?>
            <div id="ltlb-price-preview" class="ltlb-price-preview" style="display:none;">
                <p class="ltlb-price-label"><?php echo esc_html__('Preis-Schätzung:', 'ltl-bookings'); ?></p>
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
                <legend id="ltlb-step-service"><?php echo $is_hotel_mode ? esc_html__('Zimmertyp', 'ltl-bookings') : esc_html__('Leistung', 'ltl-bookings'); ?><span class="ltlb-required" aria-label="erforderlich">*</span></legend>
                
                <div class="ltlb-form-group">
                    <select name="service_id" class="ltlb-service-select ltlb-input" required aria-required="true" data-price-cents="">
                        <option value=""><?php echo $is_hotel_mode ? esc_html__('Zimmertyp auswählen', 'ltl-bookings') : esc_html__('Leistung auswählen', 'ltl-bookings'); ?></option>
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
                        <span class="ltlb-field-hint"><?php echo esc_html__('Preis gilt pro Nacht', 'ltl-bookings'); ?></span>
                    <?php endif; ?>
                </div>

                <div class="ltlb-step-nav">
                    <button type="button" class="button-secondary" data-ltlb-back disabled><?php echo esc_html__('Zurück', 'ltl-bookings'); ?></button>
                    <button type="button" class="button-primary" data-ltlb-next><?php echo esc_html__('Weiter', 'ltl-bookings'); ?></button>
                </div>
            </fieldset>

            <!-- Step 2: Date & Time -->
            <?php if ( $is_hotel_mode ) : ?>
                <fieldset class="ltlb-step ltlb-step-panel" aria-labelledby="ltlb-step-dates" data-ltlb-step="datetime">
                    <legend id="ltlb-step-dates"><?php echo esc_html__('Daten', 'ltl-bookings'); ?></legend>
                    <div class="ltlb-form-row">
                        <div class="ltlb-form-group">
                            <label for="ltlb-checkin">
                                <?php echo esc_html__('Anreise', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                            </label>
                            <input type="date" id="ltlb-checkin" name="checkin" required aria-required="true" class="ltlb-input">
                        </div>
                        <div class="ltlb-form-group">
                            <label for="ltlb-checkout">
                                <?php echo esc_html__('Abreise', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                            </label>
                            <input type="date" id="ltlb-checkout" name="checkout" required aria-required="true" class="ltlb-input">
                        </div>
                    </div>
                    <div class="ltlb-form-group">
                        <label for="ltlb-guests">
                            <?php echo esc_html__('Gäste', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                        </label>
                        <input type="number" id="ltlb-guests" name="guests" min="1" value="1" required aria-required="true" class="ltlb-input">
                    </div>

                    <div class="ltlb-step-nav">
                        <button type="button" class="button-secondary" data-ltlb-back><?php echo esc_html__('Zurück', 'ltl-bookings'); ?></button>
                        <button type="button" class="button-primary" data-ltlb-next><?php echo esc_html__('Weiter', 'ltl-bookings'); ?></button>
                    </div>
                </fieldset>
            <?php else : ?>
                <fieldset class="ltlb-step ltlb-step-panel" aria-labelledby="ltlb-step-datetime" data-ltlb-step="datetime">
                    <legend id="ltlb-step-datetime"><?php echo esc_html__('Datum & Uhrzeit', 'ltl-bookings'); ?></legend>
                    <div class="ltlb-form-group">
                        <label for="ltlb-date">
                            <?php echo esc_html__('Datum', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                        </label>
                        <input type="date" id="ltlb-date" name="date" required aria-required="true" class="ltlb-input">
                    </div>
                    <div class="ltlb-form-group">
                        <label for="ltlb-time-slot">
                            <?php echo esc_html__('Uhrzeit', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                        </label>
                        <select id="ltlb-time-slot" name="time_slot" required aria-required="true" class="ltlb-input">
                            <option value=""><?php echo esc_html__('Zuerst Datum wählen', 'ltl-bookings'); ?></option>
                        </select>
                        <span class="ltlb-field-hint"><?php echo esc_html__('Verfügbare Zeiten werden geladen, sobald du ein Datum auswählst', 'ltl-bookings'); ?></span>
                    </div>

                    <div class="ltlb-step-nav">
                        <button type="button" class="button-secondary" data-ltlb-back><?php echo esc_html__('Zurück', 'ltl-bookings'); ?></button>
                        <button type="button" class="button-primary" data-ltlb-next><?php echo esc_html__('Weiter', 'ltl-bookings'); ?></button>
                    </div>
                </fieldset>
            <?php endif; ?>

            <!-- Step 3: Resource (Optional) -->
            <fieldset class="ltlb-step ltlb-step-panel" id="ltlb-resource-step" aria-labelledby="ltlb-step-resource" style="display:none;" data-ltlb-step="resource">
                <legend id="ltlb-step-resource"><?php echo $is_hotel_mode ? esc_html__('Zimmerwunsch', 'ltl-bookings') : esc_html__('Ressource', 'ltl-bookings'); ?></legend>
                <div class="ltlb-form-group">
                    <select id="ltlb-resource-select" name="resource_id" class="ltlb-input">
                        <option value=""><?php echo esc_html__('Beliebig', 'ltl-bookings'); ?></option>
                    </select>
                    <span class="ltlb-field-hint" id="ltlb-resource-hint">
                        <?php echo $is_hotel_mode ? esc_html__('Mehrere Zimmer für deine Daten verfügbar', 'ltl-bookings') : esc_html__('Mehrere Ressourcen für diese Zeit verfügbar', 'ltl-bookings'); ?>
                    </span>
                </div>

                <div class="ltlb-step-nav">
                    <button type="button" class="button-secondary" data-ltlb-back><?php echo esc_html__('Zurück', 'ltl-bookings'); ?></button>
                    <button type="button" class="button-primary" data-ltlb-next><?php echo esc_html__('Weiter', 'ltl-bookings'); ?></button>
                </div>
            </fieldset>

            <!-- Step 4: User Details + Submit -->
            <fieldset class="ltlb-step ltlb-step-panel" aria-labelledby="ltlb-step-details" data-ltlb-step="details">
                <legend id="ltlb-step-details"><?php echo esc_html__('Deine Daten', 'ltl-bookings'); ?></legend>
                <div class="ltlb-form-group">
                    <label for="ltlb-email">
                        <?php echo esc_html__('Email', 'ltl-bookings'); ?><span class="ltlb-required">*</span>
                    </label>
                    <input type="email" id="ltlb-email" name="email" required aria-required="true" placeholder="you@example.com" autocomplete="email" class="ltlb-input">
                </div>
                <div class="ltlb-form-row">
                    <div class="ltlb-form-group">
                        <label for="ltlb-first-name"><?php echo esc_html__('Vorname', 'ltl-bookings'); ?></label>
                        <input type="text" id="ltlb-first-name" name="first_name" autocomplete="given-name" placeholder="Max" class="ltlb-input">
                    </div>
                    <div class="ltlb-form-group">
                        <label for="ltlb-last-name"><?php echo esc_html__('Nachname', 'ltl-bookings'); ?></label>
                        <input type="text" id="ltlb-last-name" name="last_name" autocomplete="family-name" placeholder="Mustermann" class="ltlb-input">
                    </div>
                </div>
                <div class="ltlb-form-group">
                    <label for="ltlb-phone"><?php echo esc_html__('Telefon', 'ltl-bookings'); ?></label>
                    <input type="tel" id="ltlb-phone" name="phone" placeholder="+49 151 23456789" autocomplete="tel" class="ltlb-input">
                </div>

                <div class="ltlb-step-nav">
                    <button type="button" class="button-secondary" data-ltlb-back><?php echo esc_html__('Zurück', 'ltl-bookings'); ?></button>
                    <?php submit_button( esc_html__('Buchung abschließen', 'ltl-bookings'), 'primary', 'ltlb_book_submit', false, ['aria-label' => esc_attr__('Buchungsformular absenden', 'ltl-bookings')] ); ?>
                </div>
            </fieldset>
        </div>
    </form>
</div>
