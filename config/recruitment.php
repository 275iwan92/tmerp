<?php

// Switch backend portal kandidat (adapter pattern, docs/recruitment_tier3.md
// §1.1). Ganti Plan A<->B = ganti PORTAL_BACKEND di .env, tanpa sentuh
// business logic sync (App\Services\Recruitment\PortalSyncService).
return [

    'backend' => env('PORTAL_BACKEND', 'mariadb'), // 'firebase' | 'mariadb'

    'sync' => [
        // batas jumlah submission ditarik per jalan job (recruitment_tier3.md §1.2)
        'batch_limit' => env('PORTAL_SYNC_BATCH_LIMIT', 20),
    ],

    'firebase' => [
        'use_emulator' => env('FIREBASE_USE_EMULATOR', false),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'firestore_emulator_host' => env('FIRESTORE_EMULATOR_HOST'),
        'auth_emulator_host' => env('FIREBASE_AUTH_EMULATOR_HOST'),
        'storage_emulator_host' => env('FIREBASE_STORAGE_EMULATOR_HOST'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'credentials' => env('FIREBASE_CREDENTIALS'), // path service-account.json, kosong = emulator (tak perlu credential asli)
    ],

];
