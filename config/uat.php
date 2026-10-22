<?php

// Pengaturan KHUSUS UAT (Fase 2, mockup.test). Diisi hanya di .env.mockup -- bukan data aplikasi, jadi tidak disimpan
// di database/konstanta dan tidak ikut tersalin ke live (keputusan user 2026-09-25). Kosong = fitur UAT mati.
return [
    // Daftar akun di halaman login (klik = login & password terisi): "login|password|peran;login|password|peran;..."
    'accounts' => env('UAT_ACCOUNTS'),

    // Gerbang keamanan login MATI sampai Jo bilang UAT selesai (Jo 2026-10-22: "baru boleh jalan setelah UAT selesai, TIDAK untuk saat ini").
    // Berlaku di semua situs tanpa perlu .env; setelah UAT selesai isi kedua variabel dengan nilai kosong (UAT_SECURITY_SETUP_SKIP= dan UAT_PASSWORD_CHANGE_SKIP=) atau ubah bawaannya.
    // Login yang dilewati dari kewajiban 4 pertanyaan keamanan: "*" = semua, atau "johiwan,carrij,nisa".
    'security_setup_skip' => env('UAT_SECURITY_SETUP_SKIP', '*'),

    // Login yang TIDAK dipaksa ganti password walau must_change_password = 1: "*" = semua.
    'password_change_skip' => env('UAT_PASSWORD_CHANGE_SKIP', '*'),

    // Bilah QC cepat (Approved / Need improvement) di tiap layar v2: hanya situs UAT (UAT_QC=true di .env.mockup), mati di live.
    'qc' => (bool) env('UAT_QC', false),
];
