# LazyBookings (ltl-booking) – Projektdatei (MVP-Fokus)

> Zweck dieser Datei: **Single Source of Truth** für dich + deine CLI-KIs.  
> Ziel: Aus dem bestehenden Plugin ein **funktionierendes MVP** machen, ohne alles neu zu erfinden.

## 1) Kurz-Ziel (in 1 Satz)
Ein WordPress-Plugin, das **Buchungen** (Termine oder Zimmer) annimmt, **Verfügbarkeit** prüft und eine Buchung **bezahlt** abschließen kann – stabil genug für echte Tests.

## 2) Was “MVP” hier bedeutet (einfach erklärt)
**MVP = kleinste Version, die wirklich nutzbar ist.**  
Nicht “Amelia + Vik Booking komplett”, sondern nur der Kern-Flow.

### Kern-Flow (Happy Path)
1. Nutzer wählt Service/Room + Datum/Zeit (oder Check-in/out)
2. System zeigt freie Optionen (Verfügbarkeit)
3. Nutzer gibt Daten ein (Name/E-Mail)
4. Buchung wird erstellt (Status: „wartet auf Zahlung“)
5. Nutzer bezahlt über **WooCommerce Checkout**
6. Nach erfolgreicher Zahlung wird Buchung auf „bezahlt/bestätigt“ gesetzt
7. Bestätigung wird angezeigt + E-Mail optional

## 3) Wichtigste Entscheidung (spart extrem Zeit)
### Zahlung NICHT selbst bauen → WooCommerce übernimmt Checkout
**Warum:** Zahlungen sind voller Sonderfälle (Abbruch, Rückerstattung, Webhooks). WooCommerce ist dafür gemacht.  
Dein Plugin macht Buchung + Status, WooCommerce macht „Kasse“.

## 4) Scope (was wir für v1 bauen) ✅
Wir bauen **nur** das, was heute nötig ist, damit es “wie ein echtes Produkt” funktioniert.

### v1: Muss drin sein
- Verfügbarkeitsabfrage funktioniert (Service ODER Hotel – siehe unten)
- Buchung wird sauber in DB gespeichert
- WooCommerce Zahlung startet und kommt zurück
- Buchung bekommt nach Zahlung den richtigen Status
- Admin kann Buchungen sehen (einfach, nicht perfekt)
- Grund-Fehlertexte: “Keine Plätze frei”, “Zahlung fehlgeschlagen”

### v1: Bewusst NICHT drin (kommt später)
- Komplexe Regeln/Staff-Exceptions in voller Breite
- Super-Admin UI mit 10 Unterseiten
- AI/Automations Features
- Perfektes Design/Branding
- 20 Zahlungsarten direkt im Plugin

## 5) Modus-Entscheidung (Service vs. Hotel)
Damit wir schnell fertig werden, wählen wir **einen** Modus für v1:

- **Service-Modus**: Datum + Uhrzeit-Slots (z.B. Yoga, Beratung)
- **Hotel-Modus**: Check-in/Check-out + Gäste (z.B. Zimmer)

**Default für v1 (Empfehlung):** Service-Modus, weil er einfacher zu testen ist.

## 6) Strategie: “Foundation-Reset” statt alles wegwerfen
Wir behalten, was gut ist (z.B. Verfügbarkeitslogik/Shortcodes), aber wir bringen Ordnung rein:

- Klarer Kern: Buchung erstellen → Zahlung → Status update
- Alles, was nicht nötig ist, bleibt erstmal “aus” (nicht löschen, nur verschieben/abschalten)
- Große Admin-Menüs später wieder aktivieren

## 7) Fertig-Regel (Definition of Done = „Woran erkennen wir, dass es fertig ist?“)
Ein Ticket/Schritt gilt als fertig, wenn:
- Keine PHP-Fatals
- Kern-Flow läuft einmal komplett durch
- Admin sieht die Buchung
- Zahlung im Testmodus klappt (WooCommerce + Stripe/PayPal Test)

## 8) Arbeitsweise (wie du mit CLI-KIs arbeitest, ohne Token zu verbrennen)
### Regel 1: Kleine Aufgaben (30–60 min)
Statt „Bau das Plugin“, immer nur:
- „Baue genau Feature X in Datei Y, ändere nichts anderes“

### Regel 2: Kontext klein halten
Gib der KI nur:
- diese Datei (Projektdatei)
- 1–2 relevante Code-Dateien
- konkrete Aufgabe

### Regel 3: Teures Modell nur bei schweren Stellen
Teures Modell nur für:
- Architektur, Datenmodell, Zahlungs-Statuslogik
Günstiger für:
- UI-Texte, kleinere Funktionen, Aufräumen

## 9) Ticket-Liste (MVP in Reihenfolge) 🧾
> Jede Zeile ist ein eigenes Ticket (klein halten).

### Phase A – Stabilität & Basis
1. **MVP-Modus festlegen** (Service oder Hotel) und alles andere deaktivieren
2. **DB + Migration prüfen** (läuft `wp ltlb migrate` ohne Fehler?)
3. **Seed-Daten** (Demo-Daten) zum Testen sicher nutzen (`wp ltlb seed`)

### Phase B – Buchung (ohne Zahlung)
4. Frontend: Verfügbarkeit anzeigen (funktioniert zuverlässig)
5. Frontend: Buchung anlegen (Status = pending/wartet)
6. Admin: Buchungsliste (minimal) + Detailansicht (minimal)

### Phase C – Zahlung über WooCommerce
7. WooCommerce-Produkt/Checkout-Flow definieren (z.B. “Buchung Service X”)
8. Beim „Buchen“-Klick: WooCommerce Checkout starten (Buchungs-ID merken)
9. Nach Zahlung: Buchungsstatus auf „confirmed/paid“ setzen (Hook/Callback)
10. Fehlerfälle: Zahlung abgebrochen → Buchung bleibt pending (oder wird cancelled)

### Phase D – Polishing (nur Minimal)
11. Saubere Erfolgs-/Fehlermeldungen im Frontend
12. Optional: Bestätigungs-E-Mail (simple)

## 10) Dateien/Orte im Repo (Orientierung)
- Plugin Entry: `ltl-booking.php`
- Core Boot: `Includes/Core/Plugin.php`
- Frontend Shortcodes/Submission: `public/Shortcodes.php`
- Doku: `docs/` (API, DB Schema, etc.)

## 11) Risiko-Liste (damit du nicht wieder festhängst)
- Zahlung “halb selbst” machen → lieber ganz WooCommerce übernehmen
- Zu viele Features gleichzeitig → Scope klein halten
- Admin-UI perfektionieren bevor der Flow läuft → erst Flow, dann UI

## 12) Nächster Schritt (Start heute)
Wir starten mit Ticket 1: **MVP-Modus festlegen**.

---

# Anhang: Mini-Glossar (ohne Fachchinesisch)
- **Blueprint/Template**: fertige Start-Basis, die vieles schon kann.
- **MVP**: kleinste Version, die wirklich nutzbar ist.
- **Ticket**: eine kleine Aufgabe (30–60 Minuten).
- **Hook/Callback**: “WordPress ruft eine Funktion automatisch auf”, z.B. nach erfolgreicher Zahlung.
