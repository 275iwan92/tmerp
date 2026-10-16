<?php

/*
 * REMARK
 * Tujuan       : Menyajikan diagram SVG Technical Guide (docs/tg/<modul>/img/NAMA.svg) ke Help, karena markdown Help meng-escape HTML dan tidak bisa menyisipkan SVG inline. Hanya baca.
 * Sumber hulu  : Berkas docs/tg/<modul>/img/NAMA.svg dan docs/tg/img/NAMA.svg (ikut git).
 * Input        : GET /v2/tg-img/{name}; name hanya huruf kecil, angka, tanda minus.
 * Output       : image/svg+xml dengan CSP tanpa skrip; 404 bila tidak ada.
 * Dipakai oleh : TechGuideLookup::article() (menulis ulang tautan gambar kartu ke rute ini); dimuat routes/web.php di grup login.
 * Status       : BARU 2026-10-16.
 */

use Illuminate\Support\Facades\Route;

Route::get('/v2/tg-img/{name}', function (string $name) {
    $path = (glob(base_path("docs/tg/*/img/$name.svg")) ?: [])[0] ?? (is_file(base_path("docs/tg/img/$name.svg")) ? base_path("docs/tg/img/$name.svg") : null);
    abort_unless($path, 404);

    return response((string) file_get_contents($path), 200, ['Content-Type' => 'image/svg+xml', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'"]);
})->where('name', '[a-z0-9-]+')->name('v2.tg-img');
