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
## Production Readiness Tests (Phase 4.1)

### Smoke Test for Release

This minimal test verifies core functionality is working after plugin update/activation:

**Prerequisites:**
- Fresh WordPress installation OR existing site with LazyBookings installed
- Admin access
- Test email account accessible

**Test Steps:**

1. **Plugin Activation**
   - [ ] Activate/Update plugin without fatal errors
   - [ ] Visit LazyBookings → Diagnostics
   - [ ] Verify DB version matches plugin version (0.4.0)
   - [ ] Verify all 8 tables show "✓ Exists" status

2. **Create Test Data**
   - [ ] Create 1 Service (e.g., "Test Service", 60min, 10€)
   - [ ] Create 1 Resource (e.g., "Test Room", capacity 2)
   - [ ] Map Resource to Service (Services → Edit → Resources)
   - [ ] Verify saves without errors

3. **Frontend Booking Flow**
   - [ ] Create test page with `[lazy_book]` shortcode
   - [ ] Select service, date, time slot
   - [ ] Fill customer details with test email
   - [ ] Submit booking
   - [ ] Verify success message displayed
   - [ ] Check Appointments admin page shows new booking

4. **Admin Functions**
   - [ ] Open Appointments page
   - [ ] Change appointment status to "Confirmed"
   - [ ] Verify status updated
   - [ ] Test CSV Export button - downloads appointments.csv with extended fields (resource, seats/nights, mode-aware)
   - [ ] Test customer search filter (search by email)

5. **WP-CLI Commands** (if WP-CLI available)
   - [ ] Run `wp ltlb doctor` - verify output shows system info, table status, lock support
   - [ ] Run `wp ltlb migrate` - verify migrations run without errors
   - [ ] Enable dev tools (Settings → `enable_dev_tools=1` or set `WP_DEBUG=true`)
   - [ ] Run `wp ltlb seed --mode=service` - verify demo data created (services, resources, staff)
   - [ ] Run `wp ltlb seed --mode=hotel` - verify hotel demo data created and template mode switched

6. **Diagnostics Admin UI**
   - [ ] Visit LazyBookings → Diagnostics
   - [ ] Click "Run Doctor" button
   - [ ] Verify diagnostics output displayed (version check, lock support, email config, logging status)
   - [ ] Click "Run Migrations" button - verify migrations execute without errors

7. **Settings & Email**
   - [ ] Settings → Email section
   - [ ] Enter test email in "Send test email to" field
   - [ ] Click "Send Test Email"
   - [ ] Check email received with correct From/Reply-To headers

8. **Diagnostics & Health** (legacy check)
   - [ ] Visit Diagnostics page
   - [ ] Verify system info displays (WP version, PHP version, template mode)
   - [ ] Verify database statistics show correct counts
   - [ ] Click "Run Migrations" button - no errors

**Expected Result:** All steps pass without fatal errors, bookings create successfully, emails send correctly.

### Hotel Mode Flow (Phase 4)

This test validates the real hotel booking flow with check-in/out and room assignment.

**Prerequisites:**
- Services configured as Room Types (e.g., Double Room)
- Resources configured as Rooms (e.g., Room 101, Room 102) with capacity
- Hotel times set in Settings (check-in/check-out)

**Test Steps:**
1. Create a page with `[lazy_book]` and switch template mode to hotel.
2. Choose a Room Type service and select check-in and check-out dates.
3. Verify available rooms list reflects capacity and existing bookings.
4. Complete booking; confirm `lazy_appointments` has correct start/end (check-in/out times).
5. Confirm `lazy_appointment_resources` contains assigned room for the booking.
6. Create overlapping booking with same room; verify conflict is prevented unless capacity allows.
7. Verify check-out exclusivity: next booking can start on the check-out date.

**Expected Result:** Hotel bookings store correct date ranges, room assignment persists, capacity and overlap rules enforced.

### Upgrade Test from Previous DB Version

This test verifies smooth upgrade path from previous plugin versions.

**Prerequisites:**
- Site running previous LazyBookings version (e.g., 0.3.0)
- Existing appointments/customers/services in database
- Backup database before test

**Test Steps:**

1. **Pre-Upgrade Verification**
   - [ ] Note current plugin version (wp-admin → Plugins)
   - [ ] Record current `ltlb_db_version` option value
   - [ ] Export appointments via CSV (keep as reference)
   - [ ] Count records: services, customers, appointments, resources
   - [ ] Take screenshot of Diagnostics page (table status)

2. **Perform Upgrade**
   - [ ] Update plugin via WordPress admin OR upload new version
   - [ ] Activate updated plugin
   - [ ] Check for activation errors in debug.log

3. **Post-Upgrade Verification**
   - [ ] Visit Diagnostics page
   - [ ] Verify `ltlb_db_version` updated to 0.4.0
   - [ ] Verify all tables still show "✓ Exists"
   - [ ] Verify record counts match pre-upgrade counts
   - [ ] Check for new tables/columns (if applicable):
     - Verify indexes added (check schema changes)

4. **Data Integrity**
   - [ ] Open Appointments page - all appointments display correctly
   - [ ] Open Customers page - all customers present
   - [ ] Open Services page - all services intact
   - [ ] Open Resources page - all resources present
   - [ ] Test appointment status change - verify updates work

5. **Feature Regression**
   - [ ] Test frontend booking (create new appointment)
   - [ ] Test email sending (Settings → Send Test Email)
   - [ ] Test CSV export (Appointments → Export CSV)
   - [ ] Test filters (filter appointments by status, service, customer)

6. **New Features (Phase 4.1)**
   - [ ] Verify Diagnostics menu item exists
   - [ ] Verify Privacy menu item exists (new in 0.4.0)
   - [ ] Test customer anonymization (Privacy → Manual Anonymization)
   - [ ] Verify logging settings present (Settings → Logging Settings)
   - [ ] Enable logging, create booking, check debug.log for LTLB-INFO entries

**Expected Result:** 
- All pre-existing data preserved
- No data loss or corruption
- All features from previous version still work
- New Phase 4.1 features accessible and functional
- `ltlb_db_version` updated correctly

**Rollback Plan:** 
If upgrade fails critically, restore database backup and reinstall previous plugin version.

### Performance & Load Testing (Optional)

**Concurrent Booking Test:**
- Use browser dev tools or automated script
- Simulate 5+ simultaneous booking requests to same slot
- Expected: Only 1 booking succeeds (or up to capacity), others rejected with lock timeout or conflict error
- Verify no duplicate bookings in database

**Lock Manager Validation:**
- Enable logging (Settings → Logging → Debug level)
- Create 3 concurrent bookings to same slot
- Check debug.log for "lock timeout" warnings
- Verify GET_LOCK/RELEASE_LOCK behavior (MySQL logs if available)

**Email Deliverability:**
- Create 10 bookings in quick succession
- Verify all confirmation emails sent (check mail queue/logs)
- Verify no email failures block booking creation (graceful failure)