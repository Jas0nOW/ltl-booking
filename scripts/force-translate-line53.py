# Python Script: Force translate line 53 (the single dash)
import os

script_dir = os.path.dirname(os.path.abspath(__file__))
po_file = os.path.join(os.path.dirname(script_dir), 'languages', 'de_DE.po')

print(f"Reading {po_file}...")
with open(po_file, 'r', encoding='utf-8-sig') as f:
    lines = f.readlines()

# Line 53 is index 52 (0-based)
if len(lines) > 52:
    print(f"Line 52 (index 51): {lines[51].strip()}")
    print(f"Line 53 (index 52): {lines[52].strip()}")
    print(f"Line 54 (index 53): {lines[53].strip()}")
    
    # Check if line 53 is msgstr ""
    if lines[52].strip() == 'msgstr ""':
        lines[52] = 'msgstr "–"\n'
        print(f"\n✓ Changed line 53 to: msgstr \"–\"")
        
        # Write back
        with open(po_file, 'w', encoding='utf-8-sig') as f:
            f.writelines(lines)
        print("✓ File saved")
    else:
        print(f"\nLine 53 is already: {lines[52].strip()}")
else:
    print("File too short")

print("\n===== DONE =====")
