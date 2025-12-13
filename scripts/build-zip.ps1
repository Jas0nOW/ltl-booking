# LazyBookings Build Script (PowerShell)
param(
    [string]$RootDir = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = "Stop"

$PluginSlug = "ltl-bookings"
$VersionFile = Join-Path $RootDir "ltl-booking.php"
$DistDir = Join-Path $RootDir "dist"

# Extract version from plugin file
$VersionMatch = Select-String -Path $VersionFile -Pattern "define\(\s*'LTLB_VERSION'\s*,\s*'([^']+)'\s*\)"
if (-not $VersionMatch) {
    throw "Could not find LTLB_VERSION in $VersionFile"
}
$Version = $VersionMatch.Matches[0].Groups[1].Value
$ZipName = "$PluginSlug-$Version.zip"
$ZipPath = Join-Path $DistDir $ZipName

Write-Host "Building LazyBookings v$Version..." -ForegroundColor Cyan

# Create dist directory
New-Item -ItemType Directory -Force -Path $DistDir | Out-Null

# Create temp directory
$TempDir = Join-Path $env:TEMP "ltlb-build-$(Get-Random)"
$TempPluginDir = Join-Path $TempDir $PluginSlug
New-Item -ItemType Directory -Force -Path $TempPluginDir | Out-Null

Write-Host "Copying files to temp directory..." -ForegroundColor Yellow

# Exclusion patterns
$Exclude = @(
    '.git',
    '.github',
    'node_modules',
    'vendor',
    '.env',
    '*.log',
    'dist'
)

# Copy files with exclusions
Get-ChildItem -Path $RootDir -Recurse | ForEach-Object {
    $relativePath = $_.FullName.Substring($RootDir.Length + 1)
    
    # Check if path matches any exclusion pattern
    $shouldExclude = $false
    foreach ($pattern in $Exclude) {
        if ($relativePath -like "*$pattern*") {
            $shouldExclude = $true
            break
        }
    }
    
    if (-not $shouldExclude) {
        $destPath = Join-Path $TempPluginDir $relativePath
        
        if ($_.PSIsContainer) {
            New-Item -ItemType Directory -Force -Path $destPath | Out-Null
        } else {
            $destDir = Split-Path -Parent $destPath
            if (-not (Test-Path $destDir)) {
                New-Item -ItemType Directory -Force -Path $destDir | Out-Null
            }
            Copy-Item -Path $_.FullName -Destination $destPath -Force
        }
    }
}

Write-Host "Creating ZIP archive..." -ForegroundColor Yellow

# Remove old ZIP if exists
if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

# Create ZIP using .NET (works on all PowerShell versions)
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($TempDir, $ZipPath, 'Optimal', $false)

Write-Host "Generating SHA256 checksum..." -ForegroundColor Yellow

# Generate SHA256 checksum
$Hash = Get-FileHash -Path $ZipPath -Algorithm SHA256
$ChecksumFile = Join-Path $DistDir "SHA256SUMS.txt"
"$($Hash.Hash.ToLower())  $ZipName" | Out-File -FilePath $ChecksumFile -Encoding ASCII

# Cleanup temp directory
Remove-Item -Path $TempDir -Recurse -Force

Write-Host "`nBuild complete!" -ForegroundColor Green
Write-Host "  ZIP: $ZipPath" -ForegroundColor White
Write-Host "  Checksum: $ChecksumFile" -ForegroundColor White
Write-Host "  Version: $Version" -ForegroundColor White

$ZipSize = (Get-Item $ZipPath).Length / 1KB
Write-Host "  Size: $([math]::Round($ZipSize, 2)) KB" -ForegroundColor White
