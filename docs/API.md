# LazyBookings API Reference (v0.4.4)

## 📚 Overview

LazyBookings provides three integration layers:
1. **REST API** - Public endpoints for availability checks
2. **Form Submission** - Frontend booking via shortcode
3. **WP-CLI Commands** - Admin tools for diagnostics and seeding

---

## 🌐 REST API Endpoints

### Base URL
```
/wp-json/ltlb/v1/
```

### Authentication
LazyBookings has two REST API categories:
- **Public read endpoints** (used by the frontend wizard) → no authentication
- **Admin endpoints** (calendar + CRUD helpers) → require WordPress admin login and a REST nonce

Public endpoints are unauthenticated. A lightweight rate limit exists but is disabled by default (see `lazy_settings.rate_limit_enabled`).

---

## 1️⃣ Availability Check

### Service/Kurs Slots
**URL:** `GET /wp-json/ltlb/v1/availability`

**Registered in:** [Includes/Core/Plugin.php](../Includes/Core/Plugin.php)

**Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `service_id` | int | ✅ Yes | - | Service ID to check |
| `date` | string | ✅ Yes | - | Date in `YYYY-MM-DD` format |
| `slots` | bool | No | false | If truthy, return the slot list directly |
| `slot_step` | int | No | 15 | Slot interval in minutes |

**Response (default, `slots` omitted):**
```json
{
  "slots": [
    {
      "time": "09:00",
      "start": "2025-12-13 09:00:00",
      "end": "2025-12-13 10:00:00",
      "free_resources_count": 2,
      "resource_ids": [1, 2],
      "spots_left": 5
    }
  ]
}
```

**Response (`slots=1`):**
```json
[
  {
    "time": "09:00",
    "start": "2025-12-13 09:00:00",
    "end": "2025-12-13 10:00:00",
    "free_resources_count": 2,
    "resource_ids": [1, 2],
    "spots_left": 5
  }
]
```

**Notes / Constraints Applied:**
- Respects global working hours, staff hours/exceptions (if service has staff assigned)
- Respects per-service restrictions:
  - allowed weekdays + optional time window
  - OR fixed weekly start times (e.g. Fri 18:00)
- Respects existing bookings per resource (capacity-aware)
- Returns an empty list when no slots are available

---

## 🏨 Hotel Availability (Date Range)

**URL:** `GET /wp-json/ltlb/v1/hotel/availability`

**Registered in:** [public/Shortcodes.php](../public/Shortcodes.php)

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `service_id` | int | ✅ Yes | Room type (service) ID |
| `checkin` | string | ✅ Yes | Date in `YYYY-MM-DD` |
| `checkout` | string | ✅ Yes | Date in `YYYY-MM-DD` |
| `guests` | int | No | Defaults to `1` |

**Response:**
```json
{
  "nights": 2,
  "free_resources_count": 3,
  "resources": [
    {"id": 10, "name": "Room 10", "capacity": 2, "used": 0, "available": 2, "fits": true}
  ],
  "total_price_cents": 20000,
  "currency": "EUR"
}
```

**Notes / Constraints Applied:**
- Capacity-aware using `appointments.seats` per room
- Respects `lazy_settings.pending_blocks` (include `pending` as blocking when enabled)
- Check-in/out times are applied from settings when computing the occupied datetime range

---

## 🔒 Admin REST API (Calendar + CRUD)

These endpoints are used by the WP Admin Calendar page.

**Auth requirements:**
- Logged-in WP Admin user
- Capability: `manage_options`
- REST nonce header: `X-WP-Nonce` (`wp_create_nonce('wp_rest')`)

**Registered in:** [Includes/Core/Plugin.php](../Includes/Core/Plugin.php)

### 4️⃣ Calendar Events

**URL:** `GET /wp-json/ltlb/v1/admin/calendar/events`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `start` | string | ✅ Yes | ISO date/time (FullCalendar start range) |
| `end` | string | ✅ Yes | ISO date/time (FullCalendar end range) |

**Response:** FullCalendar-compatible events
```json
[
  {
    "id": "123",
    "title": "Yoga – Jane Doe",
    "start": "2025-12-13T09:00:00+01:00",
    "end": "2025-12-13T10:00:00+01:00",
    "extendedProps": {
      "status": "confirmed",
      "service_id": 1,
      "customer_id": 5,
      "customer_email": "jane@example.com"
    }
  }
]
```

### 5️⃣ Get Appointment Details

**URL:** `GET /wp-json/ltlb/v1/admin/appointments/{id}`

**Response:**
```json
{
  "appointment": { "id": 123, "service_id": 1, "customer_id": 5, "start_at": "...", "end_at": "...", "status": "confirmed" },
  "service": { "id": 1, "name": "Yoga" },
  "customer": { "id": 5, "email": "jane@example.com", "first_name": "Jane", "last_name": "Doe" }
}
```

### 6️⃣ Move/Resize Appointment

**URL:** `POST /wp-json/ltlb/v1/admin/appointments/{id}/move`

**Body:**
```json
{ "start": "2025-12-13T09:00:00.000Z", "end": "2025-12-13T10:00:00.000Z" }
```

**Response:**
```json
{ "ok": true }
```

**Conflict behavior:**
- If the new time range overlaps an existing booking (blocking statuses), the endpoint responds with HTTP `409` and:
```json
{ "ok": false, "error": "conflict" }
```

### 7️⃣ Update Appointment Status

**URL:** `POST /wp-json/ltlb/v1/admin/appointments/{id}/status`

**Body:**
```json
{ "status": "confirmed" }
```

**Allowed:** `pending`, `confirmed`, `cancelled`

### 8️⃣ Delete Appointment

**URL:** `DELETE /wp-json/ltlb/v1/admin/appointments/{id}`

**Response:**
```json
{ "ok": true }
```

### 9️⃣ Update Customer

**URL:** `POST /wp-json/ltlb/v1/admin/customers/{id}`

**Body (any subset):**
```json
{
  "email": "jane@example.com",
  "first_name": "Jane",
  "last_name": "Doe",
  "phone": "+49...",
  "notes": "..."
}
```

**Response:**
```json
{ "ok": true }
```

### Hotel Mode
Hotel mode uses a date-range flow (check-in/check-out + guests) and exposes a dedicated public availability endpoint.

Hotel bookings created via the frontend shortcode submission are protected by a MySQL named lock (`LTLB_LockManager::build_hotel_lock_key`) to reduce double-booking race conditions.

---

## 2️⃣ Time Slots (Legacy Convenience Endpoint)

**URL:** `GET /wp-json/ltlb/v1/time-slots`

**Status:** Supported. Prefer `/availability?slots=1` if you need `slot_step` control.

**Registered in:** [public/Shortcodes.php](../public/Shortcodes.php)

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `service_id` | int | ✅ Yes | Service ID |
| `date` | string | ✅ Yes | Date in `YYYY-MM-DD` |

**Response:** same slot list format as `/availability?slots=1`.

**Note:** currently uses the default slot step (15 minutes).

---

## 3️⃣ Slot Resources

**URL:** `GET /wp-json/ltlb/v1/slot-resources`

**Registered in:** [public/Shortcodes.php](../public/Shortcodes.php)

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `service_id` | int | ✅ Yes | Service ID |
| `start` | string | ✅ Yes | Start time `YYYY-MM-DD HH:MM:SS` |

**Start time format:** accepts ISO 8601 (`2025-12-13T09:00:00+01:00`) or `YYYY-MM-DD HH:MM:SS`.

**Response:**
```json
{
  "free_resources_count": 2,
  "resources": [
    {
      "id": 1,
      "name": "Raum A",
      "capacity": 10,
      "used": 3,
      "available": 7
    }
  ]
}
```

**Usage:** Shows detailed resource availability for a specific time slot.

---

## 📋 Form Submission API

### Booking Creation (Service Mode)

Service mode uses `date` + `time_slot`.

### Booking Creation (Hotel Mode)

Hotel mode uses `checkin` + `checkout` + `guests`, and optionally `resource_id` for a preferred room.

**Key fields (POST):**
- `service_id` (room type)
- `checkin` (`YYYY-MM-DD`)
- `checkout` (`YYYY-MM-DD`)
- `guests` (maps to `appointments.seats`)
- `resource_id` (optional; room preference)

**Behavior:**
- Creates an appointment spanning check-in/out times (from settings)
- Assigns a room via `lazy_appointment_resources`
- Sends admin + customer notifications (if enabled)

### Booking Creation

**Endpoint:** Frontend form submission handled by [public/Shortcodes.php](../public/Shortcodes.php)

**Method:** POST

**Required Fields (common):**
| Field | Type | Description |
|-------|------|-------------|
| `service_id` | int | Selected service/room type |
| `email` | string | Customer email (validated) |
| `first_name` | string | Customer first name (optional) |
| `last_name` | string | Customer last name (optional) |

**Service Mode Fields:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `date` | string | ✅ Yes | Booking date `YYYY-MM-DD` |
| `time_slot` | string | ✅ Yes | Selected time slot `HH:MM` |
| `resource_id` | int | No | Explicit resource selection |

Note: the schema supports `seats` internally, but the current frontend wizard does not expose a “number of seats” input yet.

**Hotel Mode Fields (wizard inputs exist):**
These inputs exist in the wizard UI when Template Mode is set to `hotel`, but they are not yet processed by the v0.4.4 booking submission handler.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `checkin` | string | UI | Check-in date `YYYY-MM-DD` |
| `checkout` | string | UI | Check-out date `YYYY-MM-DD` |
| `guests` | int | UI | Number of guests |

**Optional Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `phone` | string | Customer phone number |

**Response:**
- **Success:** Returns a success message HTML block
- **Error:** Returns an error message HTML block

**Security:**
- Nonce verification via `ltlb_book_nonce`
- Honeypot field for bot detection
- Email validation and sanitization
- MySQL named lock prevents race conditions

---

## 🖥️ WP-CLI Commands

### Doctor Command
```bash
wp ltlb doctor
```

**Purpose:** System diagnostics

**Output:**
- Plugin version & DB version
- Template mode (service/hotel)
- Database table status with row counts
- MySQL Named Lock support test
- Email configuration
- Logging status
- Dev tools gate status
- Last migration timestamp

**Usage:** Troubleshooting production issues, pre-deployment checks

---

### Migrate Command
```bash
wp ltlb migrate
```

**Purpose:** Manual database migration trigger

**Notes:**
- Safe to run multiple times (uses `dbDelta`)
- Creates or updates all plugin tables
- Updates `ltlb_db_version` option
- Logs results to PHP error log

**Usage:** Forcing schema updates, repair corrupted tables

---

### Seed Command
```bash
wp ltlb seed [--mode=<service|hotel>]
```

**Purpose:** Create demo data for development/testing

**Availability:** Only when `WP_DEBUG=true` OR `enable_dev_tools=1` setting enabled

**Service Mode** (default):
- 1 service (Demo Yoga Class)
- 2 resources (Studio A, Studio B)
- 2 staff users with working hours
- 1 demo customer

**Hotel Mode:**
- 1 room type (Demo Double Room)
- 2 rooms (Room 101, Room 102)
- Sets template mode to hotel
- 1 demo customer

**Usage:**
```bash
# Service mode demo data
wp ltlb seed

# Hotel mode demo data
wp ltlb seed --mode=hotel
```

**⚠️ Security:** Gated by dev tools to prevent accidental seeding on production

---

## 🔮 Planned Future Endpoints

### Services CRUD
- `GET /services` — List all services
- `GET /services/{id}` — Get single service
- `POST /services` — Create service (admin only)
- `PUT /services/{id}` — Update service (admin only)
- `DELETE /services/{id}` — Delete service (admin only)

### Customers CRUD
- `GET /customers` — List customers (admin only)
- `GET /customers/{id}` — Get customer (admin only)
- `POST /customers` — Create/upsert customer

### Appointments CRUD
- `GET /appointments` — List appointments with filters (from, to, service_id, status)
- `GET /appointments/{id}` — Get appointment details
- `PUT /appointments/{id}` — Update appointment status (admin only)
- `DELETE /appointments/{id}` — Cancel appointment

---

## 🔧 Known Issues & Improvements

### Consolidation Needed
- `/time-slots` duplicates `/availability?slots=1` and exists mainly for backwards compatibility
- REST route registration is currently split between:
  - `Includes/Core/Plugin.php` (`/availability`)
  - `public/Shortcodes.php` (`/time-slots`, `/slot-resources`)
  A future refactor could centralize registration, but it is not required for correctness.

### Security Enhancements
- Add `permission_callback` for admin endpoints with `manage_options` capability check
- Implement rate limiting for public endpoints (prevent abuse)
- Add nonce validation for POST requests

### Performance Optimizations
- Cache availability results per-day (transients)
- Add query result caching to Repository classes
- Implement pagination for list endpoints

---

## 📚 Related Documentation

- **Database Schema:** [DB_SCHEMA.md](DB_SCHEMA.md)
- **Error Handling:** [ERROR_HANDLING.md](ERROR_HANDLING.md)
- **Architecture Decisions:** [ENGINE_DECISION.md](ENGINE_DECISION.md)
- **Full Specification:** [SPEC.md](SPEC.md)

---

**Last Updated:** v0.4.4

