# LazyBookings Shortcodes Documentation

> **Alle Frontend-Elemente** können über Shortcodes in Pages/Posts eingebunden werden.

---

## 📋 Übersicht

| Shortcode | Zweck | Wichtigkeit |
|-----------|-------|-------------|
| `[lazy_book]` | Vollständiger Booking Wizard | ⭐⭐⭐⭐⭐ |
| `[lazy_book_calendar]` | Wizard mit Calendar Start | ⭐⭐⭐⭐ |
| **`[lazy_book_bar]`** | **Sticky Booking Bar (wie Booking.com)** | **⭐⭐⭐⭐⭐** |
| `[lazy_book_widget]` | Booking Widget (Card Layout, Termin-Modus) | ⭐⭐⭐⭐ |
| `[lazy_hotel_widget]` | Booking Widget (Card Layout, Hotel-Modus) | ⭐⭐⭐⭐ |
| `[lazy_services]` | Services Grid / Card-Übersicht | ⭐⭐⭐⭐ |
| `[lazy_trust]` | Highlights/USP Sektion (statt Testimonials) | ⭐⭐⭐ |

---

## 🚀 Detaillierte Dokumentation

### 1️⃣ `[lazy_book]` – Booking Wizard

**Vollständiger, interaktiver Booking-Formular mit Wizard-Navigation.**

#### Syntax
```
[lazy_book mode="wizard" service_id="0" start_mode="wizard"]
```

#### Attribute
| Attribut | Wert | Default | Beschreibung |
|----------|------|---------|------------|
| `mode` | `wizard` / `calendar` | `wizard` | Wizard oder Calendar-Layout |
| `service_id` | Nummer | `0` | Service ID pre-selecten |
| `start_mode` | `wizard` / `calendar` | `wizard` | Startlayout |

#### Beispiele
```
// Standard Wizard
[lazy_book]

// Mit vorgwähltem Service
[lazy_book service_id="5"]

// Calendar-Ansicht
[lazy_book mode="calendar"]

// Kombination
[lazy_book mode="wizard" service_id="3" start_mode="calendar"]
```

#### Was wird angezeigt?
1. Service-Auswahl
2. Datum + Uhrzeit
3. Ressourcentoggles (optional)
4. Kundendetails (Name, Email, Telefon)
5. Bestätigung + Zahlung (optional)

---

### 2️⃣ `[lazy_book_calendar]` – Calendar-Ansicht

**Kurz-Syntax für Calendar-Start-Modus.**

#### Syntax
```
[lazy_book_calendar service_id="0"]
```

Entspricht: `[lazy_book mode="calendar"]`

#### Beispiel
```
[lazy_book_calendar service_id="2"]
```

---

### 3️⃣ `[lazy_book_bar]` – Quick Booking Bar ⭐⭐⭐⭐⭐

**Sticky/Fixed Booking-Leiste oben oder unten (wie Booking.com, Airbnb).**

Diese ist die **wichtigste neue Komponente** für schnelle Buchungen!

#### Syntax
```
[lazy_book_bar position="top" sticky="true" background="primary" target="" mode="wizard"]
```

#### Attribute
| Attribut | Wert | Default | Beschreibung |
|----------|------|---------|------------|
| `position` | `top` / `bottom` | `top` | Position auf Seite |
| `sticky` | `true` / `false` | `true` | Sticky Position (bleibt beim Scrollen sichtbar) |
| `background` | `primary` / `dark` / `light` | `primary` | Designstil |
| `target` | URL | *(aktuelle Seite)* | Zielseite für den Wizard (z.B. /booking) |
| `mode` | `wizard` / `calendar` | `wizard` | Übergabe an Wizard-Startmodus |

#### Beispiele
```
// Oben, sticky, Accent-Farbe
[lazy_book_bar]

// Unten, sticky, dunkler Hintergrund
[lazy_book_bar position="bottom" background="dark"]

// Nicht sticky, heller Hintergrund
[lazy_book_bar sticky="false" background="light"]

// Zu eigener Booking-Seite verlinken + Calendar Start
[lazy_book_bar target="/booking" mode="calendar"]
```

#### Wie sieht es aus?
- **Oben:** Knifflige kompakte Leiste mit Service-Dropdown, Datum, Uhrzeit, "Book Now"-Button
- **Sticky:** Bleibt beim Scrollen oben/unten sichtbar
- **Responsive:** Mobile-optimiert (stapelt sich auf kleinen Screens)

#### CSS-Klassen (für Custom Styling)
```css
.ltlb-booking-bar              /* Wrapper */
.ltlb-booking-bar__form        /* Form Element */
.ltlb-booking-bar__group       /* Input Group */
.ltlb-booking-bar__select      /* Service Dropdown */
.ltlb-booking-bar__input       /* Date/Time Inputs */
.ltlb-booking-bar__btn         /* Book Now Button */
```

---

### 4️⃣ `[lazy_services]` – Services Grid

**Zeige alle Services als Card-Grid mit Bildern, Preisen, Beschreibungen.**

#### Syntax
```
[lazy_services columns="3" show_price="true" show_description="true" target="" mode=""]
```

#### Attribute
| Attribut | Wert | Default | Beschreibung |
|----------|------|---------|------------|
| `columns` | `1` / `2` / `3` / `4` | `3` | Grid-Spalten |
| `show_price` | `true` / `false` | `true` | Preis anzeigen? |
| `show_description` | `true` / `false` | `true` | Beschreibung anzeigen? |
| `target` | URL | *(aktuelle Seite)* | Zielseite für den Wizard (z.B. `/booking`) |
| `mode` | `wizard` / `calendar` | *(leer)* | Optionaler Wizard-Startmodus |

#### Beispiele
```
// 3-spaltig mit allem
[lazy_services]

// 2-spaltig, nur Namen & Preise
[lazy_services columns="2" show_description="false"]

// Full-width ohne Preis
[lazy_services columns="1" show_price="false"]

// 4-spaltig
[lazy_services columns="4"]

// Auf eigene Booking-Seite verlinken
[lazy_services target="/booking"]

// Auf Booking-Seite verlinken + Calendar-Start
[lazy_services target="/booking" mode="calendar"]
```

#### Features
- **Hover-Effekt:** Card hebt sich ab
- **"Book Now" Link:** Leitet zum Wizard mit vorgwähltem Service
- **Responsive:** Mobile-optimiert (1 Spalte auf Mobilgeräten)

#### CSS-Klassen (für Custom Styling)
```css
.ltlb-services-grid            /* Grid Wrapper */
.ltlb-service-card             /* Einzelne Card */
.ltlb-service-card__title      /* Service-Name */
.ltlb-service-card__price      /* Preisanzeige */
.ltlb-service-card__description /* Beschreibung */
.ltlb-service-card__link       /* Book Now Button */
```

---

### 5️⃣ `[lazy_trust]` – Highlights/USP Sektion

**Schöne, neutrale "Warum bei uns buchen" Sektion (ohne echte Testimonials sammeln zu müssen).**

> Hinweis: Der alte Shortcode `[lazy_testimonials]` existiert weiterhin aus Kompatibilität, rendert aber jetzt diese Trust-Sektion.

#### Syntax
```
[lazy_trust title="Why book with us" subtitle="Fast, clear, and reliable" style="default" button_url="/booking" button_text="Start booking"]
```

#### Attribute
| Attribut | Wert | Default | Beschreibung |
|----------|------|---------|------------|
| `title` | Text | *(leer)* | Überschrift |
| `subtitle` | Text | *(leer)* | Untertitel |
| `style` | `default` / `compact` / `flat` | `default` | Layout-Variante |
| `button_url` | URL | *(aktuelle Seite)* | CTA-Link |
| `button_text` | Text | `Start booking` | CTA-Text |

#### CSS-Klassen
```css
.ltlb-trust
.ltlb-trust__inner
.ltlb-trust__grid
.ltlb-trust__card
```

---

### 6️⃣ `[lazy_book_widget]` – Booking Widget (Termin)

**Alternative zum Sticky-Bar Layout: Card-Widget für Landingpages.**

#### Syntax
```
[lazy_book_widget target="/booking" mode="wizard" style="default" title="Book in seconds" subtitle="Pick a service and time"]
```

#### Attribute
| Attribut | Wert | Default | Beschreibung |
|----------|------|---------|------------|
| `target` | URL | *(aktuelle Seite)* | Zielseite für den Wizard |
| `mode` | `wizard` / `calendar` | `wizard` | Startmodus |
| `style` | `default` / `compact` / `flat` | `default` | Layout-Variante |
| `title` | Text | *(leer)* | Überschrift |
| `subtitle` | Text | *(leer)* | Untertitel |

---

### 7️⃣ `[lazy_hotel_widget]` – Booking Widget (Hotel)

**Card-Widget für Hotelmodus: Room type + Check-in/out + Guests.**

#### Syntax
```
[lazy_hotel_widget target="/booking" style="default" title="Find your stay" subtitle="Choose dates and guests"]
```

#### Attribute
| Attribut | Wert | Default | Beschreibung |
|----------|------|---------|------------|
| `target` | URL | *(aktuelle Seite)* | Zielseite für den Wizard |
| `style` | `default` / `compact` / `flat` | `default` | Layout-Variante |
| `title` | Text | *(leer)* | Überschrift |
| `subtitle` | Text | *(leer)* | Untertitel |

---

## 🎨 Styling & Anpassung

### CSS-Variablen (System-weit)

Alle Shortcodes nutzen die Plugin-CSS-Variablen:

```css
--lazy-bg-primary       /* Hintergrund Primär */
--lazy-bg-secondary     /* Hintergrund Sekundär */
--lazy-text-primary     /* Text primär */
--lazy-text-secondary   /* Text grau */
--lazy-accent           /* Akzentfarbe (blau/grün) */
--lazy-border-medium    /* Border-Farbe */
--lazy-shadow-sm        /* Kleine Schatten */
--lazy-shadow-md        /* Größere Schatten */
--lazy-space-*          /* Spacing Tokens (8px, 12px, 16px, etc.) */
```

Diese können in `admin.css` oder `public.css` überschrieben werden:

```css
:root {
    --lazy-accent: #ff6b6b;  /* Rot statt Blau */
    --lazy-border-radius: 12px;
}
```

### Dark Mode

Alle Shortcodes respektieren `prefers-color-scheme: dark`:

```css
@media (prefers-color-scheme: dark) {
    /* Farben werden automatisch invertiert */
    --lazy-bg-primary: #1a1a1a;
    --lazy-text-primary: #f0f0f0;
}
```

---

## 📱 Responsive Design

Alle Shortcodes sind **mobile-first** und responsive:

| Breakpoint | Verhalten |
|-----------|-----------|
| **> 768px** | Volle Breite, Desktop-Layout |
| **≤ 768px** | Stapeln, optimiert für Mobile |
| **< 500px** | Sehr kompakt, vereinfachtes Layout |

**Booking Bar auf Mobile:**
- Wird zu vertikalem Stack (nicht horizontal)
- Inputs nehmen volle Breite ein

**Services Grid auf Mobile:**
- Alle Spalten → 1 Spalte
- Cards volle Breite

---

## 🔒 Sicherheit

Alle Shortcodes implementieren:
- **Nonce-Verification** für Forms
- **Sanitization** von Eingaben
- **Escaping** von Ausgaben
- **Rate-Limiting** (wenn aktiviert)
- **Capability-Checks** für Admin-Daten

---

## 🚨 Häufig gestellte Fragen

### Q: Kann ich mehrere Shortcodes auf einer Seite nutzen?
**A:** Ja! Z.B. oben `[lazy_book_bar]` und unten `[lazy_services]` für ein Landing-Page-Layout.

### Q: Kann ich die Farben anpassen?
**A:** Ja, über CSS-Variablen oder Theme-Customizer. Siehe "Styling & Anpassung".

### Q: Wie deaktiviere ich Zahlungen?
**A:** Admin → Settings → Payment Methods → Disable "Enable Payments"

### Q: Können Kunden die Booking Bar auf Mobile nutzen?
**A:** Ja, sie wird vollständig responsiv und ist Touch-optimiert.

### Q: Wie zeige ich nur bestimmte Services?
**A:** `[lazy_book_bar]` zeigt alle. Für einzelne Services: `[lazy_book service_id="5"]`

---

## 🔧 Developer Info

### Shortcode-Filter (für Entwickler)

Alle Shortcodes können per Filter angepasst werden:

```php
// Services Grid vor Ausgabe modifizieren
add_filter( 'ltlb_services_grid_html', function( $html, $services, $atts ) {
    // Custom-Logik
    return $html;
}, 10, 3 );

// Booking Bar nach Ausgabe modifizieren
add_filter( 'ltlb_booking_bar_html', function( $html, $atts ) {
    return $html;
}, 10, 2 );
```

### Custom CSS

Um Standard-Styles zu überschreiben, nutze höhere Spezifität:

```css
/* Override .ltlb-booking-bar__btn */
.my-custom-theme .ltlb-booking-bar__btn {
    background: #ff6b6b !important;
    border-radius: 20px !important;
}
```

---

## 📝 Changelog

| Version | Änderung |
|---------|----------|
| **1.0.1** | Shortcodes hinzugefügt: `lazy_book_bar`, `lazy_services`, `lazy_testimonials` |
| **1.0.0** | Initial Release mit `lazy_book` und `lazy_book_calendar` |

---

## 💡 Best Practices

1. **Landing Page:** Nutze `[lazy_book_bar sticky="true"]` oben + `[lazy_services]` unten
2. **Service-Detail:** Nutze `[lazy_book service_id="X"]` für spezifischen Service
3. **Testimonials:** Nutze `[lazy_testimonials count="5"]` für Social Proof
4. **Mobile-First:** Teste alle Shortcodes auf Mobilgeräten
5. **Performance:** Nutze Page Caching für bessere Ladzeiten

---

**Weitere Fragen?**  
Siehe [API.md](API.md) oder [DESIGN_GUIDE.md](DESIGN_GUIDE.md)
