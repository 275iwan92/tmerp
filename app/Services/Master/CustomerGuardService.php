<?php

namespace App\Services\Master;

use App\Models\Master\ProdProduct;
use App\Models\Sdi\IqpInquiry;

// Cek transaksi yang masih mereferensikan customer sebelum boleh dihapus
// permanen. CLAUDE.md §2 "Integritas lintas-modul = tanggung jawab
// Laravel" — cust_customer sendiri SENGAJA tidak punya relasi balik ke
// tabel transaksi (master tetap netral), jadi cek dimulai dari sisi
// transaksi di sini, bukan method di model CustCustomer.
class CustomerGuardService
{
    // List kosong = aman dihapus. Tidak kosong = harus dinonaktifkan saja.
    public function blockingReasons(int $customerId): array
    {
        $reasons = [];

        $productCount = ProdProduct::where('customer_id', $customerId)->count();
        if ($productCount > 0) {
            $reasons[] = "{$productCount} produk";
        }

        $inquiryCount = IqpInquiry::where('customer_id', $customerId)->count();
        if ($inquiryCount > 0) {
            $reasons[] = "{$inquiryCount} inquiry";
        }

        return $reasons;
    }

    public function canDelete(int $customerId): bool
    {
        return $this->blockingReasons($customerId) === [];
    }
}
