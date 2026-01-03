# Design System Implementation

**Scope:** Summary of the design system foundation and instructions for applying it to new components and pages.  
**Non-Scope:** Design principles (see [Design System](../explanation/design-system.md)) or CSS optimization (see [CSS Optimization](css-optimization.md)).

---

## ✅ COMPLETED (Phase 1-6)

### New Files Created

```
assets/css/
├── tokens.css       ← Design tokens (colors, spacing, typography, etc.)
├── base.css         ← Reset, typography, focus management, utilities
├── components.css   ← UI components (buttons, badges, alerts, cards, forms, etc.)
└── layout.css       ← Layout patterns (page header, settings, wizard, grids)
```

### Files Modified

```
Includes/Core/Plugin.php    ← Updated enqueue_admin_assets()
public/Shortcodes.php       ← Updated enqueue_public_assets()
```

### Documentation Created

```
docs/DESIGN_SYSTEM.md       ← Complete design system documentation
```

---

## 🎨 WHAT'S NEW

### Design Tokens (`--ltlb-*`)

All design decisions are now centralized as CSS variables:

- **Colors:** Brand, semantic (success/warning/danger/info), neutrals
- **Spacing:** 4px grid system (--ltlb-space-1 through --ltlb-space-24)
- **Typography:** Font sizes, weights, line heights, letter spacing
- **Radius:** 5 sizes (sm, md, lg, xl, full)
- **Shadows:** 5 levels (sm, md, lg, xl, 2xl)
- **Z-index:** Layered system (dropdown → sticky → modal → toast)
- **Dark Mode:** Automatic via `@media (prefers-color-scheme: dark)`

### Component Library

Ready-to-use classes for common UI patterns:

| Component | Classes | Example |
|-----------|---------|---------|
| **Buttons** | `.ltlb-btn`, `.ltlb-btn--primary`, `.ltlb-btn--secondary`, `.ltlb-btn--ghost`, `.ltlb-btn--danger` | `<button class="ltlb-btn ltlb-btn--primary">Save</button>` |
| **Badges** | `.ltlb-badge`, `.ltlb-badge--success`, `.ltlb-badge--warning`, `.ltlb-badge--danger` | `<span class="ltlb-badge ltlb-badge--success">Confirmed</span>` |
| **Alerts** | `.ltlb-alert`, `.ltlb-alert--success`, `.ltlb-alert--warning`, `.ltlb-alert--danger`, `.ltlb-alert--info` | `<div class="ltlb-alert ltlb-alert--success">...</div>` |
| **Cards** | `.ltlb-card`, `.ltlb-card--elevated`, `.ltlb-card--flat` | `<div class="ltlb-card">...</div>` |
| **Forms** | `.ltlb-input`, `.ltlb-select`, `.ltlb-textarea`, `.ltlb-label`, `.ltlb-help-text`, `.ltlb-error-text` | `<input class="ltlb-input" />` |
| **Tables** | `.ltlb-table`, `.ltlb-table--striped`, `.ltlb-table--hoverable` | `<table class="ltlb-table ltlb-table--hoverable">` |
| **Empty States** | `.ltlb-empty-state` | `<div class="ltlb-empty-state">...</div>` |
| **Spinners** | `.ltlb-spinner`, `.ltlb-spinner--sm`, `.ltlb-spinner--lg` | `<span class="ltlb-spinner"></span>` |

### Layout Patterns

Pre-built layout structures:

- **Page Header** (`.ltlb-page-header`) - Admin page top with title and actions
- **Settings Layout** (`.ltlb-settings-layout`) - Two-column with sidebar nav
- **Booking Wizard** (`.ltlb-booking-wizard`) - Multi-step progress indicator
- **KPI Grid** (`.ltlb-kpi-grid`) - Dashboard metrics cards
- **Grids** (`.ltlb-grid--2/3/4`) - Responsive column grids

---

## 🔄 BACKWARD COMPATIBILITY

### Legacy Token Aliases

Old `--lazy-*` tokens are aliased to new `--ltlb-*` tokens:

```css
:root {
  --lazy-primary: var(--ltlb-primary);
  --lazy-space-4: var(--ltlb-space-1);
  /* ... etc ... */
}
```

**Deprecation Timeline:**
- v2.0.0 (now): Aliases active, both work
- v2.5.0 (Q1 2026): Add deprecation warnings in browser console
- v3.0.0 (Q2 2026): Remove aliases

### Old Classes Still Work

Existing classes like `.button-primary`, `.button-secondary` are NOT removed. New components use `.ltlb-btn--*` but old ones continue to function.

---

## 📋 NEXT STEPS (Phase 7-10)

### Phase 7: Admin Pages Refactor (Progressive)

Priority order (safest first):

1. **Dashboard** (Low risk, high visibility)
   - Replace KPI cards with `.ltlb-kpi-grid`
   - Use `.ltlb-badge` for status labels
   - Apply `.ltlb-table--hoverable` to recent bookings

2. **Settings** (Low risk, isolated)
   - Implement `.ltlb-settings-layout` two-column
   - Replace buttons with `.ltlb-btn--*`
   - Use `.ltlb-alert` for save confirmations

3. **Design Page** (Low risk, perfect showcase)
   - Add component playground/preview
   - Show all new components with live examples

4. **Services/Resources** (Medium risk)
   - Forms → `.ltlb-form-group` + `.ltlb-input`
   - Tables → `.ltlb-table`
   - Empty states → `.ltlb-empty-state`

5. **Appointments** (High risk, critical)
   - Test thoroughly before deployment
   - Keep rollback plan

6. **Calendar** (High risk, complex)
   - Last to refactor
   - Extensive testing required

### Phase 8: Frontend Refactor

1. **Booking Wizard**
   - Apply `.ltlb-booking-wizard` structure
   - Use `.ltlb-wizard-progress`
   - Replace buttons with `.ltlb-btn--*`

2. **Service Cards**
   - Use `.ltlb-card--elevated`
   - Apply hover effects

3. **Alerts/Messages**
   - Replace with `.ltlb-alert--*`

### Phase 9: Testing

- [ ] Visual regression (Percy/BackstopJS)
- [ ] A11y audit (axe-core, WAVE)
- [ ] Cross-browser (Chrome, Firefox, Safari, Edge)
- [ ] Mobile (320px, 768px, 1024px)
- [ ] Lighthouse score ≥90

### Phase 10: Cleanup & Documentation

- [ ] Create Style Guide admin page (`/admin.php?page=ltlb_styleguide`)
- [ ] Remove legacy aliases (v3.0.0)
- [ ] CSS optimization (PurgeCSS, minification)

---

## 🚀 QUICK START GUIDE

### For Developers: Using New Components

**Old Way:**
```html
<a href="#" class="button-primary">Save Changes</a>
```

**New Way:**
```html
<button class="ltlb-btn ltlb-btn--primary">Save Changes</button>
```

**Buttons with loading state:**
```html
<button class="ltlb-btn ltlb-btn--primary is-loading">Processing...</button>
```

**Status badges:**
```html
<span class="ltlb-badge ltlb-badge--success">Confirmed</span>
<span class="ltlb-badge ltlb-badge--warning">Pending</span>
<span class="ltlb-badge ltlb-badge--danger">Cancelled</span>
```

**Form validation:**
```html
<div class="ltlb-form-group">
  <label class="ltlb-label ltlb-label--required" for="email">Email</label>
  <input type="email" id="email" class="ltlb-input has-error">
  <span class="ltlb-error-text">Please enter a valid email.</span>
</div>
```

### Using Tokens in Custom CSS

**Old:**
```css
.my-element {
  color: #2271b1;
  padding: 16px;
  border-radius: 8px;
}
```

**New:**
```css
.my-element {
  color: var(--ltlb-primary);
  padding: var(--ltlb-space-4);
  border-radius: var(--ltlb-radius-lg);
}
```

---

## 📚 DOCUMENTATION

**Full Documentation:**
- `/docs/DESIGN_SYSTEM.md` - Complete reference (this file)

**Component Examples:**
- See sections in DESIGN_SYSTEM.md for markup examples
- Style Guide page coming in Phase 10

**Token Reference:**
- All tokens defined in `/assets/css/tokens.css`
- Grouped by category (colors, spacing, typography, etc.)

---

## ⚠️ IMPORTANT NOTES

1. **Cache Busting:** Version numbers use file modification time in WP_DEBUG mode
2. **CSS Load Order:** tokens → base → components → layout → admin/public
3. **Dark Mode:** Automatic via `prefers-color-scheme`, no manual toggle yet
4. **Focus States:** Uses `:focus-visible` (keyboard-only), requires modern browsers
5. **Grid System:** 4px-based (consistency over arbitrary values)

---

## 🔧 TROUBLESHOOTING

### Styles Not Updating?
- Hard refresh: `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
- Clear browser cache
- Check console for CSS load errors

### Old Token Not Working?
- Check if legacy alias exists in `tokens.css`
- Use new `--ltlb-*` tokens instead

### Component Not Rendering Correctly?
- Verify CSS files are enqueued (check Network tab)
- Check for conflicting theme styles
- Ensure proper HTML structure (see DESIGN_SYSTEM.md examples)

---

## 📞 SUPPORT

For questions or issues:
1. Check `/docs/DESIGN_SYSTEM.md` first
2. Review component examples
3. Test in isolation (remove theme/plugin conflicts)

---

**Last Updated:** 2025-12-19  
**Version:** 2.0.0  
**Status:** ✅ Ready for Phase 7 (Admin Refactoring)
