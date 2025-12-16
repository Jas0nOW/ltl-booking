<?php
/**
 * Multi-Currency System (Money Helper)
 * 
 * Handles multiple currencies, proper formatting, and optional FX conversion.
 * Supports location-specific currencies and consistent display across UI/emails/PDFs/reports.
 *
 * @package LTL_Bookings
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LTLB_Money {
    
    /**
     * Currency configurations
     */
    private const CURRENCIES = [
        'EUR' => [
            'symbol' => '€',
            'name' => 'Euro',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'decimals' => 2,
            'symbol_position' => 'right', // 'left' or 'right'
            'symbol_space' => true
        ],
        'USD' => [
            'symbol' => '$',
            'name' => 'US Dollar',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'decimals' => 2,
            'symbol_position' => 'left',
            'symbol_space' => false
        ],
        'GBP' => [
            'symbol' => '£',
            'name' => 'British Pound',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'decimals' => 2,
            'symbol_position' => 'left',
            'symbol_space' => false
        ],
        'CHF' => [
            'symbol' => 'CHF',
            'name' => 'Swiss Franc',
            'decimal_separator' => '.',
            'thousands_separator' => "'",
            'decimals' => 2,
            'symbol_position' => 'right',
            'symbol_space' => true
        ],
        'JPY' => [
            'symbol' => '¥',
            'name' => 'Japanese Yen',
            'decimal_separator' => '',
            'thousands_separator' => ',',
            'decimals' => 0,
            'symbol_position' => 'left',
            'symbol_space' => false
        ],
        'AUD' => [
            'symbol' => 'A$',
            'name' => 'Australian Dollar',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'decimals' => 2,
            'symbol_position' => 'left',
            'symbol_space' => false
        ],
        'CAD' => [
            'symbol' => 'C$',
            'name' => 'Canadian Dollar',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'decimals' => 2,
            'symbol_position' => 'left',
            'symbol_space' => false
        ],
    ];
    
    /**
     * Format amount with currency
     *
     * @param int $amount_cents Amount in cents
     * @param string $currency Currency code (EUR, USD, etc.)
     * @param bool $include_symbol Include currency symbol
     * @return string Formatted amount
     */
    public static function format( int $amount_cents, string $currency = 'EUR', bool $include_symbol = true ): string {
        $config = self::get_currency_config( $currency );
        
        // Convert cents to major unit
        $amount = $amount_cents / 100;
        
        // Format number
        $formatted = number_format(
            $amount,
            $config['decimals'],
            $config['decimal_separator'],
            $config['thousands_separator']
        );
        
        // Add symbol if requested
        if ( $include_symbol ) {
            $symbol = $config['symbol'];
            $space = $config['symbol_space'] ? ' ' : '';
            
            if ( $config['symbol_position'] === 'left' ) {
                $formatted = $symbol . $space . $formatted;
            } else {
                $formatted = $formatted . $space . $symbol;
            }
        }
        
        return $formatted;
    }
    
    /**
     * Parse formatted amount to cents
     *
     * @param string $formatted Formatted amount string
     * @param string $currency Currency code
     * @return int Amount in cents
     */
    public static function parse( string $formatted, string $currency = 'EUR' ): int {
        $config = self::get_currency_config( $currency );
        
        // Remove currency symbol and spaces
        $cleaned = str_replace( $config['symbol'], '', $formatted );
        $cleaned = str_replace( ' ', '', $cleaned );
        
        // Remove thousands separator
        $cleaned = str_replace( $config['thousands_separator'], '', $cleaned );
        
        // Replace decimal separator with dot
        $cleaned = str_replace( $config['decimal_separator'], '.', $cleaned );
        
        // Convert to float then to cents
        $amount = floatval( $cleaned );
        
        return intval( round( $amount * 100 ) );
    }
    
    /**
     * Convert between currencies
     *
     * @param int $amount_cents Amount in cents
     * @param string $from_currency Source currency
     * @param string $to_currency Target currency
     * @return int Converted amount in cents
     */
    public static function convert( int $amount_cents, string $from_currency, string $to_currency ): int {
        if ( $from_currency === $to_currency ) {
            return $amount_cents;
        }
        
        $rate = self::get_exchange_rate( $from_currency, $to_currency );
        
        if ( ! $rate ) {
            return $amount_cents; // No conversion available
        }
        
        $amount = $amount_cents / 100;
        $converted = $amount * $rate;
        
        return intval( round( $converted * 100 ) );
    }
    
    /**
     * Get exchange rate between currencies
     *
     * @param string $from Source currency
     * @param string $to Target currency
     * @return float|null Exchange rate or null if not available
     */
    private static function get_exchange_rate( string $from, string $to ): ?float {
        // Check if exchange rates are enabled
        if ( ! self::is_conversion_enabled() ) {
            return null;
        }
        
        // Get cached rates
        $rates = get_transient( 'ltlb_exchange_rates' );
        
        if ( ! $rates ) {
            $rates = self::fetch_exchange_rates();
            
            if ( $rates ) {
                set_transient( 'ltlb_exchange_rates', $rates, HOUR_IN_SECONDS );
            }
        }
        
        if ( ! $rates || ! isset( $rates[ $from ] ) || ! isset( $rates[ $to ] ) ) {
            return null;
        }
        
        // Convert from -> USD -> to
        $from_to_usd = $rates[ $from ];
        $usd_to_target = $rates[ $to ];
        
        return ( 1 / $from_to_usd ) * $usd_to_target;
    }
    
    /**
     * Fetch exchange rates from API
     *
     * @return array|null Exchange rates keyed by currency code
     */
    private static function fetch_exchange_rates(): ?array {
        $api_key = get_option( 'ltlb_exchange_rates_api_key', '' );
        
        if ( ! $api_key ) {
            return null;
        }
        
        // Example: Using exchangeratesapi.io or similar
        $url = 'https://api.exchangerate.host/latest?base=USD';
        
        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );
        
        if ( is_wp_error( $response ) ) {
            return null;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! isset( $data['rates'] ) ) {
            return null;
        }
        
        return $data['rates'];
    }
    
    /**
     * Get currency configuration
     *
     * @param string $currency Currency code
     * @return array Configuration
     */
    private static function get_currency_config( string $currency ): array {
        $currency = strtoupper( $currency );
        
        if ( isset( self::CURRENCIES[ $currency ] ) ) {
            return self::CURRENCIES[ $currency ];
        }
        
        // Default to EUR
        return self::CURRENCIES['EUR'];
    }
    
    /**
     * Get available currencies
     *
     * @return array Currency codes and names
     */
    public static function get_available_currencies(): array {
        $currencies = [];
        
        foreach ( self::CURRENCIES as $code => $config ) {
            $currencies[ $code ] = $config['name'];
        }
        
        return $currencies;
    }
    
    /**
     * Get default currency
     *
     * @param int|null $location_id Location ID (optional)
     * @return string Currency code
     */
    public static function get_default_currency( ?int $location_id = null ): string {
        if ( $location_id ) {
            // Get location-specific currency
            global $wpdb;
            $location = $wpdb->get_row( $wpdb->prepare(
                "SELECT currency FROM {$wpdb->prefix}ltlb_locations WHERE id = %d",
                $location_id
            ), ARRAY_A );
            
            if ( $location && ! empty( $location['currency'] ) ) {
                return strtoupper( $location['currency'] );
            }
        }
        
        // Get global default
        return strtoupper( get_option( 'ltlb_default_currency', 'EUR' ) );
    }
    
    /**
     * Set default currency
     *
     * @param string $currency Currency code
     * @return bool Success
     */
    public static function set_default_currency( string $currency ): bool {
        $currency = strtoupper( $currency );
        
        if ( ! isset( self::CURRENCIES[ $currency ] ) ) {
            return false;
        }
        
        return update_option( 'ltlb_default_currency', $currency );
    }
    
    /**
     * Check if currency conversion is enabled
     *
     * @return bool
     */
    private static function is_conversion_enabled(): bool {
        return (bool) get_option( 'ltlb_enable_currency_conversion', false );
    }
    
    /**
     * Format range (e.g., "€50 - €100")
     *
     * @param int $min_cents Minimum amount in cents
     * @param int $max_cents Maximum amount in cents
     * @param string $currency Currency code
     * @return string Formatted range
     */
    public static function format_range( int $min_cents, int $max_cents, string $currency = 'EUR' ): string {
        return self::format( $min_cents, $currency ) . ' - ' . self::format( $max_cents, $currency );
    }
    
    /**
     * Get currency symbol
     *
     * @param string $currency Currency code
     * @return string Symbol
     */
    public static function get_symbol( string $currency = 'EUR' ): string {
        $config = self::get_currency_config( $currency );
        return $config['symbol'];
    }
    
    /**
     * Calculate percentage
     *
     * @param int $amount_cents Base amount
     * @param float $percentage Percentage (e.g., 10 for 10%)
     * @return int Calculated amount in cents
     */
    public static function calculate_percentage( int $amount_cents, float $percentage ): int {
        return intval( round( ( $amount_cents * $percentage ) / 100 ) );
    }
    
    /**
     * Add amounts
     *
     * @param int ...$amounts Amounts in cents
     * @return int Total in cents
     */
    public static function add( int ...$amounts ): int {
        return array_sum( $amounts );
    }
    
    /**
     * Subtract amounts
     *
     * @param int $amount Amount in cents
     * @param int ...$subtract Amounts to subtract
     * @return int Result in cents (minimum 0)
     */
    public static function subtract( int $amount, int ...$subtract ): int {
        return max( 0, $amount - array_sum( $subtract ) );
    }
    
    /**
     * Compare amounts
     *
     * @param int $amount1 First amount in cents
     * @param int $amount2 Second amount in cents
     * @return int -1 if less, 0 if equal, 1 if greater
     */
    public static function compare( int $amount1, int $amount2 ): int {
        if ( $amount1 < $amount2 ) return -1;
        if ( $amount1 > $amount2 ) return 1;
        return 0;
    }
    
    /**
     * Round amount to currency precision
     *
     * @param float $amount Amount as float
     * @param string $currency Currency code
     * @return int Amount in cents
     */
    public static function round( float $amount, string $currency = 'EUR' ): int {
        $config = self::get_currency_config( $currency );
        
        if ( $config['decimals'] === 0 ) {
            return intval( round( $amount ) ) * 100;
        }
        
        return intval( round( $amount * 100 ) );
    }
    
    /**
     * Check if amount is zero
     *
     * @param int $amount_cents Amount in cents
     * @return bool
     */
    public static function is_zero( int $amount_cents ): bool {
        return $amount_cents === 0;
    }
    
    /**
     * Check if amount is positive
     *
     * @param int $amount_cents Amount in cents
     * @return bool
     */
    public static function is_positive( int $amount_cents ): bool {
        return $amount_cents > 0;
    }
    
    /**
     * Render currency settings in admin
     */
    public static function render_settings_section(): void {
        $default_currency = self::get_default_currency();
        $conversion_enabled = self::is_conversion_enabled();
        $api_key = get_option( 'ltlb_exchange_rates_api_key', '' );
        
        ?>
        <div class="ltlb-currency-settings">
            <h3><?php esc_html_e( 'Currency Settings', 'ltl-bookings' ); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ltlb_default_currency"><?php esc_html_e( 'Default Currency', 'ltl-bookings' ); ?></label>
                    </th>
                    <td>
                        <select name="ltlb_default_currency" id="ltlb_default_currency">
                            <?php foreach ( self::get_available_currencies() as $code => $name ): ?>
                                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_currency, $code ); ?>>
                                    <?php echo esc_html( $name . ' (' . self::get_symbol( $code ) . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'The default currency for new bookings and prices.', 'ltl-bookings' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ltlb_enable_currency_conversion"><?php esc_html_e( 'Enable Currency Conversion', 'ltl-bookings' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="ltlb_enable_currency_conversion" id="ltlb_enable_currency_conversion" value="1" <?php checked( $conversion_enabled ); ?>>
                            <?php esc_html_e( 'Allow automatic currency conversion', 'ltl-bookings' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Requires Exchange Rates API key.', 'ltl-bookings' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ltlb_exchange_rates_api_key"><?php esc_html_e( 'Exchange Rates API Key', 'ltl-bookings' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="ltlb_exchange_rates_api_key" id="ltlb_exchange_rates_api_key" 
                               value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: API provider URL */
                                esc_html__( 'Get a free API key from %s', 'ltl-bookings' ),
                                '<a href="https://exchangerate.host" target="_blank">exchangerate.host</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Preview', 'ltl-bookings' ); ?></th>
                    <td>
                        <p><strong><?php esc_html_e( 'Example amounts:', 'ltl-bookings' ); ?></strong></p>
                        <ul>
                            <li>€50,00: <?php echo esc_html( self::format( 5000, 'EUR' ) ); ?></li>
                            <li>$75.00: <?php echo esc_html( self::format( 7500, 'USD' ) ); ?></li>
                            <li>£100.00: <?php echo esc_html( self::format( 10000, 'GBP' ) ); ?></li>
                            <li>¥10,000: <?php echo esc_html( self::format( 1000000, 'JPY' ) ); ?></li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Add filter to consistently format all prices in system
     */
    public static function init(): void {
        add_filter( 'ltlb_format_price', [ __CLASS__, 'format' ], 10, 2 );
        add_filter( 'ltlb_parse_price', [ __CLASS__, 'parse' ], 10, 2 );
        add_filter( 'ltlb_convert_currency', [ __CLASS__, 'convert' ], 10, 3 );
        
        // Admin settings integration
        add_action( 'ltlb_settings_page_currency_section', [ __CLASS__, 'render_settings_section' ] );
    }
}

// Initialize
LTLB_Money::init();
