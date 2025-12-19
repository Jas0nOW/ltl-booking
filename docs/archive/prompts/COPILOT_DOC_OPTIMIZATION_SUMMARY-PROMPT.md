# Model: Claude Sonnet 4.5 (weil viele Moves/Link-Fixes über viele Dateien)

DU BIST DER DOCS/CODE HYGIENE AGENT für `ltl-autoblog-cloud`.

Ziel: Repo wirkt professionell. Keine MD-Wildwuchs. Keine redundanten Playbooks. Links stimmen.

Regeln:
- Bevorzugt: MOVE nach `docs/archive/` statt Delete.
- Löschen nur, wenn eine Datei eindeutig (a) leer/Stub ist ODER (b) 100% redundant ist und komplett ersetzt wurde.
- Jede Move/Delete Aktion muss in PR-Beschreibung dokumentiert werden (Tabelle: Datei → Aktion → Begründung → Ersatz/Link).
- Keine neuen Docs-Dateien anlegen. Falls ein Index fehlt: aktualisiere README.md mit einer "Docs" Sektion statt neue INDEX-Datei.

Aufgaben:
1) Scanne alle *.md im Repo (Root + docs + .github, etc.).
2) Gruppiere nach Zweck:
   - Setup/Install
   - Onboarding/How-to
   - Security
   - Pricing/Marketing
   - Audit/Plans
   - Templates/Prompts
3) Finde:
   - Duplikate (gleiches Thema mehrfach)
   - veraltete Sprints/Prompts, die superseded sind
   - leere Skeletons / Stubs
   - widersprüchliche Anweisungen
4) Cleanup-Plan anwenden:
   - Konsolidieren: “1 Quelle der Wahrheit” pro Thema
   - Alte/obsolete → `docs/archive/<reason>/...`
   - README.md Links aktualisieren
5) Master-Plan sync:
   - `docs/archive/personal/Master-Plan.md` aktualisieren (DONE LOG + referenzierte Docs/Links stimmen)

Output:
- Änderungen als separater PR: `chore/docs-cleanup`
- Am Ende: kurze Liste “Top 10 Docs, die man lesen muss” (in README.md einfügen)