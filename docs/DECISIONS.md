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





