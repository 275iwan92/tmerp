<?php

use Illuminate\Support\Facades\Route;

// Server ini hanya menjalankan WebSocket (php artisan reverb:start); tidak ada halaman.
Route::get('/', fn () => 'Realtime server (Laravel Reverb). Run: php artisan reverb:start');
