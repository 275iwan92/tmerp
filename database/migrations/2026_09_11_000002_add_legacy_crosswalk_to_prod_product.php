<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sama pola dgn mat_raw.old_item_code/old_item_name -- crosswalk idempoten
// utk ProductMigrationService (tm2old.mst_item -> prod_product).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->table('prod_product', function (Blueprint $table) {
            $table->string('old_item_code', 255)->nullable()->after('description');
            $table->string('old_item_name', 250)->nullable()->after('old_item_code');
            $table->unique('old_item_code');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_product', function (Blueprint $table) {
            $table->dropUnique(['old_item_code']);
            $table->dropColumn(['old_item_code', 'old_item_name']);
        });
    }
};
