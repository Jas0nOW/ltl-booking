Implemented (0.4.0) Tables and minimal schema

All tables use prefix: `$wpdb->prefix . 'lazy_' . name`

`lazy_services` (implemented):
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(190) NOT NULL
- `description` LONGTEXT NULL
- `duration_min` SMALLINT UNSIGNED NOT NULL DEFAULT 60
- `buffer_before_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0
- `buffer_after_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0
- `price_cents` INT UNSIGNED NOT NULL DEFAULT 0
- `currency` CHAR(3) NOT NULL DEFAULT 'EUR'
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
  (planned) Group booking columns like `is_group`, `max_seats_per_booking` may be added later
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

`lazy_appointments` (implemented):
- `id` BIGINT UNSIGNED PK AI
- `service_id` BIGINT UNSIGNED NOT NULL
- `customer_id` BIGINT UNSIGNED NOT NULL
- `staff_user_id` BIGINT UNSIGNED NULL
- `start_at` DATETIME NOT NULL
- `end_at` DATETIME NOT NULL
- `status` VARCHAR(20) NOT NULL DEFAULT 'pending'
- `timezone` VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin'
  (planned) Group booking column `seats` may be added later
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

`lazy_resources` (implemented):
- `id` BIGINT UNSIGNED PK AI
- `name` VARCHAR(190) NOT NULL
- `capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 1
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

`lazy_service_resources` (implemented junction):
- `service_id` BIGINT UNSIGNED NOT NULL
- `resource_id` BIGINT UNSIGNED NOT NULL
- PRIMARY KEY (service_id,resource_id)
- KEY resource_id (resource_id)

`lazy_appointment_resources` (implemented junction):
- `appointment_id` BIGINT UNSIGNED NOT NULL
- `resource_id` BIGINT UNSIGNED NOT NULL
- PRIMARY KEY (appointment_id,resource_id)
- KEY resource_id (resource_id)

## Planned (Hotel Mode) Schema Notes

**No new tables required.** Hotel mode reuses existing schema with different semantics:

- **lazy_services**: Room Types (e.g., "Double Room", "Suite")
  - `is_group` and `max_seats_per_booking` remain unused in hotel mode
  - `price_cents` = nightly rate (e.g., 10000 = 100.00 EUR/night)

- **lazy_resources**: Rooms (e.g., "Room 101", "Room 102")
  - `capacity` = max guests per room (e.g., 2 for double room, 4 for family room)
  - Hotel mode validates available rooms by: `SUM(seats for overlapping bookings) + new_guests <= capacity`

- **lazy_appointments**: Hotel bookings reuse appointment table
  - `start_at` = check-in date at hotel_checkin_time (e.g., 2025-12-20 15:00)
  - `end_at` = check-out date at hotel_checkout_time (e.g., 2025-12-22 11:00)
  - `seats` = number of guests for this booking (e.g., 2)
  - Check-out is exclusive: if Room 101 booked with checkout 2025-12-22, next booking can start 2025-12-22 (no overlap)

- **lazy_service_resources**: Room Type → Rooms mapping (unchanged)
  - Service (Room Type) can map to multiple Resources (Rooms)
  - Example: Service "Double Room" maps to Resource "Room 101" and "Room 102"

- **lazy_appointment_resources**: Booking → Assigned Room (unchanged)
  - Tracks which room is assigned to a booking
  - Example: Appointment 42 assigned to Resource 5 (Room 102)

## Phase 4 Hotel Mode Settings

Settings stored in wp_options (lazy_settings):
- `hotel_checkin_time` (string, format HH:mm, e.g., "15:00")
- `hotel_checkout_time` (string, format HH:mm, e.g., "11:00")
- `hotel_min_nights` (int, minimum booking length, e.g., 1)
- `hotel_max_nights` (int, maximum booking length, e.g., 30)
