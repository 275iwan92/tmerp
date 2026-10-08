<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use RuntimeException;

// Kirim / kembali / pakai bahan subkon ditolak (tidak ada yang berubah). Pesan sudah diterjemahkan, siap tampil.
final class SubconStockRefused extends RuntimeException {}
