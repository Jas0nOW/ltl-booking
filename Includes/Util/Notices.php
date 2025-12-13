<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Notices {

    const TRANSIENT = 'ltlb_admin_notices';

    public static function add( string $message, string $type = 'success' ): void {
        $notes = get_transient( self::TRANSIENT );
        if ( ! is_array( $notes ) ) $notes = [];
        $notes[] = [ 'message' => $message, 'type' => $type ];
        set_transient( self::TRANSIENT, $notes, 30 );
    }

    public static function render(): void {
        $notes = get_transient( self::TRANSIENT );
        if ( ! is_array( $notes ) || empty( $notes ) ) {
            // Also support legacy ?message=... query params for backward compat
            if ( isset( $_GET['message'] ) ) {
                $msg = sanitize_text_field( wp_unslash( $_GET['message'] ) );
                $map = [
                    'saved' => [ 'Service saved.', 'success' ],
                    'error' => [ 'An error occurred.', 'error' ],
                    'status_changed' => [ 'Status updated.', 'success' ],
                ];
                if ( isset( $map[ $msg ] ) ) {
                    echo '<div class="notice notice-' . esc_attr( $map[$msg][1] ) . ' is-dismissible"><p>' . esc_html( $map[$msg][0] ) . '</p></div>';
                }
            }
            return;
        }

        foreach ( $notes as $n ) {
            $t = isset( $n['type'] ) ? $n['type'] : 'success';
            $m = isset( $n['message'] ) ? $n['message'] : '';
            echo '<div class="notice notice-' . esc_attr( $t ) . ' is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
        }

        delete_transient( self::TRANSIENT );
    }
}
