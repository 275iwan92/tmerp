<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use RuntimeException;

// Aturan valuasi / jurnal persediaan yang ditolak (akun belum ada, periode tertutup, dokumen tidak boleh diubah). Pesan sudah diterjemahkan
// (t / tf) dan aman ditampilkan di layar; tidak ada yang tersimpan setengah jalan.
final class ValuationRefused extends RuntimeException {}
