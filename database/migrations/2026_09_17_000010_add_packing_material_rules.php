<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Bahan pendukung pengiriman di BOM (user 2026-09-17: "Tali buat pack,
// Plastik buat pack saat kirim ... semua harus detail di BOM karena akan
// menyangkut cost"). usage_basis PER_PACK = pemakaian per pack (satuan
// pemakaian = meter), jumlah pack = qty order / isi per pack.
// Takaran = ESTIMASI AWAL, koreksi di Setting Aturan BOP.
return new class extends Migration
{
    private const ROWS = [
        [91, 'TALI PACK', 'TALI STRAPPING%|TALI RAFIA%', 3.0],
        [92, 'PLASTIK PACK', 'WRAPPING PLASTIK%|PLASTIK PE ROLL%', 1.5],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as [$seq, $role, $pattern, $rate]) {
            DB::connection('tm2mst')->table('prod_bop_rule')->insert([
                'rule_scope' => 'STANDARD',
                'step_seq' => $seq,
                'process_id' => null,
                'material_pattern' => $pattern,
                'material_rule' => 'BEST_STOCK',
                'material_role' => $role,
                'usage_basis' => 'PER_PACK',
                'usage_rate' => $rate,
                'note' => 'Meter per pack -- takaran estimasi awal, koreksi dari data pemakaian nyata',
                'activate' => 1,
                'created_at' => $now, 'updated_at' => $now,
                'created_by' => 'claude_code', 'updated_by' => 'claude_code',
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_bop_rule')
            ->whereIn('material_role', array_column(self::ROWS, 1))
            ->where('usage_basis', 'PER_PACK')
            ->delete();
    }
};
