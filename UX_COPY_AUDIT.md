# UX/Design/Polish + Copy Audit Report

## P0 (Critical)

- [x] [P0] Sprach-Mix in Fehlermeldungen — Includes/Util/BookingService.php: create_service_booking()

Fix: Aktuell sind einige Fehlermeldungen hardcodiert auf Deutsch (z.B. “Ungültiges Datum/Uhrzeit.”) obwohl die Basissprache Englisch sein soll. Alle Basis-Strings ins Englische übersetzen und per Übersetzung ins Deutsche übertragen.

Done: Basis-Strings in Includes/Util/BookingService.php auf Englisch umgestellt (weiterhin per __() übersetzbar).

Check: Seite auf Englisch nutzen und absichtlich Fehler provozieren (z.B. vergangenes Datum wählen) – die Fehlermeldungen sollen auf Englisch erscheinen, nicht auf Deutsch.

## P1 (High)

- [x] [P1] Inkonsistente Anrede (Du/Sie) — I18n.php: German dictionary & UI-Texte

Fix: In deutschen UI-Texten wird teils die Du-Form verwendet („Bist du sicher?“ in Bist du sicher?). Alle Texte auf ein einheitliches, professionelles „Sie“ umstellen. Beispiel: “Sind Sie sicher?” statt “Bist du sicher?”.

Done: Du-Form im Admin-Wörterbuch bereinigt (Includes/Util/I18n.php), inkl. konsistenter Sie-Anrede und klarerer Formulierungen.

Check: Admin-Oberfläche auf Deutsch umstellen (admin_post_ltlb_set_admin_lang) – prüfen, dass Meldungen, Bestätigungsdialoge etc. konsistent die Höflichkeitsform verwenden.

- [x] [P1] Unübersetzte Frontend-Texte — Frontend-Buchungsformular & Fehlermeldungen

Fix: Sämtliche im Frontend sichtbaren Strings durch Übersetzungsfunktionen schicken. Z.B. die Placeholder und Fehlermeldungen im Buchungsformular (public/js/public.js und PHP-Ausgaben) übersetzbar machen.

Done: Frontend-JS nutzt `ltlbI18n` (wp_localize_script) statt hardcodierter Strings; Admin-Sprache beeinflusst Frontend nicht (public/Shortcodes.php, assets/js/public.js).

Check: Das Shortcode-Formular [lazy_book] sowohl in Englisch als auch Deutsch testen – es dürfen keine fest verdrahteten deutschen Texte erscheinen, und die Labels sollten je nach Sprache wechseln.

## P2 (Medium)

- [x] [P2] Begriffs-Konsistenz — Allgemein (UI und Übersetzungen)

Fix: Einheitliche Terminologie verwenden. Z.B. wird “Appointments” mal mit “Termine”, mal mit “Buchungen” übersetzt. Im deutschen Kontext ggf. durchgängig „Termin(e)“ für Appointments und „Buchung(en)“ für Hotel-Bookings verwenden. Das Wörterbuch (get_de_dictionary) entsprechend anpassen.

Done: Wörterbuch auf konsistente Begriffe geprüft; außerdem doppelte/uneindeutige Dashboard-Übersetzung bereinigt (Includes/Util/I18n.php).

Check: In der deutschen Oberfläche überprüfen, dass Menüeinträge, Überschriften und Buttons konsistente Begriffe nutzen (z.B. nicht gleichzeitig “Termine” und “Buchungen” für dasselbe Konzept, abhängig vom Modus).

 - [x] [P2] Klarheit von Systemmeldungen — verschiedene Stellen (Notices & Alerts)

Fix: Einige Meldungen sind technisch/unpräzise (“No permission”, “Could not create outbox draft.”). Diese Nutzermeldungen verständlicher formulieren, z.B. “Sie haben keine Berechtigung.” oder “Konnte Entwurf nicht erstellen.” ohne Entwicklerjargon.

Done: Fehlende/unklare Meldungen im Admin-Wörterbuch ergänzt/verbessert (z.B. Outbox-Entwurf, Reload-Hinweis, Cleanup/Bereinigung).

Check: Aktionen im Admin ausführen, die Fehler/Erfolgsmeldungen auslösen (z.B. Outbox-Action ohne Berechtigung) – prüfen, ob die Texte benutzerfreundlich und auf Deutsch übersetzt sind.

 - [x] [P2] Übersetzungsabdeckung erhöhen — Templates und JS

Fix: Sicherstellen, dass sämtliche UI-Texte in PHP (auch in Templates, Komponenten, E-Mails) und JS (via wp_localize_script oder window.ltlbI18n) lokalisiert sind. Momentan fehlen z.B. Übersetzungen für “Smart Room Assistant”, “Draft Center” etc. im deutschen Wörterbuch.

Done: Fehlende Übersetzungen für AI/Outbox/Automations ergänzt (Includes/Util/I18n.php) und Frontend-JS lokalisiert (assets/js/public.js).

Check: Mit deutscher Sprache durch alle Admin-Seiten klicken – alle sichtbaren Texte (Menüs, Überschriften, Buttons, Platzhalter, Meldungen) sollten übersetzt sein. Fehlende Übersetzungen identifizieren und ergänzen.

## P3 (Low)

- [x] [P3] Typos und Groß-/Kleinschreibung — deutsche Texte (Wörterbuch)

Fix: Rechtschreibung und formale Einheitlichkeit prüfen. Beispiele: “Diagnose” vs “Diagnostik” – ggf. passender übersetzen, “No staff members found.” wurde mit “Noch keine Mitarbeitenden vorhanden.” übersetzt (okay, gendergerecht), aber dann konsequent solche Formen nutzen. Auch “Unzureichende Berechtigungen” sollte groß am Satzanfang stehen.

Done: Kleiner Korrektur-Pass im Wörterbuch (z.B. "Diagnostics" → "Diagnostik") und formale Sie-Anrede.

Check: Die wichtigsten UI-Texte in Deutsch auf korrekte Schreibweise prüfen (Umlaute, Großschreibung von Nomen, kein Denglisch). Z.B. im Menü, in Button-Beschriftungen und Hinweisen – sicherstellen, dass keine Tippfehler oder falsche Großschreibung auftauchen.

 - [x] [P3] Übersetzungsdateien bereitstellen — languages/ (keine Dateien)

Fix: Für die Ankündigung “German ready” sollten .po/.mo-Dateien beigelegt sein. Einen Export der im Code verwendeten Strings als ltl-bookings.pot erstellen und eine deutsche de_DE.po/.mo hinzufügen, damit Community-Übersetzer nicht auf das Inline-Wörterbuch angewiesen sind.

Done: languages/ltl-bookings.pot + languages/de_DE.po hinzugefügt und load_plugin_textdomain aktiviert (ltl-booking.php).

Check: Nach Deployment sicherstellen, dass WordPress die mitgelieferten Übersetzungsdateien erkennt (z.B. via load_plugin_textdomain in der Plugin-Init) und deutsche Texte entweder via .mo oder dem vorhandenen Dictionary ausgegeben werden.
