# LazyBookings - Context for Gemini AI

This file serves as the definitive instructional context for AI agents working on the LazyBookings plugin. It describes the project structure, architecture, current status, and development conventions.

## 1. Project Overview
LazyBookings is a professional WordPress booking system focused on high-end service businesses (e.g., Yoga studios, consultants). It replaces bloated alternatives like "Amelia" with a clean, repository-based architecture and a modern design system.

- **Current Goal:** MVP (Minimum Viable Product) implementation.
- **Active Mode:** **Service Mode** (Appointment-based bookings with time slots).
- **Core Strategy:** Use WooCommerce for the checkout flow to handle payments safely.

## 2. Key Technologies
- **Backend:** PHP 8.1+ (Strict typing, WordPress best practices).
- **Database:** MySQL/MariaDB using custom tables (Repository Pattern).
- **Frontend UI:** Vanilla JavaScript, jQuery (for legacy support), CSS Custom Properties (Tokens).
- **Admin UI:** Custom PHP Component system, FullCalendar for scheduling.
- **Build Tools:** Node.js (for CSS consolidation and minification).

## 3. Architecture & File Structure
The project follows a clean separation of concerns:

- `Includes/Core/`: Plugin orchestration and lifecycle (`Plugin.php`, `Activator.php`).
- `Includes/DB/`: Schema definitions (`Schema.php`) and versioned migrations (`Migrator.php`).
- `Includes/Repository/`: Data Access Layer (CRUD operations on custom tables).
- `Includes/Domain/`: Domain entities and status management.
- `Includes/Util/`: Cross-cutting concerns (`Time.php`, `I18n.php`, `Sanitizer.php`, `LockManager.php`).
- `admin/`: Backend UI (Pages and reusable Components).
- `public/`: Frontend UI (Shortcode handlers in `Shortcodes.php`, Templates, Blocks).
- `assets/`: Consolidated assets (`css/admin.css`, `css/public.css`, `js/public.js`).
- `_Archive/`: Physically separated non-MVP/legacy features (AI, Hotel PMS).

## 4. Development Conventions
- **Strict Mode:** Always use `declare(strict_types=1);` where possible (future goal).
- **Naming:** Classes prefixed with `LTLB_`. Use PascalCase for classes and snake_case for methods/functions.
- **Database:** Never use `wp_options` for relational data. Use the provided Repositories.
- **Security:** Use nonces for all state-changing operations. Sanitize early, escape late.
- **Time:** Always store times in **UTC** in the database. Use `LTLB_Time` for conversions.
- **CSS:** Use the `--ltlb-*` tokens defined in the design system. Do not add raw hex codes to components.

## 5. Key Commands
- **Build Assets:** `npm run build` (Consolidates CSS into `admin.css` and `public.css`).
- **Database Migrations:** Handled automatically on admin page load via `maybe_migrate()`. Manual trigger: `wp ltlb migrate` (if WP-CLI is available).
- **Seed Data:** `wp ltlb seed --mode=service` (Create demo services/customers).
- **Syntax Check:** `php -l <file_path>`

## 6. MVP Roadmap (Current Priority)
1. **Phase A (Stability):** Finalize Service-mode locking and migration reliability (DONE).
2. **Phase B (Booking Flow):** Ensure availability display and pending booking creation work perfectly.
3. **Phase C (WooCommerce Integration):** Bridge the booking creation with the WooCommerce checkout.
4. **Phase D (Status Sync):** Handle the transition from "pending" to "confirmed" after successful payment.

## 7. Operational Guidelines for AI
- **Small Steps:** Work on one specific ticket/file at a time.
- **Archive First:** Do not delete code that might be useful later; move it to `_Archive/`.
- **Stay Lean:** Keep the active codebase focused on the "Service" path.
- **Source of Truth:** Refer to `docs/LTLB_PROJECT_MVP.md` for feature priorities.
