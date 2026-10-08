<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use RuntimeException;

// Tugas gudang ditolak (pindai salah, aturan area, alasan kosong, stok tidak cukup). Pesannya sudah diterjemahkan dan boleh ditampilkan ke petugas.
final class WarehouseTaskRefused extends RuntimeException {}
