<?php

namespace App\Support\V2\Grid\Sales;

use App\Models\Sys\SecUser;

// Versi General Report dari laporan CO (user 2026-09-30): "customer order tanpa harga, setelah di-post oleh marketing,
// tersebar di report general yang bisa diakses oleh siapa saja". Dipakai oleh turunan laporan CO Sales:
// hanya CO yang sudah di-post (CONFIRMED / CLOSED), harga SELALU dibuang, terbuka untuk semua user login.
trait GeneralCoReport
{
    public function slug(): string
    {
        return 'gen-'.parent::slug();
    }

    public function module(): string
    {
        return SecUser::PUBLIC_MODULE;
    }

    public function moduleLabel(): string
    {
        return 'General Report';
    }

    public function group(): string
    {
        return 'Customer Order';
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
