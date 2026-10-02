<?php

// Dimuat AppServiceProvider::register() (bukan composer "files": tidak perlu dump-autoload di tiap checkout).

use App\Support\Localization\Dictionary;
use Devlib\Lang;

if (! function_exists('t')) {
    // Teks tampilan (LOCKED user 2026-10-01): TULIS ENGLISH di kode -> tampil dalam bahasa layar/cetak lewat kamus
    // sys_dictionary; teks baru tercatat otomatis untuk diterjemahkan. Nilai lookup DB: t($row->term_label, 'tm2mst.fin_payment_term.term_label').
    // Di Blade: @t('Customer'). Label komponen x-dl::* sudah otomatis -- cukup tulis English, tanpa t().
    function t(?string $en, string $context = 'ui'): string
    {
        return Dictionary::t($en, $context);
    }
}

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
