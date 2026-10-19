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

if (! function_exists('tf')) {
    // Kalimat yang memuat NILAI (pesan flash, pesan error, hint): teks English ber-:kunci diterjemahkan dulu, baru nilainya
    // diisikan -- kamus hanya mengenal kalimatnya, bukan nomor dokumen / nama.
    //   tf('Quotation :no created (DRAFT).', ['no' => $q->quotation_no])
    // Hasilnya teks jadi: di prop teks komponen x-dl::* bungkus raw() (pesan flash dibungkus di layar yang menampilkannya).
    function tf(string $en, array $values = [], string $context = 'ui'): string
    {
        $map = [];
        foreach ($values as $key => $value) {
            $map[':'.$key] = (string) $value;
        }

        return strtr(Dictionary::t($en, $context, null, $context === 'ui' ? 'messages' : null), $map);
    }
}

// raw() = helper library: ../devlib/src/helpers.php (diwarisi dari DevLib).
