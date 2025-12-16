<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_DB_Schema {

    public static function get_create_table_sql(string $table_name, string $type): string {
        // Einheitliches Charset/Collation korrekt aus WP holen
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        if ($type === 'services') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                description LONGTEXT NULL,
                staff_user_id BIGINT UNSIGNED NULL,
                duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
                buffer_before_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                buffer_after_min SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                price_cents INT UNSIGNED NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_group TINYINT(1) NOT NULL DEFAULT 0,
                max_seats_per_booking SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                availability_mode VARCHAR(20) NOT NULL DEFAULT 'window',
                available_weekdays VARCHAR(20) NULL,
                available_start_time TIME NULL,
                available_end_time TIME NULL,
                fixed_weekly_slots LONGTEXT NULL,
                beds_type VARCHAR(50) NULL,
                amenities LONGTEXT NULL,
                max_adults SMALLINT UNSIGNED NOT NULL DEFAULT 2,
                max_children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY is_active (is_active),
                KEY staff_user_id (staff_user_id)
            ) {$charset_collate};";
        }

        if ($type === 'customers') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(190) NOT NULL,
                first_name VARCHAR(100) NULL,
                last_name VARCHAR(100) NULL,
                phone VARCHAR(50) NULL,
                notes LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY email (email)
            ) {$charset_collate};";
        }

        if ($type === 'appointments') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                staff_user_id BIGINT UNSIGNED NULL,
                start_at DATETIME NOT NULL,
                end_at DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                payment_status VARCHAR(20) NOT NULL DEFAULT 'free',
                payment_method VARCHAR(32) NOT NULL DEFAULT 'none',
                payment_ref VARCHAR(190) NULL,
                paid_at DATETIME NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
                seats SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY service_id (service_id),
                KEY customer_id (customer_id),
                KEY start_at (start_at),
                KEY status (status),
                KEY status_start (status, start_at),
                KEY end_at (end_at),
                KEY time_range (start_at, end_at)
            ) {$charset_collate};";
        }

        if ($type === 'staff_hours') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                weekday TINYINT NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                is_active TINYINT(1) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY user_weekday (user_id, weekday)
            ) {$charset_collate};";
        }

        if ($type === 'staff_exceptions') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                date DATE NOT NULL,
                is_off_day TINYINT(1) NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                note TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY user_date (user_id, date)
            ) {$charset_collate};";
        }

        if ($type === 'resources') {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                description LONGTEXT NULL,
                capacity INT UNSIGNED NOT NULL DEFAULT 1,
                cost_per_night_cents INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY is_active (is_active)
            ) {$charset_collate};";
        }

        if ($type === 'appointment_resources') {
            return "CREATE TABLE {$table_name} (
                appointment_id BIGINT UNSIGNED NOT NULL,
                resource_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (appointment_id,resource_id),
                KEY resource_id (resource_id)
            ) {$charset_collate};";
        }

        if ($type === 'service_resources') {
            return "CREATE TABLE {$table_name} (
                service_id BIGINT UNSIGNED NOT NULL,
                resource_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (service_id,resource_id),
                KEY resource_id (resource_id)
            ) {$charset_collate};";
        }

        if ( $type === 'ai_actions' ) {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                ai_input LONGTEXT NULL,
                ai_output LONGTEXT NULL,
                final_state LONGTEXT NULL,
                metadata LONGTEXT NULL,
                notes TEXT NULL,
                approved_by BIGINT UNSIGNED NULL,
                approved_at DATETIME NULL,
                executed_at DATETIME NULL,
                failed_at DATETIME NULL,
                error_message TEXT NULL,
                PRIMARY KEY  (id),
                KEY status (status),
                KEY action_type (action_type),
                KEY created_at (created_at)
            ) {$charset_collate};";
        }

        if ( $type === 'payment_schedule' ) {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                appointment_id BIGINT UNSIGNED NOT NULL,
                sequence SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                due_date DATETIME NULL,
                payment_type VARCHAR(20) NOT NULL DEFAULT 'full',
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                paid_at DATETIME NULL,
                payment_method VARCHAR(32) NULL,
                transaction_ref VARCHAR(190) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY appointment_id (appointment_id),
                KEY status (status),
                KEY due_date (due_date),
                KEY appointment_status (appointment_id, status)
            ) {$charset_collate};";
        }

        if ( $type === 'coupons' ) {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(50) NOT NULL,
                description VARCHAR(255) NULL,
                discount_type VARCHAR(10) NOT NULL DEFAULT 'percent',
                discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
                max_discount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                min_spend_cents INT UNSIGNED NOT NULL DEFAULT 0,
                valid_from DATETIME NULL,
                valid_until DATETIME NULL,
                usage_limit INT UNSIGNED NOT NULL DEFAULT 0,
                usage_count INT UNSIGNED NOT NULL DEFAULT 0,
                usage_limit_per_customer INT UNSIGNED NOT NULL DEFAULT 0,
                service_ids TEXT NULL,
                room_ids TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY code (code),
                KEY is_active (is_active),
                KEY valid_until (valid_until)
            ) {$charset_collate};";
        }

        if ( $type === 'coupon_usage' ) {
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                coupon_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                appointment_id BIGINT UNSIGNED NULL,
                used_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY coupon_id (coupon_id),
                KEY customer_id (customer_id),
                KEY coupon_customer (coupon_id, customer_id)
            ) {$charset_collate};";
        }

        // Cancellation log
        if ( $table === 'cancellation_log' ) {
            $table_name = $wpdb->prefix . 'ltlb_cancellation_log';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                appointment_id BIGINT UNSIGNED NOT NULL,
                cancelled_at DATETIME NOT NULL,
                reason TEXT,
                fee_cents INT NOT NULL DEFAULT 0,
                refund_cents INT NOT NULL DEFAULT 0,
                hours_until_booking DECIMAL(10,2) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY appointment_id (appointment_id),
                KEY cancelled_at (cancelled_at)
            ) {$charset_collate};";
        }

        // Reschedule log
        if ( $table === 'reschedule_log' ) {
            $table_name = $wpdb->prefix . 'ltlb_reschedule_log';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                appointment_id BIGINT UNSIGNED NOT NULL,
                old_start DATETIME NOT NULL,
                new_start DATETIME NOT NULL,
                rescheduled_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY appointment_id (appointment_id),
                KEY rescheduled_at (rescheduled_at)
            ) {$charset_collate};";
        }

        // Booking policies (per service/room)
        if ( $table === 'booking_policies' ) {
            $table_name = $wpdb->prefix . 'ltlb_booking_policies';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(20) NOT NULL DEFAULT 'service',
                entity_id BIGINT UNSIGNED NOT NULL,
                free_cancellation_hours INT NOT NULL DEFAULT 24,
                cancellation_fee_percent INT NOT NULL DEFAULT 50,
                no_show_fee_percent INT NOT NULL DEFAULT 100,
                refund_window_days INT NOT NULL DEFAULT 14,
                max_reschedules INT NOT NULL DEFAULT 2,
                min_notice_hours INT NOT NULL DEFAULT 12,
                reschedule_window_days INT NOT NULL DEFAULT 30,
                reschedule_fee_cents INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY entity_policy (entity_type, entity_id),
                KEY entity_id (entity_id)
            ) {$charset_collate};";
        }

        // Locations (multi-location support)
        if ( $table === 'locations' ) {
            $table_name = $wpdb->prefix . 'ltlb_locations';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                address TEXT,
                city VARCHAR(100),
                state VARCHAR(100),
                zip VARCHAR(20),
                country VARCHAR(100),
                phone VARCHAR(50),
                email VARCHAR(255),
                timezone VARCHAR(100) NOT NULL DEFAULT 'UTC',
                opening_hours TEXT,
                tax_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
                currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
                branding TEXT,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY is_active (is_active),
                KEY sort_order (sort_order)
            ) {$charset_collate};";
        }

        // Location-Staff assignments
        if ( $table === 'location_staff' ) {
            $table_name = $wpdb->prefix . 'ltlb_location_staff';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                location_id BIGINT UNSIGNED NOT NULL,
                staff_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY location_staff (location_id, staff_id),
                KEY location_id (location_id),
                KEY staff_id (staff_id)
            ) {$charset_collate};";
        }

        // Location-Service assignments
        if ( $table === 'location_services' ) {
            $table_name = $wpdb->prefix . 'ltlb_location_services';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                location_id BIGINT UNSIGNED NOT NULL,
                service_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY location_service (location_id, service_id),
                KEY location_id (location_id),
                KEY service_id (service_id)
            ) {$charset_collate};";
        }

        // Staff capacity settings
        if ( $table === 'staff_capacity' ) {
            $table_name = $wpdb->prefix . 'ltlb_staff_capacity';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                staff_id BIGINT UNSIGNED NOT NULL,
                service_id BIGINT UNSIGNED NULL,
                max_concurrent INT NOT NULL DEFAULT 1,
                buffer_minutes INT NOT NULL DEFAULT 15,
                requires_resource TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY staff_service_capacity (staff_id, service_id),
                KEY staff_id (staff_id)
            ) {$charset_collate};";
        }

        // Invoices
        if ( $table === 'invoices' ) {
            $table_name = $wpdb->prefix . 'ltlb_invoices';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                appointment_id BIGINT UNSIGNED NOT NULL,
                invoice_number VARCHAR(50) NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                issue_date DATETIME NOT NULL,
                due_date DATETIME NOT NULL,
                subtotal_cents INT NOT NULL,
                tax_cents INT NOT NULL,
                discount_cents INT NOT NULL DEFAULT 0,
                total_cents INT NOT NULL,
                currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                pdf_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY invoice_number (invoice_number),
                KEY appointment_id (appointment_id),
                KEY customer_id (customer_id),
                KEY status (status)
            ) {$charset_collate};";
        }

        // Form fields (custom fields builder)
        if ( $table === 'form_fields' ) {
            $table_name = $wpdb->prefix . 'ltlb_form_fields';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                label VARCHAR(255) NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                type VARCHAR(50) NOT NULL,
                placeholder VARCHAR(255),
                help_text TEXT,
                default_value TEXT,
                options TEXT,
                validation TEXT,
                conditions TEXT,
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                service_id BIGINT UNSIGNED NULL,
                mode VARCHAR(20) NULL,
                sort_order INT NOT NULL DEFAULT 999,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY field_key (field_key),
                KEY service_id (service_id),
                KEY mode (mode),
                KEY sort_order (sort_order)
            ) {$charset_collate};";
        }

        // Appointment field values
        if ( $table === 'appointment_fields' ) {
            $table_name = $wpdb->prefix . 'ltlb_appointment_fields';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                appointment_id BIGINT UNSIGNED NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                field_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY appointment_id (appointment_id),
                KEY field_key (field_key)
            ) {$charset_collate};";
        }

        // Webhooks
        if ( $table === 'webhooks' ) {
            $table_name = $wpdb->prefix . 'ltlb_webhooks';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event VARCHAR(100) NOT NULL,
                url TEXT NOT NULL,
                secret TEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY event (event),
                KEY status (status)
            ) {$charset_collate};";
        }

        // Webhook delivery logs
        if ( $table === 'webhook_logs' ) {
            $table_name = $wpdb->prefix . 'ltlb_webhook_logs';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                webhook_id BIGINT UNSIGNED NOT NULL,
                status_code INT,
                response_body TEXT,
                attempt TINYINT NOT NULL DEFAULT 1,
                delivered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY webhook_id (webhook_id),
                KEY delivered_at (delivered_at)
            ) {$charset_collate};";
        }

        // Waitlist
        if ( $table === 'waitlist' ) {
            $table_name = $wpdb->prefix . 'ltlb_waitlist';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                preferred_date DATE NOT NULL,
                preferred_time TIME NOT NULL,
                preferences TEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'waiting',
                offer_expires_at DATETIME NULL,
                offered_at DATETIME NULL,
                appointment_id BIGINT UNSIGNED NULL,
                converted_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY service_id (service_id),
                KEY customer_id (customer_id),
                KEY status (status),
                KEY preferred_date (preferred_date)
            ) {$charset_collate};";
        }

        // Group bookings
        if ( $table === 'group_bookings' ) {
            $table_name = $wpdb->prefix . 'ltlb_group_bookings';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id BIGINT UNSIGNED NOT NULL,
                start_time DATETIME NOT NULL,
                participant_count SMALLINT NOT NULL DEFAULT 0,
                metadata TEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
                cancelled_at DATETIME NULL,
                cancel_reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY service_id (service_id),
                KEY start_time (start_time),
                KEY status (status)
            ) {$charset_collate};";
        }

        // Group participants
        if ( $table === 'group_participants' ) {
            $table_name = $wpdb->prefix . 'ltlb_group_participants';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                group_booking_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NULL,
                name VARCHAR(190) NOT NULL,
                email VARCHAR(190),
                phone VARCHAR(50),
                status VARCHAR(20) NOT NULL DEFAULT 'registered',
                checked_in_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY group_booking_id (group_booking_id),
                KEY customer_id (customer_id),
                KEY status (status)
            ) {$charset_collate};";
        }

        // Packages (5er-Karten, subscriptions)
        if ( $table === 'packages' ) {
            $table_name = $wpdb->prefix . 'ltlb_packages';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                customer_id BIGINT UNSIGNED NOT NULL,
                service_id BIGINT UNSIGNED NOT NULL,
                credits_total SMALLINT NOT NULL,
                credits_remaining SMALLINT NOT NULL,
                price INT NOT NULL DEFAULT 0,
                discount_percent TINYINT NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                last_used_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                cancel_reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY customer_id (customer_id),
                KEY service_id (service_id),
                KEY status (status),
                KEY expires_at (expires_at)
            ) {$charset_collate};";
        }

        // Package usage tracking
        if ( $table === 'package_usage' ) {
            $table_name = $wpdb->prefix . 'ltlb_package_usage';
            return "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                package_id BIGINT UNSIGNED NOT NULL,
                appointment_id BIGINT UNSIGNED NOT NULL,
                credits_used TINYINT NOT NULL DEFAULT 1,
                credits_remaining_after SMALLINT NOT NULL,
                used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY package_id (package_id),
                KEY appointment_id (appointment_id),
                KEY used_at (used_at)
            ) {$charset_collate};";
        }

        return '';
    }
}