Commit 1 decisions:

- Sanitizer: implemented `LTLB_Sanitizer` with basic helpers (`text`, `int`, `money_cents`, `email`, `datetime`). Uses WP sanitize helpers where available.
- Loading: For Phase 1 we use explicit `require_once` in `Includes/Core/Plugin.php` instead of an autoloader.
- Table prefix: per SPEC we use `$wpdb->prefix . 'lazy_' . name` (ServiceRepository will read `lazy_services`).

