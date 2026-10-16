<?php

/*
 * REMARK
 * Tujuan: lengkapi default ProCost Advisor (Jo 2026-10-16): toleransi umum 5% (median 9 dokumen HPP lama tmhpp: 0,1,3,3,5,6,6,6,11)
 *         dan gramatur tiap template tmhpp (legacy m_part_template.gramatur_id -> jenis + gsm), dipakai bila BOM Inquiry kosong.
 * Sumber hulu: docs/ddl/tmhpp.sql (hpp.tolerance_percent, m_part_template.gramatur_id, m_paper_grammage).
 * Input: tidak ada. Output: sys_constant TOLERANCE_DEFAULT_PCT, kunci grammage_kind dan grammage_value di prod_part_template.defaults.
 * Dipakai oleh: App\Services\Costing\CostingAdvisor.
 * Status: BARU 2026-10-16.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // legacy gramatur_id (m_paper_grammage): 8 = DUPLEX 250, 20 = DUPLEX 250 (Apr-26), 23 = DUPLEX 350, 24 = DUPLEX 400.
    private const TEMPLATE_GRAMMAGE = [
        'NS40' => ['DUPLEX', 350], 'NKC135-BADAN' => ['DUPLEX', 400], 'NKC135-TUTUP' => ['DUPLEX', 400], 'KALENDER' => ['DUPLEX', 250], 'FS GTZ-5S' => ['DUPLEX', 250],
    ];

    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->updateOrInsert(['constant_key' => 'TOLERANCE_DEFAULT_PCT'], [
            'category_code' => 'PRODUCTION', 'constant_group' => 'COSTING', 'constant_number' => 5,
            'description' => 'Toleransi umum (%) yang diisi ProCost bila belum ada riwayat: median 9 dokumen HPP lama tmhpp',
            'activate' => true, 'created_by' => 'procost_advisor', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (self::TEMPLATE_GRAMMAGE as $code => [$kind, $gsm]) {
            $row = DB::connection('tm2mst')->table('prod_part_template')->where('template_code', $code)->first();
            if (! $row) {
                continue;
            }
            $defaults = json_decode($row->defaults, true) ?? [];
            DB::connection('tm2mst')->table('prod_part_template')->where('template_code', $code)
                ->update(['defaults' => json_encode($defaults + ['grammage_kind' => $kind, 'grammage_value' => $gsm]), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'TOLERANCE_DEFAULT_PCT')->delete();
        foreach (array_keys(self::TEMPLATE_GRAMMAGE) as $code) {
            $row = DB::connection('tm2mst')->table('prod_part_template')->where('template_code', $code)->first();
            $defaults = $row ? (json_decode($row->defaults, true) ?? []) : [];
            unset($defaults['grammage_kind'], $defaults['grammage_value']);
            DB::connection('tm2mst')->table('prod_part_template')->where('template_code', $code)->update(['defaults' => json_encode($defaults)]);
        }
    }
};
