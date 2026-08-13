# run-claude.ps1 — pemicu Claude Code otonom (Windows 11)
# Taruh di root repo tmerp. Dipanggil Task Scheduler tiap 30 menit.

$ErrorActionPreference = "Stop"
$RepoPath = "C:\Users\iwanp\Herd\tmerp"          # <-- GANTI ke path repo asli
Set-Location $RepoPath

# --- Lock: cegah 2 sesi bertumpuk ---
$lock = Join-Path $RepoPath ".claude-run.lock"
if (Test-Path $lock) {
  # Lock basi > 3 jam dianggap sesi mati → hapus
  if ((Get-Date) - (Get-Item $lock).LastWriteTime -gt (New-TimeSpan -Hours 3)) {
    Remove-Item $lock -Force
  } else {
    exit 0   # sesi lain masih jalan, keluar diam-diam
  }
}
New-Item $lock -ItemType File -Force | Out-Null

# --- Log per sesi ---
$logDir = Join-Path $RepoPath "logs"
New-Item $logDir -ItemType Directory -Force | Out-Null
$log = Join-Path $logDir ("claude-{0}.log" -f (Get-Date -Format "yyyyMMdd-HHmm"))

$prompt = "Baca tasks.md dan lanjutkan backlog sesuai PROTOKOL KERJA dan aturan HEMAT TOKEN. Baca selektif hanya file yang disebut task berjalan. Kerjakan sampai token habis, commit lokal tiap task, push sekali di akhir, lalu tulis LAST SESSION dan berhenti bersih. Jika 3 task berturut blocked, STOP."

try {
  # -p = headless; skip-permissions krn kewenangan penuh & semua di Git (bisa di-revert)
  claude -p $prompt --dangerously-skip-permissions *>> $log
  "[$(Get-Date -Format s)] sesi selesai (exit=$LASTEXITCODE)" | Out-File $log -Append
}
catch {
  "[$(Get-Date -Format s)] ERROR: $_" | Out-File $log -Append
}
finally {
  Remove-Item $lock -Force -ErrorAction SilentlyContinue
}
