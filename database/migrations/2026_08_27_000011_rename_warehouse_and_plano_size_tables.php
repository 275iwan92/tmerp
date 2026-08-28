<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Kategori C dari analisa "kenapa masih ada m_*" (2026-08-27): m_warehouse/
// m_wh_loc/m_plano_size cuma salah prefix, tak ada duplikat. Rekomendasi
// disetujui user: m_warehouse -> wh_warehouse, m_wh_loc -> wh_loc (prefix
// domain baru `wh_`, tetap di tm2mst krn dipakai lintas modul Sales/
// Procurement/Inventory -- bukan pindah DB ke tm2inv), m_plano_size ->
// mat_plano_size (spesifikasi fisik lembar kertas, satu keluarga dgn
// mat_grammage/mat_sheet_rate). FK m_wh_loc.warehouse_id -> m_warehouse ikut
// otomatis (RENAME TABLE MySQL mempertahankan FK).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');
        $db->rename('m_warehouse', 'wh_warehouse');
        $db->rename('m_wh_loc', 'wh_loc');
        $db->rename('m_plano_size', 'mat_plano_size');
    }

    public function down(): void
    {
        $db = Schema::connection('tm2mst');
        $db->rename('wh_warehouse', 'm_warehouse');
        $db->rename('wh_loc', 'm_wh_loc');
        $db->rename('mat_plano_size', 'm_plano_size');
    }
};
