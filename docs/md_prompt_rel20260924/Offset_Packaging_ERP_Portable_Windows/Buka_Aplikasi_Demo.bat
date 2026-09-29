@echo off
title Offset Packaging ERP - Demo Portable Windows
color 0A

echo =========================================================================
echo   OFFSET PACKAGING ERP - NEW PRODUCT INQUIRY DEMO
echo   Portable Windows 10 / Windows 11 Edition (100%% Offline)
echo =========================================================================
echo.
echo Sedang menyiapkan demo portable untuk Anda...
echo.

:: Port 32145 dijamin AMAN dari Laravel Herd (Herd memakai port 80/443/8080/3306)
set DEMO_PORT=32145

echo [1/2] Menyalakan server lokal portable di port %DEMO_PORT% (Aman dari Laravel Herd)...
start /b "" powershell -WindowStyle Hidden -Command "$p=%DEMO_PORT%;$l=New-Object System.Net.HttpListener;$l.Prefixes.Add(\"http://localhost:$p/\");$l.Start();while($true){$c=$l.GetContext();$req=$c.Request.Url.LocalPath.TrimStart('/');if($req -eq ''){$req='index.html'};$f=Join-Path '%~dp0' $req;if(Test-Path $f){$b=[System.IO.File]::ReadAllBytes($f);$ext=[System.IO.Path]::GetExtension($f);$mime='application/octet-stream';if($ext -eq '.html'){$mime='text/html'};if($ext -eq '.js'){$mime='application/javascript'};if($ext -eq '.css'){$mime='text/css'};if($ext -eq '.svg'){$mime='image/svg+xml'};if($ext -eq '.png'){$mime='image/png'};if($ext -eq '.jpg'){$mime='image/jpeg'};$c.Response.ContentType=$mime;$c.Response.OutputStream.Write($b,0,$b.Length)}else{$c.Response.StatusCode=404};$c.Response.Close()}"

timeout /t 1 >nul
echo [2/2] Membuka browser: http://localhost:%DEMO_PORT%/
start http://localhost:%DEMO_PORT%/

echo.
echo =========================================================================
echo SUKSES! Aplikasi telah berjalan di: http://localhost:%DEMO_PORT%/
echo.
echo CATATAN PENTING:
echo - Laravel Herd Anda tetap jalan normal dan TIDAK TERGANGGU.
echo - Biarkan jendela hitam ini tetap terbuka selama presentasi ke Bos.
echo - Jika demo selesai, cukup tutup jendela ini atau tekan tombol apapun.
echo =========================================================================
echo.
pause
