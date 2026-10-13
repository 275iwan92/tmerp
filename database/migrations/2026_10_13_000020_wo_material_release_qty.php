<?php

/*
 * REMARK
 * Tujuan       : Menambah prd_wo_material.qty_release dan qty_purchase (Required = Release + Purchase).
 * Sumber hulu  : prd_wo_material (tm2prd).
 * Input        : -
 * Output       : Dua kolom baru.
 * Dipakai oleh : Ppic::bomSheet, GateService, bomCells.
 * Status       : Sudah dijalankan di ddt* saja; jalankan di tm2* saat rilis.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// qty_release = quantity taken from stock for this Work Order, qty_purchase = quantity to buy; Required = Release + Purchase.
// Both null = not edited: Release defaults to the available stock (capped at Required) and Purchase to the rest.
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('tm2prd');
        $s->table('prd_wo_material', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('prd_wo_material', 'qty_release')) {
                $t->decimal('qty_release', 18, 4)->nullable()->after('qty_standar');
            }
            if (! $s->hasColumn('prd_wo_material', 'qty_purchase')) {
                $t->decimal('qty_purchase', 18, 4)->nullable()->after('qty_release');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tm2prd')->table('prd_wo_material', fn (Blueprint $t) => $t->dropColumn(['qty_release', 'qty_purchase']));
    }
};
