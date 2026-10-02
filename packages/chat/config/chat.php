<?php

// Setelan paket chat. Aplikasi pemakai menimpa lewat config/chat.php miliknya (cukup kunci yang berbeda).
return [
    // Koneksi database tempat tabel chat (null = koneksi default aplikasi).
    'connection' => null,

    // Tabel user aplikasi untuk FK sys_user_presence.user_id saat tabel dibuat (null = tanpa FK).
    'users_table' => null,
    'users_key' => 'id',

    // Guard yang usernya memakai chat (null = guard default). Sign out di guard lain tidak dianggap keluar dari chat.
    'guard' => null,

    // Alamat & gerbang rute: <prefix>/poll, /messages, /upload, /send, /file/{id}; nama rute chat.*.
    'prefix' => 'chat',
    'middleware' => ['web', 'auth'],

    // Disk (privat) tempat file lampiran; foldernya chat/.
    'disk' => 'local',

    // Lampiran dikirim berpotong-potong sebesar ini (di bawah upload_max_filesize PHP bawaan, 2M).
    'chunk_bytes' => 1048576,

    // Nilai bawaan; aplikasi boleh menggantinya dengan sumber sendiri lewat Chat\Chat::settingsUsing().
    'attachment_max_mb' => 20,
    'poll_seconds' => 5,
    'online_timeout_seconds' => 90,

    // Dipakai Chat\EloquentDirectory (bawaan): model user (null = model auth) dan kolom namanya.
    'user_model' => null,
    'user_name' => 'name',
];
