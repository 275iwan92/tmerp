<?php

use App\Assistant\Metrics\DeadstockValue;
use App\Assistant\Metrics\SalaryQuestion;
use App\Assistant\Metrics\SalesToday;

// Paket asisten (packages/assistant) di ERP ini. Kunci yang tidak ditulis memakai bawaan paket; angka setelan (model,
// masa simpan riwayat) dibaca dari Settings > Constants kategori Assistant lewat App\Providers\AssistantHostProvider.
return [
    'connection' => 'tm2sys',
    'middleware' => ['web', 'auth', 'force.password'],

    // Satu pertanyaan angka = satu kelas. Frasa, modul, dan level minimumnya dirawat di Setup > Assistant Questions.
    // SalaryQuestion paling dulu: frasa gaji tidak boleh jatuh ke angka lain.
    'metrics' => [SalaryQuestion::class, SalesToday::class, DeadstockValue::class],

    // Halaman profil Layer Menu untuk kirim tiket dari jawaban "tidak tahu".
    'ticket_page' => 'ticket',
];
