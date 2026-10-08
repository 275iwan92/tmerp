<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use RuntimeException;

// Pick list tidak bisa dibuat / dipindai / dikonfirmasi karena aturan bisnis (pesan sudah diterjemahkan, aman ditampilkan ke petugas).
class PickListRefused extends RuntimeException {}
