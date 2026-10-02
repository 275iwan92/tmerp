<?php

namespace App\Services\Inventory;

use RuntimeException;

// Posting dokumen stok ditolak di tengah jalan (saldo jadi minus): dilempar di dalam transaksi supaya semua baris kartu
// yang sudah tertulis untuk dokumen itu ikut batal. Ditangkap StockPostingService::postDocument(), tidak sampai ke layar.
class StockPostingRefused extends RuntimeException {}
