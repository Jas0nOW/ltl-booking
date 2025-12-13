<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_Validator {

    public static function email(?string $value): bool {
        if ( empty( $value ) ) return false;
        return is_email( $value );
    }

    public static function is_date(?string $value): bool {
        if ( empty( $value ) ) return false;
        return (bool) strtotime( $value );
    }

    public static function required($value): bool {
        return ! empty( $value );
    }
}
