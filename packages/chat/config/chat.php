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
    // Selang poll selama WebSocket tersambung (jaring pengaman + denyut online). Wajib di bawah online_timeout_seconds.
    'realtime_poll_seconds' => 30,

    // Dorongan seketika lewat server WebSocket berprotokol Pusher (Laravel Reverb) yang berjalan terpisah. key kosong =
    // tidak dipakai (chat memakai poll saja). host/port/scheme = alamat server itu dilihat dari PHP; client_* = dilihat dari
    // browser (client_host null = host halaman yang sedang dibuka; client_port / client_scheme null = sama dengan server).
    'realtime' => [
        'app_id' => env('CHAT_REALTIME_APP_ID'),
        'key' => env('CHAT_REALTIME_KEY'),
        'secret' => env('CHAT_REALTIME_SECRET'),
        'host' => env('CHAT_REALTIME_HOST', '127.0.0.1'),
        'port' => env('CHAT_REALTIME_PORT', 8080),
        'scheme' => env('CHAT_REALTIME_SCHEME', 'http'),
        'client_host' => env('CHAT_REALTIME_CLIENT_HOST'),
        'client_port' => env('CHAT_REALTIME_CLIENT_PORT'),
        'client_scheme' => env('CHAT_REALTIME_CLIENT_SCHEME'),
    ],

    // Pemisah channel bila beberapa aplikasi / database memakai satu server WebSocket (null = nama database chat).
    'scope' => null,

    // Dipakai Chat\EloquentDirectory (bawaan): model user (null = model auth) dan kolom namanya.
    'user_model' => null,
    'user_name' => 'name',
];
