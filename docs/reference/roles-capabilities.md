# LazyBookings Capabilities & Permissions

## Overview

LazyBookings implementiert ein fein granuliertes Capability-System, das klare Berechtigungsgrenzen für unterschiedliche Rollen durchsetzt. Alle Schreib-Operationen werden durch Capabilities + Nonces abgesichert, REST-Endpunkte nutzen rollen-spezifische Permission-Callbacks.

## User Roles

### Administrator (Superadmin)
- **WordPress Role**: `administrator`
- **Profil**: `superadmin`
- **Capabilities**: Alle Capabilities
- **Access**: Vollzugriff inkl. Settings, AI-Konfiguration, Payments und Refunds

### Manager
- **WordPress Role**: `editor`
- **Profil**: `mitarbeiter` (Legacy-Bezeichnung)
- **Capabilities**:
  - `view_bookings`, `manage_bookings`
  - `view_customers`, `manage_customers`
  - `view_services`
  - `view_staff`
  - `manage_own_availability`
- **Access**: Operatives Tagesgeschäft, aber keine Preis-/Settings-Änderungen und keine Refund-Freigaben

### Staff
- **WordPress Role**: `ltlb_staff`
- **Profil**: `mitarbeiter`
- **Capabilities**:
  - `view_bookings`, `manage_own_bookings`
  - `view_customers`
  - `view_services`
  - `view_staff`
  - `manage_own_availability`
- **Access**: Read-only für die meisten Daten, Verwaltung des eigenen Kalenders und der zugewiesenen Buchungen

### CEO/Reports Viewer
- **WordPress Role**: `ltlb_ceo`
- **Profil**: `ceo`
- **Capabilities**:
  - `view_ai_reports`
  - `view_reports`
  - `view_payments`
- **Access**: Read-only Dashboards, Analytics und Finanzreports

## Custom Capabilities

### AI Capabilities
- `manage_ai_settings` – AI-Konfiguration (Provider, Keys, Mode)
- `manage_ai_secrets` – API Keys anzeigen/bearbeiten
- `view_ai_reports` – AI Insights einsehen (Admin, CEO)
- `approve_ai_drafts` – AI-generierte Aktionen freigeben

### Booking Capabilities
- `view_bookings` – Alle Buchungen sehen (Admin, Manager, Staff)
- `manage_bookings` – Buchungen anlegen/bearbeiten/löschen (Admin, Manager)
- `manage_own_bookings` – Nur eigene Buchungen verwalten (Staff)

### Customer Capabilities
- `view_customers` – Kundendaten einsehen (Admin, Manager, Staff)
- `manage_customers` – Kunden anlegen/bearbeiten (Admin, Manager)

### Staff & Availability
- `view_staff` – Staff-Profile sehen
- `manage_own_availability` – Eigene Verfügbarkeit pflegen
- `manage_staff_roles` – Rollen zuweisen (Admin)

### Payments & Finance
- `view_payments` – Zahlungsübersicht sehen (Admin, CEO)
- `manage_refunds` – Refunds auslösen/bestätigen (Admin)

### Reports & Analytics
- `view_reports` – KPIs, Auslastung, Umsatzberichte sehen

Alle Capabilities werden zentral in `LTLB_RoleManager` registriert und bei Aktivierung/Deaktivierung des Plugins synchronisiert.
