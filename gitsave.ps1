# gitsave.ps1 — satu command: add semua perubahan, commit, lalu push ke origin/main
# Pakai:
#   .\gitsave.ps1                  -> pesan commit otomatis (timestamp)
#   .\gitsave.ps1 "pesan custom"   -> pesan commit custom

param(
    [string]$Message
)

git add -A

git diff --cached --quiet
$adaPerubahan = ($LASTEXITCODE -ne 0)

if (-not $adaPerubahan) {
    Write-Host "Tidak ada perubahan untuk di-commit." -ForegroundColor Yellow
} else {
    if (-not $Message) {
        $Message = "Update $(Get-Date -Format 'yyyy-MM-dd HH:mm')"
    }
    git commit -m $Message
}

git push origin main
