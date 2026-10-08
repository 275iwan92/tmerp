# Jalankan uji terhadap database DUMMY (ddt*), bukan tm2* (keputusan user 2026-10-08, rencana Inventory & Warehouse v2.01, risiko R-1).
# Cara: variabel DB_* dari .env.mockup dipasang sebagai variabel proses; Laravel (phpdotenv) tidak menimpa variabel proses,
# jadi koneksi tm2inv, tm2sys, dst. menunjuk ke ddtinv, ddtsys, dst. selama proses ini berjalan.
# Pakai: powershell -File tools/test-ddt.ps1 tests/Feature/StockReportsTest.php [--filter=nama]
# Migrasi ke ddt: php artisan migrate --env=mockup (artisan memuat .env.mockup sendiri; uji memakai skrip ini).
param([Parameter(ValueFromRemainingArguments = $true)][string[]]$TestArgs)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$file = Join-Path $root '.env.mockup'
if (-not (Test-Path $file)) { throw ".env.mockup tidak ditemukan di $root" }
$count = 0
foreach ($line in Get-Content $file) {
    if ($line -match '^\s*(DB_[A-Z0-9_]+)\s*=\s*(.*)$') {
        $value = $Matches[2].Trim().Trim('"').Trim("'")
        Set-Item -Path ("Env:" + $Matches[1]) -Value $value
        $count++
    }
}
Write-Host "Uji dijalankan terhadap database dummy: $count variabel DB_* dari .env.mockup. DB_INV=$($env:DB_INV)"
Set-Location $root
& php artisan test @TestArgs
exit $LASTEXITCODE
