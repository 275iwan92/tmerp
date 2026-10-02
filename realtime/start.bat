@echo off
rem Server WebSocket chat (Laravel Reverb), port 8080. Biarkan jendela ini terbuka; tutup = chat kembali ke mode 5 detik.
cd /d "%~dp0"
title Chat realtime (Reverb) - port 8080
php artisan reverb:start
pause
