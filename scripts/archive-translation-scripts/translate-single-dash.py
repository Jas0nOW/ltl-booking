# Python Script: Find and translate the single dash string
import os

script_dir = os.path.dirname(os.path.abspath(__file__))
po_file = os.path.join(os.path.dirname(script_dir), 'languages', 'de_DE.po')

print(f"Reading {po_file}...")
with open(po_file, 'r', encoding='utf-8-sig') as f:
    lines = f.readlines()

count = 0
found_dash = False

# Find the single dash between lines 50-55
for i in range(50, 55):
    if i < len(lines):
        current_line = lines[i]
        prev_line = lines[i-1] if i > 0 else ''
        
        # Check if this is msgstr "" and previous line is msgid "â€""
        if current_line.strip() == 'msgstr ""' and 'msgid' in prev_line and 'â€"' in prev_line:
            # Check it's not the "Select" one
            if 'Select' not in prev_line:
                print(f"Found single dash at line {i+1}")
                print(f"  Previous: {prev_line.strip()}")
                print(f"  Current: {current_line.strip()}")
                lines[i] = 'msgstr "–"\n'
                count += 1
                found_dash = True
                break

if found_dash:
    # Write back
    print(f"\nWriting changes...")
    with open(po_file, 'w', encoding='utf-8-sig') as f:
        f.writelines(lines)
    print(f"✓ Translated single dash")
else:
    print("Dash already translated or not found")

print(f"\n===== DONE =====")
