# Lazy Bookings Plugin

**Version:** 1.1.0 
**Author:** LazyTechLab  
**License:** GPLv2 or later

## Description

LazyBookings ist eine High-End-Lösung für Termin- und Ressourcenmanagement in WordPress. Der Fokus liegt auf dienstleistungsbasierten Unternehmen (Yoga-Studios, Berater, Dienstleister), die eine mächtige, provisionsfreie Buchungsplattform suchen.

## Features

### Core Functionality (MVP)
- ✅ Service & Customer Management
- ✅ Appointment Booking System (Guided Wizard)
- ✅ Resource Management (Equipment, Capacities)
- ✅ Staff Hours & Exceptions
- ✅ Email Notifications
- ✅ REST API for Frontend Integration

### Production Readiness
- ✅ **Diagnostics Dashboard** - System health monitoring
- ✅ **MySQL Named Locks** - Race condition protection
- ✅ **Performance Optimization** - Database indexes & consolidated assets
- ✅ **Admin UX** - Modern Filters, Bulk Actions, Pagination
- ✅ **Calendar** - Drag & drop management
- ✅ **Privacy** - GDPR retention settings & anonymization

## Installation

1. Upload plugin folder to `/wp-content/plugins/`
2. Activate through WordPress admin
3. Navigate to **LazyBookings** menu
4. Configure settings and create your first service

## Usage

### Frontend Shortcode
```
[lazy_book]
```
Displays the booking wizard on any page/post.

## Documentation

Comprehensive documentation is available in the `docs/` directory.

### Core Docs
1.  **[Quickstart Guide](docs/quickstart.md)** – Get up and running in 15 minutes.
2.  **[Architecture Overview](docs/architecture.md)** – High-level system design.
3.  **[Security Policy](docs/security.md)** – Authentication and GDPR.
4.  **[API Reference](docs/reference/api.md)** – REST API endpoints.

## License

GPL v2 or later.
