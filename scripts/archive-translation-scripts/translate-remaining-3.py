# Python Script: Translate remaining 3 strings by exact line replacement
import os

script_dir = os.path.dirname(os.path.abspath(__file__))
po_file = os.path.join(os.path.dirname(script_dir), 'languages', 'de_DE.po')

print(f"Reading {po_file}...")
with open(po_file, 'r', encoding='utf-8-sig') as f:
    lines = f.readlines()

count = 0

# Find and replace line by line
for i in range(len(lines)):
    # String 1: â€" (dash) at line 52
    if i == 52 and lines[i].strip() == 'msgstr ""' and 'â€"' in lines[i-1]:
        lines[i] = 'msgstr "–"\n'
        count += 1
        print(f"{count}. Translated dash at line {i+1}")
    
    # String 2: â€" Select â€" at line 56
    if i == 56 and lines[i].strip() == 'msgstr ""' and 'Select' in lines[i-1]:
        lines[i] = 'msgstr "– Auswählen –"\n'
        count += 1
        print(f"{count}. Translated 'Select' at line {i+1}")
    
    # String 3: AI settings at line 228
    if i == 228 and lines[i].strip() == 'msgstr ""' and 'AI settings' in lines[i-1]:
        lines[i] = 'msgstr "KI-Einstellungen (Anbieter, Modell, Geschäftskontext und Betriebsmodus) werden unter „KI & Automatisierungen" verwaltet."\n'
        count += 1
        print(f"{count}. Translated 'AI settings' at line {i+1}")

# Write back
print(f"\nWriting changes...")
with open(po_file, 'w', encoding='utf-8-sig') as f:
    f.writelines(lines)

print(f"\n===== TRANSLATION COMPLETE =====")
print(f"Translated: {count} strings")
print(f"=================================")
