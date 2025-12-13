# LazyBookings QA Checklist

This checklist covers manual verification steps after installing or updating the plugin.

- Activate plugin and run DB migrations (ensure tables: lazy_services, lazy_customers, lazy_appointments).
- Admin pages:
  - Open LazyBookings → Services: list, Add New, Edit, Save — confirm admin notice appears and persists only once.
  - Open LazyBookings → Customers: add/edit customer — confirm notice shown.
  - Open LazyBookings → Appointments: filter, change status (Confirm/Cancel) — confirm notices shown.
  - Open LazyBookings → Settings: change settings, Save — confirm notice shown and settings persist.
- Frontend booking (`[lazy_book]` shortcode):
  - Render form, select a service and slot, submit with valid details — verify appointment created in DB and email(s) sent (if enabled).
  - Honeypot: submit form with honeypot field filled — should be rejected (no booking created).
  - Rate limiting: submit form repeatedly >10 times within 10 minutes from same IP — should be blocked.
  - Double-booking: attempt booking overlapping slots — should be rejected by conflict check.
- Staff Management:
  - Create a new user with the `ltlb_staff` role.
  - Edit the user's working hours in the "Staff" admin page.
  - Add an exception for the user.
  - Verify that the changes are saved correctly.
- Availability Endpoint:
  - Call the `GET /wp-json/ltlb/v1/availability` endpoint with a valid service and date.
  - Check that the returned slots are correct based on the staff's working hours and exceptions.
  - Test with a date where the staff member has an exception and ensure no slots are returned.
- Timezone/DST:
  - Set site timezone and plugin timezone (Settings) and verify slot times and stored `start_at`/`end_at` values match expected local times.
- Email:
  - Verify admin and customer email templates render placeholders: {service}, {start}, {end}, {name}, {email}, {phone}, {status}, {appointment_id}.
- Notes & Logs:
  - Check for PHP errors in debug log.
  - Verify graceful failures: booking should still create if emails fail (errors logged but user sees success).

If any step fails, collect screenshots, DB rows, and wp-debug.log entries and report.

## Resource-specific tests (Phase 2c)

- Service with single resource:
  - Create a `Resource` and map it to a `Service` (Services → edit → Resources).
  - Book the only available slot for that service twice (two different customers). Second booking should be rejected when capacity is exhausted.

- Service with two resources:
  - Create two `Resources` and map both to the same `Service`.
  - Book the same slot twice (two customers) — both bookings should succeed if each maps to a different resource.
  - Book the same slot a third time — should be rejected once both resources are occupied.

- Slot blocking behavior:
  - Create appointments with `confirmed` status and verify they block resource availability immediately.
  - Toggle the `ltlb_pending_blocks` option and verify `pending` appointments also block when enabled.

- Wizard UI checks:
  - When multiple resources are free for a slot, the frontend should show a `Resource` dropdown.
  - Selecting a resource should persist the chosen resource to the appointment (check `lazy_appointment_resources`).
  - If the user doesn't choose a resource, verify the system auto-selects the first available resource.

- Edge cases & race checks (manual):
  - Rapidly submit the same slot from two browser windows to observe possible race conditions; verify at most capacity bookings are accepted.
  - Check admin Appointments list shows the `Resource` column and the correct resource name or `—` when none.

- Optional status page (admin):
  - Visit the resource status admin page (if installed) and verify counts for resources, mappings, and active bookings per resource.
