<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Google Gemini AI Provider
 * 
 * Integrates with Google Generative AI API
 */
class LTLB_AI_Gemini implements LTLB_AI_Provider {

	private string $api_key = '';
	private string $model = 'gemini-2.5-flash';
	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';
	private const TIMEOUT = 30;

	public function __construct( string $api_key, string $model = 'gemini-2.5-flash' ) {
		$this->api_key = $api_key;
		$this->model = $model;
	}

	/**
	 * Test API connection
	 */
	public function test_connection(): array {
		if ( ! $this->api_key ) {
			return [
				'success' => false,
				'message' => __('No API key provided', 'ltl-bookings'),
				'timestamp' => current_time('Y-m-d H:i:s'),
			];
		}

		$url = $this->get_endpoint_url('generateContent');
		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode([
					'contents' => [
						[
							'parts' => [
								['text' => 'Hello'],
							],
						],
					],
				]),
				'timeout' => self::TIMEOUT,
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					__('Connection failed: %s', 'ltl-bookings'),
					$response->get_error_message()
				),
				'timestamp' => current_time('Y-m-d H:i:s'),
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code || 400 === $code ) {
			// 200 = success, 400 = auth issue but API responded
			return [
				'success' => true,
				'message' => __('Connection OK', 'ltl-bookings'),
				'timestamp' => current_time('Y-m-d H:i:s'),
			];
		}

		if ( 401 === $code ) {
			return [
				'success' => false,
				'message' => __('Authentication failed (invalid API key)', 'ltl-bookings'),
				'timestamp' => current_time('Y-m-d H:i:s'),
			];
		}

		if ( 429 === $code ) {
			return [
				'success' => false,
				'message' => __('Rate limited. Try again later.', 'ltl-bookings'),
				'timestamp' => current_time('Y-m-d H:i:s'),
			];
		}

		return [
			'success' => false,
			'message' => sprintf(
				__('Server error (HTTP %d)', 'ltl-bookings'),
				$code
			),
			'timestamp' => current_time('Y-m-d H:i:s'),
		];
	}

	/**
	 * Generate text
	 */
	public function generate_text( string $prompt, array $context = [] ): string {
		if ( ! $this->api_key || ! $prompt ) {
			return '';
		}

		if ( empty( $context ) ) {
			$context = get_option( 'lazy_business_context', [] );
			if ( ! is_array( $context ) ) {
				$context = [];
			}
		}

		// Build system prompt from context
		$system_prompt = $this->build_system_prompt( $context );

		$url = $this->get_endpoint_url('generateContent');
		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode([
					'system_instruction' => [
						'parts' => [
							['text' => $system_prompt],
						],
					],
					'contents' => [
						[
							'parts' => [
								['text' => $prompt],
							],
						],
					],
				]),
				'timeout' => self::TIMEOUT,
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			LTLB_Logger::log_error( 'Gemini error: ' . $response->get_error_message() );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			LTLB_Logger::log_error( "Gemini HTTP $code" );
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			LTLB_Logger::log_error( 'Gemini: unexpected response format' );
			return '';
		}

		return $data['candidates'][0]['content']['parts'][0]['text'];
	}

	/**
	 * Get model info
	 */
	public function get_model_info(): array {
		return [
			'name' => 'Google Gemini',
			'version' => $this->model,
			'max_tokens' => 8192,
		];
	}

	/**
	 * Private: Build API endpoint URL with key
	 */
	private function get_endpoint_url( string $method ): string {
		$model = str_replace( 'gemini-', '', (string) $this->model );
		return sprintf(
			'%s/models/%s:%s?key=%s',
			self::API_BASE,
			$this->model,
			$method,
			urlencode( $this->api_key )
		);
	}

	/**
	 * Private: Build system prompt from business context
	 */
	private function build_system_prompt( array $context ): string {
		$parts = ["You are a professional AI assistant for a booking service."];

		if ( isset( $context['send_brand_name'] ) && $context['send_brand_name'] && ! empty( $context['brand_name'] ) ) {
			$parts[] = "Brand name: " . $context['brand_name'];
		}

		if ( isset( $context['send_brand_voice'] ) && $context['send_brand_voice'] && ! empty( $context['brand_voice'] ) ) {
			$parts[] = "Brand voice: " . $context['brand_voice'];
		}

		if ( isset( $context['send_faq'] ) && $context['send_faq'] && ! empty( $context['faq'] ) ) {
			$parts[] = "FAQs: " . $context['faq'];
		}

		if ( isset( $context['send_policies'] ) && $context['send_policies'] && ! empty( $context['policies'] ) ) {
			$parts[] = "Policies: " . $context['policies'];
		}

		if ( isset( $context['send_invoice_terms'] ) && $context['send_invoice_terms'] && ! empty( $context['invoice_terms'] ) ) {
			$parts[] = "Invoice terms: " . $context['invoice_terms'];
		}

		if ( isset( $context['send_contact_info'] ) && $context['send_contact_info'] && ! empty( $context['contact_info'] ) ) {
			$parts[] = "Contact info: " . $context['contact_info'];
		}

		$parts[] = "Be concise, professional, and friendly.";

		return implode( "\n\n", $parts );
	}
}

