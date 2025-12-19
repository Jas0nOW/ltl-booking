# Shortcodes Reference

**Scope:** Alle verfügbaren Shortcodes mit Attributen und Verwendungsbeispielen.  
**Non-Scope:** Custom Shortcode-Entwicklung oder PHP Template Tags.

---

## 📋 Inhaltsverzeichnis

- [Booking Shortcodes](#booking-shortcodes)
  - [lazy_book](#lazy_book) - Haupt-Buchungsformular
  - [lazy_book_calendar](#lazy_book_calendar) - Buchung mit Kalenderansicht
- [Booking Bars](#booking-bars)
  - [lazy_book_bar](#lazy_book_bar) - Service Booking Bar
  - [lazy_hotel_bar](#lazy_hotel_bar) - Hotel Booking Bar
  - [lazy_book_widget](#lazy_book_widget) - Service Booking Widget
  - [lazy_hotel_widget](#lazy_hotel_widget) - Hotel Booking Widget
- [Display Shortcodes](#display-shortcodes)
  - [lazy_services](#lazy_services) - Services Grid
  - [lazy_room_types](#lazy_room_types) - Zimmertypen Grid
  - [lazy_trust / lazy_testimonials](#lazy_trust--lazy_testimonials) - Trust Section
- [Utility Shortcodes](#utility-shortcodes)
  - [lazy_lang_switcher](#lazy_lang_switcher) - Sprachwechsler

---

## Booking Shortcodes

### `lazy_book`

Haupt-Buchungsformular mit Wizard-Interface (Schritt-für-Schritt Buchung).

**Verwendung:**
```
[lazy_book]
[lazy_book service="5"]
[lazy_book mode="wizard"]
[lazy_book service="3" mode="calendar"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `service` | int | - | Service-ID zum Vorauswählen (optional) |
| `mode` | string | `wizard` | `wizard` oder `calendar` |

**URL-Parameter (automatisch unterstützt):**
- `?service=5` - Service vorauswählen
- `?service_id=5` - Alternative zu `service`
- `?date=2025-12-25` - Datum vorauswählen
- `?time=14:00` - Uhrzeit vorauswählen
- `?checkin=2025-12-20` - Check-in Datum (Hotel-Modus)
- `?checkout=2025-12-27` - Check-out Datum (Hotel-Modus)
- `?guests=2` - Anzahl Gäste

**Beispiele:**
```
<!-- Einfaches Buchungsformular -->
[lazy_book]

<!-- Mit vorausgewähltem Service -->
[lazy_book service="5"]

<!-- Kalenderansicht als Startmodus -->
[lazy_book mode="calendar"]

<!-- Kombination -->
[lazy_book service="3" mode="calendar"]
```

---

### `lazy_book_calendar`

Identisch zu `[lazy_book mode="calendar"]` - startet direkt mit Kalenderansicht.

**Verwendung:**
```
[lazy_book_calendar]
[lazy_book_calendar service="5"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `service` | int | - | Service-ID zum Vorauswählen (optional) |

**Beispiel:**
```
[lazy_book_calendar service="3"]
```

---

## Booking Bars

### `lazy_book_bar`

Kompakte Booking-Bar für Services (z.B. oben auf Landing Page).

**Verwendung:**
```
[lazy_book_bar]
[lazy_book_bar sticky="true"]
[lazy_book_bar position="top" background="dark"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `position` | string | `top` | Position der Bar |
| `sticky` | bool | `false` | Fixiert beim Scrollen |
| `background` | string | `primary` | `primary`, `dark`, `light` |
| `target` | URL | - | Ziel-URL für Buchung |
| `mode` | string | `wizard` | `wizard` oder `calendar` |

**Beispiele:**
```
<!-- Standard Booking Bar -->
[lazy_book_bar]

<!-- Sticky Bar mit dunklem Hintergrund -->
[lazy_book_bar sticky="true" background="dark"]

<!-- Bar verlinkt zu Buchungsseite -->
[lazy_book_bar target="/booking/" mode="calendar"]
```

---

### `lazy_hotel_bar`

Spezielle Booking-Bar für Hotel/Unterkunfts-Modus mit Check-in/Check-out.

**Verwendung:**
```
[lazy_hotel_bar]
[lazy_hotel_bar sticky="true" background="dark"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `position` | string | `top` | Position der Bar |
| `sticky` | bool | `false` | Fixiert beim Scrollen |
| `background` | string | `primary` | `primary`, `dark`, `light` |
| `target` | URL | - | Ziel-URL für Buchung |

**Beispiel:**
```
[lazy_hotel_bar sticky="true" background="dark" target="/rooms/"]
```

---

### `lazy_book_widget`

Kompaktes Booking-Widget für Sidebar oder Footer (Services).

**Verwendung:**
```
[lazy_book_widget]
[lazy_book_widget target="/booking/"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `target` | URL | - | Ziel-URL für Buchung |
| `mode` | string | `wizard` | `wizard` oder `calendar` |

**Beispiel:**
```
[lazy_book_widget target="/booking/" mode="calendar"]
```

---

### `lazy_hotel_widget`

Kompaktes Booking-Widget für Sidebar oder Footer (Hotel/Unterkunft).

**Verwendung:**
```
[lazy_hotel_widget]
[lazy_hotel_widget target="/rooms/"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `target` | URL | - | Ziel-URL für Buchung |

**Beispiel:**
```
[lazy_hotel_widget target="/rooms/"]
```

---

## Display Shortcodes

### `lazy_services`

Grid-Ansicht aller verfügbaren Services mit Preisen und Beschreibungen.

**Verwendung:**
```
[lazy_services]
[lazy_services columns="4" show_price="true"]
[lazy_services show_description="false" target="/booking/"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `columns` | int | `3` | Anzahl Spalten (1-6) |
| `show_price` | bool | `true` | Preise anzeigen |
| `show_description` | bool | `true` | Beschreibung anzeigen |
| `target` | URL | - | Ziel-URL für "Buchen" Button |
| `mode` | string | - | `wizard` oder `calendar` |

**Beispiele:**
```
<!-- Standard Grid mit 3 Spalten -->
[lazy_services]

<!-- 4 Spalten ohne Beschreibungen -->
[lazy_services columns="4" show_description="false"]

<!-- Mit Kalender-Link -->
[lazy_services target="/booking/" mode="calendar"]

<!-- Minimalistisch: nur Namen -->
[lazy_services show_price="false" show_description="false"]
```

---

### `lazy_room_types`

Grid-Ansicht aller Zimmertypen (Hotel-Modus).

**Verwendung:**
```
[lazy_room_types]
[lazy_room_types columns="2" show_price="true"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `columns` | int | `3` | Anzahl Spalten (1-6) |
| `show_price` | bool | `true` | Preise anzeigen |
| `show_description` | bool | `true` | Beschreibung anzeigen |
| `target` | URL | - | Ziel-URL für "Buchen" Button |

**Beispiel:**
```
[lazy_room_types columns="2" target="/rooms/"]
```

---

### `lazy_trust` / `lazy_testimonials`

Trust Section mit Social Proof-Elementen (Statistiken, Bewertungen, Garantien).

**Verwendung:**
```
[lazy_trust]
[lazy_testimonials style="compact"]
[lazy_trust title="Warum uns wählen?" button_url="/booking/"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `title` | string | - | Titel der Sektion |
| `subtitle` | string | - | Untertitel |
| `style` | string | `default` | `default`, `compact`, `flat` |
| `button_url` | URL | - | URL für CTA-Button |
| `button_text` | string | "Start booking" | Button-Text |

**Beispiele:**
```
<!-- Standard Trust Section -->
[lazy_trust]

<!-- Mit eigenem Titel und CTA -->
[lazy_trust title="Über 5000 zufriedene Kunden" button_url="/booking/" button_text="Jetzt buchen"]

<!-- Kompakte Version -->
[lazy_testimonials style="compact"]

<!-- Flat Design -->
[lazy_trust style="flat" title="Vertrauen Sie uns"]
```

---

## Utility Shortcodes

### `lazy_lang_switcher`

Sprachwechsler für mehrsprachige Websites.

**Verwendung:**
```
[lazy_lang_switcher]
[lazy_lang_switcher style="buttons"]
[lazy_lang_switcher style="dropdown" show_flags="yes"]
```

**Attribute:**

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `style` | string | `dropdown` | `dropdown` oder `buttons` |
| `show_flags` | bool | `yes` | Flaggen anzeigen |

**Unterstützte Sprachen:**
- 🇩🇪 Deutsch (`de_DE`)
- 🇬🇧 English (`en_US`)
- 🇪🇸 Español (`es_ES`)

**Beispiele:**
```
<!-- Dropdown mit Flaggen -->
[lazy_lang_switcher]

<!-- Button-Style -->
[lazy_lang_switcher style="buttons"]

<!-- Ohne Flaggen -->
[lazy_lang_switcher show_flags="no"]
```

---

## 💡 Best Practices

### Landing Page Setup
```
<!-- Hero Section -->
<h1>Willkommen bei Yoga Ibiza</h1>
[lazy_book_bar sticky="true" background="dark"]

<!-- Services Section -->
<h2>Unsere Angebote</h2>
[lazy_services columns="3" show_price="true"]

<!-- Trust Section -->
[lazy_trust title="Über 5000 zufriedene Kunden" button_url="/booking/"]

<!-- Footer Widget -->
[lazy_lang_switcher style="dropdown"]
```

### Booking Page
```
[lazy_book mode="wizard"]
```

### Service Detail Page
```
<h1>Vinyasa Flow Yoga</h1>
<p>Beschreibung...</p>

[lazy_book service="5" mode="calendar"]
```

### Sidebar Widget (über Widgets oder Elementor)
```
[lazy_book_widget target="/booking/"]
```

---

## 🎨 Design-Anpassungen

Alle Shortcodes verwenden das Agency Design System aus `public.css`:
- **CSS Custom Properties**: `--ltlb-agency-*` für Farben, Abstände, Schatten
- **Responsive**: Mobile-first Design
- **Accessibility**: ARIA-Labels, Keyboard-Navigation
- **Animations**: Smooth Transitions mit `--ltlb-ease-*`

Siehe [design-system.md](../explanation/design-system.md) für Details.

---

## 🔌 Integration

### Gutenberg Blocks
Alle Shortcodes sind auch als Gutenberg-Blocks verfügbar:
- **Booking Form Block** → `[lazy_book]`
- **Calendar Block** → `[lazy_book_calendar]`

### Elementor Widgets
- **Booking Form Widget** → `[lazy_book]`
- **Calendar Widget** → `[lazy_book_calendar]`

---

## 📝 Changelog

| Version | Änderung |
|---------|----------|
| **1.2.0** | Vollständige Dokumentation aller 11 Shortcodes, Agency Design Integration |
| **1.1.0** | Shortcodes stabilisiert und Doku für `lazy_book_bar`, `lazy_services`, `lazy_testimonials` ergänzt |
| **1.0.1** | Shortcodes hinzugefügt: `lazy_book_bar`, `lazy_services`, `lazy_testimonials` |
| **1.0.0** | Initial Release mit `lazy_book` und `lazy_book_calendar` |

---

## 🆘 Hilfe & Support

**Weitere Informationen:**
- [API Referenz](api.md) - REST API Endpunkte
- [Design System](../explanation/design-system.md) - CSS Framework
- [Troubleshooting](../troubleshooting.md) - Häufige Probleme

**Probleme?**
- Prüfe Browser-Konsole auf JavaScript-Fehler
- Aktiviere `WP_DEBUG` für detaillierte Fehlermeldungen
- Prüfe, ob Services in Admin angelegt sind
