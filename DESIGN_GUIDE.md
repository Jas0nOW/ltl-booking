# LazyBookings Extended Design System

## Übersicht

Das erweiterte Design-System ermöglicht dir vollständige Kontrolle über das Aussehen deines Buchungs-Wizards **ohne Code-Änderungen**. Alle Einstellungen sind in der WordPress Admin-Seite unter **LazyBookings → Design** verfügbar.

---

## 🎨 Design-Einstellungen

### 1. **Farben** (Colors)
Passe die vier Hauptfarben deines Wizards an:

| Einstellung | Verwendung | Standard |
|------------|-----------|---------|
| **Background Color** | Hintergrund des gesamten Wizards | #ffffff (weiß) |
| **Primary Color** | Buttons, Links, Highlights | #2b7cff (blau) |
| **Text Color** | Alle Texte und Labels | #222222 (dunkelgrau) |
| **Accent Color** | Hover-Effekte, Sekundäre Highlights | #ffcc00 (gelb) |
| **Border Color** | Rahmen von Inputs und Cards | #cccccc (hellgrau) |

**💡 Tipp:** Verwende einen Farbwähler, um deine Markenfarben zu kopieren.

---

### 2. **Abstand & Formen** (Spacing & Shapes)

| Einstellung | Effekt | Bereich |
|------------|--------|--------|
| **Border Radius** | Rundheit von Buttons und Input-Feldern | 0-50px |
| **Border Width** | Dicke der Ränder | 0-10px |

**Beispiele:**
- Border Radius 0px = eckig
- Border Radius 25px = sehr rund
- Border Width 0px = keine Ränder
- Border Width 2px = deutliche Ränder

---

### 3. **Schatten & Effekte** (Shadow & Effects)

| Einstellung | Funktion |
|------------|---------|
| **Enable Box Shadow** | Checkbox zum Aktivieren/Deaktivieren von Schatten |
| **Shadow Blur** | Weichheit des Schattens (0-20px) |
| **Shadow Spread** | Ausbreitung des Schattens (0-10px) |
| **Enable Gradient** | Checkbox für Farbverlauf von Primary zu Accent |
| **Animation Duration** | Geschwindigkeit von Hover-Effekten (0-1000ms) |

**Visual Effects:**
- Animation Duration 0ms = keine Animationen
- Animation Duration 200ms = schnell (Standard)
- Animation Duration 500ms = langsam und elegant

---

### 4. **Custom CSS**
Für fortgeschrittene Benutzer: Schreibe eigene CSS-Regeln!

**Verfügbare CSS-Klassen:**
```css
.ltlb-booking              /* Gesamter Wizard-Container */
.ltlb-booking h3           /* Titel */
.ltlb-booking .button-primary  /* Primäre Buttons */
.ltlb-booking .button-secondary /* Sekundäre Buttons */
.ltlb-booking .service-card    /* Service-Auswahl Cards */
.ltlb-booking .ltlb-price-preview /* Preis-Vorschau (Hotel-Modus) */
.ltlb-booking input        /* Alle Input-Felder */
.ltlb-booking select       /* Dropdown-Felder */
.ltlb-booking .ltlb-success    /* Erfolgs-Meldungen */
.ltlb-booking .ltlb-error      /* Fehler-Meldungen */
```

**Beispiel Custom CSS:**
```css
.ltlb-booking {
  border: 2px solid var(--lazy-primary);
  border-radius: 12px;
}

.ltlb-booking .service-card {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  background: rgba(255, 255, 255, 0.95);
}

.ltlb-booking .button-primary:hover {
  transform: scale(1.05);
}
```

---

## 🔄 CSS-Variablen (für Entwickler)

Wenn du Custom CSS schreibst, kannst du diese Variablen nutzen:

```css
:root {
  --lazy-bg                    /* Background oder Gradient */
  --lazy-primary               /* Primary Button Color */
  --lazy-text                  /* Text Color */
  --lazy-accent                /* Accent/Hover Color */
  --lazy-border-color          /* Border Color */
  --lazy-border-width          /* Border Width (z.B. "1px") */
  --lazy-border-radius         /* Border Radius (z.B. "6px") */
  --lazy-box-shadow            /* Box Shadow String */
  --lazy-transition-duration   /* Animation Duration (z.B. "200ms") */
}
```

**Beispiel:**
```css
.ltlb-booking .button-primary {
  background: var(--lazy-primary);
  border-radius: var(--lazy-border-radius);
  transition: all var(--lazy-transition-duration) ease;
}
```

---

## 📋 Live-Vorschau

Die Design-Seite zeigt dir eine **Live-Vorschau** mit:
- Service Card Beispiel
- Primary Button
- Secondary Button
- Input Field

Hier kannst du alle Änderungen in **Echtzeit** sehen, bevor du speicherst.

---

## 💾 Speichern und Anwenden

1. Öffne **LazyBookings → Design**
2. Ändere Farben, Schatten, Animationen
3. Schau dir die Vorschau an
4. Klicke **"Save Design"**
5. Besuche deine Booking-Seite im Frontend - die Änderungen sind sofort live!

---

## 🎯 Design-Ideen für verschiedene Branchen

### 🧘 Yoga & Wellness
```
Background: #FDFCF8 (Creme)
Primary: #A67B5B (Terrakotta)
Text: #3D3D3D (Dunkelgrau)
Accent: #8DA399 (Salbei)
Border Radius: 8px
Box Shadow: Enabled, Blur 6px
Gradient: Disabled
```

### 🏥 Medizin & Beratung
```
Background: #FFFFFF (Weiß)
Primary: #0066CC (Tiefblau)
Text: #1F1F1F (Schwarz)
Accent: #00AA00 (Grün)
Border Radius: 4px
Box Shadow: Enabled, Blur 4px
Gradient: Disabled
```

### 🏨 Hotel & Unterkunft
```
Background: #F5F5F5 (Hellgrau)
Primary: #8B4513 (Braun)
Text: #333333 (Dunkelgrau)
Accent: #FFD700 (Gold)
Border Radius: 12px
Box Shadow: Enabled, Blur 8px
Gradient: Enabled (Brown → Gold)
Animation Duration: 250ms
```

---

## ❓ Häufig gestellte Fragen

**F: Änderungen werden nicht gespeichert?**
A: Stelle sicher, dass:
- Du auf "Save Design" klickst
- Du Admin-Berechtigung hast
- JavaScript im Browser aktiviert ist

**F: Können Gäste (nicht registrierte Benutzer) das Design sehen?**
A: Ja! Das Design wird auf der Frontend-Seite mit dem Shortcode `[lazy_book]` angewendet.

**F: Kann ich nur einzelne Farben ändern und andere behalten?**
A: Ja! Lass leere Felder einfach leer oder füge nur die Farben ein, die du ändern möchtest. Die Standardwerte werden für leere Felder verwendet.

**F: Wie kann ich Custom CSS zurücksetzen?**
A: Leere einfach das Custom CSS-Feld und klicke "Save Design".

**F: Funktionieren die Designs auf mobilen Geräten?**
A: Ja! Das Design ist vollständig responsive und arbeitet mit allen Viewport-Größen.

---

## 🚀 Advanced Tips

### Responsive Custom CSS
```css
@media (max-width: 640px) {
  .ltlb-booking {
    padding: 1rem;
  }
  
  .ltlb-booking .button-primary {
    width: 100%;
  }
}
```

### Gradient Hintergrund (ohne Gradient-Checkbox zu nutzen)
```css
.ltlb-booking {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```

### Custom Hover-Effekt
```css
.ltlb-booking .button-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}
```

---

## 📞 Unterstützung

Wenn etwas nicht funktioniert:
1. Öffne die **Browser Developer Tools** (F12)
2. Schau in der **Console** auf Fehler
3. Überprüfe die **Network**-Requests
4. Stelle sicher, die Plugin-Version ist **0.4.0+**

---

**Version:** 0.4.0  
**Datum:** Dezember 2025
