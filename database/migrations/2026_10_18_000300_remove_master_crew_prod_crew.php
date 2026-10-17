<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * REMARK
 * Tujuan       : Hapus modul ganda "Master Crew" (prod_crew, 217 nama dari tm2old.mst_machine, employee_id kosong semua, tak dibaca
 *                scheduler). Crew yang dipakai penjadwalan = Assign Crew (prod_machine_crew + _member + _period).
 * Sumber hulu  : audit modul ganda 2026-10-18, disetujui Jo (A6). Isi lama diarsipkan di tmerp_unused/prod_crew_tm2mst_2026-10-18.json;
 *                sumber aslinya tetap tm2old.mst_machine.
 * Input        : tm2mst.prod_crew, sys_app_menu (target v2:prod-crew).
 * Output       : tabel dan baris menu dihapus; down() membuat ulang struktur kosong (data dari arsip JSON).
 * Dipakai oleh : php artisan migrate --env=mockup lalu migrate biasa (tmerp).
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->dropIfExists('prod_crew');
        DB::connection('tm2sys')->table('sys_app_menu')->where('target', 'v2:prod-crew')->delete();
    }

    public function down(): void
    {
        $mst = Schema::connection('tm2mst');
        if (! $mst->hasTable('prod_crew')) {
            $mst->create('prod_crew', function (Blueprint $t) {
                $t->id('id_crew');
                $t->string('crew_name', 150);
                $t->unsignedBigInteger('process_id')->nullable()->index();
                $t->unsignedBigInteger('employee_id')->nullable()->index();
                $t->string('legacy_employee_code', 30)->nullable();
                $t->text('notes')->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
            });
        }
    }
};
