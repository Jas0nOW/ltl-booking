<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Activator {

    public static function activate(): void {
        // Register capabilities first
        if ( class_exists('LTLB_Role_Manager') ) {
			LTLB_Role_Manager::register_roles();
            LTLB_Role_Manager::register_capabilities();
        }

        // Tabellen anlegen
        LTLB_DB_Migrator::migrate();

        // Default-Options (nur anlegen, wenn nicht vorhanden)
        add_option('lazy_settings', [
            'timezone' => 'Europe/Berlin',
            // Production defaults
            'logging_enabled' => 0,
            'log_level' => 'error',
            // Reserved flags for future privacy/rate limiting/email tests
            'rate_limit_enabled' => 0,
            'rate_limit_per_minute' => 60,
            'delete_data_on_uninstall' => 0,
			// Retention defaults (0 = disabled)
			'retention_delete_canceled_days' => 0,
			'retention_anonymize_after_days' => 0,
            // AI defaults
            'ai_enabled' => 0,
            'ai_operating_mode' => 'human-in-the-loop',
        ]);

        add_option('lazy_design', [
            // Colors
            'background' => '#FDFCF8',
            'text'       => '#3D3D3D',
            'primary'    => '#A67B5B',
            'primary_hover' => '#8DA399',
            'secondary' => '#A67B5B',
            'secondary_hover' => '#A67B5B',
            'accent'     => '#8DA399',
            'border_color' => '#cccccc',
            'panel_background' => 'transparent',
            'button_text' => '#ffffff',

            // Numeric tokens
            'border_width' => 1,
            'border_radius' => 4,
            'box_shadow_blur' => 4,
            'box_shadow_spread' => 0,
            'transition_duration' => 200,

            // Feature toggles
            'use_gradient' => 0,
            'enable_animations' => 1,
            'auto_button_text' => 1,
            'shadow_container' => 1,
            'shadow_button' => 1,
            'shadow_input' => 0,
            'shadow_card' => 1,

            // Custom CSS
            'custom_css' => '',
        ]);

        // Separate palette for WP admin (colors only). Defaults to the same palette as the frontend.
        add_option('lazy_design_backend', [
            'background' => '#FDFCF8',
            'text'       => '#3D3D3D',
            'primary'    => '#A67B5B',
            'primary_hover' => '#8DA399',
            'secondary' => '#A67B5B',
            'secondary_hover' => '#A67B5B',
            'accent'     => '#8DA399',
            'border_color' => '#cccccc',
            'panel_background' => 'transparent',
            'button_text' => '#ffffff',
        ]);

        // AI Config
        add_option('lazy_ai_config', [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'operating_mode' => 'human-in-the-loop',
            'enabled' => 0,
        ]);

        // Business Context (empty by default, admin fills in)
        add_option('lazy_business_context', [
            'brand_name' => '',
            'brand_voice' => '',
            'faq' => '',
            'policies' => '',
            'invoice_terms' => '',
            'contact_info' => '',
            'send_brand_name' => 0,
            'send_brand_voice' => 1,
            'send_faq' => 1,
            'send_policies' => 1,
            'send_invoice_terms' => 1,
            'send_contact_info' => 0,
        ]);

        // API Keys (autoload: false for security)
        add_option('lazy_api_keys', [], '', 'no');

		// Payment Keys (autoload: false for security)
		add_option('lazy_payment_keys', [], '', 'no');

        // Mail Keys (autoload: false for security)
        add_option('lazy_mail_keys', [], '', 'no');

		// Schedule retention cleanup (daily)
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_event' ) ) {
			if ( ! wp_next_scheduled( 'ltlb_retention_cleanup' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ltlb_retention_cleanup' );
			}
		}
    }
}