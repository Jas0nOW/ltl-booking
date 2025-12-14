# LazyBookings DB Schema (v0.4.4)

Alle Custom Tables nutzen Prefix: `$wpdb->prefix . 'lazy_' . <name>`.
Schema wird per `dbDelta()` gepflegt und bei Versionswechsel automatisch migriert.

Hinweis: WordPress entfernt via `dbDelta()` keine alten Spalten automatisch. Neue Spalten werden hinzugefügt.

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
- `availability_mode` VARCHAR(20) NOT NULL DEFAULT 'window'  (`window` | `fixed`)
- `available_weekdays` VARCHAR(20) NULL  (CSV: `0..6`, 0=Sun)
- `available_start_time` TIME NULL
- `available_end_time` TIME NULL
- `fixed_weekly_slots` LONGTEXT NULL (JSON array, z.B. `[{"weekday":5,"time":"18:00"}]`)

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

## `lazy_resources`

Resource = Raum/Studio/Equipment/Hotelzimmer.

- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(190) NOT NULL
- `description` LONGTEXT NULL
- `capacity` INT UNSIGNED NOT NULL DEFAULT 1
- `cost_per_night_cents` INT UNSIGNED NOT NULL DEFAULT 0
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexes:
- `KEY is_active (is_active)`

---

## Junction Tables

### `lazy_service_resources`
- `service_id` BIGINT UNSIGNED NOT NULL
- `resource_id` BIGINT UNSIGNED NOT NULL
- PRIMARY KEY (`service_id`,`resource_id`)
- KEY `resource_id` (`resource_id`)

### `lazy_appointment_resources`
- `appointment_id` BIGINT UNSIGNED NOT NULL
- `resource_id` BIGINT UNSIGNED NOT NULL
- PRIMARY KEY (`appointment_id`,`resource_id`)
- KEY `resource_id` (`resource_id`)

---

## Staff Tables

### `lazy_staff_hours`
- `id` BIGINT UNSIGNED PK AI
- `user_id` BIGINT UNSIGNED NOT NULL
- `weekday` TINYINT NOT NULL (0=Sun..6=Sat)
- `start_time` TIME NOT NULL
- `end_time` TIME NOT NULL
- `is_active` TINYINT(1) NOT NULL
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

Indexes:
- `KEY user_id (user_id)`
- `KEY user_weekday (user_id, weekday)`

### `lazy_staff_exceptions`
- `id` BIGINT UNSIGNED PK AI
- `user_id` BIGINT UNSIGNED NOT NULL
- `date` DATE NOT NULL
- `is_off_day` TINYINT(1) NOT NULL
- `start_time` TIME NULL
- `end_time` TIME NULL
- `note` TEXT NULL
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
- `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

Indexes:
- `KEY user_id (user_id)`
- `KEY user_date (user_id, date)`
