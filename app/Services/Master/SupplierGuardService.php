<?php

namespace App\Services\Master;

// Cek transaksi yang masih mereferensikan supplier sebelum boleh dihapus
// permanen. Modul Purchasing (tm2pur) BELUM punya skema/tabel apapun
// (CLAUDE.md §5) — jadi belum ada yang bisa "memblokir" hapus saat ini.
// Tambahkan cek PR/PO di sini begitu tm2pur.pur_* punya kolom supplier_id.
class SupplierGuardService
{
    public function blockingReasons(int $supplierId): array
    {
        return [];
    }

    public function canDelete(int $supplierId): bool
    {
        return $this->blockingReasons($supplierId) === [];
    }
}
