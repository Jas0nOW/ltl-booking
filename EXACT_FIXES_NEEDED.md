# i18n Audit: Exact Fixes Required

## FIX #1: Remove Duplicate/Incorrect Div in wizard.php

**Current Code (Lines 31-32):**
```php
<div class="ltlb-booking<?php echo $start_mode === 'calendar' ? ' ltlb-start-calendar' : ''; ?>" role="region" aria-label="<?php echo esc_attr__('Buchungsassistent', 'ltl-bookings'); ?>" data-ltlb-start-mode="<?php echo esc_attr( $start_mode ); ?>" data-ltlb-prefill-service="<?php echo esc_attr( $prefill_service_id ); ?>">
<div class="ltlb-booking<?php echo $start_mode === 'calendar' ? ' ltlb-start-calendar' : ''; ?>" role="region" aria-label="<?php echo esc_attr__( 'Booking Wizard', 'ltl-bookings' ); ?>" data-ltlb-start-mode="<?php echo esc_attr( $start_mode ); ?>" data-ltlb-prefill-service="<?php echo esc_attr( $prefill_service_id ); ?>">
```

**Issues:**
1. Line 31 contains hardcoded German: `esc_attr__('Buchungsassistent', ...)`
2. Two identical opening `<div>` tags (duplicate)
3. Creates invalid HTML

**Fixed Code:**
```php
<div class="ltlb-booking<?php echo $start_mode === 'calendar' ? ' ltlb-start-calendar' : ''; ?>" role="region" aria-label="<?php echo esc_attr__( 'Booking Wizard', 'ltl-bookings' ); ?>" data-ltlb-start-mode="<?php echo esc_attr( $start_mode ); ?>" data-ltlb-prefill-service="<?php echo esc_attr( $prefill_service_id ); ?>">
```

**Action:**
1. Open `public/Templates/wizard.php`
2. Find the two div lines around line 31-32
3. Delete the FIRST one (with 'Buchungsassistent')
4. Keep the SECOND one (with 'Booking Wizard')

---

## FIX #2: Verify Translation Dictionary

**File:** `Includes/Util/I18n.php`

**Current Entry:**
```php
'Booking Wizard' => 'Buchungsassistent',
```

**Status:** Already exists in the dictionary at line 467 ✅

**No action needed** – once you remove the hardcoded German from wizard.php, the I18n class will automatically provide the correct translation when admin locale is German.

---

## FIX #3 (Optional): Standardize Services Translation

**Current Inconsistency:**
```php
// Line 73:
'Services' => 'Services',  // Keeps English

// Line 143:
'All Services' => 'Alle Services',  // German "All" + English "Services"

// Line 281:
'Resources are rooms, equipment, or capacities. Link them to services to control availability.' 
=> '...Verknüpfe sie mit Leistungen, um...'  // Uses "Leistungen"

// Line 328:
'Resources are rooms, equipment, or staff capacity. Link them to services to manage availability.' 
=> '...Verknüpfe sie mit Services, um...'  // Uses "Services"
```

**Recommendation:** 
Choose ONE standard term and update all entries. Options:

**Option A: Keep "Service" (simpler, already partially done)**
```php
'Services' => 'Services',
'All Services' => 'Alle Services',
// Line 281: Change 'Leistungen' to 'Services'
// Line 328: Already correct
```

**Option B: Use "Leistungen" (more natural German)**
```php
'Services' => 'Leistungen',
'All Services' => 'Alle Leistungen',
// Line 281: Already correct  
// Line 328: Change 'Services' to 'Leistungen'
```

**Recommended:** Option A (keeps consistency with existing entries)

---

## VERIFICATION CHECKLIST

After making fixes:

- [ ] Line 31 deleted from `public/Templates/wizard.php`
- [ ] Line 32 (with "Booking Wizard") still present
- [ ] No duplicate opening divs
- [ ] HTML is valid (paired opening/closing tags)
- [ ] Frontend booking form loads without errors
- [ ] German translation shows "Buchungsassistent" (from I18n dictionary)
- [ ] Admin pages in German work correctly
- [ ] Both Appointments and Hotel modes work

---

## TESTING STEPS

### Test 1: Frontend German Translation
1. Set WordPress locale to German (de_DE)
2. View frontend booking wizard
3. Verify aria-label says "Buchungsassistent" (translated from code string "Booking Wizard")

### Test 2: Admin German
1. Login to WordPress admin
2. Switch admin language to German via plugin header
3. Navigate through all admin pages
4. Verify all labels translate correctly

### Test 3: HTML Validation
1. View page source of booking wizard
2. Verify only ONE `<div class="ltlb-booking">` opening tag
3. Verify matching closing tag
4. No duplicate elements

---

## FILE SUMMARY

| File | Lines | Change | Priority |
|------|-------|--------|----------|
| public/Templates/wizard.php | 31 | DELETE entire line | 🔴 CRITICAL |
| public/Templates/wizard.php | 32 | KEEP (no change needed) | ✅ OK |
| Includes/Util/I18n.php | 467 | Already has 'Booking Wizard' | ✅ OK |
| Includes/Util/I18n.php | 73,143,281,328 | STANDARDIZE (optional) | 🟡 RECOMMENDED |

