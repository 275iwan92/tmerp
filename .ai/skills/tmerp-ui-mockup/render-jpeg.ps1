# Render file HTML mockup -> JPEG via Chrome/Edge headless + System.Drawing.
# Pakai: powershell -File render-jpeg.ps1 -Html <file.html> -Out <file.jpg> [-Width 1440] [-Height 900] [-Quality 92]
param(
    [Parameter(Mandatory)][string]$Html,
    [Parameter(Mandatory)][string]$Out,
    [int]$Width = 1440,
    [int]$Height = 900,
    [int]$Quality = 92
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$browser = @(
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $browser) { throw 'Chrome/Edge tidak ditemukan.' }

$htmlFull = (Resolve-Path $Html).Path
$outFull = [System.IO.Path]::GetFullPath($Out)
$png = [System.IO.Path]::ChangeExtension([System.IO.Path]::GetTempFileName(), 'png')
$profile = Join-Path ([System.IO.Path]::GetTempPath()) ('mockup-chrome-' + [guid]::NewGuid())

# virtual-time-budget memberi waktu CDN (Tailwind/FontAwesome) selesai dimuat sebelum screenshot.
& $browser --headless=new --disable-gpu --hide-scrollbars "--user-data-dir=$profile" `
    "--window-size=$Width,$Height" --virtual-time-budget=8000 "--screenshot=$png" `
    ([uri]$htmlFull).AbsoluteUri | Out-Null
if (-not (Test-Path $png)) { throw 'Screenshot gagal dibuat.' }

$img = [System.Drawing.Image]::FromFile($png)
try {
    $codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object MimeType -eq 'image/jpeg'
    $params = New-Object System.Drawing.Imaging.EncoderParameters(1)
    $params.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, [long]$Quality)
    New-Item -ItemType Directory -Force (Split-Path $outFull) | Out-Null
    $img.Save($outFull, $codec, $params)
} finally { $img.Dispose() }
Remove-Item $png, $profile -Recurse -Force -ErrorAction SilentlyContinue
Write-Output $outFull
