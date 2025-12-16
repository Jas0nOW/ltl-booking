# Update translation files for ltl-bookings plugin
# Requires: Local by Flywheel site running

$ErrorActionPreference = "Stop"

$pluginDir = Split-Path -Parent $PSScriptRoot
$languagesDir = Join-Path $pluginDir "languages"
$potFile = Join-Path $languagesDir "ltl-bookings.pot"
$poFile = Join-Path $languagesDir "de_DE.po"
$moFile = Join-Path $languagesDir "de_DE.mo"

Write-Host "=== LTL Bookings Translation Update ===" -ForegroundColor Cyan
Write-Host "Plugin directory: $pluginDir" -ForegroundColor Gray
Write-Host "Languages directory: $languagesDir" -ForegroundColor Gray

# Optional check (disabled for flexibility)
# Can run from any environment

# Extract strings using basic regex patterns (simplified POT generation)
Write-Host "`n[1/4] Scanning PHP files for translatable strings..." -ForegroundColor Yellow

function ConvertTo-PoString {
    param(
        [Parameter(Mandatory = $true)]
        [AllowEmptyString()]
        [string]$Value
    )

    # Escape for gettext PO/POT format
    # - backslash -> double backslash
    # - double quote -> \"
    $escaped = $Value.Replace([string][char]92, ([string][char]92 + [string][char]92))
    $escaped = $escaped.Replace('"', ([string][char]92 + '"'))
    return $escaped
}

function ConvertFrom-PoString {
    param(
        [Parameter(Mandatory = $true)]
        [AllowEmptyString()]
        [string]$Value
    )

    $backslash = [string][char]92
    $unescaped = $Value
    $unescaped = $unescaped.Replace(($backslash + '"'), '"')
    $unescaped = $unescaped.Replace(($backslash + $backslash), $backslash)
    return $unescaped
}

$strings = @()
$files = Get-ChildItem -Path $pluginDir -Recurse -Include *.php |
    Where-Object { $_.FullName -notmatch 'vendor' -and $_.FullName -notmatch 'node_modules' }

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $relativePath = $file.FullName.Substring($pluginDir.Length + 1).Replace([char]92, [char]47)
    
    # Match translation function calls (single-quoted strings)
    $patterns = @(
        '__[(]\s*''([^'']+)''\s*,\s*''ltl-bookings''\s*[)]',
        'esc_html__[(]\s*''([^'']+)''\s*,\s*''ltl-bookings''\s*[)]',
        'esc_attr__[(]\s*''([^'']+)''\s*,\s*''ltl-bookings''\s*[)]',
        '_e[(]\s*''([^'']+)''\s*,\s*''ltl-bookings''\s*[)]',
        '_x[(]\s*''([^'']+)''\s*,\s*''[^'']*''\s*,\s*''ltl-bookings''\s*[)]'
    )
    
    foreach ($pattern in $patterns) {
        $regexMatches = [System.Text.RegularExpressions.Regex]::Matches($content, $pattern)
        foreach ($match in $regexMatches) {
            if ($match.Groups.Count -gt 1) {
                $str = $match.Groups[1].Value
                if ($str -ne '') {
                    $strings += [PSCustomObject]@{
                        String = $str
                        File = $relativePath
                    }
                }
            }
        }
    }
}

$uniqueStrings = $strings | Sort-Object -Property String -Unique
Write-Host "Found $($uniqueStrings.Count) unique translatable strings" -ForegroundColor Green

# Generate POT file
Write-Host "`n[2/4] Generating POT file..." -ForegroundColor Yellow

$year = Get-Date -Format 'yyyy'
$potCreationDate = Get-Date -Format 'yyyy-MM-dd HH:mm+0000'

$potHeaderLines = @(
    ('# Copyright (C) {0} LazyBookings' -f $year),
    '# This file is distributed under the same license as the LazyBookings plugin.',
    'msgid ""',
    'msgstr ""',
    '"Project-Id-Version: LazyBookings 1.0.1\n"',
    '"Report-Msgid-Bugs-To: \n"',
    ('"POT-Creation-Date: {0}\n"' -f $potCreationDate),
    '"MIME-Version: 1.0\n"',
    '"Content-Type: text/plain; charset=UTF-8\n"',
    '"Content-Transfer-Encoding: 8bit\n"',
    '"Plural-Forms: nplurals=2; plural=(n != 1);\n"',
    '"Language: en\n"',
    ''
)

$potHeader = ($potHeaderLines -join "`n") + "`n"

$potContent = $potHeader

foreach ($entry in $uniqueStrings) {
    $potContent += "`n#: $($entry.File)`n"
    $escaped = ConvertTo-PoString -Value $entry.String
    $potContent += ('msgid "{0}"' -f $escaped) + "`n"
    $potContent += 'msgstr ""' + "`n"
}

Set-Content -Path $potFile -Value $potContent -Encoding UTF8
Write-Host "POT file created: $potFile" -ForegroundColor Green

# Merge with existing PO file
Write-Host "`n[3/4] Merging with existing German translations..." -ForegroundColor Yellow

if (Test-Path $poFile) {
    # Read existing translations
    $existingLines = Get-Content $poFile -Encoding UTF8
    $translations = @{}

    for ($i = 0; $i -lt $existingLines.Count; $i++) {
        $line = $existingLines[$i]
        $msgidMatch = [regex]::Match($line, '^msgid\s+"(.*)"\s*$')
        if (-not $msgidMatch.Success) {
            continue
        }

        $msgid = ConvertFrom-PoString -Value $msgidMatch.Groups[1].Value

        # msgid can span multiple quoted lines after msgid ""
        $i++
        while ($i -lt $existingLines.Count) {
            $contMatch = [regex]::Match($existingLines[$i], '^"(.*)"\s*$')
            if (-not $contMatch.Success) { break }
            $msgid += ConvertFrom-PoString -Value $contMatch.Groups[1].Value
            $i++
        }

        if ($i -ge $existingLines.Count) {
            $i--
            continue
        }
        $msgstrMatch = [regex]::Match($existingLines[$i], '^msgstr\s+"(.*)"\s*$')
        if (-not $msgstrMatch.Success) {
            $i--
            continue
        }

        $msgstrValue = ConvertFrom-PoString -Value $msgstrMatch.Groups[1].Value

        # msgstr can span multiple quoted lines after msgstr ""
        $i++
        while ($i -lt $existingLines.Count) {
            $contMatch2 = [regex]::Match($existingLines[$i], '^"(.*)"\s*$')
            if (-not $contMatch2.Success) { break }
            $msgstrValue += ConvertFrom-PoString -Value $contMatch2.Groups[1].Value
            $i++
        }

        if ($msgid -ne '' -and $msgstrValue -ne '') {
            $translations[$msgid] = $msgstrValue
        }

        $i--
    }
    
    Write-Host "Loaded $($translations.Count) existing translations" -ForegroundColor Green
    
    # Create updated PO file
    $poHeaderLines = @(
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: LazyBookings 1.0.1\n"',
        '"Language: de_DE\n"',
        '"MIME-Version: 1.0\n"',
        '"Content-Type: text/plain; charset=UTF-8\n"',
        '"Content-Transfer-Encoding: 8bit\n"',
        '"Plural-Forms: nplurals=2; plural=(n != 1);\n"',
        ''
    )
    $poHeader = ($poHeaderLines -join "`n") + "`n"
    
    $poContent = $poHeader
    $newCount = 0
    
    foreach ($entry in $uniqueStrings) {
        $poContent += "`n#: $($entry.File)`n"
        $escaped = ConvertTo-PoString -Value $entry.String
        $poContent += ('msgid "{0}"' -f $escaped) + "`n"
        
        if ($translations.ContainsKey($entry.String)) {
            $poContent += ('msgstr "{0}"' -f (ConvertTo-PoString -Value $translations[$entry.String])) + "`n"
        } else {
            $poContent += 'msgstr ""' + "`n"
            $newCount++
        }
    }
    
    Set-Content -Path $poFile -Value $poContent -Encoding UTF8
    Write-Host "PO file updated: $poFile" -ForegroundColor Green
    Write-Host "  - $newCount new untranslated strings added" -ForegroundColor Yellow
} else {
    Write-Host "No existing PO file found. Creating new one..." -ForegroundColor Yellow
    Copy-Item $potFile $poFile
    Write-Host "PO file created: $poFile" -ForegroundColor Green
}

# Compile MO file (basic, no msgfmt available - note for manual step)
Write-Host "`n[4/4] Note: MO compilation requires msgfmt tool" -ForegroundColor Yellow
Write-Host "After translating, compile with: msgfmt -o $moFile $poFile" -ForegroundColor Gray
Write-Host "Or use Poedit to auto-compile when saving." -ForegroundColor Gray

Write-Host "`n=== Translation update complete ===" -ForegroundColor Cyan
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Open $poFile in Poedit or text editor" -ForegroundColor Gray
Write-Host "2. Translate empty msgstr entries" -ForegroundColor Gray
Write-Host "3. Save and compile to .mo file" -ForegroundColor Gray
Write-Host "4. Test in WordPress admin (Settings > General > Site Language: German)" -ForegroundColor Gray
