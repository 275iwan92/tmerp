<?php

// Konfigurasi DevLib untuk tmerp (layar v2). Akun `users` di bawah hanya dipakai situs devlib.test; tmerp login lewat sec_user.
return [
    'users' => [
        ['login' => 'admin', 'password' => 'admin', 'name' => 'Administrator DevLib', 'email' => 'admin@devlib.test', 'role' => 'Administrator', 'developer' => true],
    ],

    // Hak akses akun DevLib (Devlib\\Access). '*' = semua; 'po.*' = semua aksi PO.
    // Route pencarian Lookup remote (/_devlib-lookup/{name}).
    'lookup_middleware' => ['web', 'auth'],

    'abilities' => ['po.*', 'devlib.*'],

    // Bahasa antarmuka bawaan (id | en); label tombol preset ikut ini.
    'lang' => env('DEVLIB_LANG', 'id'),

    // Zona waktu yang ditampilkan semua komponen jam/tanggal/salam (bukan jam komputer pengguna).
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'timezone_label' => 'WIB',

    // Folder media (gambar latar bg/<kelompok>/*.jpg, teks text/*.txt).
    'media_path' => public_path('media'),
    'media_url' => '/media',

    // Variasi login yang dipakai halaman /login = resources/views/demos/login-<n>.blade.php.
    // Identitas cetak contoh. Non PPN = nama dagang; PPN = badan usaha terdaftar + NPWP (keputusan user 2026-09-23).
    // Di tmerp nilainya dari Settings > Identifikasi (sys_constant COMPANY_NAME / COMPANY_LEGAL_NAME / NPWP).
    'company' => ['name' => 'Telasih Makmur', 'initials' => 'TM', 'tagline' => 'Offset Printing & Packaging', 'address' => 'Jl. Contoh Industri No. 42, Surabaya (contoh)', 'contact' => 'Telp. (031) 000-0000 · contoh@telasihmakmur.test'],
    'company_ppn' => ['name' => 'CV. Langgeng Jaya Makmur', 'initials' => 'LJ', 'tagline' => 'Offset Printing & Packaging', 'address' => 'Jl. Contoh Industri No. 42, Surabaya (contoh)', 'contact' => 'Telp. (031) 000-0000', 'npwp' => '00.000.000.0-000.000 (contoh)'],

    'login_variant' => env('DEVLIB_LOGIN_VARIANT', 1),

    // Folder mockup user (di repo tmerp, satu tingkat di atas situs ini).
    'mockup_path' => base_path('docs/mockups'),

    // Endpoint Print/Email x-dl::doc-share (routes/v2.php docshare.*). Kosong = PDF/JPEG saja tanpa QR/online/email.
    'doc_share_url' => '/v2/doc-share',
    // Pencarian kontak (customer / supplier / user) untuk dialog Email x-dl::doc-share. Kosong = hanya daftar :contacts.
    'mail_contacts_url' => '/v2/mail-contacts',

    // Kamus penerjemah (LOCKED 2026-10-01): teks English yang dipakai JS tetapi belum ada di sys_dictionary dicatat lewat URL ini.
    'dictionary_register_url' => '/v2/dictionary/register',
    'dictionary_autoregister' => env('DICTIONARY_AUTOREGISTER', true),

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
        'item' => [
            'source' => 'tm2mst.mat_raw (contoh)',
            'key' => 'code', 'label' => 'name',
            'columns' => ['code' => 'Kode', 'name' => 'Nama Item', 'uom' => 'Satuan', 'price_txt' => 'Harga'],
            'fill' => ['spec' => 'spec', 'uom' => 'uom', 'price' => 'price'],
            'rows' => [
                ['code' => 'RM-KRT-0012', 'name' => 'Kertas HVS 210 gsm', 'spec' => 'Plano 79x109', 'uom' => 'RIM', 'price' => 21500, 'price_txt' => '21.500'],
                ['code' => 'RM-IVR-0310', 'name' => 'Ivory 310 gsm', 'spec' => 'Plano 65x100', 'uom' => 'LBR', 'price' => 4200, 'price_txt' => '4.200'],
                ['code' => 'RM-TNT-0044', 'name' => 'Tinta Offset Cyan', 'spec' => '5 kg/kaleng', 'uom' => 'KG', 'price' => 185000, 'price_txt' => '185.000'],
                ['code' => 'RM-TNT-0045', 'name' => 'Tinta Offset Magenta', 'spec' => '5 kg/kaleng', 'uom' => 'KG', 'price' => 192000, 'price_txt' => '192.000'],
                ['code' => 'RM-PLT-0072', 'name' => 'Plat CTP 72x103', 'spec' => 'Thermal, 0,30 mm', 'uom' => 'PCS', 'price' => 96000, 'price_txt' => '96.000'],
                ['code' => 'RM-LEM-0005', 'name' => 'Lem Samping PVAc', 'spec' => 'Jerigen 20 kg', 'uom' => 'KG', 'price' => 27500, 'price_txt' => '27.500'],
                ['code' => 'RM-LAM-0020', 'name' => 'Film Laminasi Doff', 'spec' => 'Lebar 65 cm, 3000 m', 'uom' => 'ROLL', 'price' => 1450000, 'price_txt' => '1.450.000'],
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
