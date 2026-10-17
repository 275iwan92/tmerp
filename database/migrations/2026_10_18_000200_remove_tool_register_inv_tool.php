<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * REMARK
 * Tujuan       : Hapus modul ganda "Tool Register" (inv_tool). Register die / plat / klise yang dipakai scheduler adalah
 *                Manufacture > PPIC Settings > Tooling Master (tm2mst.prod_tooling_asset); inv_tool pulau tanpa pembaca (0 baris).
 * Sumber hulu  : audit modul ganda 2026-10-18, disetujui Jo (A2).
 * Input        : tm2inv.inv_tool, sys_app_menu (INV_TOOLS_REG, grup INV_TOOLS).
 * Output       : tabel dan dua baris menu dihapus; down() membuat ulang struktur kosong.
 * Dipakai oleh : php artisan migrate --env=mockup lalu migrate biasa (tmerp).
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = Schema::connection('tm2inv')->hasTable('inv_tool') ? DB::connection('tm2inv')->table('inv_tool')->count() : 0;
        if ($rows > 0) {
            throw new RuntimeException("inv_tool masih berisi {$rows} baris; arsipkan dulu sebelum dihapus.");
        }

        Schema::connection('tm2inv')->dropIfExists('inv_tool');
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_TOOLS_REG')->delete();
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_TOOLS')->delete();
    }

    public function down(): void
    {
        $inv = Schema::connection('tm2inv');
        if (! $inv->hasTable('inv_tool')) {
            $inv->create('inv_tool', function (Blueprint $t) {
                $t->id('id_tool');
                $t->string('tool_no', 30)->unique();
                $t->string('tool_type', 10)->default('DIE');
                $t->string('description', 255)->nullable();
                $t->string('status', 12)->default('AVAILABLE');
                $t->unsignedBigInteger('location_id')->nullable();
                $t->unsignedInteger('impressions')->default(0);
                $t->string('notes', 255)->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index('status');
            });
        }
    }
};
