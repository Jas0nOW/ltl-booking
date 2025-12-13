Commit 1 decisions:

- Sanitizer: implemented `LTLB_Sanitizer` with basic helpers (`text`, `int`, `money_cents`, `email`, `datetime`). Uses WP sanitize helpers where available.
- Loading: For Phase 1 we use explicit `require_once` in `Includes/Core/Plugin.php` instead of an autoloader.
- Table prefix: per SPEC we use `$wpdb->prefix . 'lazy_' . name` (ServiceRepository will read `lazy_services`).
- Staff role `ltlb_staff` added with capabilities to view/edit their own working hours.
- Admin can manage all staff hours.

Commit 2 decisions (Time & storage):

- Time helper: added `LTLB_Time` in `includes/Util/Time.php` providing `wp_timezone()`, `create_datetime_immutable()`, `parse_date_and_time()`, `format_wp_datetime()`, `day_start()/day_end()` and `generate_slots_for_day()`.
- Storage concept: `start_at` and `end_at` are stored in the site timezone as `Y-m-d H:i:s` strings (matching `LTLB_Time::format_wp_datetime`). This keeps DB values consistent with admin views; DST edge-cases are handled by using site timezone during parse/format. Note: this choice simplifies Phase 1 — migrating to UTC storage can be considered later.
- New tables `lazy_staff_hours` and `lazy_staff_exceptions` created for staff working hours and exceptions.
- Schema updated in `DB_SCHEMA.md`.

Commit 3 decisions (Conflict handling & race conditions):

- Blocking statuses: by default only `confirmed` blocks a slot. There's a site option `ltlb_pending_blocks` (boolean) which, when enabled, makes `pending` also block slots. For Phase 1 this option is false by default.
- Double-check before insert: `AppointmentRepository::create()` performs a final `has_conflict()` check immediately before insertion to reduce race conditions.
- Lightweight lock: to further reduce races we use an option-based mutex (`add_option($lock_key)`) keyed by service+start+end. `add_option` is atomic in WP and fails when the option already exists. The option is deleted after insert. This is not a perfect solution (durability/cleanup edge-cases), but reduces concurrent insert races on typical hosts.
- Limitations: Without DB transactions or row-level locking, true atomicity cannot be guaranteed across all hosts. We document this and plan to consider DB transactions / UTC storage / unique constraints in future commits.
- Repository layer for staff hours and exceptions implemented with sanitization and validation.

Commit 4 decisions (Settings):

- Option names chosen:
	- `ltlb_working_hours_start` (int hour, default 9)
	- `ltlb_working_hours_end` (int hour, exclusive, default 17)
	- `ltlb_slot_minutes` (int, default 60)
	- `ltlb_timezone` (string timezone identifier, optional; if empty use site timezone)
	- `ltlb_default_status` (string, default 'pending')
	- `ltlb_pending_blocks` (bool/int, default 0) — when enabled, pending appointments also block slots

- The Shortcode and Time helpers now read these options. Slots are generated using `LTLB_Time::generate_slots_for_day()` with the configured start/end/slot minutes.
- Admin UI for staff management created, allowing assignment of the `ltlb_staff` role.

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
- Working hours editor implemented for staff with nonce verification.

Commit 6 decisions (Design & CSS variables):

- `lazy_design` option stores four hex colors: `background`, `primary`, `text`, `accent`.
- The plugin emits CSS variables `--lazy-bg`, `--lazy-primary`, `--lazy-text`, `--lazy-accent` on the frontend (only when the `[lazy_book]` shortcode is present) and in admin pages under the `ltlb_` menu via `wp_head`/`admin_head` hooks.
- Frontend widgets (wizard/shortcode) use those variables for background, button and text styling; themes can override or extend these variables. This keeps color logic centralized and non-invasive.
- Exception management added for staff with the ability to add/delete exceptions.

Commit 7 decisions:

- Service to staff association implemented with a new table `lazy_service_staff`.

Commit 8 decisions:

- Availability engine upgraded to consider staff hours and exceptions.





