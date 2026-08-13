# register-task.ps1 — daftarkan scheduler Windows 11 (interval 30 menit)
# Jalankan SEKALI sebagai Administrator (klik kanan PowerShell > Run as administrator).

$RepoPath = "C:\Users\iwanp\Herd\tmerp"          # <-- GANTI sama dgn run-claude.ps1
$script   = Join-Path $RepoPath "run-claude.ps1"

$action = New-ScheduledTaskAction `
  -Execute "powershell.exe" `
  -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$script`""

# Trigger: mulai sekarang, ulang tiap 30 menit, tanpa batas
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
  -RepetitionInterval (New-TimeSpan -Minutes 30)

# Jalan walau pakai baterai; jangan berhenti jika beralih ke baterai
$settings = New-ScheduledTaskSettingsSet `
  -AllowStartIfOnBatteries `
  -DontStopIfGoingOnBatteries `
  -StartWhenAvailable `
  -ExecutionTimeLimit (New-TimeSpan -Hours 3)

Register-ScheduledTask `
  -TaskName "TMERP-Claude" `
  -Action $action -Trigger $trigger -Settings $settings `
  -Description "Pemicu Claude Code otonom tiap 30 menit (baca tasks.md)" `
  -RunLevel Highest `
  -Force

Write-Host "OK. Task 'TMERP-Claude' terdaftar (tiap 30 menit)."
Write-Host "Uji manual: Start-ScheduledTask -TaskName TMERP-Claude ; lalu cek folder logs\"
Write-Host "Hapus     : Unregister-ScheduledTask -TaskName TMERP-Claude -Confirm:`$false"
