(Phase 1) Minimal API notes

REST namespace planned: `/wp-json/ltlb/v1/` (to be implemented in later commits)

Planned endpoints (Phase 1):
- `GET /services` - list services
- `GET /services/{id}` - get service
- `GET /customers` - list customers
- `POST /customers` - create/upsert customer
- `GET /appointments` - list appointments (filters: from,to,service_id,status)
- `POST /appointments` - create appointment (admin/public with nonce)
- `GET /availability?service_id=ID&date=YYYY-MM-DD` - get available slots for a service and date

Slot response fields (added in Phase 2c):

- Each slot returned by `GET /time-slots` includes:
	- `start`: `YYYY-MM-DD HH:MM:SS` start datetime
	- `end`: `YYYY-MM-DD HH:MM:SS` end datetime
	- `free_resources_count`: integer number of allowed resources that are free for the full slot
	- `resource_ids`: array of resource IDs that are free (may be empty)

The `free_resources_count` and `resource_ids` values are provided when resource mapping is enabled for a service (or when resources exist). They allow the frontend to render a resource dropdown and show availability counts for debugging.

