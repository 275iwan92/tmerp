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

    // Data contoh Lookup (di tmerp: query tabel/VIEW master). Nama sengaja fiktif.
    'lookups' => [
        'supplier' => [
            'source' => 'tm2mst.sup_supplier (contoh)',
            'key' => 'id', 'label' => 'name',
            'columns' => ['code' => 'Kode', 'name' => 'Nama Supplier', 'city' => 'Kota', 'term' => 'Syarat Bayar'],
            'fill' => ['payment_term' => 'term', 'ship_address' => 'address'],
            'rows' => [
                ['id' => 1, 'code' => 'SUP-001', 'name' => 'PT Contoh Kertas Jaya', 'city' => 'Surabaya', 'term' => 'Net 30', 'address' => 'Jl. Contoh Industri 1, Surabaya'],
                ['id' => 2, 'code' => 'SUP-002', 'name' => 'CV Contoh Tinta Prima', 'city' => 'Sidoarjo', 'term' => 'Net 14', 'address' => 'Jl. Contoh Raya 22, Sidoarjo'],
                ['id' => 3, 'code' => 'SUP-003', 'name' => 'PT Contoh Karton Nusantara', 'city' => 'Gresik', 'term' => 'COD', 'address' => 'Kawasan Contoh Blok C3, Gresik'],
                ['id' => 4, 'code' => 'SUP-004', 'name' => 'UD Contoh Lem Sejahtera', 'city' => 'Malang', 'term' => 'Net 45', 'address' => 'Jl. Contoh Timur 8, Malang'],
            ],
        ],
        'uom' => [
            'source' => 'tm2mst.uom_unit (contoh)',
            'key' => 'code', 'label' => 'code',
            'columns' => ['code' => 'Kode', 'name' => 'Satuan'],
            'rows' => [['code' => 'PCS', 'name' => 'Pieces'], ['code' => 'LBR', 'name' => 'Lembar'], ['code' => 'RIM', 'name' => 'Rim (500 lembar)'], ['code' => 'KG', 'name' => 'Kilogram'], ['code' => 'ROLL', 'name' => 'Roll']],
        ],
    ],
];
