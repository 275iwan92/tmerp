<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ACC customer per product (docs/flow_product_master.md §5-§6): ACC Digital (dari sample cetak plat CTP, WAJIB
// sebelum Cetak) dan ACC Sample Bentuk (sheet polos, dicatat saja). Selama ini manual lewat WhatsApp/e-mail ->
// dicatat tanggal & jam, penyetuju, saluran, bukti. drawing_by/design_date = isian blok Proof Digital.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('prod_product_acc', function (Blueprint $t) {
            $t->id('id_acc');
            $t->unsignedBigInteger('product_id');
            $t->string('acc_type', 10)->comment('EMAIL | DIGITAL | SAMPLE');
            $t->dateTime('acc_at');
            $t->string('approved_by', 100)->comment('Penyetuju di pihak customer');
            $t->string('channel', 10)->comment('WA | EMAIL | LANGSUNG');
            $t->string('evidence_path', 255)->nullable();
            $t->string('evidence_filename', 255)->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->string('data_origin', 10)->default('LIVE');
            $t->boolean('data_ready')->default(true);
            $t->boolean('data_origin_manual')->default(false);
            $t->index(['product_id', 'acc_type']);
            $t->foreign('product_id')->references('id_product')->on('prod_product')->cascadeOnDelete();
        });

        Schema::connection('tm2mst')->table('prod_product', function (Blueprint $t) {
            $t->string('drawing_by', 100)->nullable()->after('design_no')->comment('Desainer (blok DRAWING BY Proof Digital)');
            $t->date('design_date')->nullable()->after('drawing_by');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->table('prod_product', fn (Blueprint $t) => $t->dropColumn(['drawing_by', 'design_date']));
        Schema::connection('tm2mst')->dropIfExists('prod_product_acc');
    }
};
