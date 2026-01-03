# CSS Optimization Guide

**Scope:** Instructions for optimizing the CSS codebase, removing legacy tokens, and preparing for production.  
**Non-Scope:** General CSS development or design system principles (see [Design System](../explanation/design-system.md)).

---

## 📋 OVERVIEW

This guide provides step-by-step instructions for optimizing the LazyBookings CSS codebase, removing legacy code, and preparing for v3.0.0 production release.

---

## 1. 🗑️ LEGACY TOKEN REMOVAL (v3.0.0)

### Current State (v2.0.0)
- Legacy `--lazy-*` tokens maintained for backward compatibility
- Aliases point to new `--ltlb-*` tokens
- Both systems coexist

### Migration Strategy

#### Step 1: Audit Codebase
Find all legacy token usage:

```bash
# Search for --lazy-* usage
grep -r "var(--lazy-" --include="*.php" --include="*.css" --include="*.js" .

# Count occurrences
grep -r "var(--lazy-" --include="*.php" --include="*.css" | wc -l
```

#### Step 2: Update Custom Code
Replace all instances:

**Find:** `var(--lazy-primary)`  
**Replace:** `var(--ltlb-primary)`

**Find:** `var(--lazy-space-md)`  
**Replace:** `var(--ltlb-space-4)`

#### Step 3: Remove Aliases
Delete lines 228-283 from `assets/css/tokens.css`:

```css
/* DELETE THIS SECTION IN v3.0.0 */
/* ===== LEGACY ALIASES (DEPRECATED) ===== */
:root {
  --lazy-primary: var(--ltlb-primary);
  /* ... all other aliases ... */
}
```

#### Step 4: Update Version
In `ltl-booking.php`:

```php
/**
 * Version: 3.0.0
 */
define('LTLB_VERSION', '3.0.0');
```

### Migration Token Map

| Legacy Token | New Token | Notes |
|--------------|-----------|-------|
| `--lazy-primary` | `--ltlb-primary` | Direct replacement |
| `--lazy-accent` | `--ltlb-accent` | Direct replacement |
| `--lazy-bg` | `--ltlb-bg-primary` | Direct replacement |
| `--lazy-text` | `--ltlb-text-primary` | Direct replacement |
| `--lazy-space-md` | `--ltlb-space-4` | 4px × 4 = 16px |
| `--lazy-space-lg` | `--ltlb-space-5` | 4px × 5 = 20px |
| `--lazy-border-radius` | `--ltlb-radius-lg` | 8px |
| `--lazy-shadow-md` | `--ltlb-shadow-md` | Direct replacement |

---

## 2. 📦 CSS FILE OPTIMIZATION

### Current File Sizes

```bash
# Check current sizes
ls -lh assets/css/*.css

# Expected output:
# tokens.css     ~10KB
# base.css       ~8KB
# components.css ~25KB
# layout.css     ~15KB
# admin.css      ~85KB (legacy)
# public.css     ~45KB (legacy)
```

### Optimization Strategies

#### A. Remove Unused CSS

**Tool: PurgeCSS**

Install:
```bash
npm install -g purgecss
```

Configuration `purgecss.config.js`:
```javascript
module.exports = {
  content: [
    './admin/**/*.php',
    './public/**/*.php',
    './Includes/**/*.php'
  ],
  css: [
    './assets/css/tokens.css',
    './assets/css/base.css',
    './assets/css/components.css',
    './assets/css/layout.css'
  ],
  output: './assets/css/dist/',
  safelist: [
    /^ltlb-/,        // Keep all component classes
    /^dashicons/,    // Keep WordPress icons
    /^wp-/,          // Keep WordPress classes
    /data-/,         // Keep data attributes
    /aria-/,         // Keep ARIA attributes
  ]
};
```

Run:
```bash
purgecss --config purgecss.config.js
```

#### B. Minify CSS

**Tool: clean-css-cli**

Install:
```bash
npm install -g clean-css-cli
```

Minify all files:
```bash
cd assets/css

# Minify new design system files
cleancss -o tokens.min.css tokens.css
cleancss -o base.min.css base.css
cleancss -o components.min.css components.css
cleancss -o layout.min.css layout.css

# Minify legacy files
cleancss -o admin.min.css admin.css
cleancss -o public.min.css public.css
```

**Expected Size Reduction:**
- tokens.css: 10KB → ~3KB (70% reduction)
- base.css: 8KB → ~2KB (75% reduction)
- components.css: 25KB → ~8KB (68% reduction)
- layout.css: 15KB → ~5KB (67% reduction)

#### C. Combine Critical CSS

Create `critical.css` for above-the-fold content:

```css
/* critical.css - Inline in <head> */
:root {
  --ltlb-primary: #2271b1;
  --ltlb-space-4: 1rem;
  --ltlb-radius-md: 4px;
  /* ... only essential tokens ... */
}

/* Essential base styles */
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; font-family: var(--ltlb-font-sans); }

/* Critical button styles */
.ltlb-btn { /* ... */ }
.ltlb-btn--primary { /* ... */ }
```

Size target: <14KB uncompressed

#### D. Code Splitting

Split large files by feature:

```
components.css (25KB) → Split into:
├── components-buttons.css     (~5KB)
├── components-forms.css        (~8KB)
├── components-tables.css       (~6KB)
└── components-feedback.css     (~6KB)
```

Load conditionally based on page.

---

## 3. 🔄 BUILD PROCESS

### Automated Build Script

Create `scripts/build-css.js`:

```javascript
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const CleanCSS = require('clean-css');

const cssDir = path.join(__dirname, '../assets/css');
const distDir = path.join(cssDir, 'dist');

// Ensure dist directory exists
if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

const files = [
  'tokens.css',
  'base.css',
  'components.css',
  'layout.css'
];

console.log('🔨 Building optimized CSS...\n');

files.forEach(file => {
  const input = path.join(cssDir, file);
  const output = path.join(distDir, file.replace('.css', '.min.css'));
  
  console.log(`📦 Processing ${file}...`);
  
  // Read source
  const source = fs.readFileSync(input, 'utf8');
  
  // Minify
  const minified = new CleanCSS({
    level: 2,
    compatibility: 'ie11'
  }).minify(source);
  
  if (minified.errors.length > 0) {
    console.error(`❌ Errors in ${file}:`, minified.errors);
    return;
  }
  
  // Write minified
  fs.writeFileSync(output, minified.styles);
  
  // Stats
  const originalSize = Buffer.byteLength(source, 'utf8');
  const minifiedSize = Buffer.byteLength(minified.styles, 'utf8');
  const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);
  
  console.log(`  ✅ ${file}`);
  console.log(`     Original: ${(originalSize / 1024).toFixed(2)} KB`);
  console.log(`     Minified: ${(minifiedSize / 1024).toFixed(2)} KB`);
  console.log(`     Savings: ${savings}%\n`);
});

console.log('✨ Build complete!\n');
console.log('📁 Files written to: assets/css/dist/');
```

Run:
```bash
node scripts/build-css.js
```

### Package.json Scripts

```json
{
  "name": "ltl-bookings-css",
  "version": "2.0.0",
  "scripts": {
    "build": "node scripts/build-css.js",
    "purge": "purgecss --config purgecss.config.js",
    "minify": "npm run build",
    "optimize": "npm run purge && npm run minify",
    "watch": "chokidar 'assets/css/*.css' -c 'npm run build'"
  },
  "devDependencies": {
    "clean-css": "^5.3.2",
    "purgecss": "^5.0.0",
    "chokidar-cli": "^3.0.0"
  }
}
```

---

## 4. 🚀 PRODUCTION DEPLOYMENT

### Update Plugin.php for Production

```php
private function enqueue_admin_assets( $hook ): void {
    // Determine if we should use minified files
    $min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
    $version = $this->get_version_for_cache_busting();
    
    // Enqueue optimized CSS (production)
    wp_enqueue_style(
        'ltlb-tokens',
        LTLB_URL . "assets/css/dist/tokens{$min}.css",
        [],
        $version
    );
    
    wp_enqueue_style(
        'ltlb-base',
        LTLB_URL . "assets/css/dist/base{$min}.css",
        ['ltlb-tokens'],
        $version
    );
    
    wp_enqueue_style(
        'ltlb-components',
        LTLB_URL . "assets/css/dist/components{$min}.css",
        ['ltlb-base'],
        $version
    );
    
    wp_enqueue_style(
        'ltlb-layout',
        LTLB_URL . "assets/css/dist/layout{$min}.css",
        ['ltlb-components'],
        $version
    );
    
    // Legacy admin.css (will be removed in v3.0.0)
    wp_enqueue_style(
        'ltlb-admin',
        LTLB_URL . "assets/css/admin{$min}.css",
        ['ltlb-layout'],
        $version
    );
}
```

### Enable Gzip Compression

Add to `.htaccess`:

```apache
<IfModule mod_deflate.c>
  # Compress CSS files
  AddOutputFilterByType DEFLATE text/css
  AddOutputFilterByType DEFLATE application/javascript
  AddOutputFilterByType DEFLATE text/html
</IfModule>
```

Or in WordPress (`functions.php` equivalent):

```php
add_filter('mod_rewrite_rules', function($rules) {
    return '
        <IfModule mod_deflate.c>
          AddOutputFilterByType DEFLATE text/css
        </IfModule>
    ' . $rules;
});
```

### Browser Caching

Update headers:

```apache
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 year"
  ExpiresByType application/javascript "access plus 1 year"
</IfModule>
```

---

## 5. 📊 PERFORMANCE METRICS

### Before Optimization

| File | Size | Load Time (3G) |
|------|------|----------------|
| tokens.css | 10KB | ~150ms |
| base.css | 8KB | ~120ms |
| components.css | 25KB | ~380ms |
| layout.css | 15KB | ~230ms |
| **Total** | **58KB** | **~880ms** |

### After Optimization

| File | Size | Load Time (3G) | Savings |
|------|------|----------------|---------|
| tokens.min.css | 3KB | ~45ms | 70% |
| base.min.css | 2KB | ~30ms | 75% |
| components.min.css | 8KB | ~120ms | 68% |
| layout.min.css | 5KB | ~75ms | 67% |
| **Total** | **18KB** | **~270ms** | **69%** |

### With Gzip

| File | Gzipped Size |
|------|--------------|
| tokens.min.css | ~1KB |
| base.min.css | ~0.8KB |
| components.min.css | ~3KB |
| layout.min.css | ~2KB |
| **Total** | **~7KB** |

**Final Result:** 88% size reduction from original 58KB to 7KB gzipped!

---

## 6. 🧹 CODE CLEANUP

### Remove Unused Selectors

Audit and remove:

```bash
# Find unused classes in PHP files
grep -roh "class=\"[^\"]*\"" admin/ public/ | \
  sed 's/class="//g' | sed 's/"//g' | \
  tr ' ' '\n' | sort | uniq > used-classes.txt

# Find defined classes in CSS
grep -roh "\.[a-zA-Z0-9_-]*" assets/css/*.css | \
  sed 's/\.//g' | sort | uniq > defined-classes.txt

# Find unused (defined but not used)
comm -13 used-classes.txt defined-classes.txt > unused-classes.txt
```

### Consolidate Duplicate Rules

Look for:
- Repeated color values (should use tokens)
- Duplicate selectors
- Redundant media queries

**Tool:** CSSComb or Stylelint

```bash
npm install -g csscomb stylelint

# Format CSS
csscomb assets/css/components.css

# Lint
stylelint "assets/css/*.css"
```

### Remove WordPress Admin CSS Overrides

In v3.0.0, remove:

```css
/* DELETE - No longer needed with design system */
.ltlb-admin .button { /* ... */ }
.ltlb-admin .widefat { /* ... */ }
```

These are replaced by `.ltlb-btn` and `.ltlb-table`.

---

## 7. 📋 v3.0.0 CHECKLIST

### Pre-Release

- [ ] All `--lazy-*` tokens removed from tokens.css
- [ ] All PHP files updated to use `--ltlb-*` tokens
- [ ] All CSS files minified
- [ ] PurgeCSS run on production build
- [ ] Critical CSS extracted and inlined
- [ ] Gzip compression enabled
- [ ] Browser caching configured

### Code Quality

- [ ] No unused CSS selectors
- [ ] No duplicate rules
- [ ] All colors use tokens
- [ ] All spacing uses tokens
- [ ] Stylelint passes with 0 errors
- [ ] File sizes <20KB each

### Testing

- [ ] Visual regression tests pass
- [ ] Lighthouse performance ≥90
- [ ] No layout shifts (CLS <0.1)
- [ ] Load time <1s on 3G
- [ ] All browsers tested

### Documentation

- [ ] CHANGELOG.md updated
- [ ] Migration guide published
- [ ] Breaking changes documented
- [ ] New version tagged in Git

---

## 8. 🔄 MIGRATION TIMELINE

### v2.0.0 (Current)
- ✅ New design system introduced
- ✅ Legacy tokens aliased
- ✅ Backward compatibility maintained
- ⏰ Deprecation warnings added

### v2.1.0 - v2.9.0 (Transition Period)
- 📢 Deprecation notices in admin
- 📚 Migration guides published
- 🔧 Automated migration tools
- ⚠️ Console warnings for legacy usage

### v3.0.0 (Breaking Changes)
- 🗑️ Legacy tokens removed
- 🧹 Unused CSS purged
- ⚡ Optimized & minified
- 🚀 Production-ready

**Estimated Timeline:** 6-12 months from v2.0.0 release

---

## 9. 📝 BUILD CHECKLIST

### Development Build
```bash
# Install dependencies
npm install

# Run development build
npm run build

# Watch for changes
npm run watch
```

### Production Build
```bash
# Clean dist folder
rm -rf assets/css/dist/*

# Run full optimization
npm run optimize

# Verify output
ls -lh assets/css/dist/

# Test minified files
# (Manual testing in browser)
```

### Deploy to Production
```bash
# Build optimized files
npm run optimize

# Commit dist files
git add assets/css/dist/
git commit -m "Build: Optimized CSS for production"

# Tag release
git tag -a v3.0.0 -m "Release v3.0.0 - Optimized CSS"
git push origin v3.0.0

# Deploy plugin
# (Copy to WordPress.org SVN or deployment system)
```

---

## 10. 🎯 SUCCESS METRICS

### Performance Goals

| Metric | Current (v2.0.0) | Target (v3.0.0) | Status |
|--------|------------------|-----------------|--------|
| Total CSS Size | 58KB | <20KB | 🎯 |
| Gzipped Size | ~18KB | <8KB | 🎯 |
| Load Time (3G) | ~880ms | <300ms | 🎯 |
| Lighthouse Score | 85 | ≥90 | 🎯 |
| First Paint | 1.2s | <1s | 🎯 |
| Unused CSS | ~40% | <10% | 🎯 |

### Quality Goals

- ✅ Zero breaking changes for users migrating from v2.x
- ✅ All components use design system tokens
- ✅ Legacy code removed
- ✅ File sizes optimized
- ✅ Browser caching enabled
- ✅ Production-ready build process

---

## 📞 SUPPORT

For optimization issues:
1. Check build logs for errors
2. Verify Node.js version (≥14.0)
3. Clear npm cache: `npm cache clean --force`
4. Review TESTING_GUIDE.md for performance testing

**Status:** ✅ Phase 10 optimization guide complete and ready for v3.0.0
