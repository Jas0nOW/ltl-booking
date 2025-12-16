# LazyBookings Capabilities & Permissions

## Overview

LazyBookings implements a granular capabilities system that enforces strict permission boundaries for different user roles. All write operations are gated by capabilities + nonces, and REST endpoints use role-specific permission callbacks.

## User Roles

### Administrator (Superadmin)
- **WordPress Role**: `administrator`
- **Profile**: `superadmin`
- **Capabilities**: All capabilities granted
- **Access**: Full system access including settings, AI configuration, payments, and refunds

### Manager
- **WordPress Role**: `editor`
- **Profile**: `mitarbeiter` (legacy naming)
- **Capabilities**: 
  - `view_bookings`, `manage_bookings`
  - `view_customers`, `manage_customers`
  - `view_services`
  - `view_staff`
  - `manage_own_availability`
- **Access**: Can manage day-to-day operations but cannot change prices, settings, or process refunds

### Staff
- **WordPress Role**: `ltlb_staff`
- **Profile**: `mitarbeiter`
- **Capabilities**:
  - `view_bookings`, `manage_own_bookings`
  - `view_customers`
  - `view_services`
  - `view_staff`
  - `manage_own_availability`
- **Access**: Read-only for most data, can manage own schedule and assigned bookings

### CEO/Reports Viewer
- **WordPress Role**: `ltlb_ceo`
- **Profile**: `ceo`
- **Capabilities**:
  - `view_ai_reports`
  - `view_reports`
  - `view_payments`
- **Access**: Read-only dashboards, analytics, and financial reports

## Custom Capabilities

### AI Capabilities
- `manage_ai_settings` - Manage AI configuration (Administrator only)
- `manage_ai_secrets` - View/Edit AI API keys (Administrator only)
- `view_ai_reports` - View AI-generated insights (Administrator, CEO)
- `approve_ai_drafts` - Approve AI-generated actions (Administrator only)

### Bookings Capabilities
- `view_bookings` - View all bookings (Administrator, Manager, Staff)
- `manage_bookings` - Create/Edit/Delete bookings (Administrator, Manager)
- `manage_own_bookings` - Manage only own assigned bookings (Staff)

### Customers Capabilities
- `view_customers` - View customer data (Administrator, Manager, Staff)
- `manage_customers` - Create/Edit/Delete customers (Administrator, Manager)

### Services & Resources Capabilities
- `view_services` - View services/rooms (Administrator, Manager, Staff)
- `manage_services` - Create/Edit/Delete services/rooms (Administrator only)
- `manage_service_prices` - Edit pricing (Administrator only)

### Staff Capabilities
- `view_staff` - View staff list (Administrator, Manager, Staff)
- `manage_staff` - Create/Edit/Delete staff (Administrator only)
- `manage_own_availability` - Edit own schedule (Staff, Manager)

### Settings Capabilities
- `manage_booking_settings` - Change plugin settings (Administrator only)
- `view_reports` - View analytics/reports (Administrator, CEO)

### Payment Capabilities
- `view_payments` - View payment information (Administrator, CEO)
- `process_refunds` - Issue refunds (Administrator only)

## REST Endpoint Permissions

All REST endpoints use capability-based permission callbacks:

### Bookings Endpoints
- `GET /admin/appointments/{id}` - Requires: `view_bookings`
- `DELETE /admin/appointments/{id}` - Requires: `manage_bookings`
- `POST /admin/appointments/{id}/move` - Requires: `manage_bookings`
- `POST /admin/appointments/{id}/status` - Requires: `manage_bookings`

### Customers Endpoints
- `POST /admin/customers/{id}` - Requires: `manage_customers`

### Refund Endpoint
- `POST /admin/appointments/{id}/refund` - Requires: `process_refunds`

### Calendar Endpoints
All calendar endpoints require `view_bookings` capability.

## Admin Page Permissions

Admin pages check capabilities on load and for all write operations:

- **Appointments** - View: `view_bookings`, Edit: `manage_bookings`
- **Customers** - View: `view_customers`, Edit: `manage_customers`
- **Services** - View: `view_services`, Edit: `manage_services`
- **Staff** - View: `view_staff`, Edit: `manage_staff`
- **Settings** - Requires: `manage_booking_settings` or `manage_options`
- **Calendar** - View: `view_bookings`, Edit: `manage_bookings`

## Security Checks

All mutating operations enforce:

1. **Nonce Verification** - `check_admin_referer()` or `wp_verify_nonce()`
2. **Capability Check** - `current_user_can()` with granular capability
3. **Input Sanitization** - All inputs sanitized via `LTLB_Sanitizer`
4. **Output Escaping** - All outputs escaped with `esc_html()`, `esc_attr()`, etc.

## Error Responses

- **401 Unauthorized** - User not logged in
- **403 Forbidden** - User lacks required capability
- **422 Unprocessable Entity** - Validation failed (invalid input)
- **400 Bad Request** - Malformed request or missing nonce

## Implementation Notes

### Registration
Capabilities are registered on plugin activation via `LTLB_Role_Manager::register_capabilities()`.

### Menu Filtering
Admin menu is filtered based on user profile via `LTLB_Role_Manager::filter_admin_menu()`.

### Future Enhancements
- **Staff-specific filtering** - Staff should only see their own bookings
- **Multi-location support** - Location-based access control
- **Custom role creation UI** - Admin interface to create custom roles
- **Audit log for permission changes** - Track who gained/lost capabilities

## Testing Checklist

✅ Administrator can access all pages and perform all actions  
✅ Staff user cannot change prices  
✅ Staff user cannot access Settings page  
✅ Staff user cannot process refunds  
✅ Manager can create/edit bookings but not change settings  
✅ CEO can view reports but not edit anything  
✅ All REST endpoints return 403 for unauthorized users  
✅ Nonce checks prevent CSRF attacks  
