<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Titik peralihan Average per item (D15): per item satu baris berisi qty / nilai total semua bin pada saat peralihan.
// Diisi php artisan inv:revalue-per-item --apply; dibaca App\Services\Inventory\ItemAverageCost. Tanpa jurnal.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tm2inv')->hasTable('inv_item_avg_open')) {
            return;
        }
        Schema::connection('tm2inv')->create('inv_item_avg_open', function (Blueprint $t) {
            $t->id('id_open');
            $t->string('stock_class', 5)->comment('RM / SFG / FG');
            $t->unsignedBigInteger('item_id')->comment('material_id / parts_id / product_id (logis)');
            $t->unsignedBigInteger('part_id')->default(0)->comment('FG: part, 0 = tanpa part');
            $t->dateTime('cutover_date')->comment('baris kartu sampai (cutover_date, cutover_doc_id) tidak dihitung ulang');
            $t->unsignedBigInteger('cutover_doc_id');
            $t->decimal('open_qty', 30, 5);
            $t->decimal('open_value', 30, 5);
            $t->decimal('open_avg', 30, 5)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->string('created_by', 100)->nullable();
            $t->unique(['stock_class', 'item_id', 'part_id'], 'uq_inv_item_avg_open');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2inv')->dropIfExists('inv_item_avg_open');
    }
};
