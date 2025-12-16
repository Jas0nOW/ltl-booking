# Python Script: Translate final 11 strings in .po file
import os
import re

script_dir = os.path.dirname(os.path.abspath(__file__))
po_file = os.path.join(os.path.dirname(script_dir), 'languages', 'de_DE.po')

print(f"Reading {po_file} with UTF-8 encoding...")
with open(po_file, 'r', encoding='utf-8-sig') as f:
    content = f.read()

count = 0
original = content

# Dictionary of translations (English pattern -> German translation)
translations = {
    r'(#: admin/Pages/ReplyTemplatesPage\.php\s+msgid "\{service_name\} .+ booking confirmation"\s+)msgstr ""':
        r'\1msgstr "{service_name} – Buchungsbestätigung"',
    
    r'(#: admin/Pages/AppointmentsPage\.php\s+msgid ".+"\s+)msgstr ""\s+\n\s+#: admin/Pages/ServicesPage\.php':
        r'\1msgstr "–"\n\n#: admin/Pages/ServicesPage.php',
    
    r'(#: admin/Pages/ServicesPage\.php\s+msgid ".+ Select .+"\s+)msgstr ""':
        r'\1msgstr "– Auswählen –"',
    
    r'(#: admin/Pages/SettingsPage\.php\s+msgid "AI settings \(provider, model, business context, and operating mode\) are managed under .+AI & Automations.+"\s+)msgstr ""':
        r'\1msgstr "KI-Einstellungen (Anbieter, Modell, Geschäftskontext und Betriebsmodus) werden unter „KI & Automatisierungen" verwaltet."',
    
    r'(#: admin/Pages/DiagnosticsPage\.php\s+msgid ".+ DB version matches plugin version\."\s+)msgstr ""':
        r'\1msgstr "✓ DB-Version entspricht Plugin-Version."',
    
    r'(#: admin/Pages/DiagnosticsPage\.php\s+msgid ".+ Present"\s+)msgstr ""':
        r'\1msgstr "✓ Vorhanden"',
    
    r'(#: admin/Pages/DiagnosticsPage\.php\s+msgid ".+ DB version is behind plugin version\."\s+)msgstr ""':
        r'\1msgstr "⚠ DB-Version liegt hinter Plugin-Version."',
    
    r'(#: admin/Pages/HotelDashboardPage\.php\s+msgid "Deterministic: gross profit = revenue .+ fees .+ room costs \(from assigned rooms .+ nights\)\."\s+)msgstr ""':
        r'\1msgstr "Deterministisch: Bruttogewinn = Umsatz − Gebühren − Zimmerkosten (aus zugewiesenen Zimmern × Nächte)."',
    
    r'(#: admin/Pages/AutomationsPage\.php\s+msgid "No rules yet\. Click .+Add Default Rules.+ to get started\."\s+)msgstr ""':
        r'\1msgstr "Noch keine Regeln. Klicken Sie auf „Standardregeln hinzufügen", um zu beginnen."',
    
    r'(#: public/Shortcodes\.php\s+msgid "Optimized for phone, tablet, and desktop .+ without breaking your theme\."\s+)msgstr ""':
        r'\1msgstr "Optimiert für Handy, Tablet und Desktop – ohne Ihr Theme zu beeinträchtigen."',
    
    r'(#: admin/Pages/DiagnosticsPage\.php\s+msgid "Supported .+"\s+)msgstr ""':
        r'\1msgstr "Unterstützt ✓"',
}

# Apply each translation
for pattern, replacement in translations.items():
    new_content = re.sub(pattern, replacement, content, flags=re.DOTALL)
    if new_content != content:
        count += 1
        print(f"{count}. Applied translation")
        content = new_content

# Write back with UTF-8 BOM
print(f"\nWriting changes back to file...")
with open(po_file, 'w', encoding='utf-8-sig') as f:
    f.write(content)

print(f"\n===== FINAL TRANSLATION COMPLETE =====")
print(f"Successfully translated: {count} strings")
print(f"========================================")
