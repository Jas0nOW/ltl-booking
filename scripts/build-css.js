/**
 * CSS Build Script
 * 
 * Minifies and optimizes CSS files for production
 * Run: node scripts/build-css.js
 */

const fs = require('fs');
const path = require('path');

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
  'layout.css',
  'admin.css',
  'public.css'
];

console.log('🔨 Building optimized CSS...\n');

files.forEach(file => {
  const input = path.join(cssDir, file);
  const output = path.join(distDir, file.replace('.css', '.min.css'));
  
  if (!fs.existsSync(input)) {
    console.log(`⏭️  Skipping ${file} (not found)`);
    return;
  }
  
  console.log(`📦 Processing ${file}...`);
  
  // Read source
  const source = fs.readFileSync(input, 'utf8');
  
  // Simple minification (remove comments, extra whitespace, newlines)
  let minified = source
    // Remove comments
    .replace(/\/\*[\s\S]*?\*\//g, '')
    // Remove newlines
    .replace(/\n/g, '')
    // Remove multiple spaces
    .replace(/\s+/g, ' ')
    // Remove space after colons
    .replace(/:\s+/g, ':')
    // Remove space before/after { }
    .replace(/\s*{\s*/g, '{')
    .replace(/\s*}\s*/g, '}')
    // Remove space before/after , ;
    .replace(/\s*,\s*/g, ',')
    .replace(/\s*;\s*/g, ';')
    // Remove space around >
    .replace(/\s*>\s*/g, '>')
    // Trim
    .trim();
  
  // Write minified
  fs.writeFileSync(output, minified);
  
  // Stats
  const originalSize = Buffer.byteLength(source, 'utf8');
  const minifiedSize = Buffer.byteLength(minified, 'utf8');
  const savings = ((1 - minifiedSize / originalSize) * 100).toFixed(1);
  
  console.log(`  ✅ ${file}`);
  console.log(`     Original: ${(originalSize / 1024).toFixed(2)} KB`);
  console.log(`     Minified: ${(minifiedSize / 1024).toFixed(2)} KB`);
  console.log(`     Savings: ${savings}%\n`);
});

console.log('✨ Build complete!\n');
console.log('📁 Files written to: assets/css/dist/');
console.log('\n💡 Next steps:');
console.log('   1. Review minified files in dist/ folder');
console.log('   2. Test minified files in development');
console.log('   3. Update Plugin.php to use .min.css files');
console.log('   4. Run performance tests (see TESTING_GUIDE.md)');
