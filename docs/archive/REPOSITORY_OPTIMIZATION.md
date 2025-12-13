# Repository Optimierung

## Aktueller Stand
Repositories werden in fast jeder Methode neu instanziiert:

```php
public function render(): void {
    $service_repo = new LTLB_ServiceRepository();
    $customer_repo = new LTLB_CustomerRepository();
    // ...
}
```

**Problem:** Keine Wiederverwendung, kein Caching, redundante DB-Verbindungen.

## Empfohlene Lösungen

### Option 1: Repository Registry Pattern (Empfohlen)
**Vorteil:** Zentrale Verwaltung, lazy loading, einfach zu testen

```php
// Includes/Repository/RepositoryFactory.php
class LTLB_RepositoryFactory {
    private static $instances = [];
    
    public static function get_service_repository(): LTLB_ServiceRepository {
        if (!isset(self::$instances['service'])) {
            self::$instances['service'] = new LTLB_ServiceRepository();
        }
        return self::$instances['service'];
    }
    
    // Repeat for each repository...
    
    public static function reset(): void {
        self::$instances = [];
    }
}

// Usage
$service_repo = LTLB_RepositoryFactory::get_service_repository();
```

### Option 2: Dependency Injection (Komplexer, aber testbarer)
```php
class LTLB_Admin_ServicesPage {
    private $service_repository;
    
    public function __construct(
        LTLB_ServiceRepository $service_repo = null,
        LTLB_ResourceRepository $resource_repo = null
    ) {
        $this->service_repository = $service_repo ?? new LTLB_ServiceRepository();
        $this->resource_repository = $resource_repo ?? new LTLB_ResourceRepository();
    }
}
```

### Option 3: Static Accessor Methods in Repository
```php
class LTLB_ServiceRepository {
    private static $instance = null;
    
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

// Usage
$services = LTLB_ServiceRepository::instance()->get_all();
```

## Entscheidung für dieses Projekt

✅ **OPTION 3: Static Accessor (Singleton-Light)**

**Begründung:**
- Minimale Code-Änderungen
- Rückwärtskompatibel (constructor bleibt)
- Einfach zu verstehen
- Keine neue Factory-Klasse nötig

## Implementation

Da dies viele Dateien betrifft und optional ist, wird dies als **DEFERRED** markiert.

### Wenn implementiert, dann:
1. Füge `instance()` Methode zu allen Repository-Klassen hinzu
2. Ändere `new Repository()` → `Repository::instance()` in oft genutzten Pfaden
3. Behalte `new Repository()` in Tests für Isolation

## Performance-Impact

**Aktuell:** Gering - Repositories haben keinen State und DB-Connection ist global via `$wpdb`.

**Nach Optimierung:** Marginal besser - hauptsächlich weniger Objekt-Allokation, nicht DB-Performance.

## Status
⏳ **DEFERRED** - Optional, niedrige Priorität, kein kritischer Bug
