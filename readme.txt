=== LazyBookings ===
Contributors: lazytechlab
Tags: bookings, appointments, services, resources
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

LazyBookings is a booking and resource management plugin for WordPress (Amelia alternative). It provides custom tables, admin pages, REST endpoints for availability, and a modern frontend booking wizard.

== Description ==
LazyBookings provides services, customers, and appointments management with custom database tables for performance. Admin pages support CRUD operations. Frontend shortcode `[lazy_book]` offers a minimal booking wizard.

== Installation ==
1. Upload the ZIP via Plugins > Add New.
2. Activate the plugin.
3. Go to LazyBookings in the admin menu to configure settings.

== Frequently Asked Questions ==
= What is the REST namespace? =
The REST namespace is `ltlb/v1`.

= Which shortcodes exist? =
- `[lazy_book]` – Standard wizard.
- `[lazy_book_calendar]` – Wizard starting in calendar-first mode.

== Screenshots ==
1. Services admin list.
2. Appointments admin list.
3. Customers admin list.
4. Booking wizard.

== Changelog ==
= 1.1.0 =
- PHP-Minimum auf 8.1 angehoben; Stabilitäts-/Security-Hardening.
- Version-Bump; Vorbereitungen für neue Payments & UI/UX-Refactor.

= 1.0.1 =
- Copy/UX: clearer labels and notices across admin + booking wizard.
- A11y: improved focus styles and required field indicators.
- Frontend: smoother wizard step transitions on mobile.
- Admin: improved dark-mode styling consistency.

= 0.4.4 =
- Admin calendar management (drag & drop rescheduling).
- Admin REST endpoints for calendar + appointment/customer edits.
- Conflict-safe rescheduling (409 + UI revert).
- Per-user admin language switch (EN/DE).

= 0.4.3 =
- Per-service availability rules incl. fixed weekly start times.
- Schema update: appointments include `seats` column.
- Docs and API behavior aligned to current implementation.

== Upgrade Notice ==
Version 1.1.0: Läuft auf PHP 8.1+. Nach dem Update sicherstellen, dass Cron/Payments korrekt konfiguriert sind; DB-Migrationen laufen automatisch bei Aktivierung oder Versionswechsel.
