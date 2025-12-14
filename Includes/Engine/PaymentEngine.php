<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Payment Engine Interface
 */
interface LTLB_PaymentProcessorInterface {
    public function process_payment( array $appointment, float $amount ): array;
    public function is_enabled(): bool;
    public function get_settings(): array;
}

/**
 * Payment Engine - Stripe & PayPal
 */
class LTLB_PaymentEngine {

    private static $instance = null;
    private $enabled = false;
    private $processor = null;

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $settings = get_option('lazy_settings', []);
        $this->enabled = ! empty($settings['enable_payments']);
        
        if ( $this->enabled && ! empty($settings['payment_processor']) ) {
            $processor_type = $settings['payment_processor'];
            if ( $processor_type === 'stripe' ) {
                $this->processor = new LTLB_StripeProcessor();
            } elseif ( $processor_type === 'paypal' ) {
                $this->processor = new LTLB_PayPalProcessor();
            }
        }
    }

    /**
     * Payment keys are stored separately (autoload=no). Fallback to legacy keys
     * in lazy_settings for backwards compatibility.
     */
    public static function get_payment_keys(): array {
        $keys = get_option( 'lazy_payment_keys', [] );
        if ( ! is_array( $keys ) ) {
            $keys = [];
        }

        $legacy = get_option( 'lazy_settings', [] );
        if ( ! is_array( $legacy ) ) {
            $legacy = [];
        }

        return [
            'stripe_public_key' => (string) ( $keys['stripe_public_key'] ?? $legacy['stripe_public_key'] ?? '' ),
            'stripe_secret_key' => (string) ( $keys['stripe_secret_key'] ?? $legacy['stripe_secret_key'] ?? '' ),
            'paypal_client_id' => (string) ( $keys['paypal_client_id'] ?? $legacy['paypal_client_id'] ?? '' ),
            'paypal_secret' => (string) ( $keys['paypal_secret'] ?? $legacy['paypal_secret'] ?? '' ),
        ];
    }

    /**
     * Check if payments are enabled
     */
    public function is_enabled(): bool {
        return $this->enabled && $this->processor && $this->processor->is_enabled();
    }

    /**
     * Get processor type
     */
    public function get_processor_type(): ?string {
        if ( ! $this->processor ) return null;
        return $this->processor instanceof LTLB_StripeProcessor ? 'stripe' : 'paypal';
    }

    /**
     * Render payment form HTML
     */
    public function render_payment_form( array $appointment ): void {
        if ( ! $this->is_enabled() ) return;

        $amount = 0.0;
        if ( isset( $appointment['amount_cents'] ) ) {
            $amount = floatval( intval( $appointment['amount_cents'] ) ) / 100;
        } else {
            $amount = floatval( $appointment['price'] ?? 0 );
        }
        if ( $amount <= 0 ) return;

        echo '<div class="ltlb-payment-form-wrapper">';
        echo '<h3>' . esc_html__('Payment', 'ltl-bookings') . '</h3>';

        if ( $this->processor instanceof LTLB_StripeProcessor ) {
            $this->processor->render_form($appointment, $amount);
        } elseif ( $this->processor instanceof LTLB_PayPalProcessor ) {
            $this->processor->render_form($appointment, $amount);
        }

        echo '</div>';
    }

    /**
     * Process payment (called after booking form submission)
     */
    public function process_payment( array $appointment, float $amount ): array {
        if ( ! $this->is_enabled() ) {
            return ['success' => true, 'payment_status' => 'free'];
        }

        if ( $amount <= 0 ) {
            return ['success' => true, 'payment_status' => 'free'];
        }

        return $this->processor->process_payment($appointment, $amount);
    }

    /**
     * Get payment settings for frontend JS
     */
    public function get_frontend_config(): array {
        if ( ! $this->is_enabled() ) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'processor' => $this->get_processor_type(),
            'public_key' => $this->processor->get_public_key(),
        ];
    }
}

/**
 * Stripe Payment Processor
 */
class LTLB_StripeProcessor implements LTLB_PaymentProcessorInterface {

    public function is_enabled(): bool {
		$keys = LTLB_PaymentEngine::get_payment_keys();
		return ! empty( $keys['stripe_public_key'] ) && ! empty( $keys['stripe_secret_key'] );
    }

    public function get_settings(): array {
		$keys = LTLB_PaymentEngine::get_payment_keys();
		return [
			'public_key' => $keys['stripe_public_key'] ?? '',
			'secret_key' => $keys['stripe_secret_key'] ?? '',
		];
    }

    public function get_public_key(): string {
		$keys = LTLB_PaymentEngine::get_payment_keys();
		return $keys['stripe_public_key'] ?? '';
    }

    public function render_form( array $appointment, float $amount ): void {
        ?>
        <div id="stripe-payment-form">
            <div class="ltlb-payment-field">
                <label><?php echo esc_html__('Card Details', 'ltl-bookings'); ?></label>
                <div id="stripe-card-element" class="ltlb-stripe-card-element"></div>
                <div id="stripe-card-errors" class="ltlb-stripe-card-errors" role="alert" aria-live="polite"></div>
            </div>
            <button type="button" id="stripe-pay-button" class="button button-primary" data-amount="<?php echo esc_attr($amount * 100); ?>">
                <?php echo esc_html__('Pay Now', 'ltl-bookings') . ' €' . number_format($amount, 2); ?>
            </button>
        </div>
        <script>
            window.ltlbStripeConfig = {
                publicKey: '<?php echo esc_js($this->get_public_key()); ?>',
                appointmentId: <?php echo intval($appointment['id'] ?? 0); ?>,
                amount: <?php echo floatval($amount * 100); ?>
            };
        </script>
        <?php
    }

    public function process_payment( array $appointment, float $amount ): array {
        // Mock implementation - real Stripe integration would verify token here
        $settings = $this->get_settings();
        if ( empty($settings['secret_key']) ) {
            return [
                'success' => false,
                'error' => 'Stripe not properly configured'
            ];
        }

        // In production: verify Stripe token, create charge, handle response
        // For now: simulate successful payment
        return [
            'success' => true,
            'payment_status' => 'paid',
            'transaction_id' => 'stripe_' . uniqid(),
            'amount' => $amount,
        ];
    }
}

/**
 * PayPal Payment Processor
 */
class LTLB_PayPalProcessor implements LTLB_PaymentProcessorInterface {

    public function is_enabled(): bool {
		$keys = LTLB_PaymentEngine::get_payment_keys();
		return ! empty( $keys['paypal_client_id'] ) && ! empty( $keys['paypal_secret'] );
    }

    public function get_settings(): array {
		$keys = LTLB_PaymentEngine::get_payment_keys();
		return [
			'client_id' => $keys['paypal_client_id'] ?? '',
			'secret' => $keys['paypal_secret'] ?? '',
		];
    }

    public function get_public_key(): string {
		$keys = LTLB_PaymentEngine::get_payment_keys();
		return $keys['paypal_client_id'] ?? '';
    }

    public function render_form( array $appointment, float $amount ): void {
        ?>
        <div id="paypal-payment-form">
            <div class="ltlb-payment-field">
                <div id="paypal-button-container"></div>
                <div id="paypal-messages"></div>
            </div>
        </div>
        <script>
            window.ltlbPayPalConfig = {
                clientId: '<?php echo esc_js($this->get_public_key()); ?>',
                appointmentId: <?php echo intval($appointment['id'] ?? 0); ?>,
                amount: '<?php echo number_format($amount, 2, '.', ''); ?>',
                currency: 'EUR'
            };
        </script>
        <?php
    }

    public function process_payment( array $appointment, float $amount ): array {
        // Mock implementation - real PayPal integration would verify transaction here
        $settings = $this->get_settings();
        if ( empty($settings['secret']) ) {
            return [
                'success' => false,
                'error' => 'PayPal not properly configured'
            ];
        }

        // In production: verify PayPal transaction ID, capture payment, handle response
        return [
            'success' => true,
            'payment_status' => 'paid',
            'transaction_id' => 'paypal_' . uniqid(),
            'amount' => $amount,
        ];
    }
}
