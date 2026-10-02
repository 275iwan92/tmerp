<?php

namespace App\Support\Finance;

use App\Models\Sys\SysConstant;

// Tarif pajak dokumen penjualan dari Settings > Constants (kategori Finance), sama dengan yang dibaca Purchase Order.
// Dibaca sekali per request: totals() dipanggil per baris grid.
final class TaxRate
{
    public static function ppn(): float
    {
        return once(fn () => SysConstant::number('PPN', 11));
    }

    public static function pph23(): float
    {
        return once(fn () => SysConstant::number('PPH23_JASA', 2));
    }
}
