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

Commit 9 decisions (Availability slots & API):

- REST API: added `GET /wp-json/ltlb/v1/availability` which accepts `service_id` and `date=YYYY-MM-DD`.
- The endpoint supports returning either raw free intervals per staff or discrete time slots when the `slots` parameter is provided. Use `slot_step` to control the step in minutes (default 15).
- Slot generation: for each staff member, free intervals (respecting weekly hours, exceptions, existing appointments and buffers) are split into candidate start times by `slot_step`. A slot is valid if the full service duration fits within the free interval.
- Returned slot format: `['start' => 'YYYY-MM-DD HH:MM:SS', 'end' => 'YYYY-MM-DD HH:MM:SS']` grouped by staff user ID.
- Permissions: the availability endpoint is public (no auth) for now to allow frontend widgets to fetch available times. If needed, we will add nonce or auth protections later.
- Defaults & assumptions:
	- Default `slot_step` is 15 minutes.
	- Service duration and buffers are taken from `lazy_services` table (`duration_min`, `buffer_before_min`, `buffer_after_min`).
	- Times in DB are stored and compared using the site timezone.

Notes:
- This implementation prioritizes clarity and a workable Phase 2 delivery. Future improvements may include caching computed availability per-day, improving concurrency controls, and returning aggregated availability across staff (e.g., next N slots across all staff sorted by time).
- Tests and QA checks for the availability engine are pending (see TODO list).

## Uninstall & Data Policy

- Default behavior: data is preserved on uninstall. The option `delete_data_on_uninstall` defaults to `0` (disabled) in `lazy_settings`.
- If an admin explicitly enables `delete_data_on_uninstall = 1`, uninstall will drop all custom tables and delete plugin options (`lazy_settings`, `lazy_design`, `ltlb_db_version`).
- Rationale: safer default to avoid accidental data loss; explicit opt-in required for destructive actions.

## Go-Live Tools (WP-CLI + Admin)

**Dev Tools Gating:**
- Seed command (`wp ltlb seed`) is only available when `WP_DEBUG` is true OR `enable_dev_tools=1` setting is explicitly enabled.
- Default: dev tools disabled in production. Rationale: prevent accidental demo data seeding on live sites.

**WP-CLI Commands:**
- `wp ltlb doctor`: Runs system diagnostics (version check, table status, lock support, email config, logging status). Read-only, safe to run anytime.
- `wp ltlb migrate`: Manually triggers database migrations. Safe to call multiple times (uses `dbDelta`).
- `wp ltlb seed`: Creates demo data for service or hotel mode. Gated by dev tools flag.

**Admin UI Enhancements:**
- Diagnostics page: "Run Doctor" button shows inline diagnostics output (read-only).
- CSV Export: Extended with resource name, seats (service mode) or nights/guests (hotel mode), mode-aware column headers.

**Migration Tracking:**
- `ltlb_last_migration_time` option stores timestamp of last migration run for diagnostics/auditing.

Commit 10 decisions (Resource Model):

- An appointment in Phase 2c is associated with exactly one resource (e.g., a specific room or a piece of equipment). The `lazy_appointment_resources` table therefore only contains `appointment_id` and `resource_id`.
- Future enhancements (Phase 4) may allow an appointment to use multiple resources or resources with a capacity greater than one, which would require changes to this table and the availability logic. For now, we are keeping it simple.

Commit 11 decisions (Service ↔ Resource mapping):

- Services can be mapped to a set of allowed resources via the `lazy_service_resources` table. If a service has no mappings, it is considered compatible with any resource. When computing availability for a service, only resources allowed for that service are considered for blocking.

Migration decisions (Commit 2c.1 - auto-migrate):

- The plugin runs lightweight migration checks on `plugins_loaded` via `LTLB_DB_Migrator::maybe_migrate()`. This compares stored `ltlb_db_version` against `LTLB_VERSION` and runs `migrate()` if the stored version is older.
- `LTLB_Activator::activate()` continues to run a full `migrate()` during activation to ensure fresh installs create tables immediately.
- `maybe_migrate()` is designed to be safe on frontend requests: it only compares versions and runs migrations when needed; migration failures are logged to PHP error log and do not fatal-error the page.

## Phase 4.1 - Production Readiness (Commits 1-9)

**Commit 1: Health/Diagnostics (DiagnosticsPage.php)**
- New admin page showing system info: WP/PHP versions, database prefix, template mode, DB version, plugin version
- Database statistics: counts for services, customers, appointments, resources
- Table status check: verifies all 6 core tables exist and shows row counts
- Manual "Run Migrations" button for admin troubleshooting (calls LTLB_DB_Migrator::migrate() with nonce protection)
- Helps diagnose activation issues and provides visibility into database state

**Commit 2: Named Lock Protection (LockManager.php)**
- Implemented MySQL `GET_LOCK()` / `RELEASE_LOCK()` based locking to prevent race conditions during booking creation
- Lock timeout set to 3 seconds (configurable via `LOCK_TIMEOUT` constant)
- Lock keys use MD5 hash of service_id + start_at + resource_id (max 64 chars for MySQL compatibility)
- Graceful fallback: if lock acquisition fails (timeout or MySQL doesn't support locks), returns error to user ("Another booking is in progress")
- Applied to `Shortcodes::_create_appointment_from_submission()` via `LockManager::with_lock()` wrapper
- Limitations documented: named locks are connection-based and won't work across separate DB connections; not all MySQL configurations support named locks
- Significantly reduces double-booking risk compared to previous option-based mutex, but still not 100% guaranteed without true transactions

**Commit 3: Indexes & Query Performance (Schema.php)**
- Added composite indexes to `lazy_staff_hours`: `user_id`, `user_weekday (user_id, weekday)`
- Added composite indexes to `lazy_staff_exceptions`: `user_id`, `user_date (user_id, date)`
- These indexes optimize frequent queries: fetching staff hours by user, filtering by weekday, checking exceptions by date
- Existing indexes on `lazy_appointments` (service_id, customer_id, start_at, status) already cover most query patterns
- Future optimization: consider composite index on `lazy_appointments(service_id, start_at, status)` if filtering queries become bottleneck

**Commit 4: Admin UX Upgrade (AppointmentsPage.php, AppointmentRepository.php)**
- **Filters added**: Service dropdown filter, customer search (email/name via LEFT JOIN on customers table)
- **CSV Export**: "Export CSV" button generates appointments CSV with headers: ID, Service, Customer Email, Customer Name, Resource, Start, End, Status, Created
- **Repository enhancement**: `AppointmentRepository::get_all()` now supports `service_id` and `customer_search` filters
- Customer search uses `LIKE` query on email, first_name, last_name with proper `esc_like()` escaping
- Export respects current filters (can export filtered subset)
- Improves admin workflow for large datasets and reporting needs

**Commit 5: Email Deliverability (SettingsPage.php, Mailer.php)**
- **Reply-To field**: Added optional `mail_reply_to` setting; if set, emails include `Reply-To:` header
- **From email validation**: Uses `is_email()` validation before sending
- **Test Email button**: Sends test email using current From/Reply-To settings to verify deliverability
- Test email shows From name/email and Reply-To in message body for debugging
- Mailer updated to include Reply-To header when configured
- Helps diagnose email delivery issues before customers report problems

**Commit 6: GDPR Basics (PrivacyPage.php)**
- **Retention settings**: `retention_delete_canceled_days` (auto-delete canceled appointments after X days), `retention_anonymize_after_days` (auto-anonymize customer data after X days)
- Settings default to 0 (disabled); must be explicitly configured
- **Manual anonymization**: Admin can anonymize customer by email via nonce-protected form
- Anonymization replaces email with `anonymized_{hash}@deleted.local`, name with "Anonymized User", clears phone/notes
- **Future automation**: Retention cleanup via WP Cron (not yet implemented; documented as "Run Cleanup Now" placeholder)
- Supports GDPR right to erasure and data minimization principles
- Note: Full GDPR compliance requires additional measures (privacy policy, consent tracking, data export) - this is baseline implementation

**Commit 7: Logging System (Logger.php)**
- **Log levels**: error, warn, info, debug (hierarchical: debug includes all, error only critical)
- **Privacy-safe**: Automatically hashes/truncates PII fields (email, phone, first_name, last_name, name)
- Email logging format: `abc***@***.12345678` (first 3 chars + MD5 hash)
- Other PII: `ab***1234` (first 2 chars + hash)
- **Settings toggles**: `logging_enabled` (on/off), `log_level` (dropdown in Settings)
- Logs written to WordPress debug.log via `error_log()` with `[LTLB-{LEVEL}]` prefix
- Applied to Shortcodes booking flow: logs lock timeouts (warn), booking failures (error), successful bookings (info)
- Requires `WP_DEBUG_LOG` enabled in wp-config.php
- Helps troubleshoot production issues without exposing customer data in logs

**Commit 8: QA Automation (QA_CHECKLIST.md)**
- **Smoke Test for Release**: 6-step minimal test covering plugin activation, data creation, frontend booking, admin functions, email sending, diagnostics
- **Upgrade Test from Previous DB Version**: 6-step test covering pre-upgrade data snapshot, upgrade execution, post-upgrade verification, data integrity checks, feature regression, new feature validation
- **Performance & Load Testing**: Optional concurrent booking test, lock manager validation, email deliverability test
- Provides checklist for manual QA before production deployment
- Ensures no regressions during version upgrades (data loss, feature breakage)
- Smoke test can be executed in < 10 minutes for rapid validation

**Commit 9: Documentation Sweep**
- Updated DECISIONS.md with all Phase 4.1 architectural decisions and rationale
- Updated QA_CHECKLIST.md with comprehensive test scenarios
- Verified consistency across SPEC.md, DB_SCHEMA.md, API.md
- Plugin version: 0.4.0
- DB version: 0.4.0 (tracked via `ltlb_db_version` option)

**Overall Phase 4.1 Goal**: Harden plugin for production use with diagnostics, concurrency protection, admin productivity features, email reliability, GDPR basics, privacy-safe logging, and comprehensive QA procedures.

---

## Extended Design System (Post-UX-Polish)

**Design Page Enhancements:**
- **Color Controls**: Background, Primary, Text, Accent, Border Color (all hex input with color picker)
- **Spacing & Shape Controls**: Border Radius (0-50px), Border Width (0-10px)
- **Shadow & Effects**: Enable/Disable Box Shadow, Shadow Blur (0-20px), Shadow Spread (0-10px)
- **Gradient Background**: Checkbox to enable linear gradient from Primary to Accent color (135deg)
- **Animation Duration**: Global transition duration (0-1000ms, default 200ms) for all hover effects
- **Custom CSS Block**: Textarea to paste arbitrary CSS rules targeting `.ltlb-booking`, `.service-card`, `.button-primary`, etc.

**CSS Variables Available to Frontend:**
```css
--lazy-bg                   /* background or gradient */
--lazy-primary              /* button/link color */
--lazy-text                 /* text color */
--lazy-accent               /* hover effects */
--lazy-border-color         /* input & card borders */
--lazy-border-width         /* border thickness in px */
--lazy-border-radius        /* roundness in px */
--lazy-box-shadow           /* shadow string (or none) */
--lazy-transition-duration  /* animation speed in ms */
```

**Frontend Styling (public.css):**
- All `.ltlb-booking` elements respect design variables
- Service cards (`.service-card`) have hover effects with shadow/border color change
- Buttons (`.button-primary`, `.button-secondary`) use primary/accent with smooth transitions
- Input fields inherit border-radius, border-color, and transition-duration
- Wizard steps (`.ltlb-step`) show active/completed states using accent color
- Price preview box (`.ltlb-price-preview`) styled with accent background
- All focus states use 2px solid primary color outline
- Hover effects include `translateY(-2px)` on buttons with updated shadow

**Shortcodes.php Updates:**
- Removed inline style block, now relies entirely on CSS variables in `<style>` tags from `Plugin.php`
- Used semantic HTML `<fieldset>` and `<legend>` elements instead of `<h4>` for better accessibility and styling hooks
- Added unique IDs to all form elements for consistent styling (ltlb-checkin, ltlb-checkout, ltlb-guests, ltlb-date, ltlb-time-slot, ltlb-resource-select)
- Service select now has class `ltlb-service-select` for specific styling if needed
- Honeypot field updated: removed `tabindex="-1"` and added to parent `<div>` instead

**Rationale:**
- Users want pixel-perfect control over wizard appearance without coding
- Custom CSS allows advanced users to add gradients, shadows, or brand-specific styles
- CSS variables eliminate need for code changes when colors change
- Gradient support adds visual depth without complexity
- Animation duration control lets users match their site's interaction style
- All variables have fallback values for graceful degradation if not set

---

**Goal**: User-facing polish for mobile-first wizard, admin tables, hotel mode, and accessibility — no new features.

**Commit 1: Mobile-First Wizard CSS (public.css)**
- **Rationale**: Original wizard CSS used pixel-based layout with poor mobile behavior
- **Changes**: 
  - Converted to rem units for consistent spacing across devices
  - Implemented mobile-first approach with `@media (min-width: 640px)` breakpoints
  - Increased touch targets: buttons min-height 2.75rem, padding 0.75rem
  - Improved spacing: consistent 1rem gutters, 1.5rem section spacing
  - Enhanced visual hierarchy: larger step headings (1.25rem), clear service card structure
- **Impact**: Wizard now fully responsive and touch-friendly on mobile devices

**Commit 2: Admin Tables Polish (ServicesPage.php, CustomersPage.php, ResourcesPage.php)**
- **Problem**: Empty states showed bare "No items" messages without guidance
- **Solutions**:
  - **Services**: Centered empty state with "Create your first service" CTA button (`.button-primary`)
  - **Customers**: Added description "Customers are created automatically from bookings", clarified empty state
  - **Resources**: Added description with hyperlinked quick link to Services page, improved context
- **Rationale**: Empty states should guide users to next action, not dead-end screens
- **Admin UX Philosophy**: Provide context and actionable next steps at every stage

**Commit 3: Hotel Wizard Enhancements (Shortcodes.php, public.js, public.css)**
- **Price Preview Calculator**: Real-time JavaScript calculates `nights × nightly_rate` when service/checkin/checkout change
- **Display Format**: Shows "€XX.XX - N nights × €YY.YY" in styled preview box below date pickers
- **Label Improvements**: Changed "Service" to "Room Type", "Date" to "Check-in Date"/"Check-out Date" for hotel mode
- **Visual Styling**: Price preview box with salbei background (#E8EDE8), distinct from form fields
- **Rationale**: Users need cost transparency before submitting booking; prevents surprise pricing

**Commit 4: Accessibility Improvements (Shortcodes.php, public.css)**
- **Skip Link**: Added keyboard navigation skip link to jump directly to booking form
- **ARIA Attributes**: 
  - Added `aria-label`, `aria-required`, `role` to all form elements
  - Marked honeypot with `aria-hidden="true"` and `tabindex="-1"`
  - Added `aria-label` to submit button
- **Explicit Label Associations**: All inputs now have explicit IDs (e.g., `ltlb-email`) with corresponding `for` attributes
- **Autocomplete Attributes**: Added proper autocomplete hints (`email`, `given-name`, `family-name`, `tel`) for autofill support
- **Focus States**: Improved focus outlines (2px solid) for all interactive elements (buttons, links, inputs)
- **Screen Reader Utilities**: Added `.ltlb-sr-only` class for screen reader only content
- **Required Indicators**: Visual `*` with `aria-label="required"` for screen reader announcement
- **Button States**: Added disabled button styles with opacity and cursor changes
- **Rationale**: WCAG 2.1 Level AA compliance, keyboard navigation support, screen reader compatibility

**Commit 5: Docs-Sweep (SPEC.md, DECISIONS.md, QA_CHECKLIST.md)**
- Document all UX polish changes in appropriate locations
- Add mobile viewport testing steps to QA checklist
- Update DECISIONS.md with UX rationale and design philosophy

**Overall Phase 4.2 Goal**: Polish user-facing surfaces for production launch — mobile users, admin operators, hotel bookings, and accessibility users all have smooth, professional experience.

---

## Phase 4.1.1: Bug Fixes (Post-Review)

**Junction Table Migration Fix (Migrator.php, Schema.php)**
- **Problem**: dbDelta caused "Multiple primary key defined" errors on junction tables during plugin reactivation
- **Root Cause**: dbDelta struggles with composite PRIMARY KEYs on existing tables, especially when trying to ALTER existing keys
- **Additional Issue**: Some junction tables had AUTO_INCREMENT fields (incorrect for junction tables), preventing PRIMARY KEY drops
- **Solution**: Implemented `ensure_junction_table()` helper method with 3-tier approach:
  1. **New tables**: Creates using dbDelta
  2. **Missing PRIMARY KEY**: Adds composite key manually
  3. **Incorrect PRIMARY KEY**: Backs up data → drops table → recreates with correct structure → restores data
- **Why recreate instead of ALTER**: MySQL doesn't allow `DROP PRIMARY KEY` on tables with AUTO_INCREMENT columns
- **Data Safety**: Automatic backup/restore ensures no data loss during structure correction
- **Schema Change**: Removed spaces in composite key syntax: `PRIMARY KEY (id1,id2)` instead of `PRIMARY KEY (id1, id2)` for better dbDelta compatibility
- **Affected Tables**: `lazy_appointment_resources`, `lazy_service_resources`
- **Impact**: Plugin now activates/deactivates cleanly without database errors, automatically repairs corrupted junction table structures from previous migrations while preserving all existing relationships


