<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// po_line.i_department_id sebelumnya NOT NULL krn PO selalu lahir dari
// pr_line_alloc (yang selalu punya departemen). PQ->PO (Purchase Quotation,
// 2026-08-22) adalah jalur BARU yang tak selalu lewat alokasi PR (PQ boleh
// berdiri sendiri tanpa i_pr_id) -- ditemukan via test nyata (INSERT gagal
// "doesn't have a default value") sebelum sempat jadi bug produksi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2pur')->table('po_line', function (Blueprint $table) {
            $table->unsignedBigInteger('i_department_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2pur')->table('po_line', function (Blueprint $table) {
            $table->unsignedBigInteger('i_department_id')->nullable(false)->change();
        });
    }
};
