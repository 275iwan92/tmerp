<?php

/*
 * REMARK
 * Tujuan       : Pelaku operasi di Bill of Process: crew (work center HRM) atau subcon (vendor), dipilih per baris lewat dropdown.
 * Sumber hulu  : Sheet BOP Form WO (PpicController::targetCells) -> PrdWoOperation::assignActor.
 * Input        : -
 * Output       : prd_wo_operation.crew_work_center_id (-> tm2mst.emp_work_center) dan subcon_supplier_id (-> tm2mst.sup_supplier is_subcon=1); paling banyak satu terisi.
 * Dipakai oleh : Ppic::bopSheet, ProductionOutput (antrean crew).
 * Status       : Dijalankan di ddt* saja; menunggu persetujuan Jo untuk rilis ke tm2*. Relasi lintas-DB logis tanpa FK.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2prd');
        $s->table('prd_wo_operation', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('prd_wo_operation', 'crew_work_center_id')) {
                $t->unsignedBigInteger('crew_work_center_id')->nullable();
                $t->unsignedBigInteger('subcon_supplier_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_operation', fn (Blueprint $t) => $t->dropColumn(['crew_work_center_id', 'subcon_supplier_id']));
    }
};
