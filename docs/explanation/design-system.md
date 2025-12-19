# Design System

**Scope:** Principles, tokens, and component library for the LazyBookings admin UI and frontend components.  
**Non-Scope:** Specific implementation details for individual pages (see [Design System Implementation](../how-to/design-system-implementation.md)).

---

## 📋 IMPLEMENTATION CHECKLIST

### Phase 1: Token Foundation ✅ COMPLETE
- [x] Create `assets/css/tokens.css` with all CSS variables
- [x] Define color tokens (brand, semantic, neutral)
- [x] Define spacing tokens (4px grid)
- [x] Define typography tokens
- [x] Define radius, shadow, z-index tokens
- [x] Add dark mode support (`@media (prefers-color-scheme: dark)`)

### Phase 2: Base Styles ✅ COMPLETE
- [x] Create `assets/css/base.css`
- [x] CSS Reset (normalize)
- [x] Typography base styles
- [x] Focus visible styles
- [x] Utility classes (text align, display, margin/padding)

### Phase 3: Component Library ✅ COMPLETE
- [x] Create `assets/css/components.css`
- [x] Buttons (`.ltlb-btn`, variants, sizes, states)
- [x] Badges (`.ltlb-badge`, semantic colors)
- [x] Alerts (`.ltlb-alert`, success/warning/danger/info)
- [x] Cards (`.ltlb-card`, elevated, flat)
- [x] Forms (`.ltlb-input`, `.ltlb-select`, `.ltlb-textarea`)
- [x] Tables (`.ltlb-table`, striped, hoverable)
- [x] Empty States (`.ltlb-empty-state`)
- [x] Spinners (`.ltlb-spinner`)
- [x] Tooltips (`.ltlb-tooltip`)
- [x] Pagination (`.ltlb-pagination`)
- [x] Modal (`.ltlb-modal`)

### Phase 4: Layout Patterns ✅ COMPLETE
- [x] Create `assets/css/layout.css`
- [x] Page Header (`.ltlb-page-header`)
- [x] Settings Layout (`.ltlb-settings-layout`)
- [x] Wizard Layout (`.ltlb-booking-wizard`)
- [x] Grid utilities (`.ltlb-grid`)
- [x] KPI Grid (`.ltlb-kpi-grid`)
- [x] Container/Stack/Cluster utilities

### Phase 5: Legacy Compatibility ✅ COMPLETE
- [x] Add token aliases in `tokens.css` (`--lazy-*` → `--ltlb-*`)
- [x] Keep existing classes for backward compatibility
- [x] Add deprecation warnings (CSS comments)

### Phase 6: Enqueue New Files ✅ COMPLETE
- [x] Update `Plugin.php` to enqueue new CSS files
- [x] Update `Shortcodes.php` to enqueue new CSS files
- [x] Order: tokens → base → components → layout → admin/public
- [x] Add version control for cache busting

### Phase 7: Admin Pages Refactor ✅ COMPLETE
- [x] Dashboard: Convert to new components (Appointments + Hotel)
- [x] Appointments: Update table styles and buttons
- [x] Calendar: Auto-sort button updated
- [x] Services: Form components update
- [x] Resources: Button updates
- [x] Staff: Button updates (primary/secondary/danger)
- [x] Settings: Advanced toggle buttons
- [x] Design: Component showcase added

### Phase 8: Frontend Refactor ✅ COMPLETE
- [x] Booking Wizard: Update all navigation buttons
- [x] Service Cards: Ready for `.ltlb-card` usage
- [x] Buttons: All replaced with `.ltlb-btn`
- [x] Shortcodes: Primary action buttons updated

### Phase 9: Testing ✅ COMPLETE
- [x] Create comprehensive testing guide (docs/TESTING_GUIDE.md)
- [x] Visual regression tests framework (BackstopJS configuration)
- [x] Accessibility audit framework (axe-core + WAVE + Lighthouse)
- [x] Cross-browser testing matrix (Chrome, Firefox, Safari, Edge)
- [x] Mobile responsiveness checklist (5 viewport sizes)
- [x] Performance testing procedures (Lighthouse CLI integration)
- [x] Manual testing scenarios (keyboard navigation, dark mode, RTL)
- [x] CI/CD integration templates (GitHub Actions workflow)

### Phase 10: Optimization & Cleanup ✅ COMPLETE
- [x] Create Style Guide admin page (`ltlb_styleguide`)
- [x] Component playground with copy-paste examples
- [x] Add migration notes to admin.css and public.css
- [x] Document legacy token deprecation timeline
- [x] Create CSS optimization guide (docs/CSS_OPTIMIZATION_GUIDE.md)
- [x] Build CSS minification script (scripts/build-css.js)
- [x] Configure PurgeCSS for unused CSS removal
- [x] Setup Stylelint for CSS quality checks
- [x] Update Plugin.php to load minified CSS in production
- [x] Update Shortcodes.php to load minified CSS in production
- [x] Create package.json with build scripts
- ⏰ Remove legacy token aliases (scheduled for v3.0.0)

---

## 🎨 BRAND IDENTITY

### Colors

**Primary:**
- `--ltlb-primary`: `#2271b1` (WP Admin Blue)
- `--ltlb-primary-hover`: `#135e96`
- `--ltlb-primary-active`: `#0a3e5f`

**Accent:**
- `--ltlb-accent`: `#ffcc00` (Lazy Yellow)
- `--ltlb-accent-hover`: `#e6b800`

**Semantic:**
- Success: `#2ea44f`
- Warning: `#fb8500`
- Danger: `#d1242f`
- Info: `#0969da`

**Neutrals (Light Mode):**
- Background: `#ffffff`, `#f9f9f9`, `#f4f4f4`
- Text: `#1d2327`, `#646970`, `#999999`
- Borders: `rgba(0,0,0,0.06)`, `rgba(0,0,0,0.1)`, `#ccd0d4`

### Typography

**Font Stack:**
```
-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif
```

**Scale:**
- xs: 12px
- sm: 14px
- base: 16px
- lg: 18px
- xl: 20px
- 2xl: 24px
- 3xl: 30px

**Weights:**
- normal: 400
- medium: 500
- semibold: 600
- bold: 700
- extrabold: 800

### Spacing (4px Grid)

```
--ltlb-space-1: 4px
--ltlb-space-2: 8px
--ltlb-space-3: 12px
--ltlb-space-4: 16px
--ltlb-space-5: 20px
--ltlb-space-6: 24px
--ltlb-space-8: 32px
--ltlb-space-10: 40px
--ltlb-space-12: 48px
--ltlb-space-16: 64px
```

### Radius

```
--ltlb-radius-sm: 4px
--ltlb-radius-md: 6px
--ltlb-radius-lg: 8px
--ltlb-radius-xl: 12px
--ltlb-radius-full: 9999px
```

---

## 🧩 COMPONENT LIBRARY

### Buttons

**Classes:**
- `.ltlb-btn` - Base button
- `.ltlb-btn--primary` - Primary action
- `.ltlb-btn--secondary` - Secondary action (outlined)
- `.ltlb-btn--ghost` - Tertiary action (transparent)
- `.ltlb-btn--danger` - Destructive action
- `.ltlb-btn--sm` - Small size
- `.ltlb-btn--lg` - Large size

**States:**
- `:hover` - Elevated, darker background
- `:focus-visible` - Focus ring
- `:active` - Pressed state
- `:disabled` / `.is-disabled` - Reduced opacity, no pointer events
- `.is-loading` - Shows spinner, text hidden

**Example:**
```html
<button class="ltlb-btn ltlb-btn--primary">Save Changes</button>
<button class="ltlb-btn ltlb-btn--secondary">Cancel</button>
<button class="ltlb-btn ltlb-btn--ghost ltlb-btn--sm">Learn More</button>
<button class="ltlb-btn ltlb-btn--danger" disabled>Delete</button>
<button class="ltlb-btn ltlb-btn--primary is-loading">Processing...</button>
```

---

### Badges

**Classes:**
- `.ltlb-badge` - Base badge
- `.ltlb-badge--success` - Green (Confirmed)
- `.ltlb-badge--warning` - Orange (Pending)
- `.ltlb-badge--danger` - Red (Cancelled)
- `.ltlb-badge--info` - Blue (Info)
- `.ltlb-badge--neutral` - Gray (Inactive)

**Example:**
```html
<span class="ltlb-badge ltlb-badge--success">Confirmed</span>
<span class="ltlb-badge ltlb-badge--warning">Pending</span>
<span class="ltlb-badge ltlb-badge--danger">Cancelled</span>
```

---

### Alerts

**Classes:**
- `.ltlb-alert` - Base alert
- `.ltlb-alert--success` - Success message
- `.ltlb-alert--warning` - Warning message
- `.ltlb-alert--danger` - Error message
- `.ltlb-alert--info` - Informational message

**Structure:**
```html
<div class="ltlb-alert ltlb-alert--success">
  <svg class="ltlb-alert__icon">...</svg>
  <div class="ltlb-alert__content">
    <div class="ltlb-alert__title">Success!</div>
    <div>Your changes have been saved.</div>
  </div>
</div>
```

---

### Cards

**Classes:**
- `.ltlb-card` - Base card
- `.ltlb-card--elevated` - Elevated shadow, hover effect
- `.ltlb-card--flat` - No shadow

**Structure:**
```html
<div class="ltlb-card">
  <div class="ltlb-card__header">
    <h2 class="ltlb-card__title">Card Title</h2>
  </div>
  <div class="ltlb-card__body">
    Content goes here
  </div>
  <div class="ltlb-card__footer">
    Footer content
  </div>
</div>
```

---

### Forms

**Classes:**
- `.ltlb-input` - Text input
- `.ltlb-select` - Select dropdown
- `.ltlb-textarea` - Textarea
- `.ltlb-label` - Form label
- `.ltlb-label--required` - Required field indicator
- `.ltlb-help-text` - Help text below field
- `.ltlb-error-text` - Error message
- `.has-error` - Error state modifier

**Example:**
```html
<div class="ltlb-form-group">
  <label class="ltlb-label ltlb-label--required" for="email">Email</label>
  <input type="email" id="email" class="ltlb-input" placeholder="your@email.com">
  <span class="ltlb-help-text">We'll never share your email.</span>
</div>

<div class="ltlb-form-group">
  <label class="ltlb-label" for="message">Message</label>
  <textarea id="message" class="ltlb-textarea has-error"></textarea>
  <span class="ltlb-error-text">Message is required.</span>
</div>
```

---

### Tables

**Classes:**
- `.ltlb-table` - Base table
- `.ltlb-table--striped` - Alternating row colors
- `.ltlb-table--hoverable` - Hover effect on rows
- `.ltlb-table-wrapper` - Responsive scroll wrapper

**Example:**
```html
<div class="ltlb-table-wrapper">
  <table class="ltlb-table ltlb-table--hoverable">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>John Doe</td>
        <td>john@example.com</td>
        <td><span class="ltlb-badge ltlb-badge--success">Active</span></td>
      </tr>
    </tbody>
  </table>
</div>
```

---

### Empty States

**Classes:**
- `.ltlb-empty-state` - Container
- `.ltlb-empty-state__icon` - Icon/illustration
- `.ltlb-empty-state__title` - Heading
- `.ltlb-empty-state__description` - Body text
- `.ltlb-empty-state__action` - CTA button

**Example:**
```html
<div class="ltlb-empty-state">
  <svg class="ltlb-empty-state__icon">...</svg>
  <h3 class="ltlb-empty-state__title">No appointments yet</h3>
  <p class="ltlb-empty-state__description">
    Start by creating your first service or accepting bookings.
  </p>
  <div class="ltlb-empty-state__action">
    <a href="#" class="ltlb-btn ltlb-btn--primary">Create Service</a>
  </div>
</div>
```

---

### Spinners

**Classes:**
- `.ltlb-spinner` - Default (24px)
- `.ltlb-spinner--sm` - Small (16px)
- `.ltlb-spinner--lg` - Large (40px)

**Example:**
```html
<span class="ltlb-spinner"></span>
<span class="ltlb-spinner ltlb-spinner--sm"></span>
<span class="ltlb-spinner ltlb-spinner--lg"></span>
```

---

## 📐 LAYOUT PATTERNS

### Page Header

**Usage:** Admin page top section with title and actions.

**Example:**
```html
<div class="ltlb-page-header">
  <div class="ltlb-page-header__breadcrumb">
    <a href="#">LazyBookings</a> › <span>Appointments</span>
  </div>
  <div class="ltlb-page-header__title">
    <h1>Appointments</h1>
  </div>
  <div class="ltlb-page-header__actions">
    <button class="ltlb-btn ltlb-btn--secondary">Filter</button>
    <button class="ltlb-btn ltlb-btn--primary">New Appointment</button>
  </div>
</div>
```

---

### Settings Layout

**Usage:** Two-column layout with sidebar navigation.

**Example:**
```html
<div class="ltlb-settings-layout">
  <aside class="ltlb-settings-sidebar">
    <nav class="ltlb-settings-nav">
      <a href="#" class="ltlb-settings-nav__item is-active">General</a>
      <a href="#" class="ltlb-settings-nav__item">Email</a>
      <a href="#" class="ltlb-settings-nav__item">Payments</a>
    </nav>
  </aside>
  <main class="ltlb-settings-content">
    <div class="ltlb-card">
      <!-- Settings form -->
    </div>
  </main>
</div>
```

---

### Booking Wizard

**Usage:** Frontend multi-step form with progress indicator.

**Example:**
```html
<div class="ltlb-booking-wizard">
  <div class="ltlb-wizard-progress">
    <div class="ltlb-wizard-progress__step is-complete">
      <span class="ltlb-wizard-progress__number">1</span>
      <span class="ltlb-wizard-progress__label">Service</span>
    </div>
    <div class="ltlb-wizard-progress__step is-active">
      <span class="ltlb-wizard-progress__number">2</span>
      <span class="ltlb-wizard-progress__label">Date & Time</span>
    </div>
    <div class="ltlb-wizard-progress__step">
      <span class="ltlb-wizard-progress__number">3</span>
      <span class="ltlb-wizard-progress__label">Details</span>
    </div>
  </div>
  
  <div class="ltlb-wizard-content">
    <!-- Step content -->
  </div>
  
  <div class="ltlb-wizard-nav">
    <button class="ltlb-btn ltlb-btn--secondary">Back</button>
    <button class="ltlb-btn ltlb-btn--primary">Continue</button>
  </div>
</div>
```

---

## ♿ ACCESSIBILITY REQUIREMENTS

### Focus Management
- All interactive elements MUST have visible focus indicator
- Use `:focus-visible` for keyboard-only focus styles
- Focus ring: `0 0 0 3px rgba(34, 113, 177, 0.25)`

### Color Contrast
- Text: ≥4.5:1 (WCAG AA)
- Large text (≥18px or bold ≥14px): ≥3:1
- UI components: ≥3:1

### Keyboard Navigation
- Tab order must be logical
- All actions accessible without mouse
- Escape closes modals/dropdowns
- Enter/Space activates buttons

### Screen Readers
- Form labels properly associated (`for` attribute)
- Error messages announced (`aria-live="polite"`)
- Dynamic content changes announced
- Icon-only buttons have `aria-label`

### Touch Targets
- Minimum 44x44px on mobile
- Adequate spacing between clickable elements

---

## 📱 RESPONSIVE BREAKPOINTS

```css
/* Mobile First */
/* Default: 320px - 767px */

@media (min-width: 640px) {
  /* Small tablets */
}

@media (min-width: 768px) {
  /* Tablets */
}

@media (min-width: 1024px) {
  /* Desktops */
}

@media (min-width: 1280px) {
  /* Large desktops */
}
```

---

## 🚀 MIGRATION GUIDE

### For Developers

**Old vs. New:**

| Old Class | New Class | Notes |
|-----------|-----------|-------|
| `.button-primary` | `.ltlb-btn--primary` | Keep old for backward compat |
| `.button-secondary` | `.ltlb-btn--secondary` | Keep old for backward compat |
| `color: #2271b1` | `color: var(--ltlb-primary)` | Use tokens |
| `padding: 10px` | `padding: var(--ltlb-space-2)` | Use spacing scale |
| `.ltlb-card` (old) | `.ltlb-card` (new) | Refactored structure |

**Step-by-Step:**

1. **Review Component Library:** Understand available components
2. **Use New Classes:** When creating new UI
3. **Refactor Gradually:** Update existing pages one at a time
4. **Test Thoroughly:** Visual regression, A11y, Responsive
5. **Remove Legacy:** After deprecation period

---

## 🧪 TESTING CHECKLIST

### Visual
- [ ] Component renders correctly in Light Mode
- [ ] Component renders correctly in Dark Mode
- [ ] All states visible (hover, focus, active, disabled)
- [ ] Responsive at all breakpoints

### Functional
- [ ] Interactions work as expected
- [ ] Form validation displays correctly
- [ ] AJAX loading states show
- [ ] Keyboard navigation functional

### Accessibility
- [ ] Focus visible on keyboard navigation
- [ ] Color contrast passes WCAG AA
- [ ] Screen reader announces changes
- [ ] Touch targets ≥44x44px

### Performance
- [ ] CSS file size reasonable (<100KB)
- [ ] No layout shift (CLS)
- [ ] Lighthouse score ≥90

---

## 📚 RESOURCES

### Internal
- **[DESIGN_SYSTEM_IMPLEMENTATION.md](./DESIGN_SYSTEM_IMPLEMENTATION.md)** - Quick start & implementation summary
- `/docs/DB_SCHEMA.md` - Database structure
- `/docs/API.md` - REST API endpoints
- `/docs/DESIGN_GUIDE.md` - Old design guide (deprecated, replaced by this file)

### External
- [WordPress Admin UI](https://developer.wordpress.org/block-editor/design/)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [CSS Custom Properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties)

---

## 📝 CHANGELOG

### v2.0.0 (2025-12-19)
- Complete design system overhaul
- New token-based architecture (`--ltlb-*`)
- Component library (buttons, badges, alerts, cards, forms, tables)
- Layout patterns (page header, settings, wizard)
- Accessibility improvements (focus states, contrast, keyboard nav)
- Dark mode support
- Responsive optimizations

### v1.0.0 (Previous)
- Initial design tokens (`--lazy-*`)
- Basic admin and frontend styles
- WordPress theme integration

---

**Status Legend:**
- ✅ Complete
- 🚧 In Progress
- ⏳ Planned
- ❌ Blocked


---

## ?? Frontend Customization (Tokens)

## Token Storage

- Option name: `lazy_design`
- Type: associative array

## Tokens

### Colors

| Key | Meaning |
|-----|---------|
| `background` | Main background color for the booking widget |
| `text` | Primary text color |
| `primary` | Primary button background |
| `primary_hover` | Primary button hover background/border |
| `secondary` | Secondary (outline) button color |
| `secondary_hover` | Secondary button hover color |
| `accent` | Accent color (e.g., highlights, optional gradient end) |
| `border_color` | Borders for inputs/cards |
| `panel_background` | Inner panel / card background |
| `button_text` | Manual primary button text color (only used when `auto_button_text=0`) |

### Shape & Motion

| Key | Meaning |
|-----|---------|
| `border_width` | Border thickness in px |
| `border_radius` | Border radius in px |
| `transition_duration` | Transition duration in ms |
| `enable_animations` | 1/0 toggle for UI transitions |

### Shadows

| Key | Meaning |
|-----|---------|
| `box_shadow_blur` | Shadow blur in px |
| `box_shadow_spread` | Shadow spread in px |
| `shadow_container` | 1/0 shadow on main container |
| `shadow_button` | 1/0 shadow on buttons |
| `shadow_input` | 1/0 shadow on inputs |
| `shadow_card` | 1/0 shadow on cards/panels |

### Extras

| Key | Meaning |
|-----|---------|
| `use_gradient` | 1/0 uses `linear-gradient(primary, accent)` as background |
| `auto_button_text` | 1/0 automatically picks readable text color (black/white) |
| `custom_css` | Extra CSS appended for `.ltlb-booking` scope |

## Defaults

Defaults are created on plugin activation in `lazy_design` and can be adjusted any time via WP Admin → **LazyBookings → Design**.

