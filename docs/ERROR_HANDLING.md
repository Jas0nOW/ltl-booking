# Error Handling Strategie (v0.4.4)

## Übersicht
LazyBookings verwendet eine **gemischte Error-Handling-Strategie**:
- WordPress `WP_Error` für erwartete Fehler (User-Input, Business-Logic)
- PHP `error_log()` für System-Fehler
- Custom `LTLB_Logger` für strukturiertes Logging

## Konventionen

### 1. Business Logic Errors → `WP_Error`
Verwende `WP_Error` für Fehler, die dem Benutzer angezeigt werden können:

```php
// ❌ FALSCH
if (empty($email)) {
    return false;
}

// ✅ RICHTIG
if (empty($email)) {
    return new WP_Error('missing_email', __('Email is required', 'ltl-bookings'));
}
```

**Verwendung:**
- Validierungsfehler (fehlende Felder, ungültige Werte)
- Business-Konflikte (Slot bereits gebucht, keine Kapazität)
- Zugriffsrechte-Probleme

### 2. System Errors → `LTLB_Logger`
Verwende Logger für technische Fehler, die nicht User-facing sind:

```php
// Datenbankfehler
if (!$result) {
    LTLB_Logger::error('Failed to create appointment', [
        'service_id' => $service_id,
        'error' => $wpdb->last_error
    ]);
    return new WP_Error('db_error', __('System error. Please try again.', 'ltl-bookings'));
}

// Lock-Timeout
if (!$lock_acquired) {
    LTLB_Logger::warn('Lock timeout', ['key' => $lock_key]);
    return new WP_Error('lock_timeout', __('Another booking in progress', 'ltl-bookings'));
}
```

### 3. Return Types

#### Repository-Methoden
```php
// Create/Insert → int|WP_Error
public function create(array $data): int|WP_Error {
    // Returns: new ID on success, WP_Error on failure
}

// Get/Fetch → array|null
public function get_by_id(int $id): ?array {
    // Returns: associative array, or null if not found
}

// Update/Delete → bool
public function delete(int $id): bool {
    // Returns: true on success, false on failure
}
```

#### Service/Engine-Methoden
```php
// Operations that can fail with user-facing errors
public function create_booking(array $payload): int|WP_Error {
    // Returns: appointment ID or WP_Error
}

// Validation
public function validate_payload(array $payload): true|WP_Error {
    // Returns: true if valid, WP_Error with details if invalid
}
```

#### Utility-Methoden
```php
// Pure functions → specific type or null
public function parse_date(string $input): ?DateTime {
    // Returns: DateTime object or null
}

// Operations with side effects → bool
public function send_email(array $data): bool {
    // Returns: true if sent, false otherwise
}
```

## Error Checking Best Practices

### 1. Immer prüfen nach Repository-Calls
```php
$appt_id = $appointment_repo->create($data);
if (!$appt_id) {
    LTLB_Logger::error('Failed to create appointment');
    return new WP_Error('db_error', 'Could not save appointment');
}
```

### 2. WP_Error Propagation
```php
$result = $this->validate_booking($data);
if (is_wp_error($result)) {
    return $result; // Bubble up
}
```

### 3. User-Facing vs Internal Messages
```php
// ❌ FALSCH - zeigt interne Details
return new WP_Error('query_failed', $wpdb->last_error);

// ✅ RICHTIG - generische User-Message, detailliertes Logging
LTLB_Logger::error('DB query failed', ['query' => $sql, 'error' => $wpdb->last_error]);
return new WP_Error('system_error', __('An error occurred. Please try again.', 'ltl-bookings'));
```

## Logging Levels

```php
LTLB_Logger::error()  // Fatale Fehler (DB-Fehler, Booking fehlgeschlagen)
LTLB_Logger::warn()   // Warnungen (Lock-Timeout, Rate-Limit erreicht)
LTLB_Logger::info()   // Normale Events (Booking erstellt, Email versendet)
LTLB_Logger::debug()  // Debug-Info (Availability-Berechnung, Parameter)
```

## Exception Handling

**Aktueller Stand:** Plugin verwendet **KEINE** Exceptions.

**Empfehlung für Future:**
- Behalte `WP_Error` für Business Logic
- Erwäge Exceptions für kritische System-Fehler (DB-Verbindung verloren, Config fehlt)
- **Niemals** unbehandelte Exceptions im Frontend

## Testing Error Paths

### Unit Tests sollten prüfen:
```php
// Happy Path
$result = $service->create_booking($valid_data);
$this->assertIsInt($result);

// Error Path
$result = $service->create_booking($invalid_data);
$this->assertWPError($result);
$this->assertEquals('missing_email', $result->get_error_code());
```

## Zusammenfassung

| Situation | Return Type | Logging | User Message |
|-----------|-------------|---------|--------------|
| Validation Fehler | `WP_Error` | Nein | Ja, spezifisch |
| Business Konflikt | `WP_Error` | Optional | Ja, spezifisch |
| DB Fehler | `WP_Error` | Ja (error) | Ja, generisch |
| Lock Timeout | `WP_Error` | Ja (warn) | Ja, generisch |
| Success Info | - | Ja (info) | Nein |
| Debug Info | - | Ja (debug) | Nein |
