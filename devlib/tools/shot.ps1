# Potret satu bagian halaman snapshot (hasil check.ps1) di anchor tertentu.
# Pakai: & .\tools\shot.ps1 -Page lib_p2_choices -Anchor field-slider [-Width 1440 -Height 900]
param(
    [Parameter(Mandatory)][string]$Page,
    [string]$Anchor = '',
    [int]$Width = 1440,
    [int]$Height = 900,
    [string]$Js = ''   # dijalankan setelah halaman siap, sebelum dipotret (mis. buka form)
)
$snap = Join-Path $PSScriptRoot '..\storage\app\snap' | Resolve-Path
$browser = @("${env:ProgramFiles}\Google\Chrome\Application\chrome.exe", "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
$file = Join-Path $snap "$Page.html"
$png = Join-Path $snap ("{0}__{1}.png" -f $Page, ($Anchor -replace '[^A-Za-z0-9]+', '_'))
$hash = ''
if ($Anchor) {
    # Tampilkan hanya bagian anchor (halaman kategori memakai scroller dalam, jadi #anchor tidak bisa dipakai).
    $html = (Get-Content $file -Raw) -replace '</body>', "<script>window.addEventListener('load',()=>{const s=document.getElementById('$Anchor');if(!s)return;const w=document.createElement('div');w.style.cssText='padding:24px;max-width:1100px';w.appendChild(s);document.body.replaceChildren(w);document.body.style.overflow='auto';$Js});</script></body>"
    $file = Join-Path $snap "_shot.html"; Set-Content $file $html -Encoding utf8
} elseif ($Js) {
    $html = (Get-Content $file -Raw) -replace '</body>', "<script>window.addEventListener('load',()=>{$Js});</script></body>"
    $file = Join-Path $snap "_shot.html"; Set-Content $file $html -Encoding utf8
}
& $browser --headless=new --disable-gpu --hide-scrollbars "--window-size=$Width,$Height" --virtual-time-budget=4000 "--screenshot=$png" ("file:///" + ($file -replace '\\', '/') + $hash) 2>$null | Out-Null
$png
