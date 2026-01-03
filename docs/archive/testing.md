# Testing Guide

**Scope:** Visual regression, accessibility, and functional testing procedures for the design system and core features.  
**Non-Scope:** Unit testing or server-side performance benchmarking.

---

## 📋 OVERVIEW

This guide provides comprehensive testing procedures for the LazyBookings design system implementation. All tests should be performed before deploying to production.

---

## 1. 🎨 VISUAL REGRESSION TESTING

### Tools
- **Percy.io** (Recommended) - Visual testing platform
- **BackstopJS** (Alternative) - Open-source visual regression tool
- **Playwright** - For automated screenshot capture

### Setup (BackstopJS)

```bash
# Install BackstopJS
npm install -g backstopjs

# Initialize in plugin directory
cd /path/to/ltl-bookings
backstop init
```

### Configuration (`backstop.json`)

```json
{
  "id": "ltlb_design_system",
  "viewports": [
    {
      "label": "phone",
      "width": 320,
      "height": 480
    },
    {
      "label": "tablet",
      "width": 768,
      "height": 1024
    },
    {
      "label": "desktop",
      "width": 1920,
      "height": 1080
    }
  ],
  "scenarios": [
    {
      "label": "Dashboard",
      "url": "http://localhost/wp-admin/admin.php?page=ltlb_dashboard",
      "readySelector": ".ltlb-kpi-grid"
    },
    {
      "label": "Style Guide",
      "url": "http://localhost/wp-admin/admin.php?page=ltlb_styleguide",
      "readySelector": ".ltlb-btn"
    },
    {
      "label": "Booking Wizard",
      "url": "http://localhost/booking-page",
      "readySelector": ".ltlb-booking"
    }
  ],
  "paths": {
    "bitmaps_reference": "backstop_data/bitmaps_reference",
    "bitmaps_test": "backstop_data/bitmaps_test",
    "engine_scripts": "backstop_data/engine_scripts",
    "html_report": "backstop_data/html_report"
  },
  "report": ["browser"],
  "engine": "puppeteer"
}
```

### Test Scenarios to Cover

#### Admin Pages
- [ ] Dashboard (Appointments mode)
- [ ] Dashboard (Hotel mode)
- [ ] Style Guide page
- [ ] Settings page
- [ ] Services page
- [ ] Appointments page
- [ ] Calendar page
- [ ] Design page

#### Frontend
- [ ] Booking wizard (all steps)
- [ ] Service cards
- [ ] Confirmation page

#### Component States
- [ ] Buttons (hover, active, disabled)
- [ ] Forms (focus, error, disabled)
- [ ] Tables (empty, populated, hoverable)
- [ ] Alerts (all types)
- [ ] Badges (all colors)
- [ ] Modals (open, closed)

### Run Tests

```bash
# Create reference screenshots
backstop reference

# Run tests (compare against reference)
backstop test

# Approve changes
backstop approve
```

---

## 2. ♿ ACCESSIBILITY TESTING

### Automated Tools

#### 1. axe DevTools (Browser Extension)
- Install: [Chrome](https://chrome.google.com/webstore/detail/axe-devtools) | [Firefox](https://addons.mozilla.org/en-US/firefox/addon/axe-devtools/)
- Run on all admin pages
- Fix all Critical and Serious issues

#### 2. WAVE (Web Accessibility Evaluation Tool)
- Install: [WAVE Extension](https://wave.webaim.org/extension/)
- Check for errors, alerts, and contrast issues

#### 3. Lighthouse (Chrome DevTools)
- Open DevTools → Lighthouse tab
- Run Accessibility audit
- Target score: **≥95**

### Manual Testing Checklist

#### Keyboard Navigation
- [ ] All interactive elements reachable via Tab key
- [ ] Focus visible on all elements (`:focus-visible` styles)
- [ ] Escape key closes modals
- [ ] Enter/Space activates buttons
- [ ] Arrow keys navigate within components (where appropriate)

#### Screen Reader Testing
**Tool:** NVDA (Windows) or VoiceOver (Mac)

- [ ] All buttons have descriptive labels
- [ ] Form inputs have associated labels
- [ ] Error messages announced
- [ ] Status changes announced (ARIA live regions)
- [ ] Landmark regions properly defined
- [ ] Heading hierarchy correct (h1 → h2 → h3)

#### Color Contrast
**Tool:** [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)

- [ ] Text color vs background: **≥4.5:1** (AA)
- [ ] Large text (≥18px): **≥3:1** (AA)
- [ ] Interactive elements: **≥3:1** (focus indicators)
- [ ] Disabled state still distinguishable

#### Form Accessibility
- [ ] All inputs have `<label>` tags
- [ ] Required fields marked with `required` attribute
- [ ] Error messages use `aria-describedby`
- [ ] Fieldsets with legends for radio/checkbox groups
- [ ] Autocomplete attributes on personal data fields

### Automated Test Script

Create `tests/accessibility.test.js`:

```javascript
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  
  const pages = [
    'admin.php?page=ltlb_dashboard',
    'admin.php?page=ltlb_styleguide',
    'admin.php?page=ltlb_settings',
  ];
  
  for (const url of pages) {
    await page.goto(`http://localhost/wp-admin/${url}`);
    
    const results = await new AxeBuilder({ page }).analyze();
    
    console.log(`\n📄 ${url}`);
    console.log(`✅ Passes: ${results.passes.length}`);
    console.log(`❌ Violations: ${results.violations.length}`);
    
    if (results.violations.length > 0) {
      console.log('\n🚨 Violations:');
      results.violations.forEach(v => {
        console.log(`  - ${v.id}: ${v.description}`);
        console.log(`    Impact: ${v.impact}`);
        console.log(`    Nodes: ${v.nodes.length}`);
      });
    }
  }
  
  await browser.close();
})();
```

Run with:
```bash
npm install playwright @axe-core/playwright
node tests/accessibility.test.js
```

---

## 3. 🌐 CROSS-BROWSER TESTING

### Target Browsers

| Browser | Minimum Version | Market Share |
|---------|----------------|--------------|
| Chrome | 90+ | ~65% |
| Firefox | 88+ | ~10% |
| Safari | 14+ | ~20% |
| Edge | 90+ | ~5% |

### Testing Matrix

#### Desktop
- [ ] Chrome (Windows)
- [ ] Firefox (Windows)
- [ ] Safari (macOS)
- [ ] Edge (Windows)

#### Mobile
- [ ] Safari (iOS 14+)
- [ ] Chrome (Android 10+)

### Test Checklist (Each Browser)

#### Layout & Rendering
- [ ] CSS Grid renders correctly
- [ ] Flexbox layouts work
- [ ] CSS custom properties applied
- [ ] Shadows and borders render
- [ ] Animations smooth (no jank)

#### Interactive Elements
- [ ] Buttons clickable
- [ ] Forms submittable
- [ ] Dropdowns functional
- [ ] Modals open/close
- [ ] Tooltips appear

#### CSS Features
- [ ] `:focus-visible` works
- [ ] `clamp()` functions work
- [ ] CSS Grid `auto-fit`/`auto-fill` works
- [ ] Custom properties cascade
- [ ] Media queries trigger

### BrowserStack Setup (Optional)

```javascript
// browserstack.config.js
module.exports = {
  user: process.env.BROWSERSTACK_USERNAME,
  key: process.env.BROWSERSTACK_ACCESS_KEY,
  
  capabilities: [{
    browser: 'Chrome',
    browser_version: 'latest',
    os: 'Windows',
    os_version: '10'
  }, {
    browser: 'Firefox',
    browser_version: 'latest',
    os: 'Windows',
    os_version: '10'
  }, {
    browser: 'Safari',
    browser_version: 'latest',
    os: 'OS X',
    os_version: 'Big Sur'
  }]
};
```

---

## 4. 📱 MOBILE RESPONSIVENESS

### Test Viewports

| Device | Width | Height | DPR |
|--------|-------|--------|-----|
| iPhone SE | 375px | 667px | 2x |
| iPhone 12/13 | 390px | 844px | 3x |
| iPad | 768px | 1024px | 2x |
| iPad Pro | 1024px | 1366px | 2x |
| Galaxy S21 | 360px | 800px | 3x |

### Chrome DevTools Testing

1. Open DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Test each viewport
4. Check landscape/portrait modes

### Mobile Test Checklist

#### Layout
- [ ] No horizontal scroll
- [ ] Content fits viewport
- [ ] Text readable without zoom
- [ ] Images scale proportionally
- [ ] Buttons minimum 44x44px (touch target)

#### Navigation
- [ ] Hamburger menu (if applicable)
- [ ] Links easily tappable
- [ ] No overlapping elements
- [ ] Sticky headers work
- [ ] Scroll smooth

#### Forms
- [ ] Input fields large enough
- [ ] Keyboard doesn't obscure inputs
- [ ] Proper input types (email, tel, etc.)
- [ ] Submit button visible
- [ ] Error messages visible

#### Performance
- [ ] Fast initial load (<3s)
- [ ] Smooth scrolling
- [ ] No layout shifts (CLS <0.1)
- [ ] Images optimized
- [ ] Fonts load quickly

### Responsive Breakpoints

```css
/* Phone */
@media (max-width: 767px) { }

/* Tablet */
@media (min-width: 768px) and (max-width: 1023px) { }

/* Desktop */
@media (min-width: 1024px) { }

/* Large Desktop */
@media (min-width: 1440px) { }
```

---

## 5. ⚡ PERFORMANCE TESTING

### Google Lighthouse

**Target Scores:**
- Performance: **≥90**
- Accessibility: **≥95**
- Best Practices: **≥90**
- SEO: **≥90**

### Running Lighthouse

```bash
# Install Lighthouse CLI
npm install -g lighthouse

# Run audit
lighthouse http://localhost/wp-admin/admin.php?page=ltlb_styleguide \
  --output html \
  --output-path ./lighthouse-report.html \
  --chrome-flags="--headless"
```

### Performance Metrics

| Metric | Target | Critical |
|--------|--------|----------|
| First Contentful Paint | <1.8s | <3.0s |
| Largest Contentful Paint | <2.5s | <4.0s |
| Total Blocking Time | <200ms | <600ms |
| Cumulative Layout Shift | <0.1 | <0.25 |
| Speed Index | <3.4s | <5.8s |

### CSS Performance Checklist

- [ ] Critical CSS inlined
- [ ] Non-critical CSS deferred
- [ ] Unused CSS removed
- [ ] CSS minified
- [ ] Files gzipped
- [ ] HTTP/2 enabled
- [ ] CSS file size <100KB

### JavaScript Performance

- [ ] No render-blocking scripts
- [ ] Scripts deferred/async
- [ ] Code splitting implemented
- [ ] Third-party scripts lazy-loaded

### Image Optimization

- [ ] WebP format used
- [ ] Lazy loading enabled
- [ ] Proper sizing (no oversized images)
- [ ] Compressed (<100KB per image)

### Font Loading

```css
/* Optimal font loading strategy */
@font-face {
  font-family: 'CustomFont';
  src: url('font.woff2') format('woff2');
  font-display: swap; /* FOIT prevention */
}
```

---

## 6. 🧪 MANUAL TESTING SCENARIOS

### User Flows

#### 1. Book an Appointment
1. Navigate to booking page
2. Select service
3. Choose date/time
4. Fill customer info
5. Confirm booking
6. Verify success message

**Expected:** Smooth flow, clear feedback, no errors

#### 2. Admin Dashboard Review
1. Login as admin
2. Navigate to dashboard
3. Check KPI cards
4. Review latest appointments
5. Generate AI report

**Expected:** All components render, data displays correctly

#### 3. Style Guide Review
1. Open style guide page
2. Interact with all components
3. Test button states
4. Check form validation
5. View responsive layouts

**Expected:** All examples work, no console errors

### Error Handling

- [ ] Network error displays message
- [ ] Form validation shows errors
- [ ] 404 pages styled
- [ ] Empty states show helpful content
- [ ] Loading states visible

---

## 7. 📊 TEST REPORTING

### Test Report Template

```markdown
# Design System Test Report

**Date:** 2024-12-19
**Tester:** [Name]
**Environment:** [Local/Staging/Production]

## Summary
- Total Tests: X
- Passed: X
- Failed: X
- Pass Rate: X%

## Visual Regression
- [ ] No unexpected visual changes
- [ ] All components render correctly
- [ ] Responsive layouts intact

## Accessibility
- Lighthouse Score: X/100
- axe Violations: X
- WCAG Level: AA ✅

## Cross-Browser
- Chrome: ✅ Pass
- Firefox: ✅ Pass
- Safari: ✅ Pass
- Edge: ✅ Pass

## Performance
- LCP: X.Xs
- FID: Xms
- CLS: X.XX
- Overall: X/100

## Issues Found
1. [Issue description]
   - Severity: High/Medium/Low
   - Browser: [Browser]
   - Steps to reproduce: [...]

## Recommendations
- [Recommendation 1]
- [Recommendation 2]
```

---

## 8. 🚀 CI/CD INTEGRATION

### GitHub Actions Workflow

Create `.github/workflows/design-system-tests.yml`:

```yaml
name: Design System Tests

on:
  pull_request:
    branches: [ main ]
  push:
    branches: [ Phase6-Full-Auto-Polish-and-Dev ]

jobs:
  accessibility:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm install
      - run: npm run test:a11y
      
  visual-regression:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
      - run: npm install
      - run: npm run test:visual
      
  lighthouse:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: treosh/lighthouse-ci-action@v9
        with:
          urls: |
            http://localhost/wp-admin/admin.php?page=ltlb_styleguide
          uploadArtifacts: true
```

---

## 9. ✅ SIGN-OFF CHECKLIST

Before marking Phase 9 complete:

### Visual Testing
- [ ] All pages screenshot tested
- [ ] No visual regressions detected
- [ ] Mobile layouts verified

### Accessibility
- [ ] Lighthouse accessibility ≥95
- [ ] Zero critical axe violations
- [ ] Keyboard navigation works
- [ ] Screen reader compatible

### Cross-Browser
- [ ] Chrome tested
- [ ] Firefox tested
- [ ] Safari tested
- [ ] Edge tested
- [ ] No major issues found

### Mobile
- [ ] Phone viewports tested
- [ ] Tablet viewports tested
- [ ] Touch interactions work
- [ ] No layout issues

### Performance
- [ ] Lighthouse performance ≥90
- [ ] LCP <2.5s
- [ ] CLS <0.1
- [ ] CSS optimized

---

## 📞 SUPPORT

For testing issues:
1. Check browser console for errors
2. Review test logs
3. Compare against reference screenshots
4. Consult DESIGN_SYSTEM.md

**Status:** ✅ Testing framework complete and ready to use
