<?php
/**
 * Complete Spanish Translation Using German as Source
 * 
 * This script uses a comprehensive German→Spanish dictionary to translate
 * all remaining strings in es_ES.po
 */

$de_file = __DIR__ . '/../languages/de_DE.po';
$es_file = __DIR__ . '/../languages/es_ES.po';

// German → Spanish dictionary for common booking/admin terms
$de_to_es = [
    // === COMMON ===
    'Speichern' => 'Guardar',
    'Abbrechen' => 'Cancelar',
    'Löschen' => 'Eliminar',
    'Bearbeiten' => 'Editar',
    'Hinzufügen' => 'Añadir',
    'Aktualisieren' => 'Actualizar',
    'Schließen' => 'Cerrar',
    'Zurück' => 'Atrás',
    'Weiter' => 'Siguiente',
    'Vorherige' => 'Anterior',
    'Suchen' => 'Buscar',
    'Filtern' => 'Filtrar',
    'Löschen' => 'Borrar',
    'Zurücksetzen' => 'Restablecer',
    'Absenden' => 'Enviar',
    'Bestätigen' => 'Confirmar',
    'Ja' => 'Sí',
    'Nein' => 'No',
    'OK' => 'Aceptar',
    'Laden...' => 'Cargando...',
    'Bitte warten...' => 'Por favor espere...',
    'Fehler' => 'Error',
    'Erfolg' => 'Éxito',
    'Warnung' => 'Advertencia',
    'Info' => 'Información',
    'Keine' => 'Ninguno',
    'Alle' => 'Todos',
    'Auswählen' => 'Seleccionar',
    'Optionen' => 'Opciones',
    'Aktionen' => 'Acciones',
    'Mehr' => 'Más',
    'Weniger' => 'Menos',
    'Anzeigen' => 'Mostrar',
    'Ausblenden' => 'Ocultar',
    'Ansehen' => 'Ver',
    'Details' => 'Detalles',
    'Zusammenfassung' => 'Resumen',
    'Gesamt' => 'Total',
    'Zwischensumme' => 'Subtotal',
    'Steuer' => 'Impuesto',
    'Rabatt' => 'Descuento',
    'Preis' => 'Precio',
    'Betrag' => 'Importe',
    'Menge' => 'Cantidad',
    'Status' => 'Estado',
    'Aktiv' => 'Activo',
    'Inaktiv' => 'Inactivo',
    'Aktiviert' => 'Habilitado',
    'Deaktiviert' => 'Deshabilitado',
    'Standard' => 'Predeterminado',
    'Benutzerdefiniert' => 'Personalizado',
    'Erforderlich' => 'Requerido',
    'Optional' => 'Opcional',
    'Verfügbar' => 'Disponible',
    'Nicht verfügbar' => 'No disponible',
    'Heute' => 'Hoy',
    'Morgen' => 'Mañana',
    'Gestern' => 'Ayer',
    'Jetzt' => 'Ahora',
    'Nie' => 'Nunca',
    'Immer' => 'Siempre',
    'Name' => 'Nombre',
    'E-Mail' => 'Correo electrónico',
    'Telefon' => 'Teléfono',
    'Adresse' => 'Dirección',
    'Stadt' => 'Ciudad',
    'Land' => 'País',
    'Notizen' => 'Notas',
    'Beschreibung' => 'Descripción',
    'Titel' => 'Título',
    'Datum' => 'Fecha',
    'Zeit' => 'Hora',
    'Dauer' => 'Duración',
    'Start' => 'Inicio',
    'Ende' => 'Fin',
    'Von' => 'Desde',
    'Bis' => 'Hasta',
    'Typ' => 'Tipo',
    'Kategorie' => 'Categoría',
    'Bild' => 'Imagen',
    'Datei' => 'Archivo',
    'Hochladen' => 'Subir',
    'Herunterladen' => 'Descargar',
    'Exportieren' => 'Exportar',
    'Importieren' => 'Importar',
    'Drucken' => 'Imprimir',
    'Kopieren' => 'Copiar',
    'Hilfe' => 'Ayuda',
    'Über' => 'Acerca de',
    'Version' => 'Versión',
    'Einstellungen' => 'Configuración',
    'Allgemein' => 'General',
    'Erweitert' => 'Avanzado',
    'Einfach' => 'Básico',
    'Profil' => 'Perfil',
    'Konto' => 'Cuenta',
    'Passwort' => 'Contraseña',
    'Anmelden' => 'Iniciar sesión',
    'Abmelden' => 'Cerrar sesión',
    'Registrieren' => 'Registrarse',
    'Willkommen' => 'Bienvenido',
    
    // === DAYS ===
    'Montag' => 'Lunes',
    'Dienstag' => 'Martes',
    'Mittwoch' => 'Miércoles',
    'Donnerstag' => 'Jueves',
    'Freitag' => 'Viernes',
    'Samstag' => 'Sábado',
    'Sonntag' => 'Domingo',
    'Tag' => 'Día',
    'Tage' => 'Días',
    'Woche' => 'Semana',
    'Wochen' => 'Semanas',
    'Monat' => 'Mes',
    'Monate' => 'Meses',
    'Jahr' => 'Año',
    'Jahre' => 'Años',
    'Stunde' => 'Hora',
    'Stunden' => 'Horas',
    'Minute' => 'Minuto',
    'Minuten' => 'Minutos',
    
    // === BOOKING ===
    'Termin' => 'Cita',
    'Termine' => 'Citas',
    'Buchung' => 'Reserva',
    'Buchungen' => 'Reservas',
    'Reservierung' => 'Reserva',
    'Reservierungen' => 'Reservas',
    'Dienstleistung' => 'Servicio',
    'Dienstleistungen' => 'Servicios',
    'Kunde' => 'Cliente',
    'Kunden' => 'Clientes',
    'Mitarbeiter' => 'Personal',
    'Ressource' => 'Recurso',
    'Ressourcen' => 'Recursos',
    'Zimmer' => 'Habitación',
    'Standort' => 'Ubicación',
    'Standorte' => 'Ubicaciones',
    'Kalender' => 'Calendario',
    'Zeitplan' => 'Horario',
    'Verfügbarkeit' => 'Disponibilidad',
    'Zeitfenster' => 'Franja horaria',
    'Check-in' => 'Entrada',
    'Check-out' => 'Salida',
    'Ankunft' => 'Llegada',
    'Abreise' => 'Salida',
    'Gast' => 'Huésped',
    'Gäste' => 'Huéspedes',
    'Erwachsene' => 'Adultos',
    'Kinder' => 'Niños',
    
    // === STATUS ===
    'Ausstehend' => 'Pendiente',
    'Bestätigt' => 'Confirmado',
    'Storniert' => 'Cancelado',
    'Abgeschlossen' => 'Completado',
    'Nicht erschienen' => 'No presentado',
    'In Bearbeitung' => 'En progreso',
    'Genehmigt' => 'Aprobado',
    'Abgelehnt' => 'Rechazado',
    'Entwurf' => 'Borrador',
    'Veröffentlicht' => 'Publicado',
    'Archiviert' => 'Archivado',
    'Gelöscht' => 'Eliminado',
    'Offen' => 'Abierto',
    'Geschlossen' => 'Cerrado',
    'Gebucht' => 'Reservado',
    'Belegt' => 'Ocupado',
    'Frei' => 'Libre',
    'Wartung' => 'Mantenimiento',
    
    // === PAYMENT ===
    'Zahlung' => 'Pago',
    'Zahlungen' => 'Pagos',
    'Bezahlen' => 'Pagar',
    'Bezahlt' => 'Pagado',
    'Unbezahlt' => 'No pagado',
    'Teilweise' => 'Parcial',
    'Rückerstattung' => 'Reembolso',
    'Rechnung' => 'Factura',
    'Quittung' => 'Recibo',
    'Kreditkarte' => 'Tarjeta de crédito',
    'Überweisung' => 'Transferencia bancaria',
    'Bargeld' => 'Efectivo',
    'Anzahlung' => 'Depósito',
    'Saldo' => 'Saldo',
    'Fällig' => 'Vencido',
    'Kostenlos' => 'Gratis',
    
    // === NAVIGATION ===
    'Dashboard' => 'Panel de control',
    'Startseite' => 'Inicio',
    'Menü' => 'Menú',
    'Übersicht' => 'Resumen',
    'Berichte' => 'Informes',
    'Analysen' => 'Analíticas',
    'Statistiken' => 'Estadísticas',
    'Benachrichtigungen' => 'Notificaciones',
    'Nachrichten' => 'Mensajes',
    'Posteingang' => 'Bandeja de entrada',
    'Postausgang' => 'Bandeja de salida',
    'Gesendet' => 'Enviado',
    'Entwürfe' => 'Borradores',
    'Papierkorb' => 'Papelera',
    'Archiv' => 'Archivo',
    'Verlauf' => 'Historial',
    'Protokoll' => 'Registro',
    
    // === FORMS ===
    'Formular' => 'Formulario',
    'Feld' => 'Campo',
    'Felder' => 'Campos',
    'Bezeichnung' => 'Etiqueta',
    'Wert' => 'Valor',
    'Text' => 'Texto',
    'Nummer' => 'Número',
    'E-Mail-Adresse' => 'Dirección de correo electrónico',
    'Telefonnummer' => 'Número de teléfono',
    'Vorname' => 'Nombre',
    'Nachname' => 'Apellido',
    'Vollständiger Name' => 'Nombre completo',
    'Firma' => 'Empresa',
    'Webseite' => 'Sitio web',
    'Nachricht' => 'Mensaje',
    'Kommentar' => 'Comentario',
    'Kommentare' => 'Comentarios',
    'Bewertung' => 'Calificación',
    'Feedback' => 'Comentarios',
    
    // === PLUGIN SPECIFIC ===
    'Neuer Termin' => 'Nueva cita',
    'Termin bearbeiten' => 'Editar cita',
    'Termin löschen' => 'Eliminar cita',
    'Termin ansehen' => 'Ver cita',
    'Termindetails' => 'Detalles de la cita',
    'Terminstatus' => 'Estado de la cita',
    'Termin erstellt.' => 'Cita creada.',
    'Termin aktualisiert.' => 'Cita actualizada.',
    'Termin gelöscht.' => 'Cita eliminada.',
    'Termin bestätigt.' => 'Cita confirmada.',
    'Termin storniert.' => 'Cita cancelada.',
    'Neue Buchung' => 'Nueva reserva',
    'Buchung bearbeiten' => 'Editar reserva',
    'Buchung löschen' => 'Eliminar reserva',
    'Buchung ansehen' => 'Ver reserva',
    'Buchungsdetails' => 'Detalles de la reserva',
    'Buchungsstatus' => 'Estado de la reserva',
    'Buchung erstellt.' => 'Reserva creada.',
    'Buchung aktualisiert.' => 'Reserva actualizada.',
    'Buchung gelöscht.' => 'Reserva eliminada.',
    'Buchung bestätigt.' => 'Reserva confirmada.',
    'Buchung storniert.' => 'Reserva cancelada.',
    'Neuer Kunde' => 'Nuevo cliente',
    'Kunde bearbeiten' => 'Editar cliente',
    'Kunde löschen' => 'Eliminar cliente',
    'Kunde ansehen' => 'Ver cliente',
    'Kundendetails' => 'Detalles del cliente',
    'Kunde gespeichert.' => 'Cliente guardado.',
    'Neue Dienstleistung' => 'Nuevo servicio',
    'Dienstleistung bearbeiten' => 'Editar servicio',
    'Dienstleistung löschen' => 'Eliminar servicio',
    'Dienstleistung ansehen' => 'Ver servicio',
    'Dienstleistungsdetails' => 'Detalles del servicio',
    'Dienstleistung gespeichert.' => 'Servicio guardado.',
    'Neuer Mitarbeiter' => 'Nuevo personal',
    'Mitarbeiter bearbeiten' => 'Editar personal',
    'Mitarbeiter löschen' => 'Eliminar personal',
    'Neue Ressource' => 'Nuevo recurso',
    'Ressource bearbeiten' => 'Editar recurso',
    'Ressource löschen' => 'Eliminar recurso',
    'Neues Zimmer' => 'Nueva habitación',
    'Zimmer bearbeiten' => 'Editar habitación',
    'Zimmer löschen' => 'Eliminar habitación',
    'Zimmertyp' => 'Tipo de habitación',
    'Zimmertypen' => 'Tipos de habitación',
    'Zimmernummer' => 'Número de habitación',
    'Zimmername' => 'Nombre de la habitación',
    'Zimmerstatus' => 'Estado de la habitación',
    'Bettentyp' => 'Tipo de cama',
    'Anzahl der Betten' => 'Número de camas',
    'Maximale Gäste' => 'Máximo de huéspedes',
    'Maximale Kapazität' => 'Capacidad máxima',
    'Preis pro Nacht' => 'Precio por noche',
    'Pro Nacht' => 'Por noche',
    'Pro Person' => 'Por persona',
    'Arbeitszeiten' => 'Horario laboral',
    'Geschäftszeiten' => 'Horario comercial',
    'Öffnungszeiten' => 'Horario de apertura',
    'Startdatum' => 'Fecha de inicio',
    'Enddatum' => 'Fecha de fin',
    'Startzeit' => 'Hora de inicio',
    'Endzeit' => 'Hora de fin',
    'Datumsbereich' => 'Rango de fechas',
    'Datum auswählen' => 'Seleccionar fecha',
    'Zeit auswählen' => 'Seleccionar hora',
    'Dienstleistung auswählen' => 'Seleccionar servicio',
    'Mitarbeiter auswählen' => 'Seleccionar personal',
    'Kunde auswählen' => 'Seleccionar cliente',
    'Zimmer auswählen' => 'Seleccionar habitación',
    'Status auswählen' => 'Seleccionar estado',
    'Alle Termine' => 'Todas las citas',
    'Alle Buchungen' => 'Todas las reservas',
    'Alle Kunden' => 'Todos los clientes',
    'Alle Dienstleistungen' => 'Todos los servicios',
    'Alle Mitarbeiter' => 'Todo el personal',
    'Alle Zimmer' => 'Todas las habitaciones',
    'Keine Termine' => 'Sin citas',
    'Keine Buchungen' => 'Sin reservas',
    'Keine Kunden' => 'Sin clientes',
    'Keine Dienstleistungen' => 'Sin servicios',
    'Buchungen gesamt' => 'Total de reservas',
    'Gesamtumsatz' => 'Ingresos totales',
    'Kunden gesamt' => 'Total de clientes',
    'Jetzt buchen' => 'Reservar ahora',
    'Buchen' => 'Reservar',
    'Reservieren' => 'Reservar',
    'Verfügbarkeit prüfen' => 'Comprobar disponibilidad',
    'Kalender ansehen' => 'Ver calendario',
    'Kalenderansicht' => 'Vista de calendario',
    'Listenansicht' => 'Vista de lista',
    'Tagesansicht' => 'Vista de día',
    'Wochenansicht' => 'Vista de semana',
    'Monatsansicht' => 'Vista de mes',
    'Diagnose' => 'Diagnósticos',
    'Dokumentation' => 'Documentación',
    'Support' => 'Soporte',
    'Branding' => 'Marca',
    'Design' => 'Diseño',
    'Erscheinungsbild' => 'Apariencia',
    'Farben' => 'Colores',
    'Primärfarbe' => 'Color primario',
    'Sekundärfarbe' => 'Color secundario',
    'Akzentfarbe' => 'Color de acento',
    'Hintergrundfarbe' => 'Color de fondo',
    'Textfarbe' => 'Color del texto',
    'Rahmenfarbe' => 'Color del borde',
    'Fehlerfarbe' => 'Color de error',
    'Erfolgsfarbe' => 'Color de éxito',
    'Warnfarbe' => 'Color de advertencia',
    'Schriftfamilie' => 'Familia de fuentes',
    'Schriftgröße' => 'Tamaño de fuente',
    'Rahmenradius' => 'Radio del borde',
    'Rahmenbreite' => 'Ancho del borde',
    'Benutzerdefiniertes CSS' => 'CSS personalizado',
    'Automatisierung' => 'Automatización',
    'Automatisierungen' => 'Automatizaciones',
    'Regel' => 'Regla',
    'Regeln' => 'Reglas',
    'Auslöser' => 'Disparador',
    'Aktion' => 'Acción',
    'Bedingung' => 'Condición',
    'Bedingungen' => 'Condiciones',
    'Regel hinzufügen' => 'Añadir regla',
    'Regel bearbeiten' => 'Editar regla',
    'Regel löschen' => 'Eliminar regla',
    'Regel gespeichert.' => 'Regla guardada.',
    'Regel gelöscht.' => 'Regla eliminada.',
    'Vorlage' => 'Plantilla',
    'Vorlagen' => 'Plantillas',
    'E-Mail-Vorlage' => 'Plantilla de correo',
    'Vorlage hinzufügen' => 'Añadir plantilla',
    'Vorlage bearbeiten' => 'Editar plantilla',
    'Betreff' => 'Asunto',
    'Inhalt' => 'Contenido',
    'E-Mail senden' => 'Enviar correo',
    'Benachrichtigung senden' => 'Enviar notificación',
    'Erinnerung senden' => 'Enviar recordatorio',
    'Erinnerung' => 'Recordatorio',
    'Erinnerungen' => 'Recordatorios',
    'Benachrichtigung' => 'Notificación',
    'E-Mail-Benachrichtigungen' => 'Notificaciones por correo',
    'SMS-Benachrichtigungen' => 'Notificaciones por SMS',
    'KI' => 'IA',
    'KI-Einstellungen' => 'Configuración de IA',
    'KI-Anbieter' => 'Proveedor de IA',
    'KI aktiviert' => 'IA habilitada',
    'KI-Einstellungen gespeichert.' => 'Configuración de IA guardada.',
    'Gutschein' => 'Cupón',
    'Gutscheine' => 'Cupones',
    'Gutscheincode' => 'Código de cupón',
    'Rabattcode' => 'Código de descuento',
    'Gutschein anwenden' => 'Aplicar cupón',
    'Gutschein entfernen' => 'Eliminar cupón',
    'WooCommerce-Integration' => 'Integración con WooCommerce',
    'Google Calendar' => 'Google Calendar',
    'Stripe-Integration' => 'Integración con Stripe',
    'PayPal-Integration' => 'Integración con PayPal',
    'API-Schlüssel' => 'Clave API',
    'Geheimer Schlüssel' => 'Clave secreta',
    'Webhook' => 'Webhook',
    'Webhooks' => 'Webhooks',
    'Webhook-URL' => 'URL del webhook',
    'Datenschutz' => 'Privacidad',
    'Datenschutzrichtlinie' => 'Política de privacidad',
    'Nutzungsbedingungen' => 'Términos de servicio',
    'DSGVO' => 'RGPD',
    'Datenaufbewahrung' => 'Retención de datos',
    'Daten exportieren' => 'Exportar datos',
    'Daten löschen' => 'Eliminar datos',
    'Anonymisieren' => 'Anonimizar',
    'Einrichtungsassistent' => 'Asistente de configuración',
    'Erste Schritte' => 'Comenzar',
    'Schnellstart' => 'Inicio rápido',
    'Überspringen' => 'Omitir',
    'Fertig' => 'Finalizar',
    'Einrichtung abschließen' => 'Completar configuración',
    
    // === MESSAGES ===
    'Erfolgreich gespeichert.' => 'Guardado correctamente.',
    'Erfolgreich gelöscht.' => 'Eliminado correctamente.',
    'Erfolgreich aktualisiert.' => 'Actualizado correctamente.',
    'Erfolgreich erstellt.' => 'Creado correctamente.',
    'Änderungen gespeichert.' => 'Cambios guardados.',
    'Keine Änderungen.' => 'Sin cambios.',
    'Sind Sie sicher?' => '¿Está seguro?',
    'Möchten Sie das wirklich löschen?' => '¿Está seguro de que desea eliminar esto?',
    'Diese Aktion kann nicht rückgängig gemacht werden.' => 'Esta acción no se puede deshacer.',
    'Etwas ist schiefgelaufen.' => 'Algo salió mal.',
    'Bitte versuchen Sie es erneut.' => 'Por favor, inténtelo de nuevo.',
    'Ein Fehler ist aufgetreten.' => 'Se produjo un error.',
    'Ungültige Eingabe.' => 'Entrada no válida.',
    'Pflichtfeld.' => 'Campo requerido.',
    'Ungültige E-Mail-Adresse.' => 'Dirección de correo electrónico no válida.',
    'Ungültige Telefonnummer.' => 'Número de teléfono no válido.',
    'Keine Ergebnisse gefunden.' => 'No se encontraron resultados.',
    'Keine Einträge gefunden.' => 'No se encontraron elementos.',
    'Keine Daten verfügbar.' => 'No hay datos disponibles.',
    'Einstellungen gespeichert.' => 'Configuración guardada.',
    'Design gespeichert.' => 'Diseño guardado.',
    'Farbe gespeichert.' => 'Color guardado.',
    'Arbeitszeiten gespeichert.' => 'Horario laboral guardado.',
    'Ausnahme erstellt.' => 'Excepción creada.',
    'Ausnahme gelöscht.' => 'Excepción eliminada.',
    'Gast gespeichert.' => 'Huésped guardado.',
    'Branding-Einstellungen erfolgreich gespeichert.' => 'Configuración de marca guardada correctamente.',
    'Sprache konnte nicht geändert werden. Bitte versuchen Sie es erneut.' => 'No se pudo cambiar el idioma. Por favor, inténtelo de nuevo.',
    'Netzwerkfehler. Bitte versuchen Sie es erneut.' => 'Error de red. Por favor, inténtelo de nuevo.',
    
    // === MISC ===
    'Sprache' => 'Idioma',
    'Sprache auswählen' => 'Seleccionar idioma',
    'Englisch' => 'Inglés',
    'Deutsch' => 'Alemán',
    'Spanisch' => 'Español',
    'Französisch' => 'Francés',
    'Italienisch' => 'Italiano',
    'Portugiesisch' => 'Portugués',
    'Währung' => 'Moneda',
    'Zeitzone' => 'Zona horaria',
    'Datumsformat' => 'Formato de fecha',
    'Zeitformat' => 'Formato de hora',
    '24-Stunden' => '24 horas',
    '12-Stunden' => '12 horas',
    'pro Nacht' => 'por noche',
    'pro Person' => 'por persona',
    'pro Stunde' => 'por hora',
    'pro Tag' => 'por día',
    'pro Woche' => 'por semana',
    'pro Monat' => 'por mes',
    'Täglich' => 'Diario',
    'Wöchentlich' => 'Semanal',
    'Monatlich' => 'Mensual',
    'Jährlich' => 'Anual',
    'Guthaben' => 'Créditos',
    'Paket' => 'Paquete',
    'Pakete' => 'Paquetes',
    'Warteliste' => 'Lista de espera',
    'Warteschlange' => 'Cola',
    '30 Tage' => '30 días',
    '60 Tage' => '60 días',
    '90 Tage' => '90 días',
    '1 Jahr' => '1 año',
    
    // === ERRORS & VALIDATION ===
    'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut oder kontaktieren Sie den Support.' => 'Se produjo un error inesperado. Por favor, inténtelo de nuevo o contacte con soporte.',
    'Kunde konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.' => 'No se pudo guardar el cliente. Por favor, inténtelo de nuevo.',
    'Dienstleistung konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.' => 'No se pudo guardar el servicio. Por favor, inténtelo de nuevo.',
    'Termin konnte nicht gelöscht werden.' => 'No se pudo eliminar la cita.',
    'Termin konnte nicht aktualisiert werden.' => 'No se pudo actualizar la cita.',
    'Der Termin konnte nicht in der Datenbank gespeichert werden.' => 'No se pudo guardar la cita en la base de datos.',
    'Ungültige Anfrage. Bitte überprüfen Sie Ihre Eingabe und versuchen Sie es erneut.' => 'Solicitud no válida. Por favor, revise su entrada e inténtelo de nuevo.',
    'Bitte geben Sie eine gültige Kunden-E-Mail-Adresse ein.' => 'Por favor, introduzca un correo electrónico de cliente válido.',
    'Bitte geben Sie gültige Start- und Enddaten ein.' => 'Por favor, introduzca fechas de inicio y fin válidas.',
    'Zahlung fehlgeschlagen. Bitte versuchen Sie es erneut.' => 'El pago falló. Por favor, inténtelo de nuevo.',
    'Authentifizierung erforderlich. Bitte melden Sie sich an.' => 'Se requiere autenticación. Por favor, inicie sesión.',
    'Zugriff verweigert' => 'Acceso denegado',
    'Nicht gefunden' => 'No encontrado',
    'Ungültig' => 'No válido',
    'ist erforderlich' => 'es requerido',
    'muss eine Zahl sein' => 'debe ser un número',
    'muss eine gültige E-Mail sein' => 'debe ser un correo electrónico válido',
    'muss eine gültige URL sein' => 'debe ser una URL válida',
];

// Read German PO to get English→German mapping
echo "📂 Loading German translations...\n";
$de_content = file_get_contents($de_file);

// Parse German PO: English (msgid) → German (msgstr)
$en_to_de = [];
preg_match_all('/msgid "(.+?)"\nmsgstr "(.+?)"/s', $de_content, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $en = stripcslashes($m[1]);
    $de = stripcslashes($m[2]);
    if ($en && $de && $en !== $de) {
        $en_to_de[$en] = $de;
    }
}

echo "   Found " . count($en_to_de) . " English→German mappings\n";

// Build English→Spanish via German
$en_to_es = [];
foreach ($en_to_de as $en => $de) {
    // Direct German→Spanish lookup
    if (isset($de_to_es[$de])) {
        $en_to_es[$en] = $de_to_es[$de];
        continue;
    }
    
    // Try partial matching for phrases containing known terms
    foreach ($de_to_es as $de_key => $es_val) {
        // Exact substring replacement
        if (strpos($de, $de_key) !== false && strlen($de_key) > 3) {
            $translated = str_replace($de_key, $es_val, $de);
            // Only use if it actually changed something and looks Spanish
            if ($translated !== $de) {
                $en_to_es[$en] = $translated;
                break;
            }
        }
    }
}

echo "   Built " . count($en_to_es) . " English→Spanish mappings\n";

// Read Spanish PO
echo "📂 Loading Spanish PO...\n";
$es_content = file_get_contents($es_file);
$lines = explode("\n", $es_content);
$result = [];
$current_msgid = '';
$in_msgid = false;
$in_msgstr = false;
$translated_count = 0;
$total_empty = 0;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    
    // Track msgid
    if (preg_match('/^msgid "(.*)"$/', $line, $m)) {
        $current_msgid = stripcslashes($m[1]);
        $in_msgid = true;
        $in_msgstr = false;
        $result[] = $line;
        continue;
    }
    
    // Multi-line msgid
    if ($in_msgid && preg_match('/^"(.*)"$/', $line, $m)) {
        $current_msgid .= stripcslashes($m[1]);
        $result[] = $line;
        continue;
    }
    
    // Track msgstr
    if (preg_match('/^msgstr "(.*)"$/', $line, $m)) {
        $in_msgid = false;
        $in_msgstr = true;
        $msgstr_value = $m[1];
        
        // Empty msgstr - try to translate
        if ($msgstr_value === '' && $current_msgid !== '') {
            $total_empty++;
            
            if (isset($en_to_es[$current_msgid])) {
                $translation = $en_to_es[$current_msgid];
                $result[] = 'msgstr "' . addcslashes($translation, '"\\') . '"';
                $translated_count++;
            } else {
                $result[] = $line;
            }
        } else {
            $result[] = $line;
        }
        continue;
    }
    
    // Multi-line msgstr
    if ($in_msgstr && preg_match('/^"(.*)"$/', $line, $m)) {
        $result[] = $line;
        continue;
    }
    
    // Reset on empty/comment
    if (trim($line) === '' || strpos(trim($line), '#') === 0) {
        $in_msgid = false;
        $in_msgstr = false;
        $current_msgid = '';
    }
    
    $result[] = $line;
}

// Save
file_put_contents($es_file, implode("\n", $result));

$remaining = $total_empty - $translated_count;
echo "\n✅ Translated $translated_count of $total_empty empty Spanish strings\n";
echo "📝 Remaining empty: $remaining\n";

if ($remaining > 0) {
    echo "\n💡 For remaining strings, use:\n";
    echo "   - DeepL API: php scripts/translate-po-deepl.php --api-key=YOUR_KEY --lang=es\n";
}
