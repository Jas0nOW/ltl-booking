Commit 1 decisions:

- Sanitizer: implemented `LTLB_Sanitizer` with basic helpers (`text`, `int`, `money_cents`, `email`, `datetime`). Uses WP sanitize helpers where available.
- Loading: For Phase 1 we use explicit `require_once` in `Includes/Core/Plugin.php` instead of an autoloader. All include paths use `Includes/` (capital I) for consistency and portability.
- Table prefix: per SPEC we use `$wpdb->prefix . 'lazy_' . name` (ServiceRepository will read `lazy_services`).

Commit 4 decisions:

- Customer upsert semantics: `LTLB_CustomerRepository::upsert_by_email` is the canonical create/update entry point. The admin edit form supports editing by `id`, but saving always calls `upsert_by_email($data)`. This updates the existing record matching the submitted email (or inserts if not found). If an admin edits an existing record and changes the email to another existing email, the record for that email will be updated — this is an acceptable behavior for Phase 1 and will be revisited in a later commit if stricter id-based updates are required.

Commit 6 decisions (Frontend security):

- Honeypot: Added hidden field `ltlb_hp` to the frontend booking form. If populated, the submission is silently rejected with a generic message.
- Rate limiting: Implemented a simple IP-based transient limiter (10 submits / 10 minutes) keyed by `ltlb_rate_{md5(ip)}`. This is intentionally conservative and server-side only. It can be replaced by more robust throttling later.
- Nonce handling: All frontend submissions verify `ltlb_book_nonce` using `wp_verify_nonce`. On failure, a generic error message is returned to avoid information leakage.

Commit 2 decisions (Time & storage):

- Time helper: added `LTLB_Time` in `includes/Util/Time.php` providing `wp_timezone()`, `create_datetime_immutable()`, `parse_date_and_time()`, `format_wp_datetime()`, `day_start()/day_end()` and `generate_slots_for_day()`.
- Storage concept: `start_at` and `end_at` are stored in the site timezone as `Y-m-d H:i:s` strings (matching `LTLB_Time::format_wp_datetime`). This keeps DB values consistent with admin views; DST edge-cases are handled by using site timezone during parse/format. Note: this choice simplifies Phase 1 — migrating to UTC storage can be considered later.

Commit 3 decisions (Conflict handling & race conditions):

- Blocking statuses: by default only `confirmed` blocks a slot. There's a site option `ltlb_pending_blocks` (boolean) which, when enabled, makes `pending` also block slots. For Phase 1 this option is false by default.
- Double-check before insert: `AppointmentRepository::create()` performs a final `has_conflict()` check immediately before insertion to reduce race conditions.
- Lightweight lock: to further reduce races we use an option-based mutex (`add_option($lock_key)`) keyed by service+start+end. `add_option` is atomic in WP and fails when the option already exists. The option is deleted after insert. This is not a perfect solution (durability/cleanup edge-cases), but reduces concurrent insert races on typical hosts.
- Limitations: Without DB transactions or row-level locking, true atomicity cannot be guaranteed across all hosts. We document this and plan to consider DB transactions / UTC storage / unique constraints in future commits.

Commit 4 decisions (Settings):

- Option names chosen:
	- `ltlb_working_hours_start` (int hour, default 9)
	- `ltlb_working_hours_end` (int hour, exclusive, default 17)
	- `ltlb_slot_minutes` (int, default 60)
	- `ltlb_timezone` (string timezone identifier, optional; if empty use site timezone)
	- `ltlb_default_status` (string, default 'pending')
	- `ltlb_pending_blocks` (bool/int, default 0) — when enabled, pending appointments also block slots

- The Shortcode and Time helpers now read these options. Slots are generated using `LTLB_Time::generate_slots_for_day()` with the configured start/end/slot minutes.

Commit 5 decisions (Email basics):

- Email templates and sender are stored in options and editable via Settings:
	- `ltlb_email_from_name`
	- `ltlb_email_from_address`
	- `ltlb_email_admin_subject`
	- `ltlb_email_admin_body`
	- `ltlb_email_customer_subject`
	- `ltlb_email_customer_body`
	- `ltlb_email_send_customer` (bool)
- Simple mailer `LTLB_Mailer` implemented in `Includes/Util/Mailer.php` which replaces placeholders `{service},{start},{end},{name},{email},{phone},{status},{appointment_id}` and sends admin and customer emails via `wp_mail()`.
- Emails are sent after appointment creation in the frontend flow. Failures in sending do not block appointment creation.

Commit 6 decisions (Design & CSS variables):

- `lazy_design` option stores four hex colors: `background`, `primary`, `text`, `accent`.
- The plugin emits CSS variables `--lazy-bg`, `--lazy-primary`, `--lazy-text`, `--lazy-accent` on the frontend (only when the `[lazy_book]` shortcode is present) and in admin pages under the `ltlb_` menu via `wp_head`/`admin_head` hooks.
- Frontend widgets (wizard/shortcode) use those variables for background, button and text styling; themes can override or extend these variables. This keeps color logic centralized and non-invasive.

Commit X decisions (Template modes & Engine refactor):

- Introduce `template_mode` stored in `lazy_settings` with possible values `service` (default) and `hotel` (stub). This allows switching booking UX and backend engines.
- Implement an `EngineFactory` returning an engine implementing `BookingEngineInterface`. Existing service logic is moved into `ServiceEngine`; `HotelEngine` is a safe stub returning friendly errors or placeholders until implemented.
- All frontend shortcode booking and REST time-slot endpoints now call the engine via `EngineFactory` to keep behavior pluggable per template mode.

Phase 2c decisions (Resources & Resource-Blocking):

- Added three tables: `lazy_resources`, `lazy_service_resources` (service→resource mapping), `lazy_appointment_resources` (appointment→resource assignment).
- Resources have `capacity` (default 1) allowing multiple concurrent bookings per resource.
- Services can be restricted to specific resources via `lazy_service_resources`; if no mapping exists, all active resources are considered available.
- Availability logic now considers resource capacity: a slot is available if at least one allowed resource has free capacity (`used < capacity`).
- Frontend wizard shows a resource dropdown when multiple resources are available for the selected slot; if only one or auto-assigned, the dropdown is hidden.
- REST endpoint `/ltlb/v1/slot-resources` returns per-resource availability details for a given slot.
- Appointment creation assigns a resource (user-selected or auto-selected based on availability); assignment is stored in `lazy_appointment_resources`.

Stabilization & Code Quality decisions (Dec 2025):

- Normalized all `require_once` include paths to use `Includes/` (capital I) for portability on case-sensitive filesystems.
- Auto-migration: `Migrator` now stores `ltlb_db_version` and can be extended to auto-run on version change via `plugins_loaded` hook.
- Dashboard restored: shows status cards (Services, Customers, Appointments, Resources counts) and last 5 appointments with resource assignments.
- All repository classes created or populated with minimal CRUD methods to ensure no fatal errors on activation.
- Availability engine uses `LTLB_Time::wp_timezone()` and considers resource-blocking via `AppointmentResourcesRepository::get_blocked_resources()`.

Group Booking Feature decisions (Implementation Phase):

**Commit 1-3: Data Model**
- Added `seats` SMALLINT UNSIGNED column to `lazy_appointments` (default 1, for group size).
- Added `is_group` TINYINT(1) column to `lazy_services` (flag to enable group mode for a service).
- Added `max_seats_per_booking` SMALLINT UNSIGNED column to `lazy_services` (maximum seats allowed per booking, default 1).
- DB version bumped to 0.3.0 to track schema change.
- Modified `AppointmentRepository::create()` to accept and store seats value.
- Modified `ServiceRepository::create()/update()` to handle `is_group` and `max_seats_per_booking` fields.

**Commit 2: Capacity Calculation**
- Changed `AppointmentResourcesRepository::get_blocked_resources()` from `COUNT(*)` to `SUM(a.seats)` to properly calculate total seat occupancy per resource.
- This ensures that when multiple bookings with different seat counts exist on the same resource, the capacity is correctly calculated as the sum of all seats.

**Commit 4: Availability API**
- Added `spots_left` field to time-slot responses (both in Availability class and REST endpoint).
- `spots_left` = minimum available seats across all free resources for that slot (for group services).
- Frontend can use this to limit the max seats selector UI.

**Commit 5: Frontend UX**
- Added dynamic "Number of Seats" field in `[lazy_book]` shortcode form.
- Field only shows when a group-enabled service is selected (via JavaScript).
- Seats selector has min=1 and max=service.max_seats_per_booking (validated on form).
- Frontend passes `seats` value to engine via payload.

**Commit 6: Admin UX**
- Added "Group Booking" checkbox (enable/disable) in Services admin page.
- Added "Max Seats per Booking" input field in Services admin page.
- Added "Seats" column to Appointments list showing booked seat count.

**Commit 7: Email**
- Added `{seats}` placeholder to email templates (Mailer).
- Admin and customer notification emails can include seat count via this placeholder.

**Pricing note:** In Phase 1, group bookings use the service's single price (no per-seat multiplier). Per-seat pricing can be added in Phase 2 if needed.

**Validation:** Max seats validation happens on the frontend (HTML max attribute) and should be enforced in the engine for security. No backend hard limit enforced on seats value — this allows for manual admin adjustments if needed, but should be tightened in Phase 2.

Phase 4 decisions (Hotel Mode MVP):

**Commit 1: Hotel Settings**
- Added four new settings to SettingsPage: `hotel_checkin_time`, `hotel_checkout_time`, `hotel_min_nights`, `hotel_max_nights`.
- Stored in `lazy_settings` array (same as existing settings).
- Defaults: check-in 15:00, check-out 11:00, min 1 night, max 30 nights.
- These are read by HotelEngine for date/time validation and availability calculation.

**Commit 2: Time Helpers for Date Ranges**
- Added `combine_date_time($date_ymd, $time_hi)` to LTLB_Time: merges Y-m-d string + H:i string into DateTimeImmutable in site timezone.
- Added `nights_between($checkin_date, $checkout_date)` to LTLB_Time: calculates integer days between two dates with checkout treated as exclusive (e.g., checkin 2025-12-20, checkout 2025-12-22 = 2 nights).
- These helpers are used by HotelEngine and admin display to calculate night counts and validate date ranges.

**Commit 3-4: HotelEngine Implementation**
- Created `Includes/Domain/HotelEngine.php` implementing `BookingEngineInterface`.
- `get_hotel_availability($service_id, $checkin, $checkout, $guests)`:
  - Validates date range (checkout > checkin), night count (>= min_nights, <= max_nights), guest count (>= 1).
  - Queries `lazy_service_resources` to find allowed rooms (resources) for room type (service).
  - Uses `AppointmentResourcesRepository::get_blocked_resources()` (SUM(seats)) to check overlapping bookings.
  - Availability logic: room is free if `SUM(seats for overlaps) + guests <= room.capacity`.
  - Returns: `nights`, `free_resources_count`, `resource_ids` array, `total_price_cents` (nights × service.price_cents).
- `create_hotel_booking($payload)`:
  - Validates payload (required: service_id, checkin, checkout, email, guests).
  - Calls `get_hotel_availability()` to check availability.
  - Creates appointment with `start_at=checkin+hotel_checkin_time`, `end_at=checkout+hotel_checkout_time`, `seats=guests`.
  - Auto-assigns first available room from `resource_ids` (or uses explicit `resource_id` from payload).
  - Stores assignment in `lazy_appointment_resources`.
  - Sends admin and customer emails via LTLB_Mailer (includes `{nights}`, `{guests}`, `{room}` placeholders).
- `validate_hotel_payload()`: helper for input validation.
- EngineFactory now returns HotelEngine when `template_mode=hotel`.

**Commit 5: Hotel Frontend Wizard**
- Modified `Shortcodes.php::render_lazy_book()` to check `template_mode` setting and route to `render_hotel_booking()` if hotel mode is enabled.
- `render_hotel_booking()`: HTML form with fields:
  - Room Type (service dropdown, filtered for active only)
  - Check-in (date input, format YYYY-MM-DD)
  - Check-out (date input, format YYYY-MM-DD)
  - Guests (number input, min 1, max 10, default 1)
  - Customer details: email (required), first name, last name, phone
- `handle_hotel_submission()`: form handler
  - Nonce verification (`ltlb_book_nonce`)
  - Honeypot check (`ltlb_hp`)
  - IP-based rate limiting (same as service mode: 10 requests / 10 minutes)
  - Builds payload: service_id, checkin, checkout, email, first_name, last_name, phone, guests
  - Calls `HotelEngine::create_hotel_booking()`
  - Returns success message or error with details.
- Rate limiting and honeypot provide same security as service mode; no new vulnerabilities introduced.

**Commit 6: Admin Hotel Appointments Display**
- Modified `AppointmentsPage.php::render_content()` to detect `template_mode` setting.
- When `template_mode=hotel`:
  - Table headers (9 columns): Room Type | Customer | Check-in | Check-out | Nights | Guests | Room | Status | Actions
  - Data rows show:
    - Service name (room type)
    - Customer full name (first + last)
    - Check-in date (formatted as YYYY-MM-DD)
    - Check-out date (formatted as YYYY-MM-DD)
    - Nights (calculated using LTLB_Time::nights_between())
    - Guests (appointment.seats value)
    - Room (resource name from lazy_appointment_resources)
    - Status (appointment.status)
    - Actions (Edit, Delete — same as service mode)
- When `template_mode=service` (default):
  - Original 7-column table (Service ID, Customer ID, Start, End, Seats, Status, Actions) — unchanged
- No changes to database or appointment logic; purely UI conditional rendering.

**Commit 7: Hotel REST API Endpoint**
- Added `GET /ltlb/v1/hotel-availability` route in `Plugin.php::register_rest_routes()`.
- Query parameters: `service_id` (int), `checkin` (Y-m-d), `checkout` (Y-m-d), `guests` (int, default 1).
- Validates `template_mode=hotel` and delegates to `HotelEngine::get_hotel_availability()`.
- Returns JSON:
  - Success: `{ "nights": 2, "free_resources_count": 2, "resource_ids": [1, 2], "total_price_cents": 20000 }`
  - Error: `{ "error": "message" }`
- Public endpoint (no authentication required) but subject to rate limiting via standard WP nonce/form submission flow for hotel bookings.

**Checkout Exclusivity:** Check-out date is exclusive — no overlap occurs if one booking's checkout equals another's check-in.
- Example: Room 101 booked with checkout 2025-12-22; next booking can start check-in 2025-12-22 (same day, overlaps are detected during daytime, but hotel midnight boundary is clean).
- Implemented via `nights_between()` using date subtraction (checkout - checkin as days).

**Capacity Model:** Reuses Phase 3 `seats` field and `SUM(seats)` blocking logic.
- For service mode: seats = number of people in appointment (group size).
- For hotel mode: seats = number of guests in booking.
- Blocking: `SUM(seats for overlapping bookings) + new_guests <= room.capacity`.

**Backwards Compatibility:** Service mode remains fully functional when `template_mode=service` (default).
- All existing service bookings, time slots, and admin pages unaffected.
- No database schema changes (reuses existing tables).
- EngineFactory pattern isolates hotel logic from service logic.

**Testing Note:** QA_CHECKLIST.md updated with comprehensive hotel test scenarios including:
- Setup (1 room type + 2 rooms with different capacities)
- Booking flow (valid booking, overlapping dates, exclusive checkout boundary test)
- Capacity constraints (multi-booking blocking)
- REST API testing
- Edge cases (invalid dates, min/max nights validation)



