# REMARK
# Tujuan       : Menyelaraskan server TM188 (server-dev, 103.79.247.146:222) dengan laptop, SATU ARAH laptop -> server (keputusan Jo 2026-10-24):
#                -Code : kode tmerp (untuk tmerp dan mockup, satu kode) dan devlib; sstock TIDAK ikut. Hanya berkas yang dilacak git; .env*, storage, vendor tidak disentuh.
#                -Db   : database tm2* (tmerp) dan ddt* (mockup) DITIMPA dari laptop; tm2old dan tm2crp tidak. Cadangan database server dibuat dulu.
# Prasyarat    : alias `ssh tm188` (C:\Users\iwanp\.ssh\config), setup sekali di server (`sudo bash ~/tm188-setup.sh`: kepemilikan kode dan akun MySQL claude_deploy di ~/.my.cnf).
# Pemakaian    : powershell -File scripts\sync-to-tm188.ps1 -Code -Db        (tambah -DryRun untuk hanya melihat rencana)
# Keluaran     : ringkasan per langkah; kata sandi tidak pernah dicetak (MySQL lokal dibaca dari .env di dalam proses).
# Status       : Aktif.
param([switch]$Code, [switch]$Db, [switch]$DryRun)
$ErrorActionPreference = 'Stop'
if (-not ($Code -or $Db)) { Write-Host 'Pilih -Code dan/atau -Db'; exit 1 }
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$work = "C:\Users\iwanp\Backups\TM188-sync\run-$stamp"; New-Item -ItemType Directory -Force $work | Out-Null
$repos = @(@{ Name = 'tmerp'; Local = 'C:\Users\iwanp\Herd\tmerp'; Remote = '/var/www/tmerp' }, @{ Name = 'devlib'; Local = 'C:\Users\iwanp\Herd\devlib'; Remote = '/var/www/devlib' })

function Tm188([string]$cmd) { $ErrorActionPreference = 'Continue'; $o = ssh -o BatchMode=yes tm188 $cmd 2>&1; if ($LASTEXITCODE -ne 0) { throw "ssh gagal: $cmd`n$o" }; $o }

Write-Host '== cek koneksi =='; Tm188 'hostname; id -un' | ForEach-Object { Write-Host "  $_" }
$perm = "$(Tm188 'test -w /var/www/tmerp && test -w /var/www/devlib && echo ok || echo TIDAK')"
if ($Code -and $perm -notmatch 'ok') { throw 'svradm belum boleh menulis /var/www/*: Jo perlu menjalankan: sudo bash ~/tm188-setup.sh' }

if ($Code) {
    foreach ($r in $repos) {
        Write-Host "== kode $($r.Name) =="
        Push-Location $r.Local
        $head = (git rev-parse --short HEAD).Trim(); $cnt = (git rev-list --count HEAD).Trim(); $dirty = (git status --short | Measure-Object).Count
        $files = @(git ls-files | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf })
        $list = Join-Path $work "$($r.Name).files"; [IO.File]::WriteAllLines($list, $files)
        Write-Host "  commit $head, berkas $($files.Count), perubahan belum di-commit: $dirty (berkas terlacak yang berubah ikut terkirim)"
        if ($DryRun) { Pop-Location; continue }
        $tgz = Join-Path $work "$($r.Name)-code.tar.gz"; tar -czf $tgz -T $list
        Pop-Location
        scp -q $tgz "tm188:sync/$($r.Name)-code.tar.gz"
        $prune = ''
        $last = ("$(Tm188 "cat $($r.Remote)/.synced-commit 2>/dev/null || true")").Trim()
        if ($last) { Push-Location $r.Local; $del = git diff --diff-filter=D --name-only $last HEAD 2>$null; Pop-Location; if ($del) { $prune = ($del | ForEach-Object { "rm -f '$($r.Remote)/$_'" }) -join '; ' } }
        $tail = if ($prune) { "; $prune" } else { '' }
        Tm188 "umask 002; cd $($r.Remote) && tar -xzf ~/sync/$($r.Name)-code.tar.gz --no-same-owner --no-same-permissions && echo '$head' > .synced-commit && echo '0.$cnt' > VERSION && ( [ ! -d .git ] || mv .git .git-pre-sync-$stamp )$tail" | Out-Null
        Write-Host "  terkirim dan diekstrak ke $($r.Remote)"
    }
    if (-not $DryRun) {
        Write-Host '== composer dan cache (tmerp) =='
        Tm188 'cd /var/www/tmerp && composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3; php artisan optimize:clear 2>&1 | tail -2' | ForEach-Object { Write-Host "  $_" }
    }
}

if ($Db) {
    Write-Host '== database laptop -> server (menimpa) =='
    $envMap = @{}; Get-Content 'C:\Users\iwanp\Herd\tmerp\.env' | ForEach-Object { if ($_ -match '^\s*([A-Z_]+)\s*=\s*(.*)\s*$') { $envMap[$Matches[1]] = $Matches[2].Trim('"').Trim("'") } }
    $bin = 'C:\Program Files\MySQL\MySQL Server 8.0\bin'; $env:MYSQL_PWD = $envMap['DB_PASSWORD']
    $c = @("--host=$($envMap['DB_HOST'])", "--port=$($envMap['DB_PORT'])", "--user=$($envMap['DB_USERNAME'])")
    $dbs = & "$bin\mysql.exe" @c -N -B -e "SELECT schema_name FROM information_schema.schemata WHERE schema_name REGEXP '^(tm2|ddt)' AND schema_name NOT IN ('tm2old','tm2crp') ORDER BY 1"
    Write-Host "  database: $($dbs -join ', ')"
    if (-not $DryRun) {
        $dir = Join-Path $work 'db'; New-Item -ItemType Directory -Force $dir | Out-Null
        foreach ($d in $dbs) { & "$bin\mysqldump.exe" @c --single-transaction --routines --triggers --events --add-drop-database --default-character-set=utf8mb4 --set-gtid-purged=OFF --databases $d --result-file="$dir\$d.sql" }
        Remove-Item Env:MYSQL_PWD
        Push-Location $dir; $tgz = Join-Path $work 'db.tar.gz'; tar -czf $tgz *.sql; Pop-Location
        scp -q $tgz "tm188:sync/db-$stamp.tar.gz"
        $names = ($dbs -join ' ')
        $bk = @'
set -e
cd ~/sync && rm -rf db-STAMP && mkdir db-STAMP && tar -xzf db-STAMP.tar.gz -C db-STAMP
mkdir -p ~/backups/pre-sync-STAMP
for d in NAMES; do
  if mysql -N -e "SHOW DATABASES LIKE '$d'" | grep -q .; then mysqldump --force --no-tablespaces --single-transaction --routines --triggers --events "$d" | gzip > ~/backups/pre-sync-STAMP/$d.sql.gz; fi
done
echo cadangan: ~/backups/pre-sync-STAMP
'@ -replace 'STAMP', $stamp -replace 'NAMES', $names
        Tm188 $bk | ForEach-Object { Write-Host "  $_" }
        $imp = @'
set -e
cd ~/sync/db-STAMP
for f in *.sql; do
  sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' "$f" | mysql
  echo "impor ${f%.sql}"
done
'@ -replace 'STAMP', $stamp
        Tm188 $imp | ForEach-Object { Write-Host "  $_" }
    }
}
Write-Host "Selesai. Berkas kerja: $work"
