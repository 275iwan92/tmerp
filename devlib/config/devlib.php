<?php

// Situs DevLib TANPA database: akun & pilihan tampilan disimpan di sini.
return [
    'users' => [
        ['login' => 'admin', 'password' => 'admin', 'name' => 'Administrator DevLib', 'email' => 'admin@devlib.test', 'role' => 'Administrator', 'developer' => true],
    ],

    // Model tampilan login yang dipakai halaman /login (lihat Devlib\Components\Login::MODELS).
    'login_model' => env('DEVLIB_LOGIN_MODEL', 'split'),

    // Folder mockup user (di repo tmerp, satu tingkat di atas situs ini).
    'mockup_path' => base_path('../docs/mockups'),
];
