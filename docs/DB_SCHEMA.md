(Phase 1) Tables and minimal schema

All tables use prefix: `$wpdb->prefix . 'lazy_' . name`

`lazy_services`:
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(190) NOT NULL
- `description` LONGTEXT NULL
- `duration_min` SMALLINT UNSIGNED NOT NULL DEFAULT 60
- `buffer_before_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0
- `buffer_after_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0
- `price_cents` INT UNSIGNED NOT NULL DEFAULT 0
- `currency` CHAR(3) NOT NULL DEFAULT 'EUR'
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

`lazy_customers`:
- `id` BIGINT UNSIGNED PK AI
- `email` VARCHAR(190) NOT NULL UNIQUE
- `first_name` VARCHAR(100) NULL
- `last_name` VARCHAR(100) NULL
- `phone` VARCHAR(50) NULL
- `notes` LONGTEXT NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

`lazy_appointments`:
- `id` BIGINT UNSIGNED PK AI
- `service_id` BIGINT UNSIGNED NOT NULL
- `customer_id` BIGINT UNSIGNED NOT NULL
- `staff_user_id` BIGINT UNSIGNED NULL
- `start_at` DATETIME NOT NULL
- `end_at` DATETIME NOT NULL
- `status` VARCHAR(20) NOT NULL DEFAULT 'pending'
- `timezone` VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin'
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

`lazy_resources`:
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(190) NOT NULL
- `capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 1
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

`lazy_service_resources` (mapping table):
- `id` BIGINT UNSIGNED PK AI
- `service_id` BIGINT UNSIGNED NOT NULL
- `resource_id` BIGINT UNSIGNED NOT NULL
- `created_at` DATETIME NOT NULL
- KEY service_id, resource_id

`lazy_appointment_resources` (mapping table):
- `id` BIGINT UNSIGNED PK AI
- `appointment_id` BIGINT UNSIGNED NOT NULL
- `resource_id` BIGINT UNSIGNED NOT NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL
- KEY appointment_id, resource_id

