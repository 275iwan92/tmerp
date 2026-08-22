<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Stock Out, Return from Production, Stock Adjustment & Opname (+ layar
// Persetujuan-nya) dibangun 2026-08-22 (INV_TIER_4.md §3/§4/§5). Placeholder
// item-nya sudah ada sejak sesi sebelumnya (INV_TRX_RM/FG/SFG_ITEM2..5), cuma
// perlu dipindah dari NOT_BUILT -> READY_FOR_QC utk yang benar-benar dikerjakan.
// FG/SFG untuk Stock Out & Return from Production SENGAJA dibiarkan NOT_BUILT
// -- di luar cakupan modul per INV_RULES.yaml (fg_out_bukan_milik_modul; SFG
// sama sekali tak disebut spec/mockup Tier 4 utk kedua layar itu).
return new class extends Migration
{
    private const BUILT = ['INV_RM_ITEM2', 'INV_RM_ITEM3', 'INV_RM_ITEM4', 'INV_RM_ITEM5', 'INV_FG_ITEM4', 'INV_FG_ITEM5', 'INV_SFG_ITEM4', 'INV_SFG_ITEM5'];

    private const OUT_OF_SCOPE_NOTE = 'Di luar cakupan modul Stock Out/Return v1 -- INV_RULES.yaml (fg_out_bukan_milik_modul / SFG tak disebut spec Tier 4). Hanya kelas RM yang berlaku.';

    public function up(): void
    {
        $db = DB::connection('tm2sys');

        $db->table('sys_app_menu')->whereIn('code', self::BUILT)->update(['status_code' => 'READY_FOR_QC']);

        $db->table('sys_app_menu')->whereIn('code', ['INV_FG_ITEM2', 'INV_FG_ITEM3', 'INV_SFG_ITEM2', 'INV_SFG_ITEM3'])
            ->update(['status_note' => self::OUT_OF_SCOPE_NOTE]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');
        $db->table('sys_app_menu')->whereIn('code', self::BUILT)->update(['status_code' => 'NOT_BUILT']);
        $db->table('sys_app_menu')->whereIn('code', ['INV_FG_ITEM2', 'INV_FG_ITEM3', 'INV_SFG_ITEM2', 'INV_SFG_ITEM3'])
            ->update(['status_note' => null]);
    }
};
