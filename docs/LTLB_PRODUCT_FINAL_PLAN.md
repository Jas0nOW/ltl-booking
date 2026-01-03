# LazyBookings (ltl-booking) – Finaler Produkt‑Plan (Roadmap)

> Zweck: Dieser Plan beschreibt das **Ziel‑Produkt** (ähnlich „Amelia + Vik Booking“),
> aber in einer **klaren, baubaren Reihenfolge**.  
> Er ist **nicht** der MVP‑Plan (der ist separat).  
> Ziel: Du kannst damit später Schritt für Schritt erweitern, ohne Chaos.

---

## 0) 1‑Satz‑Vision
LazyBookings ist ein WordPress‑Booking‑System für **Services/Termine** und **Hotel/Zimmer**,
mit **Zahlung**, **Verfügbarkeitslogik**, **Admin‑Kalender** und **sauberem Betrieb**.

---

## 1) Zielgruppen (damit du richtige Features baust)
Wähle später 1 Hauptzielgruppe (für Marketing + Fokus), z.B.:
- **Service‑Business:** Yoga, Beratung, Beauty, Fitness, Kurse
- **Unterkünfte:** Ferienwohnung, kleines Hotel, Apartments
- **Hybrid:** Retreats / Yoga‑Ibiza (Termine + Zimmer)

---

## 2) Produkt‑Module (grobe Bausteine)
Damit du Profis nachmachen kannst: immer in Modulen denken.

### A) Buchungs‑Engine (das Herz)
- Services/Kurse: Dauer, Puffer, Zeitfenster, fixe Slots, Wochentage
- Hotel: Check‑in/out Zeiten, Nächte, Gäste, Zimmer‑Kapazitäten
- Ressourcen: Räume/Zimmer, Kapazitäten, Blocken bei Buchung
- Staff (optional): Mitarbeiter‑Zeiten, Ausnahmen/Urlaub

### B) Frontend (was Kunden sehen)
- Buchungs‑Wizard (Service‑Modus)
- Buchungs‑Wizard (Hotel‑Modus)
- Kunden‑Bestätigung + Fehlerseiten
- Optional: Kunden‑Bereich (Buchungen ansehen / stornieren)

### C) Admin (was Betreiber sehen)
- Kalender (Tag/Woche/Monat)
- Buchungsliste + Filter
- Service/Room Verwaltung
- Ressourcen/Staff Verwaltung
- Einstellungen (Zeiten, E‑Mails, Währung, Regeln)

### D) Zahlung (Geld)
- Standard: **WooCommerce Checkout** als „Kasse“
- Optional später: Deposits (Anzahlung), Restzahlung, Coupons
- Rechnungen/Belege (wenn nötig über WooCommerce/Plugins)

### E) Nachrichten (Kommunikation)
- E‑Mail an Kunde + Admin (Bestätigung, Erinnerung, Storno)
- Optional: SMS/WhatsApp später
- Vorlagen + Variablen (Name, Datum, Ort, Link)

### F) Integrationen (später)
- iCal Export/Import
- Google Calendar Sync (optional)
- Webhooks (z.B. an n8n)
- CRM / MailerLite etc.

### G) Betrieb (damit es im echten Leben nicht kaputt geht)
- Fehler‑Tracking (wenn möglich)
- Log‑Dateien / Debug‑Infos
- Performance: Caching der Verfügbarkeit
- Datenschutz (DSGVO): Daten minimieren, Löschfunktionen, Einwilligungen

---

## 3) Roadmap in Stufen (so würden Profis es bauen)

### Stufe 1 — MVP (hast du schon als Datei)
- Nur Service‑Modus, WooCommerce Zahlung, sauberer Happy‑Path.

### Stufe 2 — “Service Pro”
Ziel: Service‑Buchungen werden „wie ein echtes Produkt“.
- Mehr Regeln: Mitarbeiter‑Zeiten + Ausnahmen
- Gruppen‑Buchungen: Seats / Plätze pro Termin
- Storno‑Regeln (z.B. bis 24h vorher)
- Erinnerungs‑Mails (z.B. 24h vorher)
- Admin‑Kalender stabil + Drag/Drop (optional)

### Stufe 3 — Hotel‑Modus (Vik‑ähnlich, aber klein starten)
Ziel: Zimmer‑Buchung mit Check‑in/out wird stabil.
- Room Types (Services) + Rooms (Resources)
- Gäste‑Anzahl, Nächte, Preisberechnung pro Nacht
- Doppelbuchung verhindern (Lock/Transaktion)
- Admin‑Ansicht für belegte Nächte

### Stufe 4 — Zahlung “Pro”
Ziel: Mehr Business‑Logik ohne Payment‑Chaos.
- Anzahlung (Deposit) + Restzahlung
- Gutscheine/Coupons
- Refund‑Flow über WooCommerce‑Status
- “Zahlung offen / bezahlt / fehlgeschlagen” sauber überall angezeigt

### Stufe 5 — Kunden‑Portal
- Login/Link per E‑Mail (oder WP User optional)
- Buchung ansehen, stornieren, umbuchen (wenn erlaubt)
- Rechnung/Beleg anzeigen (über WooCommerce)

### Stufe 6 — Integrationen & Automatisierung
- Webhooks für “booking_created/paid/cancelled”
- n8n Workflows: z.B. Slack, MailerLite, Google Sheets, CRM
- Google Calendar Sync (optional, wenn wirklich gebraucht)

### Stufe 7 — Multi‑Location / Multi‑Staff / Skalierung
- Mehr Standorte, Teams, Rechte
- Rollen im Admin (nicht jeder darf alles)
- Performance & Caching (bei vielen Buchungen)

---

## 4) “Fertig”-Regeln je Stufe (damit du nicht stecken bleibst)
Eine Stufe ist erst fertig, wenn:
- Frontend: Kunde kann den Flow komplett durchlaufen
- Admin: Betreiber kann Buchungen sehen/ändern
- Zahlung: Status ist korrekt (bezahlt vs. offen)
- Keine kritischen Fehler im Log bei Normalnutzung

---

## 5) Wichtige Produkt‑Entscheidungen (später, aber nicht vergessen)

### Zahlung: “Kasse” bleibt WooCommerce?
- Ja = weniger Risiko, schneller, mehr Payment‑Methoden
- Nein = mehr Kontrolle, aber sehr viel mehr Arbeit

### Datenspeicherung: Eigene Tabellen vs. Custom Post Types
- Du nutzt aktuell eigene Tabellen (sehr ok für Performance/Logik).
- Wichtig: Migrationen sauber halten (Versionen).

### 1 Hauptmodus zuerst
- Wir starten bewusst mit **Service‑Modus**, Hotel kommt später dazu.

---

## 6) Risiken (die Profis vorher entschärfen)
- Zu viele Features parallel → immer nur 1 Stufe bauen
- Zahlung “halb halb” → entweder ganz WooCommerce oder ganz eigenes System (später)
- UI perfektionieren bevor der Flow stabil ist → erst Flow, dann UI

---

## 7) Was du von KI wirklich erwarten solltest (realistisch)
KI ist super für:
- Bausteine bauen (ein Feature, eine Seite, eine Funktion)
- Fehler finden und fixen
- Refactoring (Aufräumen) nach klaren Regeln

KI ist schlecht, wenn:
- Du sagst “bau alles” und hoffst auf Magie
- Es fehlen klare Regeln: was ist “fertig”, was ist “nicht drin”

---

## 8) Nächster Schritt (jetzt)
Wir arbeiten weiter am MVP‑Plan (Service‑Modus) und nutzen diesen Final‑Plan als “Kompass”.

**Wenn du willst, erstellen wir als nächstes:**
- eine kurze „Feature‑Liste pro Stufe“ als Checkliste
- und eine “Ordnerstruktur‑Karte” (wo im Code was hingehört)

---

# Mini‑Glossar (einfach)
- **Modul**: ein Baustein (z.B. Zahlung oder Kalender)
- **Stufe**: ein Ausbau‑Schritt (z.B. erst Service, dann Hotel)
- **Kunden‑Portal**: Seite, wo Kunden Buchungen sehen/ändern können
