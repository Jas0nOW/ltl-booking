# AGENT-SOP (damit Sonnet nicht “stumpf” abarbeitet)

> Ziel: Jeder Punkt wird **systemisch** umgesetzt (Abhängigkeiten, Quer-Verweise, Tests, Doku), nicht nur “irgendwie” implementiert.

## Arbeitsregeln (für jeden einzelnen TODO-Punkt)
1) **Impact-Scan (Pflicht):** Suche im Repo nach allen Referenzen zum betroffenen Feature (grep nach Klassennamen, Hooks, REST-Route, Option-Key, Tabellenname, Script-Handle).
2) **Abhängigkeiten erkennen:** Notiere vor dem Coden kurz: *Inputs → Validate → Persist → Render* + *Notifications* + *Permissions* + *Migrations* + *i18n* + *UI/UX*.
3) **Single Source of Truth:** Lege zentrale Definitionen fest (Status-Maschine, Entities, Settings Scopes) und entferne Duplikate, statt neue “Parallel-Logik” zu bauen.
4) **Backward Compatibility:** Wenn Daten/Schema/Status sich ändern: Migration + Fallback-Reader + versionierter Upgrade-Pfad.
5) **Security Baseline:** Jeder Write-Endpoint braucht Capability + Nonce + Sanitization + Prepared Statements.
6) **Regression-Check:** Nach Implementierung: suche nach “TODO”, “stub”, “mock”, “not implemented”, sowie nach alten Status-Strings/Keys und passe alles an.
7) **UI Konsistenz:** Admin: gleiche Komponenten/Styles/Patterns; Frontend: keine Layout-Brüche bei Schrittwechseln.
8) **Definition of Done:** Ein Punkt gilt erst als done, wenn **Check** aus der Liste erfüllt ist *und* ein kurzer Smoke-Test dokumentiert ist.

## Reihenfolge-Hinweis
- **Nicht früh übersetzen.** Erst Features/Flows stabil machen, dann i18n/Copy finalisieren (sonst doppelte Arbeit).
- **Erst Fundament (P0), dann Parität (P1), dann Skalierung (P2), dann Produktisierung (P3).**

---

# TECHNICAL_AUDIT — Agent Reihenfolge (Top→Down)

## P0 — Blocker & Fundament (muss zuerst stabil sein)

 - 
 
## P1 — Core-Parität (Vik/Amelia) + Admin-Kern

## P2 — Scale, Ecosystem, Feinschliff (nach Kernparität)

## P3 — Produktisierung & Abschluss-Polish
