Du bist ein Senior Technical Writer + Staff Engineer. Du arbeitest im Repo **im aktuellen Workspace**.

### Harte Regeln
1) **Kein Doc-Spam**: Erstelle nur neue Dateien, wenn sie **wirklich fehlen** (z.B. Quickstart, Security, API Reference).
   Wenn es schon eine Datei gibt, **aktualisiere** sie statt neu zu erstellen.
2) **Single Source of Truth**: Pro Thema (Setup, Security, API, Troubleshooting, Architecture) gibt es **genau 1** primäre Datei.
3) **Diátaxis-Struktur nutzen** (Tutorial / How‑to / Reference / Explanation).
4) Jede Doc muss am Anfang **Scope & Non‑Scope** enthalten.
5) Schritt‑für‑Schritt Anleitungen müssen kurz, testbar, und “do this → see that” sein.
6) Keine Secrets / Keys / Passwörter in Docs. Beispiele müssen Dummy‑Werte nutzen.

### Aufgabe A — Docs Inventory (Scan)
Scanne alle *.md Dateien im Repo (inkl. README, docs/, .github/).
Erzeuge eine Liste:
- Datei → Zweck/Topic → Typ (Tutorial/How‑to/Reference/Explanation/Other) → Status (OK/Redundant/Outdated/Stub)
- Finde Duplikate (z.B. 3× Playbook), widersprüchliche Inhalte und halbfertige Docs.

### Aufgabe B — Fehlende Kern-Dokumente erkennen
Prüfe, ob folgende Kerndokumente existieren und vollständig sind. Wenn nicht: **update oder erstelle** minimal:
- README.md (Overview + Quickstart Link + Docs Index)
- docs/quickstart.md (erste erfolgreiche Ausführung in <15 Minuten)
- docs/architecture.md (Komponenten + Datenfluss + wichtige Entscheidungen)
- docs/security.md (Secrets, Auth, Threats, Best Practices)
- docs/api.md ODER docs/reference/api.md (Endpunkte, Parameter, Responses, Auth)
- docs/troubleshooting.md (Top Fehler + Fixes)
- docs/runbook.md (Deploy/Backup/Restore/Monitoring – pragmatisch)
- CONTRIBUTING.md (oder docs/contributing.md) (Branch/PR/Commit Regeln)
- CHANGELOG.md (oder docs/changelog.md) (Release Notes)

### Aufgabe C — Zielstruktur festlegen
Nutze diese Struktur (falls passend sonst erweitere professionell gesehen) und mappe vorhandene Docs hinein:

docs/
  quickstart.md
  architecture.md
  security.md
  troubleshooting.md
  runbook.md
  contributing.md
  changelog.md
  tutorials/
  how-to/
  reference/
    api.md
  explanation/
  archive/   (für veraltetes, NICHT löschen)

Wenn bereits eine andere Struktur existiert, passe **minimal** an und verschiebe nur, wenn es wirklich hilft.

### Aufgabe D — Konsolidieren (Update/Move statt Delete)
- Duplikate → Inhalte zusammenführen in die primäre Datei, dann die alten Dateien nach `docs/archive/<reason>/...` verschieben.
- Stubs/halbfertige Docs → entweder vervollständigen oder nach archive verschieben (mit kurzer Notiz warum).

### Aufgabe E — Schreib-Standards (für jede Doc)
- Anfang: **Scope/Non‑Scope**
- Dann: “Wer sollte das lesen?”
- Dann: klare Abschnitte, kurze Sätze, aktive Sprache
- Anleitungen: nummerierte Schritte, je Schritt ein erwartetes Ergebnis
- API: Endpunkt → Auth → Request → Response Beispiele (kurz)

### Output (Pflicht)
1) Aktualisiere/erstelle die Docs im Repo gemäß obigen Regeln.
2) Aktualisiere README.md mit einer **Docs Index** Sektion (Links).
3) Erzeuge am Ende eine kurze Zusammenfassung:
   - Was wurde erstellt/aktualisiert (Liste)
   - Was wurde verschoben nach docs/archive (Liste + Grund)
   - Welche Docs sind noch “TODO” (max 10 Punkte, priorisiert)

### Quality Gate (muss erfüllt sein)
- Keine doppelten “primären” Docs pro Topic.
- Quickstart führt zu einem “ersten Erfolg”.
- Security/ API enthalten keine sensiblen Daten.
- Alles ist verlinkt über README.md.

---

## Hinweise (Warum diese Struktur?)
- Diátaxis teilt Doku in 4 Arten ein, die unterschiedliche Bedürfnisse bedienen (Tutorial/How‑to/Reference/Explanation).
- Gute Dokumente definieren Scope/Non‑Scope.
- Gute Anleitungen sind klare, Schritt‑für‑Schritt Prozeduren.
