<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inventory & Warehouse v2.01, jalur "Struktur & tugas gudang" (docs/md_for_systems/inventorywarehouse_v201_technical_plan.md):
//   tm2inv.wh_task = tugas gudang (RECEIVE / PUTAWAY / PICK / MOVE / COUNT); dipakai pertama kali oleh layar Receiving & Putaway;
//   tm2sys.sys_app_menu = baris menu Inventory > Warehouse > Receiving & Putaway (v2:wh-receiving-putaway).
// Relasi dalam database yang sama (palet, batch, transfer) = FK fisik; relasi ke tm2mst / tm2prd = logis.
// Dijalankan di ddt* dulu: php artisan migrate --env=mockup --path=database/migrations/2026_10_08_000120_warehouse_tasks.php --force
return new class extends Migration
{
    private const BY = 'claude (inventory v2.01)';

    public function up(): void
    {
        $inv = Schema::connection('tm2inv');
        if (! $inv->hasTable('wh_task')) {
            $inv->create('wh_task', function (Blueprint $t) {
                $t->id('id_task');
                $t->string('task_no', 20)->unique();
                $t->string('task_type', 10)->comment('RECEIVE / PUTAWAY / PICK / MOVE / COUNT');
                $t->string('status', 10)->default('OPEN')->comment('OPEN / DONE / CANCELLED');
                $t->string('stock_class', 5)->comment('RM / SFG / FG');
                $t->unsignedBigInteger('item_id')->comment('material_id / parts_id / product_id (logis)');
                $t->unsignedBigInteger('part_id')->nullable();
                $t->string('item_code', 60)->nullable();
                $t->string('item_name', 255)->nullable();
                $t->unsignedBigInteger('pallet_id')->nullable();
                $t->unsignedBigInteger('batch_id')->nullable();
                $t->decimal('qty', 20, 4)->default(0);
                $t->string('uom_code', 50)->nullable();
                $t->unsignedBigInteger('from_location_id')->nullable()->comment('tm2mst.wh_loc (logis)');
                $t->unsignedBigInteger('to_location_id')->nullable()->comment('bin tujuan yang dikonfirmasi (logis)');
                $t->string('ref_doc_no', 50)->nullable()->comment('nomor penerimaan (SPB) asal palet');
                $t->boolean('is_urgent')->default(false)->comment('salinan tanda URGENT penerimaan saat tugas dibuat');
                $t->unsignedBigInteger('wo_id')->nullable()->comment('tm2prd.prd_wo yang menunggu bahan (dock to line, logis)');
                $t->string('assigned_to', 100)->nullable();
                $t->string('scan_pallet', 60)->nullable()->comment('kode palet yang dipindai saat konfirmasi');
                $t->string('scan_bin', 60)->nullable()->comment('kode bin yang dipindai saat konfirmasi');
                $t->string('reason', 255)->nullable()->comment('alasan bila bin berbeda dari usulan');
                $t->unsignedBigInteger('transfer_id')->nullable();
                $t->string('cancel_reason', 255)->nullable();
                $t->string('confirmed_by', 100)->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index(['task_type', 'status'], 'ix_wh_task_type_status');
                $t->index('from_location_id');
                $t->foreign('pallet_id', 'fk_wh_task_pallet')->references('id_pallet')->on('inv_pallet')->nullOnDelete();
                $t->foreign('batch_id', 'fk_wh_task_batch')->references('id_batch')->on('inv_batch')->nullOnDelete();
                $t->foreign('transfer_id', 'fk_wh_task_transfer')->references('id_transfer')->on('inv_transfer')->nullOnDelete();
            });
        }

        $menu = DB::connection('tm2sys')->table('sys_app_menu');
        $parent = (clone $menu)->where('code', 'INV_WH')->value('id');
        if ($parent && ! (clone $menu)->where('code', 'INV_WH_RCV')->exists()) {
            (clone $menu)->insert(['parent_id' => $parent, 'code' => 'INV_WH_RCV', 'label' => 'Receiving & Putaway', 'icon' => 'fa-solid fa-dolly', 'menu_type' => 'item', 'object_type' => 'grid',
                'target' => 'v2:wh-receiving-putaway', 'sort_order' => 15, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(),
                'created_at' => now(), 'doc_slug' => 'warehouselayout']);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'INV_WH_RCV')->delete();
        Schema::connection('tm2inv')->dropIfExists('wh_task');
    }
};
