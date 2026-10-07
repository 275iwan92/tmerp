<?php

// Helper global milik library (dimuat DevlibServiceProvider::register): situs devlib.test dan semua aplikasi
// pemakai (tmerp, ...) mewarisinya. Bukan composer "files" supaya tidak perlu dump-autoload di tiap checkout.

use Devlib\Lang;

if (! function_exists('raw')) {
    // NILAI DATA di dalam prop teks komponen x-dl::* (no. dokumen, nama customer, angka, tanggal): tidak diterjemahkan dan
    // tidak tercatat di kamus; label di sekitarnya tetap English dan tetap diterjemahkan komponen.
    //   :title="'Customer Order · '.raw($o->co_no)"   :subtitle="raw($customer.' · '.$status)"
    // Hanya untuk prop komponen x-dl::* (di luar itu penandanya tidak dibuang). Tetap di-escape, bukan HTML mentah.
    function raw(string|int|float|Stringable|null $value): string
    {
        return Lang::raw($value);
    }
}
