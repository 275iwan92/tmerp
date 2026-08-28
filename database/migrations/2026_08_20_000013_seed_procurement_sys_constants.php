<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// procurement_tier3.md §7.4 seed tarif pajak & toleransi. Draf Tier 3 mengasumsikan
// kolom const_code/const_value/const_type, TERNYATA nama kolom asli beda
// (dikonfirmasi 2026-08-20): constant_key/constant_group/constant_number/
// constant_text/description/is_active (lihat migration 2026_08_20_000004).
// Ini resolusi PROC-SYSCONST-COL (procurement_state.yaml open_items).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $now = now();

        $constants = [
            ['constant_key' => 'PPN', 'constant_group' => 'TAX', 'constant_number' => 11, 'description' => 'Tarif PPN (%)'],
            ['constant_key' => 'PPH23_JASA', 'constant_group' => 'TAX', 'constant_number' => 2, 'description' => 'Tarif PPh 23 atas jasa (%)'],
            ['constant_key' => 'PPH22_IMPOR_API', 'constant_group' => 'TAX', 'constant_number' => 2.5, 'description' => 'PPh 22 impor ber-API (%) - belum dipakai, po_import_doc INACTIVE'],
            ['constant_key' => 'RECEIVE_TOLERANCE_PCT', 'constant_group' => 'PROCUREMENT', 'constant_number' => 5, 'description' => 'Toleransi terima default bila m_raw_material.tol_purchase kosong'],
        ];

        foreach ($constants as $c) {
            $db->table('sys_constant')->updateOrInsert(
                ['constant_key' => $c['constant_key']],
                $c + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('sys_constant')
            ->whereIn('constant_key', ['PPN', 'PPH23_JASA', 'PPH22_IMPOR_API', 'RECEIVE_TOLERANCE_PCT'])
            ->delete();
    }
};
