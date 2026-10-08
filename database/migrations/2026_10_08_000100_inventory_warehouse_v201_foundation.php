<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inventory & Warehouse v2.01, fondasi (docs/md_for_systems/inventorywarehouse_v201_technical_plan.md, rencana Fase 1):
//   tm2inv.inv_batch (master batch) + kolom batch_id / owner_type / owner_id pada kartu stok, baris dokumen, palet;
//   kolom pembatalan + pembalik pada header dokumen stok (RM / SFG / FG), kolom In Transit pada transfer;
//   tm2mst.wh_zone (is_virtual, mix_rule, pick_order, count_every_days), wh_loc (kunci hitung), wh_warehouse.warehouse_type + VIRTUAL;
//   tm2pur.pv_line (tanda URGENT, batch); tm2inv.rm_booking (rantai booking dari costing); konstanta pengaturan.
// Semua kolom baru boleh kosong / punya default aman; relasi lintas database = logis (tanpa FK). Dijalankan di ddt* dulu (php artisan migrate --env=mockup --path=...).
return new class extends Migration
{
    private const BY = 'claude (inventory v2.01)';

    private const CARDS = ['rm_stockcard', 'sfg_stockcard', 'fg_stockcard'];

    private const LINES = ['rm_stock_d', 'sfg_stock_d', 'fg_stock_d'];

    private const HEADERS = ['rm_stock', 'sfg_stock', 'fg_stock'];

    // key => [angka, teks, kelompok, keterangan]
    private const CONSTANTS = [
        'INV_NO_LOT_BATCH_CODE' => [null, 'NO-LOT', 'INV', 'Batch code given to stock that has no lot number when old lots are turned into batches.'],
        'INV_AVG_SCOPE' => [null, 'BIN', 'INV', 'Where the moving Average is calculated: BIN (one per item per bin, the old way) or ITEM (one per item for the whole company). Switch to ITEM only after the cutover check has been reviewed.'],
        'INV_QC_ESCALATE_HOURS' => [4, null, 'INV', 'A Conditional batch without a quality decision after this many hours is escalated to the head of QC and the operations manager.'],
        'INV_QC_DEADLINE_HOURS' => [8, null, 'INV', 'Working hours allowed for a quality decision on a Conditional batch; after this a usage decision is required.'],
        'INV_URGENT_FSC_APPROVAL' => [1, null, 'INV', '1 = an URGENT receipt of FSC or food grade material needs approval of the head of QC or the operations manager.'],
        'INV_SHORTAGE_WINDOW_DAYS' => [7, null, 'INV', 'Days a supplier has to send a shortfall before the purchase order line is closed at the received quantity.'],
        'INV_BOOKING_STALE_WARN_DAYS' => [7, null, 'INV', 'A confirmed booking without a work order is flagged this many days after its need date.'],
        'INV_BOOKING_STALE_RELEASE_DAYS' => [30, null, 'INV', 'A confirmed booking without a work order is released this many days after its need date.'],
        'INV_BOOKING_HOLD_WORKDAYS' => [3, null, 'INV', 'A booking hold ends by itself after this many working days.'],
        'INV_AGING_SLOW_DAYS' => [90, null, 'INV', 'Days without an issue after which an item counts as slow moving.'],
        'INV_AGING_NONMOVING_DAYS' => [180, null, 'INV', 'Days without an issue after which an item counts as non moving.'],
        'INV_AGING_DEAD_DAYS' => [365, null, 'INV', 'Days without an issue after which an item counts as dead stock.'],
    ];

    public function up(): void
    {
        $inv = Schema::connection('tm2inv');

        if (! $inv->hasTable('inv_batch')) {
            $inv->create('inv_batch', function (Blueprint $t) {
                $t->id('id_batch');
                $t->string('batch_no', 40);
                $t->string('stock_class', 5)->comment('RM / SFG / FG');
                $t->unsignedBigInteger('item_id')->comment('material_id / parts_id / product_id (logis)');
                $t->unsignedBigInteger('part_id')->nullable();
                $t->string('item_code', 60)->nullable();
                $t->string('item_name', 255)->nullable();
                $t->unsignedBigInteger('supplier_id')->nullable()->comment('tm2mst.sup_* (logis)');
                $t->string('supplier_lot', 100)->nullable();
                $t->date('production_date')->nullable();
                $t->date('expiry_date')->nullable();
                $t->date('retest_date')->nullable();
                $t->string('qc_status', 15)->default('RELEASED')->comment('QUARANTINE / CONDITIONAL / RELEASED / REJECTED / BLOCKED');
                $t->string('qc_status_by', 100)->nullable();
                $t->timestamp('qc_status_at')->nullable();
                $t->string('certification', 20)->default('NONE')->comment('NONE / FSC / PEFC / OTHER');
                $t->boolean('food_grade')->default(false);
                $t->string('grain_direction', 10)->nullable()->comment('LONG / SHORT (paper)');
                $t->string('coa_path', 255)->nullable();
                $t->string('notes', 255)->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->unique(['stock_class', 'item_id', 'batch_no'], 'uq_inv_batch_item_no');
                $t->index('expiry_date');
                $t->index('qc_status');
                $t->index('supplier_id');
            });
        }

        // batch + pemilik pada kartu stok, baris dokumen, dan palet
        foreach (array_merge(self::CARDS, self::LINES) as $table) {
            if (! $inv->hasTable($table)) {
                continue;
            }
            $inv->table($table, function (Blueprint $t) use ($inv, $table) {
                if (! $inv->hasColumn($table, 'batch_id')) {
                    $t->unsignedBigInteger('batch_id')->nullable()->index();
                }
                if (! $inv->hasColumn($table, 'owner_type')) {
                    $t->string('owner_type', 10)->default('COMPANY')->comment('COMPANY / CUSTOMER / SUPPLIER; selain COMPANY tidak dinilai dan tidak dijurnal');
                }
                if (! $inv->hasColumn($table, 'owner_id')) {
                    $t->unsignedBigInteger('owner_id')->nullable();
                }
            });
        }
        $inv->table('inv_pallet', function (Blueprint $t) use ($inv) {
            if (! $inv->hasColumn('inv_pallet', 'batch_id')) {
                $t->unsignedBigInteger('batch_id')->nullable()->index();
            }
        });

        // pembatalan dokumen yang sudah diposting = pembalik (Cancel + alasan wajib)
        foreach (self::HEADERS as $table) {
            if (! $inv->hasTable($table)) {
                continue;
            }
            $inv->table($table, function (Blueprint $t) use ($inv, $table) {
                foreach (['cancel_reason' => fn () => $t->string('cancel_reason', 255)->nullable(), 'cancelled_by' => fn () => $t->string('cancelled_by', 100)->nullable(),
                    'cancelled_at' => fn () => $t->timestamp('cancelled_at')->nullable(), 'reverses_doc_id' => fn () => $t->unsignedBigInteger('reverses_doc_id')->nullable()->index(),
                    'reversed_by_doc_id' => fn () => $t->unsignedBigInteger('reversed_by_doc_id')->nullable()->index()] as $col => $add) {
                    if (! $inv->hasColumn($table, $col)) {
                        $add();
                    }
                }
            });
        }

        // transfer dua langkah lewat In Transit
        $inv->table('inv_transfer', function (Blueprint $t) use ($inv) {
            foreach (['transit_mode' => fn () => $t->string('transit_mode', 10)->default('DIRECT')->comment('DIRECT (satu langkah) / TWO_STEP (lewat In Transit)'),
                'shipped_at' => fn () => $t->timestamp('shipped_at')->nullable(), 'shipped_by' => fn () => $t->string('shipped_by', 100)->nullable(),
                'received_at' => fn () => $t->timestamp('received_at')->nullable(), 'received_by' => fn () => $t->string('received_by', 100)->nullable(),
                'transit_status' => fn () => $t->string('transit_status', 12)->nullable()->comment('SHIPPED / RECEIVED / DISCREPANCY / CLOSED')] as $col => $add) {
                if (! $inv->hasColumn('inv_transfer', $col)) {
                    $add();
                }
            }
        });
        $inv->table('inv_transfer_line', function (Blueprint $t) use ($inv) {
            foreach (['qty_received' => fn () => $t->decimal('qty_received', 20, 4)->nullable(), 'qty_diff' => fn () => $t->decimal('qty_diff', 20, 4)->nullable(),
                'batch_id' => fn () => $t->unsignedBigInteger('batch_id')->nullable()] as $col => $add) {
                if (! $inv->hasColumn('inv_transfer_line', $col)) {
                    $add();
                }
            }
        });

        // rantai booking dari costing (B0 pre-booking sampai B4 pick)
        $inv->table('rm_booking', function (Blueprint $t) use ($inv) {
            foreach (['chain_key' => fn () => $t->string('chain_key', 40)->nullable()->index()->comment('inquiry_id rantai kebutuhan: costing, quotation, CO'),
                'stage' => fn () => $t->string('stage', 2)->nullable()->comment('B0 pre-booking / B1 quotation / B2 CO draft / B3 CO confirmed (keras) / B4 pick'),
                'hold_until' => fn () => $t->date('hold_until')->nullable(), 'inquiry_id' => fn () => $t->unsignedBigInteger('inquiry_id')->nullable()->index(),
                'costing_id' => fn () => $t->unsignedBigInteger('costing_id')->nullable()] as $col => $add) {
                if (! $inv->hasColumn('rm_booking', $col)) {
                    $add();
                }
            }
        });

        // area dan bin gudang
        $mst = Schema::connection('tm2mst');
        $mst->table('wh_zone', function (Blueprint $t) use ($mst) {
            foreach (['is_virtual' => fn () => $t->boolean('is_virtual')->default(false)->comment('area virtual tidak punya tempat fisik dan tidak tampil di peta'),
                'mix_rule' => fn () => $t->string('mix_rule', 12)->default('ANY')->comment('ANY / ONE_ITEM / ONE_BATCH'),
                'pick_order' => fn () => $t->string('pick_order', 5)->default('FIFO')->comment('FIFO / FEFO'),
                'count_every_days' => fn () => $t->unsignedSmallInteger('count_every_days')->nullable()] as $col => $add) {
                if (! $mst->hasColumn('wh_zone', $col)) {
                    $add();
                }
            }
        });
        $mst->table('wh_loc', function (Blueprint $t) use ($mst) {
            foreach (['count_lock' => fn () => $t->boolean('count_lock')->default(false)->comment('bin dikunci selama dihitung (opname)'),
                'count_lock_by' => fn () => $t->string('count_lock_by', 100)->nullable(), 'count_lock_at' => fn () => $t->timestamp('count_lock_at')->nullable()] as $col => $add) {
                if (! $mst->hasColumn('wh_loc', $col)) {
                    $add();
                }
            }
        });
        // gudang maya untuk area virtual (subkon, In Transit, Loss)
        DB::connection('tm2mst')->statement("ALTER TABLE wh_warehouse MODIFY warehouse_type ENUM('RM','SFG','FG','MIX','WASTE','TRANSIT','VIRTUAL') NOT NULL DEFAULT 'RM'");

        // penerimaan: tanda URGENT + batch
        $pur = Schema::connection('tm2pur');
        $pur->table('pv_line', function (Blueprint $t) use ($pur) {
            foreach (['is_urgent' => fn () => $t->boolean('is_urgent')->default(false), 'urgent_by' => fn () => $t->string('urgent_by', 100)->nullable(),
                'urgent_at' => fn () => $t->dateTime('urgent_at')->nullable(), 'urgent_reason' => fn () => $t->string('urgent_reason', 255)->nullable(),
                'urgent_wo_id' => fn () => $t->unsignedBigInteger('urgent_wo_id')->nullable()->comment('tm2prd.prd_wo (logis)'),
                'i_batch_id' => fn () => $t->unsignedBigInteger('i_batch_id')->nullable()->comment('tm2inv.inv_batch (logis)')] as $col => $add) {
                if (! $pur->hasColumn('pv_line', $col)) {
                    $add();
                }
            }
        });

        // pengaturan (Settings > Constants)
        $sys = DB::connection('tm2sys');
        foreach (self::CONSTANTS as $key => [$number, $text, $group, $description]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'INVENTORY', 'constant_group' => $group, 'constant_number' => $number, 'constant_text' => $text,
                    'description' => $description, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => self::BY, 'updated_by' => self::BY]);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
        $inv = Schema::connection('tm2inv');
        $inv->dropIfExists('inv_batch');
    }
};
