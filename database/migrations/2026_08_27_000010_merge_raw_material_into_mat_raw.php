<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kategori B dari analisa "kenapa masih ada m_*" (2026-08-27): BUKAN cuma
// salah prefix -- ini bug fungsional nyata. `mat_raw` (prefix benar, dipakai
// Inquiry form/recalc/revise.blade.php buat dropdown pilih bahan) SELALU
// KOSONG (0 baris) karena skema aslinya tak pernah dituntaskan. Data ASLI
// (5894 baris, 59 kolom) ada di `m_raw_material` (prefix salah) yang justru
// dipakai Item Master + seluruh picker Procurement (PR/PO/PQ/PRT) + fitur
// Confirm. Akibatnya dropdown bahan di Inquiry kosong sejak awal.
//
// Fix: buang stub mat_raw yang kosong, lalu RENAME m_raw_material -> mat_raw
// (kolom TIDAK diubah -- material_id/create_log/update_log/create_by/
// update_by dipertahankan persis, dipakai luas oleh Procurement/Inventory).
// FK m_raw_material.prefix5_id -> mat_category ikut ke tabel baru otomatis
// (RENAME TABLE MySQL mempertahankan FK). Model MatRaw diselaraskan ke
// skema asli di app/Models/Master/MatRaw.php (kode PHP), model MRawMaterial
// dihapus, semua referensi dialihkan ke MatRaw.
//
// Ketemu saat eksekusi: prod_part_material.material_id (Master Produk BOM,
// 0 baris, belum pernah dipakai screen manapun) sudah punya FK fisik ke
// mat_raw.id_material -- nama kolom lama yg tak lagi ada setelah merge ini
// (PK sungguhan = material_id). FK di-drop dulu, dibuat ulang menunjuk
// kolom yg benar setelah rename.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2mst')->statement('ALTER TABLE prod_part_material DROP FOREIGN KEY fk_pmat_material');

        Schema::connection('tm2mst')->dropIfExists('mat_raw');
        Schema::connection('tm2mst')->rename('m_raw_material', 'mat_raw');

        DB::connection('tm2mst')->statement(
            'ALTER TABLE prod_part_material ADD CONSTRAINT fk_pmat_material FOREIGN KEY (material_id) REFERENCES mat_raw (material_id)'
        );
    }

    public function down(): void
    {
        DB::connection('tm2mst')->statement('ALTER TABLE prod_part_material DROP FOREIGN KEY fk_pmat_material');

        Schema::connection('tm2mst')->rename('mat_raw', 'm_raw_material');

        DB::connection('tm2mst')->statement(
            'ALTER TABLE prod_part_material ADD CONSTRAINT fk_pmat_material FOREIGN KEY (material_id) REFERENCES m_raw_material (material_id)'
        );
    }
};
