# Engine-Architektur Entscheidung (v0.4.4)

## Status: DEFERRED (Aufgeschoben)

## Hintergrund
Das Plugin enthält eine Engine-Architektur (`ServiceEngine`, `HotelEngine`, `EngineFactory`) die verschiedene Booking-Modi unterstützen sollte.

## Aktuelle Situation
- ✅ `EngineFactory` existiert und wird geladen
- ✅ `ServiceEngine` und `HotelEngine` sind implementiert
- ❌ **Shortcodes verwenden die Engines NICHT** - der Code ist direkt im `Shortcodes.php`
- ❌ Engines werden nirgendwo aufgerufen

## Optionen

### Option 1: Vollständige Integration (empfohlen, aber aufwändig)
**Aufwand:** Hoch (4-6 Stunden)  
**Vorteil:** Saubere Architektur, erweiterbar, wartbar

**Schritte:**
1. Refactor `Shortcodes::_create_appointment_from_submission()` → nutzt `EngineFactory::get_engine()->create_booking()`
2. Refactor `Shortcodes::get_time_slots()` → nutzt `EngineFactory::get_engine()->get_time_slots()`
3. Template-Logic trennen (wizard.php bleibt, aber Daten kommen von Engine)
4. Tests schreiben für beide Modi

### Option 2: Engine-Architektur entfernen (schnell, aber weniger elegant)
**Aufwand:** Niedrig (30 Min)  
**Vorteil:** Reduziert Komplexität, klarer Code

**Schritte:**
1. Lösche `Includes/Engine/` Ordner
2. Entferne `require_once` aus `Plugin.php`
3. Dokumentiere, dass Template-Mode nur UI-Switch ist (Service vs Hotel Formular)

### Option 3: Status Quo beibehalten (aktuell)
**Aufwand:** 0  
**Vorteil:** Nichts kaputt machen  
**Nachteil:** "Dead Code" im Projekt

## Entscheidung für diese Session

✅ **STATUS QUO BEIBEHALTEN**

**Begründung:**
- Die aktuelle Shortcode-Implementation funktioniert
- Template-Mode switcht nur zwischen `wizard.php` UI-Varianten
- Engine-Architektur ist "future-proofing" für später
- Keine Zeit für vollständige Refactoring in dieser Session

## Nächste Schritte (Future)
- [ ] Wenn HotelEngine Features hinzukommen (z.B. Nächte-Berechnung, Check-in/out), dann Option 1 implementieren
- [ ] Bis dahin: Engines als Dokumentation/Beispiel-Code behalten

## Notiz für Entwickler
Die `ServiceEngine` und `HotelEngine` Klassen sind funktionsfähig, aber werden aktuell NICHT im Live-Code verwendet. Sie dienen als Architektur-Vorlage für zukünftige Refactorings.
