# LazyBookings Design Guide (v0.4.4)

Dieser Guide beschreibt die **tatsächlich implementierten** Design-Optionen in LazyBookings.

## Admin: Design-Seite

Unter **LazyBookings → Design** können aktuell vier Farben gepflegt werden:

- Background
- Primary
- Text
- Accent

Diese Werte werden als CSS-Variablen auf dem Frontend ausgegeben, **scoped** auf den Wizard-Container.

## CSS-Variablen

Auf dem Booking-Widget (Container `.ltlb-booking`) stehen diese Variablen zur Verfügung:

```css
.ltlb-booking {
  --lazy-bg:      <hex>;
  --lazy-primary: <hex>;
  --lazy-text:    <hex>;
  --lazy-accent:  <hex>;
}
```

## Ziel der Umsetzung

- Styles bleiben auf das Widget beschränkt (keine globalen Theme-Sideeffects).
- Das Theme kann weiterhin per CSS überschreiben.

## Custom Overrides (optional)

Wenn du das Widget im Theme weiter anpassen willst, ist der empfohlene Einstieg:

```css
.ltlb-booking {
  /* Beispiel: Primärfarbe im Theme überschreiben */
  --lazy-primary: #2b7cff;
}
```

Hinweis: Dieser Guide dokumentiert bewusst nur die aktuell vorhandenen Variablen. Weitere UI-Controls (Border/Shadow/Custom CSS) sind in v0.4.4 nicht Teil der Implementierung.
