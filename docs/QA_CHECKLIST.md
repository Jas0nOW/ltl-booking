# LazyBookings QA Checklist

This checklist covers manual verification steps after installing or updating the plugin.

## Plugin Activation & DB
- Activate plugin and run DB migrations (ensure tables: `lazy_services`, `lazy_customers`, `lazy_appointments`, `lazy_resources`, `lazy_service_resources`, `lazy_appointment_resources`).
- Verify `ltlb_db_version` option is set to `0.2.0` or current version.

## Admin Pages
- **Dashboard**: Open LazyBookings → Dashboard — verify status cards show counts (Services, Customers, Appointments, Resources) and last 5 appointments table displays with resource names.
- **Services**: list, Add New, Edit, Save — confirm admin notice appears; verify resource multi-select saves mappings to `lazy_service_resources`.
  - **Group Booking**: Enable "Group Booking" checkbox for a service, set "Max Seats per Booking" (e.g., 5) — verify fields save to DB.
- **Customers**: add/edit customer — confirm notice shown.
- **Appointments**: filter, change status (Confirm/Cancel) — confirm notices shown; verify Resource column shows assigned resource name or "—".
  - **Seats Column**: Verify "Seats" column displays seat count (default 1, or group booking seat count).
- **Settings**: change template_mode (service/hotel), working hours, email settings — confirm notice shown and settings persist.
- **Resources** (if admin page exists): add/edit resources with capacity, verify saves to `lazy_resources`.

## Frontend Booking (`[lazy_book]` shortcode)
- **Service Mode**:
  - Render form, select a service and slot, submit with valid details — verify appointment created in DB and email(s) sent (if enabled).
  - **Group Bookings**: 
    - Select a group-enabled service — verify "Number of Seats" field appears (hidden for non-group services).
    - Set seat count (1..max_seats_per_booking) and submit — verify appointment created with correct `seats` value.
    - Verify capacity calculation: attempt booking with resource at capacity — should reject if total seats > available.
  - **Resource dropdown**: if multiple resources available for slot, verify dropdown appears; select one and confirm it's assigned in `lazy_appointment_resources`.
  - **Auto-assignment**: if only one resource available, verify it's auto-assigned without showing dropdown.
  - Honeypot: submit form with honeypot field filled — should be rejected (no booking created).
  - Rate limiting: submit form repeatedly >10 times within 10 minutes from same IP — should be blocked.
  - **Resource blocking**: book a slot with resource at full capacity; attempt second booking on same slot — should be rejected or auto-assign different resource if available.
  - **Pending blocks option**: toggle `ltlb_pending_blocks` in settings; verify pending appointments block/don't block slots accordingly.
- **Hotel Mode**:
  - Switch template_mode to "hotel" in Settings.
  - Load `[lazy_book]` shortcode — verify placeholder message ("Hotel booking mode coming soon") displays.

## REST API
- `GET /wp-json/ltlb/v1/time-slots?service_id=1&date=2025-12-15` — verify returns slots with `free_resources_count`, `resource_ids`, and `spots_left` (for group services).
- `GET /wp-json/ltlb/v1/slot-resources?service_id=1&start=2025-12-15 09:00:00` — verify returns per-resource availability details.

## Timezone/DST
- Set site timezone and plugin timezone (Settings) and verify slot times and stored `start_at`/`end_at` values match expected local times.

## Email
- Verify admin and customer email templates render placeholders: `{service}`, `{start}`, `{end}`, `{name}`, `{email}`, `{phone}`, `{status}`, `{appointment_id}`, `{seats}`.

## Code Quality & Logs
- Check for PHP errors in debug log (`wp-content/debug.log`).
- Verify all `require_once` paths use `Includes/` (capital I) consistently.
- Verify graceful failures: booking should still create if emails fail (errors logged but user sees success).
- Verify no fatal errors on plugin activation/deactivation.

## Notes
If any step fails, collect screenshots, DB rows, and wp-debug.log entries and report.
