<?php

// Paket chat (packages/chat) di tmerp. Kunci lain memakai bawaan paket. Batas lampiran, selang cek pesan, dan batas waktu
// online TIDAK diatur di sini: dibaca dari Settings > Constants kategori Chat (lihat AppServiceProvider).
return [
    'connection' => 'tm2sys',
    'users_table' => 'sec_user',
    'users_key' => 'id_user',
    'prefix' => 'v2/chat',
    'middleware' => ['web', 'auth', 'locale', 'force.password'],
];
