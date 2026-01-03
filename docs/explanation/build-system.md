# Build System - LazyBookings v2.0.0

Complete build and optimization pipeline for the LazyBookings design system.

---

## 🚀 QUICK START

### Prerequisites

```bash
# Node.js 14+ required
node --version

# Install dependencies
npm install
```

### Development Workflow

```bash
# Build minified CSS (one-time)
npm run build

# Watch for changes (continuous)
npm run watch

# Lint CSS files
npm run test:css
```

### Production Build

```bash
# Full optimization pipeline
npm run optimize

# Output: assets/css/dist/*.min.css
```

---

## 📦 BUILD SCRIPTS

### `npm run build`

Minifies all CSS files without external dependencies.

**Input:** `assets/css/*.css`  
**Output:** `assets/css/dist/*.min.css`  

**Process:**
1. Remove all comments
2. Remove whitespace & newlines
3. Compress selectors & properties
4. Calculate size reduction

**Result:** 25-32% size reduction

### `npm run purge`

Removes unused CSS using PurgeCSS (requires npm install).

**Input:** `assets/css/*.css`  
**Scan:** All PHP files in admin/, public/, Includes/  
**Output:** `assets/css/dist/*.css` (purged)  

**Process:**
1. Extract all used classes from PHP
2. Safelist component patterns (ltlb-*, wp-*)
3. Remove unused selectors
4. Preserve variables & keyframes

**Result:** Additional 40-60% reduction (estimated)

### `npm run optimize`

Full optimization: build + purge.

**Total Reduction:** ~70% from original size

### `npm run watch`

Development mode - automatically rebuild on file changes.

Watches: `assets/css/*.css`  
Runs: `npm run build` on change  

---

## 🔧 CONFIGURATION

### Build Script

`scripts/build-css.js` - Simple Node.js minifier

- No external dependencies
- Regex-based compression
- Works on all platforms
- Fast execution (<1s)

### PurgeCSS

`purgecss.config.js` - Unused CSS removal

**Safelist Patterns:**
```javascript
/^ltlb-/        // All LazyBookings components
/^wp-/          // WordPress core classes
/^dashicons/    // WordPress icons
/data-/         // Data attributes
/aria-/         // ARIA attributes
```

**Custom Extractor:**
```javascript
// Extracts classes from PHP:
// class="ltlb-btn ltlb-btn--primary"
// → ['ltlb-btn', 'ltlb-btn--primary']
```

### Stylelint

`.stylelintrc.json` - CSS quality checks

**Rules:**
- Custom property must start with `ltlb-`
- Class names must start with `ltlb-`
- No duplicate properties
- No vendor prefixes (use autoprefixer)
- Max 3 levels of nesting

---

## 📁 FILE STRUCTURE

```
ltl-bookings/
├── assets/css/
│   ├── tokens.css          (8.15 KB)  → Source
│   ├── base.css            (5.50 KB)  → Source
│   ├── components.css     (14.54 KB)  → Source
│   ├── layout.css         (10.14 KB)  → Source
│   ├── admin.css          (61.78 KB)  → Legacy (v2.x only)
│   ├── public.css         (35.63 KB)  → Legacy (v2.x only)
│   └── dist/
│       ├── tokens.min.css      (5.54 KB)  ← Minified
│       ├── base.min.css        (4.00 KB)  ← Minified
│       ├── components.min.css (11.69 KB)  ← Minified
│       ├── layout.min.css      (7.75 KB)  ← Minified
│       ├── admin.min.css      (46.38 KB)  ← Minified
│       └── public.min.css     (26.84 KB)  ← Minified
│
├── scripts/
│   └── build-css.js        ← Build script
│
├── docs/
│   └── CSS_OPTIMIZATION_GUIDE.md  ← Complete guide
│
├── package.json            ← NPM config
├── purgecss.config.js      ← PurgeCSS config
└── .stylelintrc.json       ← Stylelint config
```

---

## 🎯 OPTIMIZATION RESULTS

### Build Savings

| File | Original | Minified | Savings |
|------|----------|----------|---------|
| tokens.css | 8.15 KB | 5.54 KB | 32.0% |
| base.css | 5.50 KB | 4.00 KB | 27.3% |
| components.css | 14.54 KB | 11.69 KB | 19.6% |
| layout.css | 10.14 KB | 7.75 KB | 23.5% |
| admin.css | 61.78 KB | 46.38 KB | 24.9% |
| public.css | 35.63 KB | 26.84 KB | 24.7% |
| **Total** | **135.74 KB** | **102.20 KB** | **24.7%** |

### With Gzip (Estimated)

| Stage | Size | Reduction |
|-------|------|-----------|
| Original | 135.74 KB | — |
| Minified | 102.20 KB | 25% |
| Gzipped | ~35 KB | 74% |

---

## 🚦 DEPLOYMENT

### Development (Local)

WordPress loads **unminified** CSS from `assets/css/`:

```php
define('LTLB_DEBUG_ASSETS', true);  // In wp-config.php
```

Or use `SCRIPT_DEBUG`:

```php
define('SCRIPT_DEBUG', true);
```

### Staging/Production

WordPress loads **minified** CSS from `assets/css/dist/`:

```php
// Don't define LTLB_DEBUG_ASSETS or SCRIPT_DEBUG
```

Auto-detection in Plugin.php:

```php
$debug_assets = defined('LTLB_DEBUG_ASSETS') && LTLB_DEBUG_ASSETS;
$min = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
$css_dir = $debug_assets ? 'assets/css/' : 'assets/css/dist/';

wp_enqueue_style('ltlb-tokens', LTLB_URL . $css_dir . "tokens{$min}.css");
```

---

## 🧪 TESTING

### Visual Regression

See `/docs/TESTING_GUIDE.md` for complete framework.

**Quick Test:**

```bash
# Install BackstopJS
npm install -g backstopjs

# Run visual tests
backstop test --config=backstop.json
```

### Accessibility

```bash
# Install axe-core CLI
npm install -g @axe-core/cli

# Test local site
axe http://localhost/wp-admin
```

### Performance

```bash
# Install Lighthouse
npm install -g lighthouse

# Run audit
lighthouse http://localhost --output=html --output-path=./report.html
```

---

## 📊 CI/CD INTEGRATION

### GitHub Actions Workflow

Create `.github/workflows/css-build.yml`:

```yaml
name: Build CSS

on:
  push:
    paths:
      - 'assets/css/*.css'

jobs:
  build:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Build CSS
        run: npm run build
      
      - name: Commit minified files
        run: |
          git config user.name "GitHub Actions"
          git config user.email "actions@github.com"
          git add assets/css/dist/
          git commit -m "Build: Update minified CSS" || exit 0
          git push
```

---

## 🔍 TROUBLESHOOTING

### Build fails

```bash
# Clear npm cache
npm cache clean --force

# Reinstall dependencies
rm -rf node_modules package-lock.json
npm install

# Run build again
npm run build
```

### Wrong files loaded

Check constants in `wp-config.php`:

```php
// Force minified (production)
define('LTLB_DEBUG_ASSETS', false);
define('SCRIPT_DEBUG', false);

// Force unminified (development)
define('LTLB_DEBUG_ASSETS', true);
define('SCRIPT_DEBUG', true);
```

### Large file sizes

1. Run `npm run optimize` for full optimization
2. Enable Gzip compression (see CSS_OPTIMIZATION_GUIDE.md)
3. Check for duplicate CSS in legacy files

### Missing classes

PurgeCSS may have removed needed classes. Add to safelist:

```javascript
// purgecss.config.js
safelist: {
  standard: [
    /^your-pattern-/,  // Add your pattern
  ]
}
```

---

## 📚 ADDITIONAL RESOURCES

- **Design System Reference:** `/docs/DESIGN_SYSTEM.md`
- **Implementation Guide:** `/docs/DESIGN_SYSTEM_IMPLEMENTATION.md`
- **Testing Framework:** `/docs/TESTING_GUIDE.md`
- **Optimization Guide:** `/docs/CSS_OPTIMIZATION_GUIDE.md`
- **Style Guide UI:** `/wp-admin/admin.php?page=ltlb_styleguide`

---

## 🎯 NEXT STEPS

1. ✅ Run `npm run build` to create minified files
2. ✅ Test locally with `SCRIPT_DEBUG` enabled/disabled
3. ✅ Run visual regression tests (optional)
4. ✅ Deploy to staging and verify file loading
5. ⏰ Plan v3.0.0 migration (remove legacy files)

---

**Build System Version:** 1.0.0  
**Last Updated:** December 19, 2024  
**Status:** ✅ Production Ready
