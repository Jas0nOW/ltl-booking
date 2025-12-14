## Gemini 3 Pro Preview (Tech/Link/Completeness Scan)

Scanne das gesamte Repo (alle Dateien/Ordner). Ziel: Finde ALLE technischen Probleme rund um Vollständigkeit, Verlinkung und Redundanz.

Pflicht: finde
- Orphans: existiert, aber wird nirgends importiert/required/enqueued/registriert/geroutet/gehooked/aufgerufen
- Dangling references: import/include/enqueue/template/asset referenziert, aber fehlt oder Pfad falsch
- Halbe Features: UI/Felder/Settings existieren, aber Input→Validate→Persist→Render ist nicht vollständig
- Duplikate/Dead code: doppelte Logik/Files/Styles/Dependencies, ungenutzte Dateien
- Build/Release Probleme: dist/zip/excludes/version mismatch, fehlende Dateien im Paket
- Security/Performance Basics (nur offensichtliche): fehlende sanitization/escaping/nonces/capabilities (falls WP), unsichere DB/file ops, teure Loops/Queries

Output: NUR eine TODO-Liste in Markdown.
Sortierung: P0, P1, P2, P3.
Format je Punkt exakt:
- [P?] <Titel> — <pfad>:<stelle/funktion/komponente>
  - Fix: <1 Satz konkret>
  - Check: <1 Satz verifizieren>

Keine Erklärtexte. Starte jetzt und scanne das gesamte Repo vollständig.


## Claude Sonnet 4.5 (UX/Design/Polish + Copy-Audit)

Führe einen “Agency-Grade UX/Design/Polish + Copy Audit” über das gesamte Repo durch (ohne Runtime). Ziel: Finde alles, was optisch/UX-mäßig oder textlich billig/unfertig wirkt, und formuliere konkrete TODOs.

Prüfe zusätzlich explizit ALLE genutzten Texte (UI/Copy):
- Labels, Buttons, Tooltips, Helptext, Placeholder
- Fehlermeldungen, Success/Info/Warning Notices, Empty-State Texte
- Konsistenz: Du/Sie, Tonalität, Begriffe (z.B. “Buchung” vs “Termin”), Groß-/Kleinschreibung
- Verständlichkeit & Kürze: keine DEV-Texte, klare Handlungsaufforderungen
- i18n (falls WP): Strings mit __()/esc_html__(), Textdomain konsistent, keine hardcoded Strings in Templates ohne Übersetzungsfunktion
- Rechtschreibung/Typo-Fehler, doppelte/ähnliche Texte

Output: NUR eine TODO-Liste in Markdown.
Sortierung: P0, P1, P2, P3 (Design/Copy-P0 nur wenn UI unbenutzbar/irreführend).
Format je Punkt exakt:
- [P?] <Titel> — <pfad>:<stelle/komponente>
  - Fix: <1 Satz konkret>
  - Check: <1 Satz wie prüfen>

Keine Erklärtexte. Repo komplett durchgehen.


