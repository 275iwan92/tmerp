<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Permintaan user 2026-08-29: expand-row master-detail grid CO (co/list.blade.php,
// dibangun 2026-08-24) diperluas nampilin External Cost, bukan cuma Product/Service
// items -- tapi tm2sdi.co_order_external_cost ternyata 0 baris di seluruh DB, tak
// ada data buat diverifikasi manual/test. Seed 1 baris dummy di CO2600001
// (order_id=2, dipakai juga di test WO generation) -- dummy data permanen, JANGAN
// dihapus sesudah verifikasi (keputusan user: selalu seed data uji, tak pernah
// dihapus lagi).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sdi')->table('co_order_external_cost')->insert([
            'order_id' => 2,
            'category' => 'Trucking',
            'description' => 'Ongkos kirim ke gudang customer',
            'nominal' => 750000,
            'tax_treatment' => 'TAXABLE',
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sdi')->table('co_order_external_cost')
            ->where('order_id', 2)->where('category', 'Trucking')
            ->where('description', 'Ongkos kirim ke gudang customer')->delete();
    }
};
