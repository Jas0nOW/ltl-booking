/**
 * PurgeCSS Configuration
 * 
 * Removes unused CSS from production builds
 * Run: npx purgecss --config purgecss.config.js
 */

module.exports = {
  content: [
    './admin/**/*.php',
    './public/**/*.php',
    './Includes/**/*.php',
    './ltl-booking.php',
    './assets/js/**/*.js'
  ],
  css: [
    './assets/css/tokens.css',
    './assets/css/base.css',
    './assets/css/components.css',
    './assets/css/layout.css'
  ],
  output: './assets/css/dist/',
  
  // Safelist - classes that should NEVER be removed
  safelist: {
    // Component classes (all variants)
    standard: [
      /^ltlb-/,           // All LazyBookings components
      /^wp-/,             // WordPress core classes
      /^admin-/,          // WordPress admin classes
      /^post-/,           // Post classes
      /^page-/,           // Page classes
      /^menu-/,           // Menu classes
      /^widget-/,         // Widget classes
    ],
    
    // Deep matching (child selectors)
    deep: [
      /^ltlb-btn/,        // Button variants
      /^ltlb-badge/,      // Badge variants
      /^ltlb-alert/,      // Alert variants
      /^ltlb-card/,       // Card variants
      /^ltlb-form/,       // Form variants
      /^ltlb-table/,      // Table variants
      /^ltlb-tab/,        // Tab variants
      /^ltlb-dialog/,     // Dialog variants
      /^ltlb-metric/,     // Metric variants
    ],
    
    // Greedy matching (all children)
    greedy: [
      /^dashicons/,       // WordPress Dashicons
      /data-/,            // Data attributes
      /aria-/,            // ARIA attributes
      /role=/,            // Role attributes
    ]
  },
  
  // Variables to preserve
  variables: true,
  keyframes: true,
  fontFace: true,
  
  // Rejected selectors logging
  rejected: true,
  
  // Custom extractors for PHP files
  extractors: [
    {
      extractor: content => {
        // Match classes in PHP: class="ltlb-btn ltlb-btn--primary"
        const classRegex = /class=["\']([^"\']*)["\']|class=(?:"|\')?([^\s>"\']*)/g;
        const classes = [];
        let match;
        
        while ((match = classRegex.exec(content)) !== null) {
          const classString = match[1] || match[2];
          if (classString) {
            classes.push(...classString.split(/\s+/));
          }
        }
        
        return classes;
      },
      extensions: ['php']
    },
    {
      extractor: content => {
        // Standard extractor for HTML/JS
        return content.match(/[A-Za-z0-9-_:\/]+/g) || [];
      },
      extensions: ['html', 'js']
    }
  ]
};
