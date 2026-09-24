<?php

// Pengaturan KHUSUS UAT (Fase 2, mockup.test). Diisi hanya di .env.mockup -- bukan data aplikasi, jadi tidak disimpan
// di database/konstanta dan tidak ikut tersalin ke live (keputusan user 2026-09-25). Kosong = fitur UAT mati.
return [
    // Daftar akun di halaman login (klik = login & password terisi): "login|password|peran;login|password|peran;..."
    'accounts' => env('UAT_ACCOUNTS'),

    // Login yang dilewati dari kewajiban 4 pertanyaan keamanan: "*" = semua, atau "johiwan,carrij,nisa".
    'security_setup_skip' => env('UAT_SECURITY_SETUP_SKIP'),
];
