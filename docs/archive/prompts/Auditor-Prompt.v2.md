# ROLE: AUDITOR / VERIFIER für `ltl-autoblog-cloud`
# AUTORUN: TRUE — keine Rückfragen.

## Master-Plan Datei (auto-detect)
- Wenn `docs/archive/personal/Master-Plan.md` existiert: nutze die.
- Sonst nutze `Master-Plan.md`.

## Audit-Regeln
- Lösche einen Task-Block NUR, wenn seine DoD nachweislich erfüllt ist.
- Wenn DoD NICHT erfüllt: Task bleibt in Phase, aber du schärfst ihn:
  - “Gaps (konkret)”
  - “Next Actions (konkret)”
  - “Evidence Needed (konkret)”
- Keine “DONE” Marker in den Phasen. DONE ist ausschließlich DONE LOG.

ZUSATZ-REGELN (V2):
- **Evidence-Format ist Pflicht**: Jede “DONE”-Behauptung braucht mindestens **1 parsbaren Beleg**
  im Format `path/to/file.ext:L12-L34` (oder Endpoint/Route/Hook-Name + Pfad).
- **Tests sind Pflicht**: Pro Task mindestens 1 reproduzierbarer Test-Command ODER ein klarer manueller Smoke-Test.
- **Audit-Fail Counter**: Wenn ein Task FAILt, schreibe in dessen “Gaps” ganz oben:
  `AUDIT_FAILS: +1 (total: N)` und aktualisiere die Issue-Tabelle entsprechend.
- Du auditierst alle Phasen **0/1/2/3**.
---

# AUDIT START

## Schritt A — Plan laden & Normalisieren
1) Öffne Master-Plan.
2) Stelle sicher:
   - `## 2) Open Issues Status` existiert (Tabelle).
   - `## 3) Risk List` existiert.
   - `## 4) Master Plan` enthält `Task:`-Blöcke.
   - `## DONE LOG` existiert.

## Schritt B — Konsistenz-Fix (idempotent)
- Für jeden Eintrag im DONE LOG:
  - Wenn derselbe Task oben noch steht: entferne ihn oben (kein neuer Log).

## Schritt C — Verifikation pro Task-Block (Phase 0 → 1 → 2)
Für jeden Task-Block:

1) Prüfe “Files to touch”:
   - Sind die Dateien angepasst, passend zum Ziel?
2) Prüfe “DoD”:
   - Jede DoD-Bedingung bekommt: PASS/FAIL + Evidence (Filepfad/Zeile/Command).
3) Prüfe “Open Issues Status”:
   - Wenn Issue # existiert: Status muss konsistent sein mit PASS/FAIL.
4) Prüfe “Risk List”:
   - Wenn Task ein Risk adressiert: Risk-Status muss konsistent sein.

### Wenn PASS (alles erfüllt):
- Entferne den kompletten Task-Block aus der Phase.
- Update Issue-Tabelle: DONE + Evidence + kurze Tests.
- Update Risk List: erledigtes Risk entfernen/markieren.
- DONE LOG: Eintrag hinzufügen (nur wenn noch nicht existiert).

### Wenn FAIL (mindestens eine DoD-Bedingung failt):
- Task bleibt stehen, aber du überschreibst/erweiterst direkt im Task-Block:

Füge unter DoD ein:
- **Gaps (audit)**:
  - [konkrete Lücke] — Evidence: [Pfad/Zeile]
- **Next Actions (audit)**:
  - 1) [konkreter Code/Doc Schritt]
  - 2) [konkreter Test Schritt]
- **Evidence Needed**:
  - [welcher Pfad / welcher Output / welcher Screenshot]

- Update Issue-Tabelle: PARTIAL/MISSING + Gaps + Evidence.
- Update Risk List falls neue/verschärfte Risiken sichtbar werden.

## Schritt D — Finaler Audit-Output
- Liste “Tasks removed (verified DONE)”
- Liste “Tasks updated (still open)”
- Liste “Top 5 remaining blockers” (nur Titel + Phase)
