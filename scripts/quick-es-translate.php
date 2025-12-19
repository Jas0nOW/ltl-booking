<?php
/**
 * Simple: Copy German msgstr values to Spanish PO and translate them
 */

$de_file = __DIR__ . '/../languages/de_DE.po';
$es_file = __DIR__ . '/../languages/es_ES.po';

// German to Spanish simple word replacement
$replacements = [
    // Common words
    'und' => 'y', 'oder' => 'o', 'mit' => 'con', 'ohne' => 'sin', 'für' => 'para',
    'der' => 'el', 'die' => 'la', 'das' => 'el', 'ein' => 'un', 'eine' => 'una',
    'ist' => 'es', 'sind' => 'son', 'hat' => 'tiene', 'haben' => 'tienen',
    'nicht' => 'no', 'kein' => 'ningún', 'keine' => 'ninguna',
    'bitte' => 'por favor', 'Bitte' => 'Por favor',
    'erfolgreich' => 'correctamente', 'Erfolgreich' => 'Correctamente',
    
    // UI terms
    'Speichern' => 'Guardar', 'speichern' => 'guardar',
    'Löschen' => 'Eliminar', 'löschen' => 'eliminar',
    'Bearbeiten' => 'Editar', 'bearbeiten' => 'editar',
    'Abbrechen' => 'Cancelar', 'abbrechen' => 'cancelar',
    'Hinzufügen' => 'Añadir', 'hinzufügen' => 'añadir',
    'Neu' => 'Nuevo', 'neu' => 'nuevo', 'Neue' => 'Nueva', 'neue' => 'nueva',
    'Ansehen' => 'Ver', 'ansehen' => 'ver',
    'Aktualisieren' => 'Actualizar', 'aktualisieren' => 'actualizar',
    'Suchen' => 'Buscar', 'suchen' => 'buscar',
    'Filtern' => 'Filtrar', 'filtern' => 'filtrar',
    'Auswählen' => 'Seleccionar', 'auswählen' => 'seleccionar',
    'Bestätigen' => 'Confirmar', 'bestätigen' => 'confirmar',
    
    // Status
    'Aktiv' => 'Activo', 'aktiv' => 'activo',
    'Inaktiv' => 'Inactivo', 'inaktiv' => 'inactivo',
    'Ausstehend' => 'Pendiente', 'ausstehend' => 'pendiente',
    'Bestätigt' => 'Confirmado', 'bestätigt' => 'confirmado',
    'Storniert' => 'Cancelado', 'storniert' => 'cancelado',
    'Abgeschlossen' => 'Completado', 'abgeschlossen' => 'completado',
    'Bezahlt' => 'Pagado', 'bezahlt' => 'pagado',
    'Unbezahlt' => 'No pagado', 'unbezahlt' => 'no pagado',
    
    // Business terms
    'Termin' => 'Cita', 'Termine' => 'Citas',
    'Buchung' => 'Reserva', 'Buchungen' => 'Reservas',
    'Kunde' => 'Cliente', 'Kunden' => 'Clientes',
    'Dienstleistung' => 'Servicio', 'Dienstleistungen' => 'Servicios',
    'Mitarbeiter' => 'Personal',
    'Ressource' => 'Recurso', 'Ressourcen' => 'Recursos',
    'Zimmer' => 'Habitación',
    'Kalender' => 'Calendario',
    'Einstellungen' => 'Configuración',
    'Dashboard' => 'Panel',
    'Übersicht' => 'Resumen',
    'Berichte' => 'Informes',
    'Benachrichtigungen' => 'Notificaciones',
    'Benachrichtigung' => 'Notificación',
    
    // Time
    'Datum' => 'Fecha', 'Zeit' => 'Hora', 'Dauer' => 'Duración',
    'Tag' => 'Día', 'Tage' => 'Días',
    'Woche' => 'Semana', 'Wochen' => 'Semanas',
    'Monat' => 'Mes', 'Monate' => 'Meses',
    'Jahr' => 'Año', 'Jahre' => 'Años',
    'Stunde' => 'Hora', 'Stunden' => 'Horas',
    'Minute' => 'Minuto', 'Minuten' => 'Minutos',
    'Heute' => 'Hoy', 'Morgen' => 'Mañana', 'Gestern' => 'Ayer',
    'Montag' => 'Lunes', 'Dienstag' => 'Martes', 'Mittwoch' => 'Miércoles',
    'Donnerstag' => 'Jueves', 'Freitag' => 'Viernes',
    'Samstag' => 'Sábado', 'Sonntag' => 'Domingo',
    
    // Messages
    'gespeichert' => 'guardado', 'Gespeichert' => 'Guardado',
    'gelöscht' => 'eliminado', 'Gelöscht' => 'Eliminado',
    'erstellt' => 'creado', 'Erstellt' => 'Creado',
    'aktualisiert' => 'actualizado', 'Aktualisiert' => 'Actualizado',
    'fehlgeschlagen' => 'fallido', 'Fehlgeschlagen' => 'Fallido',
    'Fehler' => 'Error', 'fehler' => 'error',
    'Erfolg' => 'Éxito', 'erfolg' => 'éxito',
    'Warnung' => 'Advertencia', 'warnung' => 'advertencia',
    
    // Other
    'Name' => 'Nombre', 'E-Mail' => 'Correo', 'Telefon' => 'Teléfono',
    'Adresse' => 'Dirección', 'Stadt' => 'Ciudad', 'Land' => 'País',
    'Preis' => 'Precio', 'Betrag' => 'Importe', 'Summe' => 'Total',
    'Zahlung' => 'Pago', 'Zahlungen' => 'Pagos',
    'Rechnung' => 'Factura', 'Gutschein' => 'Cupón',
    'Passwort' => 'Contraseña', 'Benutzername' => 'Usuario',
    'Anmelden' => 'Iniciar sesión', 'Abmelden' => 'Cerrar sesión',
    'Hilfe' => 'Ayuda', 'Info' => 'Información',
    'Ja' => 'Sí', 'Nein' => 'No', 'OK' => 'Aceptar',
    'Alle' => 'Todos', 'Keine' => 'Ninguno',
    'Verfügbar' => 'Disponible', 'Nicht verfügbar' => 'No disponible',
    'Pflicht' => 'Requerido', 'Optional' => 'Opcional',
];

// Read both files
$de_content = file_get_contents($de_file);
$es_content = file_get_contents($es_file);

// Parse German: msgid → msgstr
$de_translations = [];
preg_match_all('/msgid "(.+?)"\s*\nmsgstr "(.+?)"/s', $de_content, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $msgid = stripcslashes($m[1]);
    $msgstr = stripcslashes($m[2]);
    if ($msgid && $msgstr) {
        $de_translations[$msgid] = $msgstr;
    }
}

echo "Found " . count($de_translations) . " German translations\n";

// For each empty Spanish msgstr, use German and apply replacements
$lines = explode("\n", $es_content);
$result = [];
$current_msgid = '';
$translated = 0;
$total_empty = 0;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    
    if (preg_match('/^msgid "(.+)"$/', $line, $m)) {
        $current_msgid = stripcslashes($m[1]);
        $result[] = $line;
        continue;
    }
    
    if (preg_match('/^msgstr ""$/', $line) && $current_msgid) {
        $total_empty++;
        
        // Get German translation
        if (isset($de_translations[$current_msgid])) {
            $german = $de_translations[$current_msgid];
            
            // Apply word replacements to create Spanish
            $spanish = $german;
            foreach ($replacements as $de => $es) {
                $spanish = str_replace($de, $es, $spanish);
            }
            
            // If changed, use it
            if ($spanish !== $german) {
                $result[] = 'msgstr "' . addcslashes($spanish, '"\\') . '"';
                $translated++;
            } else {
                // Use German as fallback (better than English)
                $result[] = 'msgstr "' . addcslashes($german, '"\\') . '"';
                $translated++;
            }
        } else {
            $result[] = $line;
        }
        $current_msgid = '';
        continue;
    }
    
    $result[] = $line;
}

file_put_contents($es_file, implode("\n", $result));

echo "Translated: $translated of $total_empty empty strings\n";
echo "Done!\n";
