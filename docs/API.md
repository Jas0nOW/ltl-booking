# LazyBookings REST API

REST namespace: `/wp-json/ltlb/v1/`

## Implemented Endpoints (0.4.0)

### Availability
`GET /ltlb/v1/availability`

Query parameters:
- `service_id` (required) — Service ID
- `date` (required) — Date in format `YYYY-MM-DD`
- `slots` (optional) — When present, returns discrete time slots
- `slot_step` (optional) — Slot step in minutes (default 15)

Returns (raw intervals or slot list depending on `slots`):
```json
[
  { "start": "2025-12-13 09:00:00", "end": "2025-12-13 10:00:00" }
]
```

Notes:
- Availability respects working hours, exceptions, existing appointments and service buffers.
- Permissions: public for Phase 1 to allow frontend wizard; can be tightened later.

### Booking Creation
Booking is handled via shortcode form submission (not direct REST endpoint in Phase 1).

**Form Parameters:**
- `service_id` - Selected service
- `date` - Booking date (YYYY-MM-DD)
- `time_slot` - Selected time slot
- `email` - Customer email
- `first_name` - Customer first name
- `last_name` - Customer last name
- `phone` - Customer phone
- `resource_id` - (optional) Explicitly chosen resource
- `seats` - (optional) Number of seats for group bookings (1..max_seats_per_booking)

**Notes:**
- For group-enabled services, the `seats` field becomes required and is limited to 1..max_seats_per_booking.
- For regular services, `seats` defaults to 1.
- Email templates support placeholder `{seats}` for including seat count in notifications.

### Hotel Availability (Planned)
`GET /ltlb/v1/hotel-availability`

Query parameters:
- `service_id` (required) - Room Type (Service) ID
- `checkin` (required) - Check-in date in format YYYY-MM-DD
- `checkout` (required) - Check-out date in format YYYY-MM-DD (exclusive, no overlap if equals next check-in)
- `guests` (optional, default 1) - Number of guests

Returns on success:
```json
{
  "nights": 2,
  "free_resources_count": 2,
  "resource_ids": [1, 2],
  "total_price_cents": 20000
}
```

Returns on error:
```json
{
  "error": "Invalid date range or guest count"
}
```

Notes:
- `nights` = (checkout_date - checkin_date) calculated as days between, with checkout exclusive
- Example: checkin 2025-12-20, checkout 2025-12-22 = 2 nights
- `free_resources_count` = number of rooms with sufficient capacity for guest count
- `resource_ids` = array of room IDs with available capacity
- `total_price_cents` = (nights × service.price_cents)
- Validation:
  - checkout > checkin (checkout exclusive)
  - nights >= hotel_min_nights and nights <= hotel_max_nights
  - guests >= 1
  - Room capacity >= guests (for each room in free_resources_count calculation)

## WP-CLI Commands (0.4.0)

### Doctor Command
`wp ltlb doctor`

Runs system diagnostics and outputs:
- Plugin/DB version check
- Template mode
- Database table status with row counts
- MySQL Named Lock support test
- Email configuration
- Logging status
- Dev tools gate status
- Last migration timestamp

### Migrate Command
`wp ltlb migrate`

Runs database migrations manually. Safe to call multiple times (uses `dbDelta`).

### Seed Command
`wp ltlb seed [--mode=<service|hotel>]`

Creates demo data for development/testing. Only available when `WP_DEBUG` is true or `enable_dev_tools=1` setting is enabled.

**Service mode** (default):
- 1 service (Demo Yoga Class)
- 2 resources (Studio A, Studio B)
- 2 staff users with working hours
- 1 demo customer

**Hotel mode**:
- 1 room type (Demo Double Room)
- 2 rooms (Room 101, Room 102)
- Sets template mode to hotel
- 1 demo customer

## Planned Future Endpoints
- `GET /services` — list services
- `GET /services/{id}` — get service
- `POST /services` — create service
- `GET /customers` — list customers
- `POST /customers` — create/upsert customer
- `GET /appointments` — list appointments (filters: from,to,service_id,status)
- `PUT /appointments/{id}` — update appointment status

