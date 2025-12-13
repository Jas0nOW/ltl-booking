Commit 1 decisions:

- Sanitizer: implemented `LTLB_Sanitizer` with basic helpers (`text`, `int`, `money_cents`, `email`, `datetime`). Uses WP sanitize helpers where available.
- Loading: For Phase 1 we use explicit `require_once` in `Includes/Core/Plugin.php` instead of an autoloader.
- Table prefix: per SPEC we use `$wpdb->prefix . 'lazy_' . name` (ServiceRepository will read `lazy_services`).

Commit 4 decisions:

- Customer upsert semantics: `LTLB_CustomerRepository::upsert_by_email` is the canonical create/update entry point. The admin edit form supports editing by `id`, but saving always calls `upsert_by_email($data)`. This updates the existing record matching the submitted email (or inserts if not found). If an admin edits an existing record and changes the email to another existing email, the record for that email will be updated — this is an acceptable behavior for Phase 1 and will be revisited in a later commit if stricter id-based updates are required.

Commit 6 decisions (Frontend security):

- Honeypot: Added hidden field `ltlb_hp` to the frontend booking form. If populated, the submission is silently rejected with a generic message.
- Rate limiting: Implemented a simple IP-based transient limiter (10 submits / 10 minutes) keyed by `ltlb_rate_{md5(ip)}`. This is intentionally conservative and server-side only. It can be replaced by more robust throttling later.
- Nonce handling: All frontend submissions verify `ltlb_book_nonce` using `wp_verify_nonce`. On failure, a generic error message is returned to avoid information leakage.

Commit 2 decisions (Time & storage):

- Time helper: added `LTLB_Time` in `includes/Util/Time.php` providing `wp_timezone()`, `create_datetime_immutable()`, `parse_date_and_time()`, `format_wp_datetime()`, `day_start()/day_end()` and `generate_slots_for_day()`.
- Storage concept: `start_at` and `end_at` are stored in the site timezone as `Y-m-d H:i:s` strings (matching `LTLB_Time::format_wp_datetime`). This keeps DB values consistent with admin views; DST edge-cases are handled by using site timezone during parse/format. Note: this choice simplifies Phase 1 — migrating to UTC storage can be considered later.


