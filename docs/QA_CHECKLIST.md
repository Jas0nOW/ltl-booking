# LazyBookings QA Checklist (v0.4.4)

## ✅ Smoke Test (Pre-Release)

**Time Required:** ~10 minutes

**Goal:** Rapid validation that core functionality works before deployment.

### 1. Plugin Activation
- [ ] Deactivate plugin if already active
- [ ] Activate plugin from Plugins page
- [ ] No PHP errors/warnings displayed
- [ ] Database tables created (check Tools → Diagnostics)

### 2. Basic Configuration
- [ ] Navigate to LazyBookings → Settings
- [ ] Set working hours (e.g., 09:00 - 18:00)
- [ ] Set template mode (Service or Hotel)
- [ ] Configure From email and name
- [ ] Save settings - success notice appears

### 3. Create Test Data
- [ ] Create 1 service/room type with price
- [ ] Create 1-2 resources (studios/rooms)
- [ ] Link service to resources
- [ ] Create page with `[lazy_book]` shortcode
- [ ] (Optional) Create page with `[lazy_book_calendar]` shortcode
- [ ] Publish page

### 4. Frontend Booking (Service Mode)
- [ ] Visit shortcode page as logged-out user
- [ ] Select service, date (tomorrow), time slot
- [ ] Fill customer details (email, name, phone)
- [ ] Submit booking
- [ ] Success message displayed
- [ ] Appointment visible in LazyBookings → Appointments

### 5. Frontend Booking (Hotel Mode)
- [ ] Switch to hotel mode in Settings
- [ ] Visit shortcode page
- [ ] Service selector label is "Room Type"
- [ ] Date inputs are "Check-in" / "Check-out" and "Guests" exists
- [ ] Price preview updates when room type + dates are selected
- [ ] If multiple rooms fit: "Room Preference" step appears with room dropdown
- [ ] Submit booking
- [ ] Success message displayed
- [ ] Appointment visible in LazyBookings → Appointments
- [ ] (Optional) Appointment has a room mapping in DB (`lazy_appointment_resources`)

### 6. Admin Functions
- [ ] View appointments list - filters work (service, customer search)
- [ ] Export CSV - download successful, contains correct data
- [ ] Calendar page loads (LazyBookings → Calendar)
- [ ] Calendar shows existing appointments as events
- [ ] Drag & drop an event to a new time → reload page → time change persisted
- [ ] Resize an event duration → reload page → duration change persisted
- [ ] Click an event → details panel loads service + customer data
- [ ] Change appointment status in panel → calendar refreshes and status persisted
- [ ] Edit customer fields (name/email/phone/notes) → save → reopen event and verify persisted
- [ ] Delete an appointment from the calendar panel → event disappears and appointment removed from list
- [ ] Admin header language switch works (English/Deutsch) and is saved per user
- [ ] Diagnostics page shows green status for all tables
- [ ] Email test sends successfully (Settings → Email tab)

---

## 🔄 Upgrade Test (Version Migration)

**Time Required:** ~15 minutes

**Goal:** Ensure data integrity when upgrading from previous version.

### Pre-Upgrade Snapshot
- [ ] Note current plugin version (LazyBookings → Diagnostics)
- [ ] Note DB version (`ltlb_db_version` option)
- [ ] Count existing: Services, Customers, Appointments, Resources
- [ ] Take screenshot of Appointments page
- [ ] Take database backup (phpMyAdmin or WP CLI)

### Upgrade Execution
- [ ] Upload new plugin version (FTP or Plugins → Add New)
- [ ] Visit WordPress Admin - triggers auto-migration
- [ ] Check for PHP errors in debug.log
- [ ] Visit LazyBookings → Diagnostics
- [ ] Verify DB version updated to new version
- [ ] Verify plugin version matches latest

### Post-Upgrade Validation
- [ ] All services still present with correct prices
- [ ] All customers intact with email/name
- [ ] All appointments show correct service, resource, datetime, status
- [ ] Create new appointment - success
- [ ] View old appointment - all data displays correctly
- [ ] CSV export includes old + new appointments

### Feature Regression Check
- [ ] Shortcode page renders without errors
- [ ] Availability API returns time slots (`/wp-json/ltlb/v1/availability?service_id=1&date=2024-12-20&slots=1`)
- [ ] Admin filters work (service dropdown, customer search)
- [ ] Settings save correctly
- [ ] Email sending works

### New Feature Validation
- [ ] Test any new features added in this version (check SPEC.md)
- [ ] Fixed weekly start times:
  - [ ] Create or edit a Service and set Availability Mode = "Fixed weekly start times"
  - [ ] Add a slot like Friday 18:00 (weekday=5, time=18:00)
  - [ ] Call availability for a matching Friday date and ensure returned slots include 18:00
  - [ ] Call availability for a non-matching weekday and ensure 18:00 is NOT returned
- [ ] Verify new settings fields appear if applicable
- [ ] Check new admin pages/UI elements
- [ ] Test new REST endpoints if added

**Success Criteria:**
- Zero data loss (all counts match pre-upgrade)
- Zero regressions (old features still work)
- New features operational

---

## 📱 Mobile Responsive Test

**Prerequisites:**
- Browser with DevTools (Chrome, Firefox, Edge)
- Test page with `[lazy_book]` shortcode

### Mobile Viewport (320px - 640px)
- [ ] Open booking page (DevTools → iPhone SE 375×667)
- [ ] Wizard uses full width with padding
- [ ] All text readable without horizontal scroll
- [ ] Buttons touch-friendly (min-height 44px)
- [ ] Form inputs spaced adequately
- [ ] Date/time pickers functional
- [ ] Complete booking flow without zoom

### Tablet Viewport (640px - 1024px)
- [ ] Switch to iPad (768×1024)
- [ ] Responsive styles applied
- [ ] Service cards in grid layout
- [ ] Touch targets appropriately sized
- [ ] Booking flow smooth

### Desktop Viewport (1024px+)
- [ ] Switch to desktop (1920×1080)
- [ ] Wizard max-width 40rem applied
- [ ] Centered layout with margins
- [ ] Responsive styles scale properly

### Touch Target Validation
- [ ] Accessibility inspector check:
  - [ ] Buttons min-height 2.75rem (44px)
  - [ ] Interactive elements padding ≥0.75rem
  - [ ] No overlapping click targets

**Expected:** Wizard functional across all viewports.

---

## 🎨 Admin UI/UX Test

**Prerequisites:**
- Admin access to LazyBookings

### Empty States
- [ ] Services page with 0 services:
  - [ ] Centered empty state message
  - [ ] "Create your first service" CTA button visible
  - [ ] Clicking CTA → redirects to Add New Service
- [ ] Customers page with 0 customers:
  - [ ] Description: "Customers are created automatically from bookings"
  - [ ] Empty state clear and helpful
- [ ] Resources page with 0 resources:
  - [ ] Description with hyperlinked "Services page"
  - [ ] Quick link navigates correctly

### Non-Empty States
- [ ] Create 1 service, 1 customer, 1 resource
- [ ] Descriptions persist at top of pages
- [ ] Quick links remain functional

**Expected:** Empty states guide users to next action.

---

## 🏨 Hotel Mode Test

**Prerequisites:**
- Settings → Template Mode = Hotel
- At least 1 room type with nightly rate
- Test page with `[lazy_book]` shortcode

### Label Verification
- [ ] Service selector labeled "Room Type" (not "Service")
- [ ] Date inputs: "Check-in" / "Check-out" and "Guests" exists

### Price Preview Calculator
- [ ] Select room type with price (e.g., €100/night)
- [ ] Select check-in: 2024-03-01
- [ ] Select check-out: 2024-03-03
- [ ] Price preview shows: `€200.00 - 2 nights × €100.00`
- [ ] Change check-out to 2024-03-05
- [ ] Price updates real-time: `€400.00 - 4 nights × €100.00`
- [ ] Change room type → price recalculates

### Visual Styling
- [ ] Price preview is positioned below the title, above the wizard steps
- [ ] Uses `--lazy-panel-bg` (may be transparent by default) and has visible border + padding
- [ ] Scales properly on mobile viewports

### Edge Cases
- [ ] Check-out before check-in → no negative nights displayed
- [ ] Dates unselected → price preview hidden or shows placeholder
- [ ] If no rooms fit for the selected dates/guests: room preference stays hidden and submit should fail gracefully (no booking created)

**Expected:** Price transparency, real-time updates, hotel-specific labels.

---

## ♿ Accessibility Test

**Prerequisites:**
- Keyboard (no mouse)
- Screen reader (NVDA, JAWS, VoiceOver) OR browser accessibility inspector

### Keyboard Navigation
- [ ] Open booking page, press Tab
- [ ] Skip link appears with focus (keyboard only)
- [ ] Activate skip link (Enter) → focus jumps to booking form
- [ ] Tab through all form fields
- [ ] All interactive elements focusable
- [ ] Focus outlines visible (2px solid)
- [ ] Honeypot field skipped (tabindex -1)

### Screen Reader Compatibility
- [ ] Activate screen reader
- [ ] Navigate to booking form
- [ ] Form announced with label
- [ ] Tab through fields:
  - [ ] Email: "Email, required"
  - [ ] First Name: "First Name, required"
  - [ ] Last Name: "Last Name, required"
  - [ ] Phone: "Phone, optional"
- [ ] Required indicators (`*`) announced via aria-label
- [ ] Submit button: "Complete Booking"
- [ ] Honeypot hidden from screen reader (aria-hidden)

### ARIA Attributes Validation
- [ ] Inspect HTML (DevTools)
- [ ] All inputs have explicit `id` (ltlb-email, ltlb-first-name, etc.)
- [ ] All labels have `for` matching input IDs
- [ ] Required inputs: `aria-required="true"`
- [ ] Form has `aria-label` or role
- [ ] Submit button has descriptive `aria-label`

### Autocomplete Attributes
- [ ] Email: `autocomplete="email"`
- [ ] First Name: `autocomplete="given-name"`
- [ ] Last Name: `autocomplete="family-name"`
- [ ] Phone: `autocomplete="tel"`
- [ ] Browser autofill suggestions appear correctly

### Visual Accessibility
- [ ] Focus states high contrast (not color-only)
- [ ] Disabled buttons: visible indication (opacity, cursor)
- [ ] Error messages clearly visible
- [ ] Success messages prominently displayed

**Expected:** WCAG 2.1 Level AA compliance - all functionality keyboard-accessible, screen reader compatible.

---

## ⚡ Performance & Load Test (Optional)

**Prerequisites:**
- WP-CLI access OR load testing tool
- Test environment (not production)

### Concurrent Booking Test
1. Simulate 5-10 users booking same service/time slot simultaneously
2. Expected: Only 1 booking succeeds, others receive error
3. No database corruption or orphaned records
4. Check `wp-uploads/ltlb-logs/` for lock timeout warnings

### Lock Manager Validation
- [ ] Install Query Monitor plugin
- [ ] Create booking via frontend
- [ ] Check queries log for `GET_LOCK()` call
- [ ] Lock key format: MD5 hash of service+start+resource
- [ ] Lock released after booking creation
- [ ] No lingering locks (check `SHOW PROCESSLIST`)

### Email Deliverability Test
- [ ] Create 10+ bookings in rapid succession
- [ ] Verify all confirmation emails sent
- [ ] Check email headers for correct From/Reply-To
- [ ] No timeouts or SMTP errors in debug.log
- [ ] Emails arrive within 2 minutes

**Expected:** System handles concurrent load, locks prevent double-booking, emails deliver reliably.

---

## 🚀 Release Checklist

See [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) for full deployment process.

**Quick Pre-Release Check:**
- [ ] All smoke tests passed
- [ ] Mobile responsive test passed
- [ ] Accessibility test passed
- [ ] Version number updated in plugin header
- [ ] DB version updated in constants
- [ ] DECISIONS.md updated with changes
- [ ] No PHP errors in debug.log
- [ ] Database backup taken

---

## 📝 Test Result Template

```markdown
### Test Run: [Date] - v[Version]
**Tester:** [Name]
**Environment:** WordPress [Version], PHP [Version]
**Template Mode:** [Service/Hotel]

#### Results
- [ ] Smoke Test: PASS/FAIL
- [ ] Upgrade Test: PASS/FAIL/N/A
- [ ] Mobile Test: PASS/FAIL
- [ ] Accessibility Test: PASS/FAIL
- [ ] Admin UX Test: PASS/FAIL
- [ ] Hotel Mode Test: PASS/FAIL/N/A

#### Failures/Notes:
[Describe any failures or observations]

#### Test Data:
- Services: X
- Customers: Y
- Appointments: Z
- Resources: A
```

---

## 🔗 Related Documentation

- **Release Process:** [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md)
- **Database Schema:** [DB_SCHEMA.md](DB_SCHEMA.md)
- **API Reference:** [API.md](API.md)
- **Error Handling:** [ERROR_HANDLING.md](ERROR_HANDLING.md)

---

**Last Updated:** v0.4.4
