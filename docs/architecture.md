# Architecture Overview

**Scope:** High-level system design, component interaction, and data flow.  
**Non-Scope:** Detailed class-level API documentation or specific implementation details.

## Who should read this?
- Developers contributing to the plugin.
- System architects evaluating the plugin's structure.

---

## 1. System Components

LazyBookings follows a modular architecture organized into the following layers:

### Core Layer (`Includes/Core/`)
- **`Plugin.php`**: The main entry point. Orchestrates the initialization of all other components.
- **`Activator.php`**: Handles plugin activation, including database schema creation.

### Data Layer (`Includes/DB/`, `Includes/Repository/`)
- **`Schema.php`**: Defines the custom database tables.
- **`Migrator.php`**: Handles versioned database updates.
- **Repositories**: Abstract data access for entities like Appointments, Services, and Customers.

### Domain Layer (`Includes/Domain/`)
- Contains the business logic and entities for the core booking domain (Services, Staff, Resources).

### Engine Layer (`Includes/Engine/`)
- **Booking Engine**: Handles the logic for creating and validating bookings.
- **Availability Engine**: Calculates free slots based on staff hours, service duration, and existing appointments.

### Interface Layer (`admin/`, `public/`, `Includes/REST/`)
- **Admin Pages**: PHP-based pages using a custom component system (`admin/Components/`).
- **Frontend**: Shortcodes and templates for the booking wizard.
- **REST API**: Endpoints for frontend interactions and external integrations.

---

## 2. Data Flow

### Booking Process
1. **Frontend**: User selects a service and time slot via the Booking Wizard.
2. **REST API**: The wizard sends a request to the `bookings` endpoint.
3. **Engine**: The Booking Engine validates availability and permissions.
4. **Repository**: If valid, the booking is persisted to the database.
5. **Integrations**: Notifications are triggered (Email).

---

## 3. Key Design Decisions

- **Custom Tables**: We use custom database tables instead of Custom Post Types (CPT) for performance and better relational data management.
- **Component-Based Admin**: The admin UI is built using reusable PHP components to ensure consistency and maintainability.
- **Strict Type Safety**: The plugin is optimized for PHP 8.1+, using strict typing and explicit casting to prevent deprecation warnings.

---

## Next Steps
- [Database Schema](reference/db-schema.md)
- [API Reference](reference/api.md)
- [Design System](explanation/design-system.md)
