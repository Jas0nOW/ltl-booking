# LazyBookings REST API

REST namespace: `/wp-json/ltlb/v1/`

## Implemented Endpoints

### Time Slots
`GET /ltlb/v1/time-slots`

Query parameters:
- `service_id` (required) - Service ID
- `date` (required) - Date in format YYYY-MM-DD
- `slot_step` (optional) - Slot step in minutes (default from settings or 15)

Returns:
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

Notes:
- For group services: `spots_left` = minimum available seats across all free resources
- For regular services: `spots_left` = minimum resource capacity available

### Slot Resources
`GET /ltlb/v1/slot-resources`

Query parameters:
- `service_id` (required)
- `start` (required) - Start datetime in format YYYY-MM-DD HH:MM:SS

Returns:
```json
{
  "free_resources_count": 2,
  "resources": [
    {
      "id": 1,
      "name": "Room A",
      "capacity": 5,
      "used": 2,
      "available": 3
    }
  ]
}
```

### Booking Creation
Booking is handled via shortcode form submission (not direct REST endpoint for security).

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

### Hotel Availability (Phase 4)
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

## Planned Future Endpoints
- `GET /services` - list services
- `GET /services/{id}` - get service
- `GET /customers` - list customers
- `POST /customers` - create/upsert customer
- `GET /appointments` - list appointments (filters: from,to,service_id,status)
- `PUT /appointments/{id}` - update appointment status

