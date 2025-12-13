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
    "resource_ids": [1, 2]
  }
]
```

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

## Planned Future Endpoints
- `GET /services` - list services
- `GET /services/{id}` - get service
- `GET /customers` - list customers
- `POST /customers` - create/upsert customer
- `GET /appointments` - list appointments (filters: from,to,service_id,status)
- `PUT /appointments/{id}` - update appointment status

