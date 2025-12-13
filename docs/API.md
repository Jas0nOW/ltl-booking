(Phase 1) Minimal API notes

REST namespace planned: `/wp-json/lazy/v1/` (to be implemented in later commits)

Planned endpoints (Phase 1):
- `GET /services` - list services
- `GET /services/{id}` - get service
- `GET /customers` - list customers
- `POST /customers` - create/upsert customer
- `GET /appointments` - list appointments (filters: from,to,service_id,status)
- `POST /appointments` - create appointment (admin/public with nonce)

