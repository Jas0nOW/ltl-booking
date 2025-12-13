=== LazyBookings ===
Contributors: lazytechlab
Tags: bookings, appointments, services, resources
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

LazyBookings is a high-end booking and resource management plugin for WordPress (Amelia alternative). Phase 1 provides PHP-first MVP with custom tables, admin pages, REST API, and a minimal frontend wizard.

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
- `[lazy_book service="123" mode="calendar"]` – Starts in calendar mode.

== Screenshots ==
1. Services admin list.
2. Appointments admin list.
3. Customers admin list.
4. Booking wizard.

== Changelog ==
= 0.4.0 =
- Release candidate with stable admin pages and shortcode.

== Upgrade Notice ==
Version 0.4.0: Database migrations run automatically on activation and when plugin version changes.
