<?php

// Setelan paket asisten. Aplikasi pemakai menimpa lewat config/assistant.php miliknya (cukup kunci yang berbeda).
return [
    // Koneksi database tempat tabel asisten (null = koneksi default aplikasi) dan nama tabelnya.
    'connection' => null,
    'tables' => ['message' => 'sys_assistant_message', 'metric' => 'sys_assistant_metric'],

    // Alamat & gerbang rute: <prefix>/ask, /history, /feedback; nama rute assistant.*.
    'prefix' => 'assistant',
    'middleware' => ['web', 'auth'],

    // Kelas metric (Assistant\Contracts\Metric) milik aplikasi: satu pertanyaan angka = satu kelas.
    'metrics' => [],

    // Halaman aplikasi untuk mengirim tiket bantuan dari jawaban "tidak tahu" (null = tanpa tombol itu).
    'ticket_page' => null,

    // Nilai bawaan; aplikasi boleh menggantinya dengan sumber sendiri lewat Assistant\Assistant::settingsUsing().
    // Model bahasa lokal (Ollama). enabled 0 = tanpa model: pencarian kata kunci tetap jalan.
    'model_enabled' => 0,
    'model_url' => 'http://127.0.0.1:11434',
    'model_name' => 'qwen2.5:3b',
    'model_timeout_seconds' => 60,

    // Lama simpan riwayat percakapan menurut level penanya (hari), dan batas level kelompoknya.
    'history_days_top' => 30,
    'history_days_middle' => 14,
    'history_days_low' => 7,
    'level_top_min' => 4,
    'level_middle_min' => 2,

    // Jumlah pertukaran terakhir yang dimuat saat drawer dibuka.
    'history_show' => 30,
];
