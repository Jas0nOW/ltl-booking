# LazyBookings Changelog

## 0.4.4
- Admin: shared header navigation across LazyBookings pages
- Admin: Calendar page (FullCalendar) with drag & drop / resize
- Admin: quick actions in calendar (status update, customer edit, delete)
- REST: authenticated admin endpoints for calendar + CRUD
- UX: conflict-safe drag/drop (409 conflict, client reverts)
- I18n: per-user admin language switch (EN/DE) and localized calendar UI strings

## 0.4.3
- Services: add per-service `availability_mode` and `fixed_weekly_slots` (fixed weekly start times)
- Availability: enforce fixed weekly start times when configured; add compatibility wrapper for non-slot requests
- Schema: add `seats` column to appointments to match runtime inserts
- Docs: align SPEC/API/DB schema documentation to current behavior

## 0.4.0 — Release Candidate
- Release freeze: synced plugin and DB version to 0.4.0
- Added `LTLB_VERSION` constant and automatic DB version update via Migrator
- Stabilized admin pages and `[lazy_book]` shortcode for RC
- Misc: minor polish ahead of final release
