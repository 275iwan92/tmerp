<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Engine MRP (App\Services\Production\MrpEngine) -- production_scheduling_tier1.md
// §F10 "MRP Netting (lapisan tipis)": dari WO -> net qty_required (prd_wo_material)
// terhadap stok on-hand (tm2inv.v_rm_onhand) -> kalau kurang, generate PR draft
// (tm2pur.pr_requisition, i_source=MRP, mrp_run_id -- kolom itu SUDAH ADA di
// pr_requisition sejak awal, komentar DDL-nya persis "reserved - MRP belum
// dibangun", lihat docs/tm2pur.sql). Dua tabel ini jejak audit tiap kali Engine
// MRP jalan -- run per WO, requirement per material di dalamnya.
return new class extends Migration
{
    public function up(): void
    {
        $prd = Schema::connection('tm2prd');

        $prd->create('prd_mrp_run', function (Blueprint $table) {
            $table->id('mrp_run_id');
            $table->foreignId('wo_id')->constrained('prd_wo', 'wo_id');
            $table->dateTime('run_at');
            $table->string('triggered_by', 50);
            $table->unsignedBigInteger('pr_id')->nullable()->comment('LOGICAL FK tm2pur.pr_requisition, terisi bila ada shortage');
            $table->enum('status', ['NO_SHORTAGE', 'PR_GENERATED'])->default('NO_SHORTAGE');
            $table->timestamp('created_at')->useCurrent();
            $table->index('wo_id', 'idx_prd_mrp_run_wo');
        });

        $prd->create('prd_mrp_requirement', function (Blueprint $table) {
            $table->id('requirement_id');
            $table->foreignId('mrp_run_id')->constrained('prd_mrp_run', 'mrp_run_id');
            $table->unsignedBigInteger('material_id')->comment('LOGICAL FK tm2mst.mat_raw');
            $table->decimal('qty_required', 18, 4);
            $table->decimal('qty_onhand_snapshot', 18, 4);
            $table->decimal('qty_shortage', 18, 4);
            $table->timestamp('created_at')->useCurrent();
            $table->index('material_id', 'idx_prd_mrp_req_material');
        });
    }

    public function down(): void
    {
        $prd = Schema::connection('tm2prd');
        $prd->dropIfExists('prd_mrp_requirement');
        $prd->dropIfExists('prd_mrp_run');
    }
};
