# Lazy Bookings Plugin

**Version:** 0.4.0  
**Author:** LazyTechLab  
**License:** GPLv2 or later

## Description

LazyBookings ist eine High-End-Lösung für Termin- und Ressourcenmanagement in WordPress, konzipiert als vollständiger Ersatz für das Plugin "Amelia". Der Zweck ist es, dienstleistungsbasierten Unternehmen (Yoga-Studios, Hotels, Beratern) eine mächtige, provisionsfreie Buchungsplattform zu bieten.

## Features

### Phase 1-3: Core Functionality
- ✅ Service & Customer Management
- ✅ Appointment Booking System
- ✅ Resource Management (Rooms, Equipment)
- ✅ Staff Hours & Exceptions
- ✅ Email Notifications
- ✅ REST API

### Phase 4: Hotel Mode (MVP)
- ✅ Multi-night bookings
- ✅ Check-in / Check-out logic
- ✅ Staff availability for hotel mode
- ✅ Resource capacity handling

### Phase 4.1: Production Readiness ⭐ **CURRENT**
- ✅ **Diagnostics Dashboard** - System health monitoring
- ✅ **MySQL Named Locks** - Race condition protection
- ✅ **Performance Indexes** - Optimized database queries
- ✅ **Admin UX Upgrades** - Filters, CSV export
- ✅ **Email Deliverability** - Reply-To, test emails
- ✅ **GDPR Compliance** - Data retention & anonymization
- ✅ **Privacy-Safe Logging** - PII protection with configurable levels
- ✅ **QA Automation** - Smoke tests & upgrade procedures

## Installation

1. Upload plugin folder to `/wp-content/plugins/`
2. Activate through WordPress admin
3. Navigate to **LazyBookings** menu
4. Configure settings and create your first service

## Usage

### Admin Panel
- **Dashboard** - Overview and quick stats
- **Services** - Create and manage bookable services
- **Customers** - Customer database with search
- **Appointments** - View, filter, export bookings
- **Resources** - Manage rooms, equipment (capacity)
- **Staff** - Configure availability per staff member
- **Settings** - Email, booking rules, logging
- **Design** - Customize colors (CSS variables)
- **Diagnostics** - System health & manual migrations
- **Privacy** - GDPR tools (retention, anonymization)

### Frontend Shortcode
```
[lazy_book]
```
Displays the booking wizard on any page/post.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ (with GET_LOCK support)

## Documentation

See `/docs` folder:
- `SPEC.md` - Technical specification
- `DB_SCHEMA.md` - Database structure
- `API.md` - REST API endpoints
- `DECISIONS.md` - Architecture decisions
- `QA_CHECKLIST.md` - Testing procedures

## Support

For issues and feature requests, please check the documentation first.

## Changelog

### 0.4.0 (Phase 4.1 - Production Readiness)
- Added diagnostics page with system info & migration runner
- Implemented MySQL named lock protection for bookings
- Added performance indexes on staff tables
- Enhanced admin UX with filters and CSV export
- Improved email deliverability (Reply-To, test button)
- Added GDPR basics (retention settings, anonymization)
- Implemented privacy-safe logging with PII protection
- Expanded QA procedures (smoke test, upgrade test)

### 0.3.0 (Phase 4 - Hotel Mode)
- Multi-night booking support
- Hotel mode with check-in/check-out
- Staff hours for hotel bookings
- Resource capacity management

### 0.2.0 (Phase 2-3)
- Staff & Resource management
- REST API implementation
- Email notifications

### 0.1.0 (Phase 1 - MVP)
- Initial release
- Basic service booking
- Admin dashboard
