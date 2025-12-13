Commit 1 decisions:

- Sanitizer: implemented `LTLB_Sanitizer` with basic helpers (`text`, `int`, `money_cents`, `email`, `datetime`). Uses WP sanitize helpers where available.
- Loading: For Phase 1 we use explicit `require_once` in `Includes/Core/Plugin.php` instead of an autoloader.
- Table prefix: per SPEC we use `$wpdb->prefix . 'lazy_' . name` (ServiceRepository will read `lazy_services`).

Commit 4 decisions:

- Customer upsert semantics: `LTLB_CustomerRepository::upsert_by_email` is the canonical create/update entry point. The admin edit form supports editing by `id`, but saving always calls `upsert_by_email($data)`. This updates the existing record matching the submitted email (or inserts if not found). If an admin edits an existing record and changes the email to another existing email, the record for that email will be updated — this is an acceptable behavior for Phase 1 and will be revisited in a later commit if stricter id-based updates are required.


