<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Plugin {

    public function run(): void {
        add_action('init', [ $this, 'on_init' ]);
        add_action('admin_menu', [ $this, 'register_admin_menu' ]);
        add_action('rest_api_init', [ $this, 'register_rest_routes' ]);
    }

    public function on_init(): void {
        // Phase 1: Shortcodes/CPT/Assets registrieren
    }

    public function register_admin_menu(): void {
        // Phase 1: Admin-Menü + Seiten
    }

    public function register_rest_routes(): void {
        // Phase 1: REST-Routen registrieren
    }
}