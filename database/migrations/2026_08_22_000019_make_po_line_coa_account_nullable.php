<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sama alasan dgn migration 000018 (i_department_id) -- po_line.i_coa_account_id
// juga cuma terisi lewat jalur pr_line_alloc lama. PQ->PO tak selalu punya PR
// acuan utk menurunkan akun COA-nya. Ditemukan via test nyata yang sama.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pur')->table('po_line', function (Blueprint $table) {
            $table->unsignedBigInteger('i_coa_account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->table('po_line', function (Blueprint $table) {
            $table->unsignedBigInteger('i_coa_account_id')->nullable(false)->change();
        });
    }
};
