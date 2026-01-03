=== LazyBookings - Appointments & Hotel Booking Plugin ===
Contributors: lazytechnologylab
Tags: booking, appointments, hotel, calendar, scheduling
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional booking system for appointment and resource management with premium admin UI.

== Description ==

**LazyBookings** is a high-end booking solution for service-based businesses. It provides a seamless, professional experience for both administrators and customers, handling everything from staff schedules to resource allocation.

### 🎯 Key Features

* **Service & Staff Management** - Create bookable services and assign them to staff members.
* **Real-time Calendar** - Interactive calendar with drag & drop rescheduling and quick status edits.
* **Customer CRM** - Manage your customer database with ease, including booking history and CSV export.
* **Guided Wizard** - A multi-step booking wizard for your frontend that guides users through the process.
* **Email Notifications** - Automated confirmation and notification emails for customers and admins.
* **Resource Allocation** - Manage rooms, equipment, or capacities linked to your services.

**Premium Admin Interface:**
* Modern SaaS-style UI with 8pt Grid Design.
* Intuitive Dashboards with KPIs & Week-over-Week Trends.
* Multi-step Wizards for Complex Tasks.
* Bulk Actions & CSV Export.
* Pagination & Column Visibility Toggles.
* Recently Viewed Items for Quick Navigation.

**Developer Friendly:**
* Repository Pattern Architecture.
* REST API with Nonce Protection.
* Extensible Component Library.
* Full WordPress Coding Standards.

### 🚀 Perfect For

* Yoga & Fitness Studios
* Medical Practices & Clinics
* Consulting Services
* Beauty Salons & Spas
* Meeting Rooms & Equipment Rentals

== Installation ==

1. Upload the `ltl-bookings` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **LazyBookings → Settings** to configure your options
4. Follow the setup wizard to configure your first service

== Frequently Asked Questions ==

= Does the plugin work with my theme? =

Yes, LazyBookings is designed to work with any properly coded WordPress theme. The admin interface is completely independent of your theme.

= Can I customize the booking form? =

Yes, the plugin uses customizable templates. Advanced users can override templates in their theme folder.

= Is the plugin GDPR compliant? =

Yes, the plugin includes privacy controls and data retention settings. You can configure automatic deletion of old customer data.

= Does it support multiple languages? =

Yes, the plugin is fully translatable. English is the base language, with German translation ready.

== Screenshots ==

1. Appointments Dashboard - KPIs and quick actions
2. Calendar View - Drag & drop appointments
3. Service Creation Wizard - Multi-step setup
4. Customer Management - Database with filtering
5. Settings - Core configuration options

== Changelog ==

= 1.1.0 - December 2025 =
* Major Update: Complete Dashboard Redesign
* New: Modern 2-column layout for Appointments Dashboard
* New: Hotel Dashboard with Occupancy Forecast and Room Management
* New: CSS Debugging tools in Diagnostics
* Fix: Live Preview positioning in Design settings
* Fix: Table readability improvements (contrast)
* Fix: PHP "Headers already sent" error in Design settings
* Cleanup: Removed redundant files and optimized project structure

= 1.0.1 - December 2025 =
* Initial release
* Dual-mode system (Appointments + Hotel)
* Premium admin UI with SaaS-style design
* Multi-step wizards for service/room creation
* Dashboard with KPIs and week-over-week trends
* Calendar with drag & drop functionality
* Customer/Guest management with CSV export
* Bulk actions for appointments and services
* Column visibility toggles
* Recently viewed items widget
* Collapsible calendar legend
* Keyboard shortcuts (Cmd/Ctrl+K)
* Full i18n support (EN base, DE ready)
* WCAG 2.1 Level AA accessibility
* Repository pattern architecture
* REST API with nonce protection
* Comprehensive error handling

== Upgrade Notice ==

= 1.1.0 =
Major dashboard redesign and critical bug fixes.

== Additional Info ==

**Support:** For support requests, please visit our support forum or contact support@lazytechnologylab.com

**Documentation:** Full documentation available at https://docs.lazytechnologylab.com/lazybookings

**Development:** This plugin follows WordPress Coding Standards and uses modern development practices. Developers can extend functionality through hooks and filters.

**Privacy:** The plugin stores customer/guest information in your WordPress database. You control data retention through settings. No data is sent to external services unless you configure email notifications through your SMTP settings.

**Credits:** 
* Calendar powered by FullCalendar (MIT License)
* Built with WordPress best practices
* Developed by Lazy Technology Lab

== Technical Specifications ==

**Database Tables:**
* ltlb_appointments - Appointments/bookings data
* ltlb_services - Services/room types
* ltlb_customers - Customer/guest information
* ltlb_resources - Resources/rooms
* ltlb_staff_hours - Staff scheduling
* ltlb_staff_exceptions - Staff exceptions
* ltlb_appointment_resources - Resource assignments
* ltlb_service_resources - Service-resource mappings

**API Endpoints:**
* `/wp-json/ltl-bookings/v1/appointments`
* `/wp-json/ltl-bookings/v1/services`
* `/wp-json/ltl-bookings/v1/customers`
* All endpoints protected with WordPress nonces

**System Requirements:**
* PHP 8.1+
* MySQL 5.6+ (MySQL 8.0+ recommended)
* WordPress 6.0+ (Latest version recommended)
* Modern browser (Chrome, Firefox, Safari, Edge)
