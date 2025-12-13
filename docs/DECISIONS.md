Commit 1 decisions:

- Sanitizer: implemented `LTLB_Sanitizer` with basic helpers (`text`, `int`, `money_cents`, `email`, `datetime`). Uses WP sanitize helpers where available.
- Loading: For Phase 1 we use explicit `require_once` in `Includes/Core/Plugin.php` instead of an autoloader.
- Table prefix: per SPEC we use `$wpdb->prefix . 'lazy_' . name` (ServiceRepository will read `lazy_services`).
- Staff role `ltlb_staff` added with capabilities to view/edit their own working hours.
- Admin can manage all staff hours.

Commit 2 decisions (Time & storage):

- Time helper: added `LTLB_Time` in `includes/Util/Time.php` providing `wp_timezone()`, `create_datetime_immutable()`, `parse_date_and_time()`, `format_wp_datetime()`, `day_start()/day_end()` and `generate_slots_for_day()`.
- Storage concept: `start_at` and `end_at` are stored in the site timezone as `Y-m-d H:i:s` strings (matching `LTLB_Time::format_wp_datetime`). This keeps DB values consistent with admin views; DST edge-cases are handled by using site timezone during parse/format. Note: this choice simplifies Phase 1 — migrating to UTC storage can be considered later.
- New tables `lazy_staff_hours` and `lazy_staff_exceptions` created for staff working hours and exceptions.
- Schema updated in `DB_SCHEMA.md`.

Commit 3 decisions (Conflict handling & race conditions):

- Blocking statuses: by default only `confirmed` blocks a slot. The setting `lazy_settings.pending_blocks` (boolean) controls whether `pending` also blocks slots.
- Double-check before insert: `AppointmentRepository::create()` performs a final `has_conflict()` check immediately before insertion to reduce race conditions.
- Locking: booking creation is protected with MySQL named locks (`GET_LOCK`/`RELEASE_LOCK`) via `LTLB_LockManager`.
- Fallback: if `GET_LOCK` is unavailable on a host, `LTLB_LockManager` falls back to an option-based mutex (`add_option`/`delete_option`) for best-effort protection.
- Limitations: Without DB transactions or row-level locking, true atomicity cannot be guaranteed across all hosts. We document this and plan to consider DB transactions / UTC storage / unique constraints in future commits.
# LazyBookings Decisions (v0.4.4)

Diese Datei fasst die wichtigsten technischen Entscheidungen zusammen, die für das Verständnis von LazyBookings relevant sind.
Für den vollständigen Ist-Stand: siehe `docs/SPEC.md`.

## Kernprinzipien

- **Custom Tables statt Post Types**: Services, Customers, Appointments, Resources und Staff-Verfügbarkeiten liegen in eigenen Tabellen (`lazy_*`).
- **Explizite Includes**: Kein Autoloader; Dateien werden in `Includes/Core/Plugin.php` explizit geladen.
- **Repository Layer**: Datenbankzugriffe sind in Repository-Klassen kapsuliert.

## Zeit / Timezone

- **Speicherung in Site-Timezone**: `start_at`/`end_at` werden als lokale `DATETIME` Werte gespeichert (WordPress Site Timezone).
- **Format**: `Y-m-d H:i:s`.

## Availability / Slot-Engine

- **Intersection-Prinzip**: Verfügbarkeit entsteht aus globalen Zeiten + Staff Hours/Exceptions + Service-Regeln + bestehenden Appointments.
- **Per-Service Availability Regeln**:
	- `available_weekdays` (CSV `0..6`, 0=Sonntag)
	- optionales Tages-Zeitfenster (`available_start_time`/`available_end_time`)
	- `availability_mode`:
		- `window`: Startzeiten innerhalb der (kombinierten) Fensterlogik
		- `fixed`: nur definierte wöchentliche Startzeiten (`fixed_weekly_slots` JSON)

## Concurrency / Double Booking Schutz

- **Named Locks (best effort)**: Booking-Erstellung ist mit MySQL `GET_LOCK()`/`RELEASE_LOCK()` abgesichert.
- **Fallback Mutex**: Falls `GET_LOCK` nicht verfügbar ist, wird ein Option-Lock als Fallback genutzt.

## Schnittstellen

- **REST Namespace**: `ltlb/v1`
- **Public Read Endpoints**: Availability/Slots Endpoints sind öffentlich, damit der Frontend Wizard ohne Auth laden kann.
- **Write Flow**: Buchungen werden über den Shortcode Wizard angelegt.

## Migration Policy

- **dbDelta**: Schema wird via `dbDelta()` gepflegt.
- **DB Version**: Option `ltlb_db_version` wird genutzt, um Migrationsläufe auszulösen.
Commit 8 decisions:



- Availability engine upgraded to consider staff hours and exceptions.
