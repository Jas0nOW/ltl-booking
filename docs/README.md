# 📚 LazyBookings Dokumentation (v1.1.0)

Willkommen zur LazyBookings-Dokumentation! Diese README hilft Ihnen, sich in der Dokumentationsstruktur zurechtzufinden.

## 📖 Dokumentations-Struktur

### 🎯 Schnellstart
- **[API.md](API.md)** - Public API-Dokumentation (REST + Form Submission)

### 🏗️ Architektur & Design
- **[DB_SCHEMA.md](DB_SCHEMA.md)** - Datenbankschema (aktuelle Tables + Planung)
- **[DESIGN_GUIDE.md](DESIGN_GUIDE.md)** - Design Tokens (lazy_design) & CSS Variablen
- **[ERROR_HANDLING.md](ERROR_HANDLING.md)** - Error-Handling-Konventionen

### 🔧 Entwicklung
- **[archive/REPOSITORY_OPTIMIZATION.md](archive/REPOSITORY_OPTIMIZATION.md)** - Performance-Ideen (Archiv)
- **[archive/ADMIN_COLUMNS.md](archive/ADMIN_COLUMNS.md)** - Admin-UI-Ideen (Archiv)

### 🗂️ Archiv & Ideen
- **[archive/REPOSITORY_OPTIMIZATION.md](archive/REPOSITORY_OPTIMIZATION.md)** - Performance-Optimierung (optional)
- **[archive/ADMIN_COLUMNS.md](archive/ADMIN_COLUMNS.md)** - Geplante Admin-UI-Verbesserungen
- **[archive/DOC_OPTIMIZATION_SUMMARY.md](archive/DOC_OPTIMIZATION_SUMMARY.md)** - Historie der Doku-Aufräumaktion

---

## 🚀 Für neue Entwickler

**Empfohlene Lese-Reihenfolge:**

2. **[DB_SCHEMA.md](DB_SCHEMA.md)** → Lerne die Datenstruktur kennen
3. **[API.md](API.md)** → Verstehe die öffentliche API
4. **[ERROR_HANDLING.md](ERROR_HANDLING.md)** → Lerne Code-Konventionen

---

## 📝 Dokumentations-Konventionen

### Status-Labels
- ✅ **IMPLEMENTED** - Feature ist live
- 🚧 **IN PROGRESS** - Wird aktuell entwickelt
- ⏳ **DEFERRED** - Geplant, aber zurückgestellt
- 📋 **PLANNED** - Roadmap-Feature

### Version-Referenzen
Alle Dokumente sollten die aktuelle Plugin-Version referenzieren:
- Aktuell: **v1.1.0**
- DB-Version: wird über die Option `ltlb_db_version` verfolgt (läuft typischerweise parallel zur Plugin-Version)

### Update-Policy
- **SPEC.md** ist Source of Truth - hier Updates zuerst
- **DECISIONS.md** enthält die wichtigsten Architektur-Entscheidungen (kuratiert) + ggf. Legacy-Notizen
- Technische Docs (API, DB_SCHEMA) bei Code-Änderungen updaten

---

## 🔍 Schnellreferenz nach Thema

### Datenbank
→ [DB_SCHEMA.md](DB_SCHEMA.md)

### REST API Endpunkte
→ [API.md](API.md)

### Fehlerbehandlung
→ [ERROR_HANDLING.md](ERROR_HANDLING.md)

### Testing
→ [QA_CHECKLIST.md](QA_CHECKLIST.md)

### Release
→ [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md)

### Architektur-Entscheidungen
→ [DECISIONS.md](DECISIONS.md)  
→ [archive/REPOSITORY_OPTIMIZATION.md](archive/REPOSITORY_OPTIMIZATION.md)

---

## 🛠️ Für Contributors

### Vor dem Coden
1. Lies [SPEC.md](SPEC.md) Kapitel 0 (Arbeitsvertrag)
2. Prüfe [DECISIONS.md](DECISIONS.md) für Kontext
3. Folge [ERROR_HANDLING.md](ERROR_HANDLING.md) Konventionen

### Nach dem Coden
1. Update relevante Docs (API, DB_SCHEMA)
2. Füge Entry zu [DECISIONS.md](DECISIONS.md) hinzu
3. Test mit [QA_CHECKLIST.md](QA_CHECKLIST.md)

### Vor dem Release
1. Vollständiger [QA_CHECKLIST.md](QA_CHECKLIST.md) Durchlauf
2. Folge [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md)
3. Update Version in SPEC.md + ltl-booking.php

---

## 📞 Hilfe & Support

Bei Fragen zur Dokumentation:
1. Prüfe relevante .md-Datei oben
2. Suche in [DECISIONS.md](DECISIONS.md) nach Keyword
3. Öffne Issue im Repository

**Happy Coding! 🎉**
