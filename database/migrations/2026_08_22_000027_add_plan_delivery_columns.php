<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Plan Delivery (docs/mockups/co_delivery_plan_mockup.html) -- hub logistik
// lintas-CO, MURNI penjadwalan (split/gabung/geser tanggal kirim), TIDAK
// menyentuh stok sama sekali (itu domain Delivery Order/Dispatch, belum
// dibangun -- lihat financeaccounting_tier1.md Keputusan B: HPP diakui saat DO terbit,
// bukan di sini). co_order_delivery sudah ada (dipakai tab "Delivery" di CO
// Entry, migration 2026_08_20_000008) tapi belum punya status/lineage split --
// tab CO Entry cuma menulis jadwal AWAL sekali saat CO masih DRAFT; begitu CO
// CONFIRMED, screen ini yang mengelola lanjutannya (checklistmockuperp.md
// & sales_state.yaml sebelumnya menandai relasi 2 layar ini "perlu klarifikasi
// scope" -- resolusi: saling melengkapi, bukan bentrok, lihat memory
// project_plan_delivery_screen).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('co_order_delivery', function (Blueprint $table) {
            $table->string('status', 20)->default('PLANNED')->after('ship_qty')->comment('PLANNED|SHIPPED -- SHIPPED dikunci setelah DO terbit (fase Dispatch, belum ada)');
            $table->unsignedBigInteger('split_from_id')->nullable()->after('status')->comment('Self-FK -- baris asal kalau ini hasil Split, utk kolom "Asal"');

            $table->foreign('split_from_id')->references('id_delivery')->on('co_order_delivery')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('co_order_delivery', function (Blueprint $table) {
            $table->dropForeign(['split_from_id']);
            $table->dropColumn(['status', 'split_from_id']);
        });
    }
};
