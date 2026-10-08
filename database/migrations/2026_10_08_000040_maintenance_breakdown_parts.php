<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Maintenance Fase 3: spare part keluar dari stok (Stock Out) dan PR untuk yang kurang. Link ke Inventory / Procurement = logis (lintas-DB), tanpa FK.
// Jalankan dengan --env=mockup sampai rilis ke tm2*.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mnt')->table('mnt_order_part', function (Blueprint $t) {
            $t->decimal('qty_issued', 14, 3)->default(0)->after('qty_used');
            $t->string('stock_doc_no', 40)->nullable()->after('qty_issued');    // Stock Out terakhir (tm2inv.rm_stock.doc_no)
            $t->unsignedBigInteger('pr_id')->nullable()->after('stock_doc_no'); // PR Draft untuk yang kurang (tm2pur.pr_requisition)
        });

        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_constant')->where('constant_key', 'MNT_DEPARTMENT_CODE')->exists()) {
            $sys->table('sys_constant')->insert(['constant_key' => 'MNT_DEPARTMENT_CODE', 'category_code' => 'PRODUCTION', 'constant_group' => 'MAINTENANCE', 'constant_text' => 'MAINT',
                'description' => 'Maintenance: department code that spare-part Stock Outs and purchase requisitions are charged to (falls back to PPIC).', 'activate' => 1,
                'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration']);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mnt')->table('mnt_order_part', fn (Blueprint $t) => $t->dropColumn(['qty_issued', 'stock_doc_no', 'pr_id']));
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'MNT_DEPARTMENT_CODE')->delete();
    }
};
