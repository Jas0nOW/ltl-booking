# Quick Reference: Critical i18n Fixes Required

## 🔴 BLOCKER #1: Remove Hardcoded German String

**File:** `public/Templates/wizard.php`  
**Line:** 31  
**Action:** DELETE this entire line:
```php
<div class="ltlb-booking<?php echo $start_mode === 'calendar' ? ' ltlb-start-calendar' : ''; ?>" role="region" aria-label="<?php echo esc_attr__('Buchungsassistent', 'ltl-bookings'); ?>" data-ltlb-start-mode="<?php echo esc_attr( $start_mode ); ?>" data-ltlb-prefill-service="<?php echo esc_attr( $prefill_service_id ); ?>">
```

**Why:** Code base language MUST be English, not German. WordPress.org requires this.

---

## 🔴 BLOCKER #2: Fix Duplicate Div Elements

**File:** `public/Templates/wizard.php`  
**Lines:** 31-32  
**Issue:** Two consecutive opening `<div class="ltlb-booking">` tags with different aria-labels  
**Action:** Delete line 31 (the one with German), keep line 32 (with "Booking Wizard")

**Result:** Should have exactly 1 matching opening and closing div pair

---

## 🟡 RECOMMENDED: Standardize Service Translation

**File:** `Includes/Util/I18n.php`  
**Lines:** 73, 143, 281, 328  
**Issue:** Inconsistent German terms - sometimes "Service", sometimes "Leistungen"  
**Action:** Pick ONE term and update all entries to use it consistently

Suggested: Keep "Service" (simpler) or change all to "Leistungen" (more natural German)

---

## ✅ Everything Else Looks Good

- All textdomains are correct (`'ltl-bookings'`)
- All admin pages properly localized
- Mode-aware terminology working correctly
- JavaScript strings properly localized
- No missing i18n wrapping found

---

## Testing After Fixes

1. Delete line 31 from wizard.php
2. View frontend booking form in German
3. Check admin pages in German
4. Verify both Appointments and Hotel modes work
5. Retest with fresh browser (clear cache)

