<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inventory & Warehouse v2.01, jalur Booking & Pick List (docs/md_for_systems/inventorywarehouse_v201_scope.md bagian 6, W-05, B-01..B-14):
//   tm2inv.wh_pick_list + wh_pick_list_line  (pick list per WO dari booking Confirmed, alokasi palet / batch / bin, scan palet lalu bin)
//   tm2inv.rm_booking: tanda peringatan basi + siapa / mengapa Hold (kolom chain_key, stage, hold_until sudah ada dari fondasi 000100)
//   konstanta INV_PICK_CONDITIONAL_ALLOWED; baris menu Inventory > Warehouse > Pick List dan Production > Planning > Pre-Booking.
// Relasi lintas database = logis (tanpa FK). Dijalankan di ddt* dulu: php artisan migrate --env=mockup --path=database/migrations/<berkas> --force
return new class extends Migration
{
    private const BY = 'claude (booking & pick list)';

    public function up(): void
    {
        $inv = Schema::connection('tm2inv');

        if (! $inv->hasTable('wh_pick_list')) {
            $inv->create('wh_pick_list', function (Blueprint $t) {
                $t->id('id_pick_list');
                $t->string('pick_no', 20)->unique()->comment('PKyynnnnn');
                $t->date('pick_date');
                $t->unsignedBigInteger('wo_id')->nullable()->index()->comment('tm2prd.prd_wo (logis)');
                $t->string('wo_no', 30)->nullable();
                $t->unsignedBigInteger('booking_id')->nullable()->index()->comment('rm_booking CONFIRMED yang menjadi sumber');
                $t->unsignedBigInteger('co_id')->nullable()->comment('tm2sdi.co_order (logis)');
                $t->unsignedBigInteger('customer_id')->nullable()->comment('tm2mst.cust_customer: pemilik stok titipan yang boleh dialokasikan');
                $t->date('need_date')->nullable();
                $t->unsignedBigInteger('deliver_to_location_id')->nullable()->comment('tm2mst.wh_loc staging produksi (logis)');
                $t->string('status', 12)->default('DRAFT')->comment('DRAFT / IN_PROGRESS / DONE / CANCELLED');
                $t->unsignedBigInteger('stock_out_doc_id')->nullable()->comment('rm_stock.doc_id Stock Out RM ke WO');
                $t->string('stock_out_doc_no', 30)->nullable();
                $t->string('notes', 255)->nullable();
                $t->string('cancel_reason', 255)->nullable();
                $t->string('confirmed_by', 100)->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index('status');
            });
        }

        if (! $inv->hasTable('wh_pick_list_line')) {
            $inv->create('wh_pick_list_line', function (Blueprint $t) {
                $t->id('id_pick_list_line');
                $t->unsignedBigInteger('pick_list_id');
                $t->unsignedInteger('line_no')->default(0);
                $t->unsignedBigInteger('booking_detail_id')->nullable()->index()->comment('rm_booking_d yang dipenuhi baris ini');
                $t->unsignedBigInteger('material_id')->comment('tm2mst.mat_raw (logis)');
                $t->string('item_code', 60)->nullable();
                $t->string('item_name', 255)->nullable();
                $t->string('requirement', 150)->nullable()->comment('syarat serat / sertifikasi yang dibaca dari spesifikasi booking');
                $t->decimal('qty_need', 20, 4)->default(0)->comment('kebutuhan total baris booking (sama di semua baris satu bahan)');
                $t->decimal('qty_allocated', 20, 4)->default(0)->comment('bagian yang diambil dari palet ini');
                $t->decimal('qty_picked', 20, 4)->default(0);
                $t->string('uom_code', 50)->nullable();
                $t->unsignedBigInteger('pallet_id')->nullable()->index()->comment('inv_pallet');
                $t->unsignedBigInteger('batch_id')->nullable()->comment('inv_batch');
                $t->unsignedBigInteger('from_location_id')->nullable()->comment('tm2mst.wh_loc bin asal (logis)');
                $t->string('note', 255)->nullable()->comment('alasan alokasi: urutan area, batch Conditional, peringatan');
                $t->timestamp('scan_pallet_at')->nullable();
                $t->string('scan_pallet_by', 100)->nullable();
                $t->timestamp('scan_bin_at')->nullable();
                $t->string('scan_bin_by', 100)->nullable();
                $t->string('status', 10)->default('ALLOCATED')->comment('ALLOCATED / PICKED / SHORT / CANCELLED');
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->index('pick_list_id');
                $t->foreign('pick_list_id', 'fk_wh_pick_line_head')->references('id_pick_list')->on('wh_pick_list')->cascadeOnDelete();
            });
        }

        $inv->table('rm_booking', function (Blueprint $t) use ($inv) {
            foreach (['stale_warned_on' => fn () => $t->date('stale_warned_on')->nullable()->comment('tanggal peringatan booking basi dikirim; kosong = belum'),
                'hold_by' => fn () => $t->string('hold_by', 100)->nullable(), 'hold_reason' => fn () => $t->string('hold_reason', 255)->nullable()] as $col => $add) {
                if (! $inv->hasColumn('rm_booking', $col)) {
                    $add();
                }
            }
        });

        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_constant')->where('constant_key', 'INV_PICK_CONDITIONAL_ALLOWED')->exists()) {
            $sys->table('sys_constant')->insert(['constant_key' => 'INV_PICK_CONDITIONAL_ALLOWED', 'category_code' => 'INVENTORY', 'constant_group' => 'INV', 'constant_number' => 1, 'constant_text' => null,
                'description' => '1 = a Conditional batch may be allocated to a pick list when the batch is not food grade and has no FSC / PEFC certificate (class B and C jobs). 0 = never allocate a Conditional batch.',
                'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => self::BY, 'updated_by' => self::BY]);
        }

        $this->menu();
    }

    private function menu(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $row = fn (array $v) => $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now()];
        $item = function (string $parentCode, string $code, string $label, string $icon, string $slug, int $sort) use ($menu, $row) {
            $parent = $menu()->where('code', $parentCode)->value('id');
            if ($parent && ! $menu()->where('code', $code)->exists()) {
                $menu()->insert($row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:'.$slug, 'sort_order' => $sort]));
            }
        };
        $item('INV_WH', 'INV_WH_PICK', 'Pick List', 'fa-solid fa-list-check', 'wh-pick-list', 25);
        $item('PROD_PLAN', 'PROD_PREBOOK', 'Pre-Booking', 'fa-solid fa-hourglass-half', 'material-prebooking', 18);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'INV_PICK_CONDITIONAL_ALLOWED')->delete();
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['INV_WH_PICK', 'PROD_PREBOOK'])->delete();
        Schema::connection('tm2inv')->dropIfExists('wh_pick_list_line');
        Schema::connection('tm2inv')->dropIfExists('wh_pick_list');
    }
};
