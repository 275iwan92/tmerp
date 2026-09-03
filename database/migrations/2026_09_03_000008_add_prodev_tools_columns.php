<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BACKLOG.md §0 follow-up: Tool #4 (Die-Rule Estimator) butuh tempat simpan
// hasil hitung meter lari pisau per prod_layout_group (dipakai lintas-part
// kalau group menaungi >1 part, Tool #10). Tool #2/#10 sendiri TIDAK butuh
// kolom baru -- murni komputasi on-the-fly dari data yang sudah ada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_layout_group', function (Blueprint $table): void {
            $table->decimal('die_rule_meter', 12, 4)->nullable()->after('waste_pct');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_layout_group', function (Blueprint $table): void {
            $table->dropColumn('die_rule_meter');
        });
    }
};
