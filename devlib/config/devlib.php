<?php

// Situs DevLib TANPA database: akun & pilihan tampilan disimpan di sini.
return [
    'users' => [
        ['login' => 'admin', 'password' => 'admin', 'name' => 'Administrator DevLib', 'email' => 'admin@devlib.test', 'role' => 'Administrator', 'developer' => true],
    ],

    // Zona waktu yang ditampilkan semua komponen jam/tanggal/salam (bukan jam komputer pengguna).
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'timezone_label' => 'WIB',

    // Folder media (gambar latar bg/<kelompok>/*.jpg, teks text/*.txt).
    'media_path' => public_path('media'),
    'media_url' => '/media',

    // Variasi login yang dipakai halaman /login = resources/views/demos/login-<n>.blade.php.
    'login_variant' => env('DEVLIB_LOGIN_VARIANT', 1),

    // Folder mockup user (di repo tmerp, satu tingkat di atas situs ini).
    'mockup_path' => base_path('../docs/mockups'),

    // Data contoh untuk halaman komponen (di tmerp nanti dari Lookup/tabel master).
    'demo' => [
        'locations' => [
            'Surabaya, Jawa Timur, Indonesia', 'Malang, Jawa Timur, Indonesia', 'Sidoarjo, Jawa Timur, Indonesia',
            'Jakarta Selatan, DKI Jakarta, Indonesia', 'Bandung, Jawa Barat, Indonesia', 'Medan, Sumatera Utara, Indonesia',
            'Singapore, Central Region, Singapore', 'Tokyo, Kanto, Japan', 'Sydney, New South Wales, Australia', 'London, England, United Kingdom',
        ],
        'languages' => [
            'Bahasa Indonesia' => '🇮🇩 Bahasa Indonesia', 'Bahasa Inggris' => '🇬🇧 Bahasa Inggris', 'Bahasa Mandarin' => '🇨🇳 Bahasa Mandarin',
            'Bahasa Jepang' => '🇯🇵 Bahasa Jepang', 'Bahasa Arab' => '🇸🇦 Bahasa Arab', 'Bahasa Jerman' => '🇩🇪 Bahasa Jerman',
        ],
    ],
];
