<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Sys\SecUser;

// Versi General Report dari laporan CO (user 2026-09-30): "customer order tanpa harga, setelah di-post oleh marketing,
// tersebar di report general yang bisa diakses oleh siapa saja". Dipakai oleh turunan laporan CO Sales:
// hanya CO yang sudah di-post (CONFIRMED / CLOSED), harga SELALU dibuang, terbuka untuk semua user login.
// "General" = sifat akses, bukan letak menu (user 2026-10-04): layarnya tetap di modul Sales, grup "General Report".
trait GeneralCoReport
{
    public function slug(): string
    {
        return 'gen-'.parent::slug();
    }

    // Gerbang akses: terbuka untuk semua user login (SecUser::canAccessModule), walau menunya di modul Sales.
    public function module(): string
    {
        return SecUser::PUBLIC_MODULE;
    }

    public function moduleLabel(): string
    {
        return 'Sales';
    }

    public function group(): string
    {
        return 'Sales Order › General Report';
    }

    protected function restrictedKeys(): array
    {
        return self::PRICE_KEYS;
    }

    public function rows(): array
    {
        return array_values(array_filter(parent::rows(), fn ($r) => in_array($r['status'] ?? null, ['CONFIRMED', 'CLOSED'], true)));
    }

    public function stats(): array
    {
        return [
            ['label' => 'All COs (posted)'],
            ['label' => 'Outstanding', 'where' => ['outstanding' => true], 'tone' => 'warn'],
            ['label' => 'Closed', 'where' => ['status' => 'CLOSED'], 'tone' => 'success'],
        ];
    }
}
