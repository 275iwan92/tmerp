# Verifikasi otomatis situs DevLib tanpa Browser pane:
#   1. menjalani perjalanan user (buka / -> login salah -> login benar -> Home -> halaman lain -> logout)
#   2. menyimpan HTML hasil render tiap halaman (sesi login ikut) ke storage/app/snap
#   3. memotret tiap snapshot dengan browser headless -> PNG di folder yang sama
# Pakai:  powershell -File tools\check.ps1                 (semua halaman bawaan)
#         powershell -File tools\check.ps1 -Pages '/lib/login-2'
param(
    [string]$Base = 'http://devlib.test',
    [string]$User = 'admin',
    [string]$Pass = 'admin',
    [string[]]$Pages = @('/', '/lib/basic-colors', '/lib/basic-typography', '/lib/basic-shape', '/lib/basic-themes', '/lib/basic-density', '/lib/basic-icons', '/lib/basic-breakpoints', '/lib/basic-locale', '/lib/p2-inputs', '/lib/p2-choices', '/lib/p2-datetime', '/lib/p2-numbers', '/lib/p2-files', '/lib/p2-buttons', '/lib/p2-display', '/lib/p2-containers', '/lib/p2-navigation', '/lib/p3-feedback', '/lib/p3-loading', '/lib/p3-notifications', '/lib/p3-errors', '/lib/p4-developer', '/lib/p4-guard', '/lib/p4-shortcuts', '/lib/p4-permissions', '/lib/p4-i18n', '/lib/p4-metadata', '/lib/p5-grid', '/lib/p5-forms', '/lib/p5-master', '/lib/p5-master-detail', '/lib/p5-form-wizard', '/lib/p5-kanban', '/lib/p5-gantt', '/lib/p5-calendar', '/lib/p5-print', '/lib/p5-report', '/lib/p5-import', '/lib/p5-org', '/lib/p6-charts', '/lib/p6-indicators', '/lib/p6-codes', '/lib/form', '/lib/shell', '/lib/p7-menus', '/lib/p7-layer', '/lib/p7-chat', '/lib/login-1', '/lib/login-2', '/lib/login-3', '/lib/register', '/lib/forgot-1', '/lib/forgot-2', '/lib/p8-lock', '/lib/p8-security', '/mockup/register.html', '/mockup/grid_kerapatan_baris.html'),
    [string[]]$Modules = @('P1', 'P2'),
    [int]$Width = 1440,
    [int]$Height = 900,
    [switch]$NoShots
)
$ErrorActionPreference = 'Stop'
$snap = Join-Path $PSScriptRoot '..\storage\app\snap'
New-Item -ItemType Directory -Force $snap | Out-Null
$snap = (Resolve-Path $snap).Path
$browser = @("${env:ProgramFiles}\Google\Chrome\Application\chrome.exe", "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
$fail = 0

function Req($path, $method = 'GET', $body = $null) {
    try {
        $r = Invoke-WebRequest -Uri ($Base + $path) -Method $method -Body $body -WebSession $script:s -MaximumRedirection 0 -SkipHttpErrorCheck -UseBasicParsing -ErrorAction SilentlyContinue
    } catch {
        # PowerShell 7 melempar exception untuk redirect bila MaximumRedirection 0.
        $resp = $_.Exception.Response
        if ($resp) { return [pscustomobject]@{ StatusCode = [int]$resp.StatusCode; Content = ''; Headers = @{ Location = "$($resp.Headers.Location)" } } }
        return [pscustomobject]@{ StatusCode = 0; Content = $_.Exception.Message; Headers = @{} }
    }
    return $r
}
function Token($html) { if ($html -match 'name="_token" value="([^"]+)"') { $Matches[1] } }
function Check($label, $ok, $detail = '') {
    $mark = if ($ok) { 'OK  ' } else { 'GAGAL'; $script:fail++ }
    Write-Output ("{0} {1} {2}" -f $mark, $label, $detail)
}
function Shot($name, $html, $hash = '') {
    $html = $html -replace '<head>', "<head><base href=`"$Base/`">"
    $file = Join-Path $snap "$name.html"
    Set-Content -Path $file -Value $html -Encoding utf8
    if ($NoShots -or -not $browser) { return }
    $png = Join-Path $snap "$name.png"
    & $browser --headless=new --disable-gpu --hide-scrollbars "--window-size=$Width,$Height" --virtual-time-budget=4000 "--screenshot=$png" ("file:///" + ($file -replace '\\', '/') + $hash) 2>$null | Out-Null
}
function SafeName($p) { ($p.Trim('/') -replace '[^A-Za-z0-9=]+', '_') -replace '^$', 'home' }

$script:s = New-Object Microsoft.PowerShell.Commands.WebRequestSession

$r = Req '/'
Check 'buka / tanpa login -> ke /login' ($r.StatusCode -eq 302 -and "$($r.Headers.Location)" -like '*/login') "$($r.StatusCode) $($r.Headers.Location)"

$r = Req '/login'
Check 'halaman login tampil' ($r.StatusCode -eq 200 -and $r.Content -match 'x-dl::login') "$($r.StatusCode)"
Shot 'login' $r.Content

$r = Req '/login' 'POST' @{ _token = (Token $r.Content); login = $User; password = 'salah-' + $Pass }
$r = Req '/login'
Check 'password salah -> pesan salah + username tetap' ($r.Content -match 'data-error="wrong"' -and $r.Content -match "name=`"login`"[^>]*value=`"$User`"")
Shot 'login_salah' $r.Content

$r = Req '/login' 'POST' @{ _token = (Token $r.Content); login = $User; password = $Pass }
Check 'password benar -> ke Home' ($r.StatusCode -eq 302) "$($r.StatusCode) $($r.Headers.Location)"

foreach ($p in $Pages) {
    $r = Req $p
    Check "GET $p" ($r.StatusCode -eq 200) "$($r.StatusCode)"
    if ($r.StatusCode -eq 200) { Shot (SafeName $p) $r.Content }
}

# Jendela kode </> halaman form dipotret (deep link #kode).
$r = Req '/lib/form'; Shot 'lib_form_kode' $r.Content '#kode'
$r = Req '/lib/login-2'; Shot 'lib_login_kode' $r.Content '#kode'

# Tema gelap dipotret (deep link #theme=...).
$r = Req '/lib/basic-themes'; Shot 'tema_dark_blue' $r.Content '#theme=dark_blue'
$r = Req '/lib/login-1'; Shot 'tema_hitam_green_login' $r.Content '#theme=hitam_green'

# Tiap modul menu dipotret (deep link #KODE).
foreach ($m in $Modules) { $r = Req '/'; Shot "modul_$m" $r.Content "#$m" }

$r = Req '/'
$r = Req '/logout' 'POST' @{ _token = (Token $r.Content) }
$r2 = Req '/'
Check 'logout -> kembali harus login' ($r.StatusCode -eq 302 -and $r2.StatusCode -eq 302)

Write-Output ''
Write-Output "Snapshot & gambar: $snap"
if ($fail) { Write-Output "$fail pemeriksaan GAGAL"; exit 1 } else { Write-Output 'Semua pemeriksaan OK' }
