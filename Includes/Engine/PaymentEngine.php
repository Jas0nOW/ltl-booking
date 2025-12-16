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

    public function is_stripe_enabled(): bool {
        if ( ! $this->enabled ) {
            return false;
        }
        $p = new LTLB_StripeProcessor();
        return $p->is_enabled();
    }

    public function is_paypal_enabled(): bool {
        if ( ! $this->enabled ) {
            return false;
        }
        $p = new LTLB_PayPalProcessor();
        return $p->is_enabled();
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
        // Legacy flow: this plugin confirms online payments via provider redirects + webhooks.
        // - Stripe: Checkout redirect + webhook `checkout.session.completed`
        // - PayPal: order approval redirect + server-side capture on return
        // Direct “process_payment” calls (token/charge) are intentionally unsupported.

        if ( $amount <= 0 ) {
            return [ 'success' => true, 'payment_status' => 'free' ];
        }

        return [
            'success' => false,
            'error' => 'unsupported_payment_flow',
        ];
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

    /**
     * Create a redirect URL for a Stripe Checkout payment (MVP).
     * Returns ['success'=>true,'checkout_url'=>string,'session_id'=>string] or ['success'=>false,'error'=>string]
     */
    public function create_checkout_redirect( array $appointment, string $success_url, string $cancel_url ): array {
        if ( ! $this->enabled ) {
            return [ 'success' => false, 'error' => 'Payments not enabled' ];
        }

        $stripe = new LTLB_StripeProcessor();
        if ( ! $stripe->is_enabled() ) {
            return [ 'success' => false, 'error' => 'Stripe not properly configured' ];
        }

        $ls = get_option( 'lazy_settings', [] );
        if ( ! is_array( $ls ) ) {
            $ls = [];
        }
        $stripe_flow = sanitize_key( (string) ( $ls['stripe_flow'] ?? 'checkout' ) );
        if ( $stripe_flow !== 'checkout' ) {
            return [ 'success' => false, 'error' => 'Stripe flow is not set to checkout' ];
        }

        $amount_cents = isset( $appointment['amount_cents'] ) ? max( 0, intval( $appointment['amount_cents'] ) ) : 0;
        $currency = ! empty( $appointment['currency'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $appointment['currency'] ) ) : '';
        if ( $currency === '' ) {
            $currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $ls['default_currency'] ?? 'EUR' ) ) );
        }
        $currency = $currency !== '' ? substr( $currency, 0, 3 ) : 'EUR';

        if ( $amount_cents <= 0 ) {
            return [ 'success' => true, 'checkout_url' => '', 'session_id' => '' ];
        }

        return $stripe->create_checkout_session( $appointment, $amount_cents, $currency, $success_url, $cancel_url );
    }

    private static function paypal_api_base(): string {
        $env = function_exists( 'wp_get_environment_type' ) ? (string) wp_get_environment_type() : 'production';
        // Treat anything other than production as sandbox.
        return $env === 'production' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private static function paypal_get_access_token(): array {
        $keys = self::get_payment_keys();
        $client_id = (string) ( $keys['paypal_client_id'] ?? '' );
        $secret = (string) ( $keys['paypal_secret'] ?? '' );
        if ( $client_id === '' || $secret === '' ) {
            return [ 'success' => false, 'error' => 'PayPal not properly configured' ];
        }

        $response = wp_remote_post( self::paypal_api_base() . '/v1/oauth2/token', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['access_token'] ) ) {
            return [ 'success' => false, 'error' => 'PayPal auth failed' ];
        }
        return [ 'success' => true, 'access_token' => (string) $data['access_token'] ];
    }

    public function create_paypal_redirect( array $appointment, string $success_url, string $cancel_url ): array {
        if ( ! $this->enabled ) {
            return [ 'success' => false, 'error' => 'Payments not enabled' ];
        }
        if ( ! $this->is_paypal_enabled() ) {
            return [ 'success' => false, 'error' => 'PayPal not properly configured' ];
        }
        $appointment_id = intval( $appointment['id'] ?? 0 );
        if ( $appointment_id <= 0 ) {
            return [ 'success' => false, 'error' => 'Invalid appointment ID' ];
        }

        $amount_cents = isset( $appointment['amount_cents'] ) ? max( 0, intval( $appointment['amount_cents'] ) ) : 0;
        if ( $amount_cents <= 0 ) {
            return [ 'success' => true, 'approve_url' => '', 'order_id' => '' ];
        }
        $currency = ! empty( $appointment['currency'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $appointment['currency'] ) ) : '';
        if ( $currency === '' ) {
            $ls = get_option( 'lazy_settings', [] );
            if ( ! is_array( $ls ) ) $ls = [];
            $currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $ls['default_currency'] ?? 'EUR' ) ) );
        }
        $currency = $currency !== '' ? substr( $currency, 0, 3 ) : 'EUR';
        $value = number_format( $amount_cents / 100, 2, '.', '' );

        $token_res = self::paypal_get_access_token();
        if ( empty( $token_res['success'] ) ) {
            return [ 'success' => false, 'error' => (string) ( $token_res['error'] ?? 'PayPal auth failed' ) ];
        }
        $access_token = (string) $token_res['access_token'];

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) $appointment_id,
                    'custom_id' => (string) $appointment_id,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $value,
                    ],
                ],
            ],
            'application_context' => [
                'return_url' => $success_url,
                'cancel_url' => $cancel_url,
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = wp_remote_post( self::paypal_api_base() . '/v2/checkout/orders', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['id'] ) ) {
            return [ 'success' => false, 'error' => 'PayPal order creation failed' ];
        }

        $order_id = (string) $data['id'];
        $approve_url = '';
        if ( ! empty( $data['links'] ) && is_array( $data['links'] ) ) {
            foreach ( $data['links'] as $link ) {
                if ( ! is_array( $link ) ) continue;
                if ( ( $link['rel'] ?? '' ) === 'approve' && ! empty( $link['href'] ) ) {
                    $approve_url = (string) $link['href'];
                    break;
                }
            }
        }
        if ( $approve_url === '' ) {
            return [ 'success' => false, 'error' => 'PayPal did not return an approval URL' ];
        }

        return [ 'success' => true, 'approve_url' => $approve_url, 'order_id' => $order_id ];
    }

    public function capture_paypal_order( string $order_id ): array {
        if ( ! $this->enabled ) {
            return [ 'success' => false, 'error' => 'Payments not enabled' ];
        }
        $order_id = sanitize_text_field( $order_id );
        if ( $order_id === '' ) {
            return [ 'success' => false, 'error' => 'Missing order id' ];
        }

        $token_res = self::paypal_get_access_token();
        if ( empty( $token_res['success'] ) ) {
            return [ 'success' => false, 'error' => (string) ( $token_res['error'] ?? 'PayPal auth failed' ) ];
        }
        $access_token = (string) $token_res['access_token'];

        $response = wp_remote_post( self::paypal_api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => '{}',
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            return [ 'success' => false, 'error' => 'PayPal capture failed' ];
        }
        $status = strtoupper( (string) ( $data['status'] ?? '' ) );
        if ( $status !== 'COMPLETED' ) {
            return [ 'success' => false, 'error' => 'PayPal capture not completed', 'paypal_status' => $status ];
        }

        $capture_id = '';
        if ( ! empty( $data['purchase_units'][0]['payments']['captures'][0]['id'] ) ) {
            $capture_id = (string) $data['purchase_units'][0]['payments']['captures'][0]['id'];
        }
        return [ 'success' => true, 'order_id' => $order_id, 'capture_id' => $capture_id ];
    }

    /**
     * Process a refund for an appointment (Stripe or PayPal)
     * 
     * @param int $appointment_id Appointment ID to refund
     * @param int|null $amount_cents Amount to refund in cents (null = full refund)
     * @param string $reason Optional refund reason
     * @return array ['success' => bool, 'refund_id' => string, 'amount' => int, 'error' => string]
     */
    public function refund_payment( int $appointment_id, ?int $amount_cents = null, string $reason = '' ): array {
        if ( ! $this->enabled ) {
            return [ 'success' => false, 'error' => 'Payments not enabled' ];
        }

        // Load appointment
        $repo = class_exists( 'LTLB_AppointmentRepository' ) ? new LTLB_AppointmentRepository() : null;
        if ( ! $repo ) {
            return [ 'success' => false, 'error' => 'Repository not available' ];
        }

        $appointment = $repo->get_by_id( $appointment_id );
        if ( ! $appointment ) {
            return [ 'success' => false, 'error' => 'Appointment not found' ];
        }

        $payment_status = (string) ( $appointment['payment_status'] ?? '' );
        if ( $payment_status !== 'paid' ) {
            return [ 'success' => false, 'error' => 'Appointment is not paid' ];
        }

        $payment_method = sanitize_key( (string) ( $appointment['payment_method'] ?? '' ) );
        $payment_ref = (string) ( $appointment['payment_ref'] ?? '' );
        if ( $payment_ref === '' ) {
            return [ 'success' => false, 'error' => 'No payment reference found' ];
        }

        $original_amount = intval( $appointment['amount_cents'] ?? 0 );
        $refund_amount = $amount_cents ?? $original_amount;
        if ( $refund_amount <= 0 || $refund_amount > $original_amount ) {
            return [ 'success' => false, 'error' => 'Invalid refund amount' ];
        }

        // Delegate to processor
        if ( $payment_method === 'stripe_card' && $this->processor instanceof LTLB_StripeProcessor ) {
            return $this->processor->refund( $payment_ref, $refund_amount, $reason );
        } elseif ( $payment_method === 'paypal' && $this->processor instanceof LTLB_PayPalProcessor ) {
            return $this->processor->refund( $payment_ref, $refund_amount, $reason );
        }

        return [ 'success' => false, 'error' => 'Refund not supported for this payment method' ];
    }
}

/**
 * Stripe Payment Processor
 */
class LTLB_StripeProcessor implements LTLB_PaymentProcessorInterface {
    private const API_BASE = 'https://api.stripe.com/v1';

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
        if ( $amount <= 0 ) {
            return [ 'success' => true, 'payment_status' => 'free' ];
        }

        // Unsupported legacy flow; use Stripe Checkout + webhook confirmation.
        return [
            'success' => false,
            'error' => 'unsupported_payment_flow',
        ];
    }

    /**
     * Stripe Checkout session creation (server-side, no SDK).
     */
    public function create_checkout_session( array $appointment, int $amount_cents, string $currency, string $success_url, string $cancel_url ): array {
        $settings = $this->get_settings();
        $secret_key = (string) ( $settings['secret_key'] ?? '' );
        if ( $secret_key === '' ) {
            return [ 'success' => false, 'error' => 'Stripe not properly configured' ];
        }
        $appointment_id = intval( $appointment['id'] ?? 0 );
        if ( $appointment_id <= 0 ) {
            return [ 'success' => false, 'error' => 'Invalid appointment ID' ];
        }
        if ( $amount_cents <= 0 ) {
            return [ 'success' => true, 'checkout_url' => '', 'session_id' => '' ];
        }
        $currency = strtolower( $currency );
        $currency = preg_replace( '/[^a-z]/', '', $currency );
        $currency = $currency !== '' ? substr( $currency, 0, 3 ) : 'eur';

        $name = sprintf( 'Booking #%d', $appointment_id );
        if ( ! empty( $appointment['service_name'] ) ) {
            $name = (string) $appointment['service_name'];
        }

        $params = [
            'mode' => 'payment',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'client_reference_id' => (string) $appointment_id,
            'metadata[appointment_id]' => (string) $appointment_id,
            'payment_intent_data[metadata][appointment_id]' => (string) $appointment_id,
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][product_data][name]' => $name,
            'line_items[0][price_data][unit_amount]' => (string) $amount_cents,
            'line_items[0][quantity]' => '1',
        ];

        $requested_method = sanitize_key( (string) ( $appointment['payment_method'] ?? '' ) );
        if ( $requested_method === 'klarna' ) {
            // Klarna via Stripe Checkout (availability depends on Stripe account + country/currency).
            $params['payment_method_types[0]'] = 'klarna';
            $params['billing_address_collection'] = 'required';
        } else {
            $params['payment_method_types[0]'] = 'card';
        }

        $response = wp_remote_post( self::API_BASE . '/checkout/sessions', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
            ],
            'body' => $params,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            $err = 'Stripe error';
            if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
                $err = (string) $data['error']['message'];
            }
            return [ 'success' => false, 'error' => $err ];
        }
        $checkout_url = isset( $data['url'] ) ? (string) $data['url'] : '';
        $session_id = isset( $data['id'] ) ? (string) $data['id'] : '';
        if ( $checkout_url === '' || $session_id === '' ) {
            return [ 'success' => false, 'error' => 'Stripe did not return a checkout URL' ];
        }

        return [
            'success' => true,
            'checkout_url' => $checkout_url,
            'session_id' => $session_id,
        ];
    }

    /**
     * Refund a Stripe payment
     * 
     * @param string $payment_intent_id Stripe PaymentIntent ID (from checkout.session.completed)
     * @param int $amount_cents Amount to refund in cents
     * @param string $reason Refund reason
     * @return array ['success' => bool, 'refund_id' => string, 'amount' => int, 'error' => string]
     */
    public function refund( string $payment_intent_id, int $amount_cents, string $reason = '' ): array {
        $settings = $this->get_settings();
        $secret_key = (string) ( $settings['secret_key'] ?? '' );
        if ( $secret_key === '' ) {
            return [ 'success' => false, 'error' => 'Stripe not properly configured' ];
        }

        if ( $payment_intent_id === '' || $amount_cents <= 0 ) {
            return [ 'success' => false, 'error' => 'Invalid refund parameters' ];
        }

        $params = [
            'payment_intent' => $payment_intent_id,
            'amount' => (string) $amount_cents,
        ];

        if ( $reason !== '' ) {
            $params['reason'] = $reason; // Stripe reasons: 'duplicate', 'fraudulent', 'requested_by_customer'
        }

        $response = wp_remote_post( self::API_BASE . '/refunds', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
            ],
            'body' => $params,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            $err = 'Stripe refund failed';
            if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
                $err = (string) $data['error']['message'];
            }
            return [ 'success' => false, 'error' => $err ];
        }

        $refund_id = isset( $data['id'] ) ? (string) $data['id'] : '';
        $refund_status = isset( $data['status'] ) ? (string) $data['status'] : '';

        if ( $refund_id === '' ) {
            return [ 'success' => false, 'error' => 'Stripe did not return a refund ID' ];
        }

        return [
            'success' => true,
            'refund_id' => $refund_id,
            'amount' => $amount_cents,
            'status' => $refund_status,
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
        if ( $amount <= 0 ) {
            return [ 'success' => true, 'payment_status' => 'free' ];
        }

        // Unsupported legacy flow; use PayPal approval redirect + server-side capture.
        return [
            'success' => false,
            'error' => 'unsupported_payment_flow',
        ];
    }

    /**
     * Refund a PayPal payment
     * 
     * @param string $capture_id PayPal Capture ID (from order capture response)
     * @param int $amount_cents Amount to refund in cents
     * @param string $reason Refund reason (note for PayPal)
     * @return array ['success' => bool, 'refund_id' => string, 'amount' => int, 'error' => string]
     */
    public function refund( string $capture_id, int $amount_cents, string $reason = '' ): array {
        if ( $capture_id === '' || $amount_cents <= 0 ) {
            return [ 'success' => false, 'error' => 'Invalid refund parameters' ];
        }

        $token_res = LTLB_PaymentEngine::paypal_get_access_token();
        if ( empty( $token_res['success'] ) ) {
            return [ 'success' => false, 'error' => (string) ( $token_res['error'] ?? 'PayPal auth failed' ) ];
        }
        $access_token = (string) $token_res['access_token'];

        // PayPal refunds require currency + value
        $value = number_format( $amount_cents / 100, 2, '.', '' );
        $payload = [
            'amount' => [
                'value' => $value,
                'currency_code' => 'EUR', // TODO: Get from appointment currency
            ],
        ];

        if ( $reason !== '' ) {
            $payload['note_to_payer'] = $reason;
        }

        $response = wp_remote_post( LTLB_PaymentEngine::paypal_api_base() . '/v2/payments/captures/' . rawurlencode( $capture_id ) . '/refund', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            $err = 'PayPal refund failed';
            if ( is_array( $data ) && isset( $data['message'] ) ) {
                $err = (string) $data['message'];
            }
            return [ 'success' => false, 'error' => $err ];
        }

        $refund_id = isset( $data['id'] ) ? (string) $data['id'] : '';
        $refund_status = isset( $data['status'] ) ? (string) $data['status'] : '';

        if ( $refund_id === '' ) {
            return [ 'success' => false, 'error' => 'PayPal did not return a refund ID' ];
        }

        return [
            'success' => true,
            'refund_id' => $refund_id,
            'amount' => $amount_cents,
            'status' => $refund_status,
        ];
    }
}

