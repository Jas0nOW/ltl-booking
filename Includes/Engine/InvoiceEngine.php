<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Invoice Engine - PDF Generation
 * 
 * Generates professional invoices/receipts for bookings:
 * - Sequential invoice numbers
 * - Tax calculations and breakdown
 * - Company branding and details
 * - PDF generation and storage
 * - Download links for customers
 * 
 * @package LazyBookings
 */
class LTLB_Invoice_Engine {

    /**
     * Generate invoice for appointment
     * 
     * @param int $appointment_id
     * @param bool $regenerate Force regenerate if exists
     * @return int|WP_Error Invoice ID or error
     */
    public function generate_invoice( int $appointment_id, bool $regenerate = false ) {
        global $wpdb;

        // Check if invoice already exists
        $invoice_table = $wpdb->prefix . 'ltlb_invoices';
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $invoice_table WHERE appointment_id = %d",
            $appointment_id
        ) );

        if ( $existing && ! $regenerate ) {
            return intval( $existing->id );
        }

        // Get appointment data
        $appointment = $this->get_appointment_data( $appointment_id );
        if ( is_wp_error( $appointment ) ) {
            return $appointment;
        }

        // Generate invoice number
        $invoice_number = $this->generate_invoice_number();

        // Calculate totals
        $breakdown = $this->calculate_invoice_breakdown( $appointment );

        // Create invoice record
        $invoice_data = [
            'appointment_id' => $appointment_id,
            'invoice_number' => $invoice_number,
            'customer_id' => $appointment->customer_id,
            'issue_date' => current_time( 'mysql' ),
            'due_date' => $appointment->start_at,
            'subtotal_cents' => $breakdown['subtotal_cents'],
            'tax_cents' => $breakdown['tax_cents'],
            'discount_cents' => $breakdown['discount_cents'],
            'total_cents' => $breakdown['total_cents'],
            'currency' => $appointment->currency ?? 'EUR',
            'status' => 'paid', // Since invoice is only generated after payment
            'created_at' => current_time( 'mysql' )
        ];

        if ( $existing ) {
            // Update existing
            $wpdb->update(
                $invoice_table,
                $invoice_data,
                [ 'id' => $existing->id ],
                [ '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ],
                [ '%d' ]
            );
            $invoice_id = $existing->id;
        } else {
            // Insert new
            $wpdb->insert(
                $invoice_table,
                $invoice_data,
                [ '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ]
            );
            $invoice_id = $wpdb->insert_id;
        }

        // Generate PDF
        $pdf_path = $this->generate_pdf( $invoice_id );
        
        if ( ! is_wp_error( $pdf_path ) ) {
            $wpdb->update(
                $invoice_table,
                [ 'pdf_path' => $pdf_path ],
                [ 'id' => $invoice_id ],
                [ '%s' ],
                [ '%d' ]
            );
        }

        return $invoice_id;
    }

    /**
     * Generate sequential invoice number
     * 
     * @return string Invoice number
     */
    private function generate_invoice_number(): string {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_invoices';
        
        // Get last invoice number
        $last_number = $wpdb->get_var(
            "SELECT invoice_number FROM $table ORDER BY id DESC LIMIT 1"
        );

        if ( $last_number ) {
            // Extract numeric part and increment
            preg_match( '/(\d+)$/', $last_number, $matches );
            $next_num = isset( $matches[1] ) ? intval( $matches[1] ) + 1 : 1;
        } else {
            $next_num = 1;
        }

        // Format: INV-YYYY-NNNN
        return sprintf( 'INV-%s-%04d', date( 'Y' ), $next_num );
    }

    /**
     * Calculate invoice breakdown
     * 
     * @param object $appointment
     * @return array Breakdown with subtotal, tax, discount, total
     */
    private function calculate_invoice_breakdown( object $appointment ): array {
        $amount_cents = intval( $appointment->amount_cents );
        
        // Get tax rate (from location or service)
        $tax_rate = $this->get_tax_rate( $appointment );
        
        // Calculate tax (assuming prices include tax)
        $subtotal_cents = round( $amount_cents / ( 1 + $tax_rate ) );
        $tax_cents = $amount_cents - $subtotal_cents;
        
        // Get discount (if any)
        $discount_cents = $this->get_appointment_discount( $appointment->id );

        return [
            'subtotal_cents' => $subtotal_cents,
            'tax_cents' => $tax_cents,
            'discount_cents' => $discount_cents,
            'total_cents' => $amount_cents
        ];
    }

    /**
     * Get tax rate for appointment
     * 
     * @param object $appointment
     * @return float Tax rate (0.19 = 19%)
     */
    private function get_tax_rate( object $appointment ): float {
        global $wpdb;

        // Try to get from location
        if ( ! empty( $appointment->location_id ) ) {
            $location_table = $wpdb->prefix . 'ltlb_locations';
            $tax_rate = $wpdb->get_var( $wpdb->prepare(
                "SELECT tax_rate_percent FROM $location_table WHERE id = %d",
                $appointment->location_id
            ) );
            
            if ( $tax_rate !== null ) {
                return floatval( $tax_rate ) / 100;
            }
        }

        // Default tax rate from settings
        $default_tax = get_option( 'ltlb_default_tax_rate', 19 );
        return floatval( $default_tax ) / 100;
    }

    /**
     * Get appointment discount amount
     * 
     * @param int $appointment_id
     * @return int Discount in cents
     */
    private function get_appointment_discount( int $appointment_id ): int {
        global $wpdb;

        $usage_table = $wpdb->prefix . 'ltlb_coupon_usage';
        $coupons_table = $wpdb->prefix . 'ltlb_coupons';

        $discount = $wpdb->get_var( $wpdb->prepare(
            "SELECT 
                CASE 
                    WHEN c.discount_type = 'fixed' THEN c.discount_value
                    ELSE 0
                END as discount_cents
             FROM $usage_table cu
             INNER JOIN $coupons_table c ON cu.coupon_id = c.id
             WHERE cu.appointment_id = %d
             LIMIT 1",
            $appointment_id
        ) );

        return intval( $discount );
    }

    /**
     * Generate PDF invoice
     * 
     * @param int $invoice_id
     * @return string|WP_Error PDF file path or error
     */
    private function generate_pdf( int $invoice_id ) {
        global $wpdb;

        // Get invoice data
        $invoice_table = $wpdb->prefix . 'ltlb_invoices';
        $invoice = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $invoice_table WHERE id = %d",
            $invoice_id
        ) );

        if ( ! $invoice ) {
            return new WP_Error( 'invoice_not_found', __( 'Invoice not found', 'ltl-bookings' ) );
        }

        // Get appointment and customer details
        $appointment = $this->get_appointment_data( $invoice->appointment_id );
        if ( is_wp_error( $appointment ) ) {
            return $appointment;
        }

        // Generate HTML content
        $html = $this->get_invoice_html( $invoice, $appointment );

        // Convert to PDF using available library
        // Option 1: Use mPDF if available
        if ( class_exists( 'Mpdf\Mpdf' ) ) {
            return $this->generate_pdf_mpdf( $invoice->invoice_number, $html );
        }
        
        // Option 2: Use TCPDF if available
        if ( class_exists( 'TCPDF' ) ) {
            return $this->generate_pdf_tcpdf( $invoice->invoice_number, $html );
        }

        // Fallback: Save as HTML
        return $this->save_as_html( $invoice->invoice_number, $html );
    }

    /**
     * Generate PDF using mPDF
     * 
     * @param string $invoice_number
     * @param string $html
     * @return string PDF file path
     */
    private function generate_pdf_mpdf( string $invoice_number, string $html ): string {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/ltlb-invoices';
        
        if ( ! file_exists( $pdf_dir ) ) {
            wp_mkdir_p( $pdf_dir );
        }

        $filename = sanitize_file_name( $invoice_number ) . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4'
            ]);
            
            $mpdf->WriteHTML( $html );
            $mpdf->Output( $filepath, 'F' );
            
            return $upload_dir['baseurl'] . '/ltlb-invoices/' . $filename;
        } catch ( Exception $e ) {
            return new WP_Error( 'pdf_generation_failed', $e->getMessage() );
        }
    }

    /**
     * Generate PDF using TCPDF
     * 
     * @param string $invoice_number
     * @param string $html
     * @return string PDF file path
     */
    private function generate_pdf_tcpdf( string $invoice_number, string $html ): string {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/ltlb-invoices';
        
        if ( ! file_exists( $pdf_dir ) ) {
            wp_mkdir_p( $pdf_dir );
        }

        $filename = sanitize_file_name( $invoice_number ) . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;

        $pdf = new \TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false );
        $pdf->SetCreator( PDF_CREATOR );
        $pdf->SetTitle( $invoice_number );
        $pdf->setPrintHeader( false );
        $pdf->setPrintFooter( false );
        $pdf->AddPage();
        $pdf->writeHTML( $html, true, false, true, false, '' );
        $pdf->Output( $filepath, 'F' );

        return $upload_dir['baseurl'] . '/ltlb-invoices/' . $filename;
    }

    /**
     * Save invoice as HTML (fallback)
     * 
     * @param string $invoice_number
     * @param string $html
     * @return string HTML file path
     */
    private function save_as_html( string $invoice_number, string $html ): string {
        $upload_dir = wp_upload_dir();
        $html_dir = $upload_dir['basedir'] . '/ltlb-invoices';
        
        if ( ! file_exists( $html_dir ) ) {
            wp_mkdir_p( $html_dir );
        }

        $filename = sanitize_file_name( $invoice_number ) . '.html';
        $filepath = $html_dir . '/' . $filename;

        file_put_contents( $filepath, $html );

        return $upload_dir['baseurl'] . '/ltlb-invoices/' . $filename;
    }

    /**
     * Get invoice HTML template
     * 
     * @param object $invoice
     * @param object $appointment
     * @return string HTML content
     */
    private function get_invoice_html( object $invoice, object $appointment ): string {
        $company_name = get_option( 'ltlb_company_name', get_bloginfo( 'name' ) );
        $company_address = get_option( 'ltlb_company_address', '' );
        $company_tax_id = get_option( 'ltlb_company_tax_id', '' );

        $customer = get_userdata( $invoice->customer_id );
        
        $html = '<html><head><meta charset="UTF-8"><style>';
        $html .= 'body { font-family: Arial, sans-serif; font-size: 12px; }';
        $html .= '.header { margin-bottom: 30px; }';
        $html .= '.company { font-weight: bold; font-size: 14px; }';
        $html .= '.invoice-number { font-size: 16px; font-weight: bold; margin: 20px 0; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
        $html .= 'th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }';
        $html .= 'th { background-color: #f4f4f4; }';
        $html .= '.total { font-weight: bold; font-size: 14px; }';
        $html .= '</style></head><body>';

        // Header
        $html .= '<div class="header">';
        $html .= '<div class="company">' . esc_html( $company_name ) . '</div>';
        $html .= '<div>' . nl2br( esc_html( $company_address ) ) . '</div>';
        if ( $company_tax_id ) {
            $html .= '<div>' . esc_html__( 'Tax ID:', 'ltl-bookings' ) . ' ' . esc_html( $company_tax_id ) . '</div>';
        }
        $html .= '</div>';

        // Invoice number and date
        $html .= '<div class="invoice-number">' . esc_html__( 'Invoice', 'ltl-bookings' ) . ' ' . esc_html( $invoice->invoice_number ) . '</div>';
        $html .= '<p>' . esc_html__( 'Date:', 'ltl-bookings' ) . ' ' . date_i18n( get_option( 'date_format' ), strtotime( $invoice->issue_date ) ) . '</p>';

        // Customer
        $html .= '<p><strong>' . esc_html__( 'Bill To:', 'ltl-bookings' ) . '</strong><br>';
        $html .= esc_html( $customer->display_name ) . '<br>';
        $html .= esc_html( $customer->user_email ) . '</p>';

        // Items table
        $html .= '<table>';
        $html .= '<tr><th>' . esc_html__( 'Description', 'ltl-bookings' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Date', 'ltl-bookings' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Amount', 'ltl-bookings' ) . '</th></tr>';
        
        $html .= '<tr><td>' . esc_html( $appointment->service_name ) . '</td>';
        $html .= '<td>' . date_i18n( get_option( 'date_format' ), strtotime( $appointment->start_at ) ) . '</td>';
        $html .= '<td>' . $this->format_amount( $invoice->subtotal_cents, $invoice->currency ) . '</td></tr>';

        // Totals
        if ( $invoice->discount_cents > 0 ) {
            $html .= '<tr><td colspan="2" style="text-align:right;">' . esc_html__( 'Discount:', 'ltl-bookings' ) . '</td>';
            $html .= '<td>-' . $this->format_amount( $invoice->discount_cents, $invoice->currency ) . '</td></tr>';
        }

        $html .= '<tr><td colspan="2" style="text-align:right;">' . esc_html__( 'Tax:', 'ltl-bookings' ) . '</td>';
        $html .= '<td>' . $this->format_amount( $invoice->tax_cents, $invoice->currency ) . '</td></tr>';

        $html .= '<tr class="total"><td colspan="2" style="text-align:right;">' . esc_html__( 'Total:', 'ltl-bookings' ) . '</td>';
        $html .= '<td>' . $this->format_amount( $invoice->total_cents, $invoice->currency ) . '</td></tr>';

        $html .= '</table>';

        $html .= '<p><strong>' . esc_html__( 'Status:', 'ltl-bookings' ) . '</strong> ' . esc_html( ucfirst( $invoice->status ) ) . '</p>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Get appointment data for invoice
     * 
     * @param int $appointment_id
     * @return object|WP_Error
     */
    private function get_appointment_data( int $appointment_id ) {
        global $wpdb;

        $appointment_table = $wpdb->prefix . 'ltlb_appointments';
        $service_table = $wpdb->prefix . 'ltlb_services';

        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*, s.name as service_name 
             FROM $appointment_table a
             LEFT JOIN $service_table s ON a.service_id = s.id
             WHERE a.id = %d",
            $appointment_id
        ) );

        if ( ! $appointment ) {
            return new WP_Error( 'appointment_not_found', __( 'Appointment not found', 'ltl-bookings' ) );
        }

        return $appointment;
    }

    /**
     * Format amount with currency
     * 
     * @param int $amount_cents
     * @param string $currency
     * @return string Formatted amount
     */
    private function format_amount( int $amount_cents, string $currency = 'EUR' ): string {
        $amount = $amount_cents / 100;
        
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£'
        ];
        
        $symbol = $symbols[ $currency ] ?? $currency;
        
        return number_format( $amount, 2, ',', '.' ) . ' ' . $symbol;
    }

    /**
     * Get invoice download URL
     * 
     * @param int $invoice_id
     * @return string|WP_Error Download URL or error
     */
    public function get_download_url( int $invoice_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_invoices';
        $invoice = $wpdb->get_row( $wpdb->prepare(
            "SELECT pdf_path FROM $table WHERE id = %d",
            $invoice_id
        ) );

        if ( ! $invoice || empty( $invoice->pdf_path ) ) {
            return new WP_Error( 'no_pdf', __( 'Invoice PDF not available', 'ltl-bookings' ) );
        }

        return $invoice->pdf_path;
    }
}
