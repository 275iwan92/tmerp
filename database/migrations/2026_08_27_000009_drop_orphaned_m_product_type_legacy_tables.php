<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Kategori A dari analisa "kenapa masih ada m_*" (2026-08-27): 5 tabel sisa
// skema "Master Card"/PROD_CARD asli dump TIER_3A pertama -- TIDAK punya
// Model Eloquent, TIDAK direferensi kode manapun. Isinya sudah dipindah ke
// prod_type/prod_type_param/prod_type_size/prod_type_size_val/ref_param_option
// via migration 2026_08_18_000010 (jumlah baris identik, terverifikasi).
// Backup penuh (struktur+data): docs/backups/2026_08_27_orphaned_m_tables_backup.sql
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2mst');
        // Urutan drop: child dulu (FK internal m_product_type_size_val -> m_product_type_size -> m_product_type)
        $db->dropIfExists('m_product_type_size_val');
        $db->dropIfExists('m_product_type_size');
        $db->dropIfExists('m_product_type_param');
        $db->dropIfExists('m_product_type');
        $db->dropIfExists('m_ref_param_option');
    }

    public function down(): void
    {
        // Restore dari backup: mysql tm2mst < docs/backups/2026_08_27_orphaned_m_tables_backup.sql
        DB::connection('tm2mst')->statement('SELECT 1'); // no-op, restore manual via file backup
    }
};
