<?php

// Dimuat AppServiceProvider::register() (bukan composer "files": tidak perlu dump-autoload di tiap checkout).

use App\Support\Localization\Dictionary;

if (! function_exists('t')) {
    // Teks tampilan (LOCKED user 2026-10-01): TULIS ENGLISH di kode -> tampil dalam bahasa layar/cetak lewat kamus
    // sys_dictionary; teks baru tercatat otomatis untuk diterjemahkan. Nilai lookup DB: t($row->term_label, 'tm2mst.fin_payment_term.term_label').
    // Di Blade: @t('Customer'). Label komponen x-dl::* sudah otomatis -- cukup tulis English, tanpa t().
    function t(?string $en, string $context = 'ui'): string
    {
        return Dictionary::t($en, $context);
    }
}
