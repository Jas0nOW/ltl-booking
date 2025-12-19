# Database Schema

**Scope:** Custom database table structures, indexes, and migration logic.  
**Non-Scope:** Standard WordPress tables (e.g., `wp_options`, `wp_users`).

---

## `lazy_services`

Service/Kurs bzw. (semantisch) Room Type.

- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(190) NOT NULL
- `description` LONGTEXT NULL
- `staff_user_id` BIGINT UNSIGNED NULL
- `duration_min` SMALLINT UNSIGNED NOT NULL DEFAULT 60
- `buffer_before_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0
- `buffer_after_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0
- `price_cents` INT UNSIGNED NOT NULL DEFAULT 0
- `currency` CHAR(3) NOT NULL DEFAULT 'EUR'
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `is_group` TINYINT(1) NOT NULL DEFAULT 0
- `max_seats_per_booking` SMALLINT UNSIGNED NOT NULL DEFAULT 1

Service Availability:
- `availability_mode` VARCHAR(20) NOT NULL DEFAULT 'window' (`window` | `fixed`)
- `available_weekdays` VARCHAR(20) NULL (CSV: `0..6`, 0=Sun)
- `available_start_time` TIME NULL
- `available_end_time` TIME NULL
- `fixed_weekly_slots` LONGTEXT NULL (JSON: z.B. `[{"weekday":5,"time":"18:00"}]`)

Timestamps:
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexes:
- `KEY is_active (is_active)`
- `KEY staff_user_id (staff_user_id)`

---

## `lazy_customers`

- `id` BIGINT UNSIGNED PK AI
- `email` VARCHAR(190) NOT NULL UNIQUE
- `first_name` VARCHAR(100) NULL
- `last_name` VARCHAR(100) NULL
- `phone` VARCHAR(50) NULL
- `notes` LONGTEXT NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

---

## `lazy_appointments`

Ein Appointment ist eine Buchung mit Zeitspanne.

- `id` BIGINT UNSIGNED PK AI
- `service_id` BIGINT UNSIGNED NOT NULL
- `customer_id` BIGINT UNSIGNED NOT NULL
- `staff_user_id` BIGINT UNSIGNED NULL
- `start_at` DATETIME NOT NULL
- `end_at` DATETIME NOT NULL
- `status` VARCHAR(20) NOT NULL DEFAULT 'pending'
- `amount_cents` INT UNSIGNED NOT NULL DEFAULT 0
- `currency` CHAR(3) NOT NULL DEFAULT 'EUR'
- `payment_status` VARCHAR(20) NOT NULL DEFAULT 'free'
- `payment_method` VARCHAR(32) NOT NULL DEFAULT 'none'
- `payment_ref` VARCHAR(190) NULL
- `paid_at` DATETIME NULL
- `timezone` VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin'
- `seats` SMALLINT UNSIGNED NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexes:
- `KEY service_id (service_id)`
- `KEY customer_id (customer_id)`
- `KEY start_at (start_at)`
- `KEY end_at (end_at)`
- `KEY status (status)`
- `KEY status_start (status, start_at)`
- `KEY time_range (start_at, end_at)`

---

Weitere Tabellen (`lazy_staff_hours`, `lazy_staff_exceptions`, `lazy_resources`, Junction-Tabellen, AI-/Coupons-/Payment-Schedule- und Log-Tabellen) entsprechen dem in `LTLB_DB_Schema` generierten Schema und sind dort technisch dokumentiert.
