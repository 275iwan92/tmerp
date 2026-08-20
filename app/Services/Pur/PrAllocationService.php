<?php

namespace App\Services\Pur;

use App\Models\Pur\PrLine;
use App\Models\Pur\PrLineAlloc;
use App\Models\Pur\PrRequisition;
use Illuminate\Support\Facades\Auth;

// Split/alokasi pr_line -> pr_line_alloc (PROC_TIER_3 §3.1, §3): "Line yang
// tidak dipecah tetap punya 1 alokasi PENUH -- jadi tak ada dua jalur logika."
// Dipanggil otomatis saat PR final APPROVED (PrApproval) supaya PO Entry
// (yang mengambil dari pr_line_alloc, bukan pr_line langsung) selalu punya
// sumber, tanpa memaksa officer split manual utk kasus paling umum (1 line =
// 1 supplier). Split ke banyak supplier tetap bisa dilakukan belakangan lewat
// method split() -- panel UI-nya menyusul (PROC_TIER_4 §2.2, belum dibangun).
class PrAllocationService
{
    public static function createDefaultAllocationsForPr(PrRequisition $pr): void
    {
        foreach ($pr->lines as $line) {
            static::ensureDefaultAllocation($line);
        }
    }

    public static function ensureDefaultAllocation(PrLine $line): PrLineAlloc
    {
        $existing = $line->allocations()->first();

        if ($existing) {
            return $existing;
        }

        $login = Auth::user()->login ?? null;
        $supplierId = $line->item_id ? $line->material?->suggest_supplier_id : null;

        $alloc = PrLineAlloc::create([
            'pr_line_id' => $line->pr_line_id,
            'alloc_no' => 1,
            'i_qty' => $line->i_qty,
            'i_supplier_id' => $supplierId,
            'c_is_converted' => false,
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        $line->update(['c_qty_allocated' => $line->i_qty, 'c_line_status' => 'OPEN']);

        return $alloc;
    }

    // Split satu alokasi jadi beberapa (supplier berbeda) -- Sigma qty TIDAK
    // BOLEH melebihi pr_line.i_qty (PROC_TIER_2 B-1). $splits: [['qty'=>, 'supplier_id'=>], ...]
    public static function split(PrLine $line, array $splits): void
    {
        $totalQty = array_sum(array_column($splits, 'qty'));

        if ($totalQty > (float) $line->i_qty) {
            throw new \InvalidArgumentException('Total qty alokasi melebihi qty baris PR.');
        }

        $login = Auth::user()->login ?? null;
        $line->allocations()->delete();

        foreach ($splits as $i => $s) {
            PrLineAlloc::create([
                'pr_line_id' => $line->pr_line_id,
                'alloc_no' => $i + 1,
                'i_qty' => $s['qty'],
                'i_supplier_id' => $s['supplier_id'] ?? null,
                'c_is_converted' => false,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
        }

        $line->update(['c_qty_allocated' => $totalQty]);
    }
}
