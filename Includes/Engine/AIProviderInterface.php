<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * AI Provider Interface
 * 
 * Abstract contract for AI providers (Gemini, Claude, etc.)
 */
interface LTLB_AI_Provider {

	/**
	 * Test API connection
	 * 
	 * @return array {
	 *   'success' => bool,
	 *   'message' => string,
	 *   'timestamp' => string
	 * }
	 */
	public function test_connection(): array;

	/**
	 * Generate text from prompt + context
	 *
	 * @param string $prompt
	 * @param array $context (optional fields like brand_voice, faq, etc.)
	 * @return string Generated text or empty on error
	 */
	public function generate_text( string $prompt, array $context = [] ): string;

	/**
	 * Get model info
	 *
	 * @return array {
	 *   'name' => string,
	 *   'version' => string,
	 *   'max_tokens' => int
	 * }
	 */
	public function get_model_info(): array;
}

