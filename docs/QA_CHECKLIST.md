# LazyBookings QA Checklist

This checklist covers manual verification steps after installing or updating the plugin.

**Test Steps:**

1. **Plugin Activation**
   - [X] Activate/Update plugin without fatal errors
   - [X] Visit LazyBookings → Diagnostics
   - [X] Verify DB version matches plugin version (0.4.0)
   - [ ] Verify all 8 tables show "✓ Exists" status 
   (Ich kann nur 6 sehen:
    wp_lazy_services	✓ Exists	1
    wp_lazy_customers	✓ Exists	1
    wp_lazy_appointments	✓ Exists	0
    wp_lazy_resources	✓ Exists	1
    wp_lazy_service_resources	✓ Exists	0
    wp_lazy_appointment_resources	✓ Exists	0
    )

2. **Create Test Data**
   - [X] Create 1 Service (e.g., "Test Service", 60min, 10€)
   - [X] Create 1 Resource (e.g., "Test Room", capacity 2)
   - [X] Map Resource to Service (Services → Edit → Resources)
   - [X] Verify saves without errors

3. **Frontend Booking Flow**
   - [X] Create test page with `[lazy_book]` shortcode
   - [ ] Select service, date, time slot (Select a Date first obwohl Datum ausgewählt ist!)
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
   - [X] Run `wp ltlb doctor` - verify output shows system info, table status, lock support
   - [X] Run `wp ltlb migrate` - verify migrations run without errors
   - [X] Enable dev tools (Settings → `enable_dev_tools=1` or set `WP_DEBUG=true`)
   - [X] Run `wp ltlb seed --mode=service` - verify demo data created (services, resources, staff)
   - [X] Run `wp ltlb seed --mode=hotel` - verify hotel demo data created and template mode switched

6. **Diagnostics Admin UI**
   - [X] Visit LazyBookings → Diagnostics
   - [X] Click "Run Doctor" button
   - [X] Verify diagnostics output displayed (version check, lock support, email config, logging status)
   - [X] Click "Run Migrations" button - verify migrations execute without errors

7. **Settings & Email**
   - [X] Settings → Email section
   - [X] Enter test email in "Send test email to" field
   - [ ] Click "Send Test Email"
   - [ ] Check email received with correct From/Reply-To headers

8. **Diagnostics & Health** (legacy check)
   - [X] Visit Diagnostics page
   - [X] Verify system info displays (WP version, PHP version, template mode)
   - [X] Verify database statistics show correct counts
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

## UX Polish Tests (Phase 4.2)

### Mobile-First Wizard Test

**Prerequisites:**
- Test page with `[lazy_book]` shortcode
- Access to browser dev tools OR physical mobile device

**Test Steps:**

1. **Mobile Viewport (320px - 640px)**
   - [ ] Open booking page in mobile viewport (Chrome DevTools → 375×667 iPhone SE)
   - [ ] Verify wizard container uses full width with appropriate padding
   - [ ] Verify all text is readable without horizontal scroll
   - [ ] Verify buttons are touch-friendly (min-height 44px / 2.75rem)
   - [ ] Verify form inputs have adequate spacing (1rem gutters)
   - [ ] Verify date/time pickers work correctly on mobile
   - [ ] Test entire booking flow on mobile without zoom issues

2. **Tablet Viewport (640px - 1024px)**
   - [ ] Switch to tablet viewport (768×1024 iPad)
   - [ ] Verify wizard uses responsive breakpoint styles
   - [ ] Verify service cards display in appropriate grid layout
   - [ ] Verify increased spacing and larger touch targets applied
   - [ ] Complete booking flow - all interactions smooth

3. **Desktop Viewport (1024px+)**
   - [ ] Switch to desktop viewport (1920×1080)
   - [ ] Verify wizard max-width constraint (40rem) prevents excessive width
   - [ ] Verify centered layout with appropriate margins
   - [ ] Verify all responsive enhancements gracefully scale up

4. **Touch Target Validation**
   - [ ] Use browser accessibility inspector to verify:
     - All buttons min-height 2.75rem (44px)
     - All interactive elements have adequate padding (0.75rem minimum)
     - No overlapping click targets

**Expected Result:** Wizard is fully functional and visually consistent across all viewport sizes without layout breaks or usability issues.

### Admin Tables UX Test

**Prerequisites:**
- Admin access to LazyBookings

**Test Steps:**

1. **Empty States**
   - [ ] Visit Services page with 0 services
   - [ ] Verify centered empty state message displayed
   - [ ] Verify "Create your first service" CTA button present with `.button-primary` styling
   - [ ] Click CTA button - redirects to Add New Service form
   - [ ] Visit Customers page with 0 customers
   - [ ] Verify description: "Customers are created automatically from bookings"
   - [ ] Verify empty state messaging is clear and helpful
   - [ ] Visit Resources page with 0 resources
   - [ ] Verify description contains hyperlinked "Services page" quick link
   - [ ] Click quick link - navigates to Services page

2. **Non-Empty States**
   - [ ] Create at least 1 service, 1 customer, 1 resource
   - [ ] Verify descriptions persist at top of pages even with data present
   - [ ] Verify quick links remain functional

**Expected Result:** Empty states guide users to next action, descriptions provide context, quick links improve navigation.

### Hotel Mode Wizard Test

**Prerequisites:**
- Plugin configured in hotel mode (Settings → `template_mode = hotel`)
- At least 1 service (room type) created with nightly rate
- Test page with `[lazy_book]` shortcode

**Test Steps:**

1. **Label Verification**
   - [ ] Open booking page in hotel mode
   - [ ] Verify service selector labeled "Room Type" (not "Service")
   - [ ] Verify date inputs labeled "Check-in Date" and "Check-out Date" (not generic "Date")

2. **Price Preview Calculator**
   - [ ] Select a room type with price (e.g., 100€/night)
   - [ ] Select check-in date (e.g., 2024-03-01)
   - [ ] Select check-out date (e.g., 2024-03-03)
   - [ ] Verify price preview box displays: "€200.00 - 2 nights × €100.00"
   - [ ] Change check-out date to 2024-03-05
   - [ ] Verify price updates in real-time: "€400.00 - 4 nights × €100.00"
   - [ ] Change room type to different rate
   - [ ] Verify price recalculates with new nightly rate

3. **Visual Styling**
   - [ ] Verify price preview box has distinct background (salbei/green tint)
   - [ ] Verify price preview positioned below date pickers, above customer details
   - [ ] Verify preview box scales properly on mobile viewports

4. **Edge Cases**
   - [ ] Select check-out date before check-in date
   - [ ] Verify price preview doesn't show negative nights or confusing output
   - [ ] Leave dates unselected - verify price preview hidden or shows appropriate placeholder
   - [ ] Submit booking - verify nights calculation reflected in database

**Expected Result:** Price preview provides transparent cost calculation, updates in real-time, and hotel-specific labels improve clarity.

### Accessibility Test

**Prerequisites:**
- Keyboard (no mouse)
- Screen reader (NVDA, JAWS, or VoiceOver) OR browser accessibility inspector

**Test Steps:**

1. **Keyboard Navigation**
   - [ ] Open booking page, press Tab repeatedly
   - [ ] Verify skip link appears with focus (keyboard only)
   - [ ] Activate skip link with Enter - focus jumps to booking form
   - [ ] Tab through entire form
   - [ ] Verify all interactive elements focusable (service select, date inputs, text inputs, submit button)
   - [ ] Verify focus outlines visible (2px solid) on all focused elements
   - [ ] Verify honeypot field skipped (tabindex -1)

2. **Screen Reader Compatibility** (if available)
   - [ ] Activate screen reader
   - [ ] Navigate to booking form
   - [ ] Verify form announced with appropriate label
   - [ ] Tab through each field:
     - Email field: announces "Email, required"
     - First Name: announces "First Name, required"
     - Last Name: announces "Last Name, required"
     - Phone: announces "Phone, optional"
   - [ ] Verify required indicators (`*`) announced via aria-label
   - [ ] Verify submit button announces "Complete Booking"
   - [ ] Verify honeypot field hidden from screen reader (aria-hidden)

3. **ARIA Attributes Validation**
   - [ ] Inspect form HTML (browser dev tools)
   - [ ] Verify all inputs have explicit `id` attributes (ltlb-email, ltlb-first-name, etc.)
   - [ ] Verify all labels have corresponding `for` attributes matching input IDs
   - [ ] Verify required inputs have `aria-required="true"` attribute
   - [ ] Verify form has `aria-label` or appropriate role
   - [ ] Verify submit button has descriptive `aria-label`

4. **Autocomplete Attributes**
   - [ ] Inspect email input - verify `autocomplete="email"`
   - [ ] Inspect first name input - verify `autocomplete="given-name"`
   - [ ] Inspect last name input - verify `autocomplete="family-name"`
   - [ ] Inspect phone input - verify `autocomplete="tel"`
   - [ ] Test browser autofill behavior - verify suggestions appear correctly

5. **Visual Accessibility**
   - [ ] Verify focus states are high contrast (not relying on color alone)
   - [ ] Verify disabled button states have visual indication (opacity, cursor)
   - [ ] Verify error messages are clearly visible and associated with fields
   - [ ] Verify success messages are prominently displayed

**Expected Result:** 
- All functionality accessible via keyboard without mouse
- Screen readers announce all form elements correctly with labels and required status
- ARIA attributes present and semantically correct
- Autocomplete works for common fields
- Focus states clearly visible throughout

**Accessibility Compliance:** These tests verify WCAG 2.1 Level AA compliance for perceivable, operable, understandable, and robust content.