# Admin-Spalten-Anpassung

## Status: OPTIONAL / DEFERRED

## Hintergrund
WordPress ermöglicht es, Custom-Spalten zu Admin-Listen hinzuzufügen. Da LazyBookings Custom-Tables verwendet (keine Post Types), gibt es keine nativen WordPress-Admin-Listen.

## Aktuelle Situation
- ✅ Custom Admin-Pages existieren für Services, Customers, Appointments, Resources
- ✅ Tabellen zeigen alle relevanten Daten
- ❌ Keine Sortierung, Bulk-Actions, oder Quick-Edit

## Was könnte verbessert werden

### 1. Sortierung
Erlaubt Klicken auf Spalten-Header zum Sortieren:
```php
// In AppointmentsPage.php
$order_by = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'start_at';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Header mit Links
<th><a href="?page=ltlb_appointments&orderby=start_at&order=<?php echo $order === 'ASC' ? 'desc' : 'asc'; ?>">Date</a></th>
```

### 2. Bulk-Actions
Erlaubt Mehrfach-Operationen (z.B. mehrere Appointments auf einmal löschen):
```php
<form method="post">
    <select name="bulk_action">
        <option value="">Bulk Actions</option>
        <option value="delete">Delete</option>
        <option value="confirm">Confirm</option>
    </select>
    <input type="submit" value="Apply">
    
    <table>
        <tr>
            <td><input type="checkbox" name="appointment_ids[]" value="123"></td>
            <td>...</td>
        </tr>
    </table>
</form>
```

### 3. Search/Filter Persistence
Filter-Werte in URL speichern für bessere UX:
```php
// Already implemented in AppointmentsPage ✅
```

### 4. Pagination
Für große Datenmengen:
```php
$per_page = 50;
$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$offset = ($page - 1) * $per_page;

// SQL mit LIMIT/OFFSET
$sql .= " LIMIT $per_page OFFSET $offset";

// Pagination-Links
echo paginate_links([
    'total' => ceil($total_count / $per_page),
    'current' => $page,
]);
```

## Empfehlung

⏳ **DEFERRED**

**Begründung:**
- Aktuelle Tables funktionieren gut für MVP
- Sortierung/Pagination wichtig erst bei >100 Einträgen
- Bulk-Actions nice-to-have, aber nicht kritisch
- Zeit besser in Features investieren (z.B. Calendar-View)

## Future Implementation Priority

1. **Pagination** (HIGH) - Wird wichtig bei realer Nutzung
2. **Sortierung** (MEDIUM) - UX-Verbesserung
3. **Bulk-Actions** (LOW) - Zeitersparnis für Admins
4. **Quick-Edit** (LOW) - Nice-to-have

## Wenn implementiert:

### File: `admin/Pages/AppointmentsPage.php`
- [ ] Add sortable columns (orderby/order params)
- [ ] Add pagination with `paginate_links()`
- [ ] Add bulk action dropdown and checkbox column
- [ ] Process bulk actions in POST handler

### File: `admin/Pages/ServicesPage.php`
- [ ] Same as above

### File: `admin/Pages/CustomersPage.php`
- [ ] Same as above

## Aktueller Status
✅ Die Listen funktionieren bereits gut für kleine/mittlere Datenmengen. Pagination und Sortierung können später bei Bedarf ergänzt werden.
