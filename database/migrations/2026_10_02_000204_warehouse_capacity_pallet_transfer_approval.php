<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inventory & Warehouse, putaran 2 (jawaban user 2026-10-02):
//   - "kapasitas dalam luasan dan tinggi gedung, termasuk lantai 1 dan 2 dan 3; kapasitas kg boleh juga"
//       -> tinggi per lantai di gedung (height_f1_m .. height_f3_m) + tinggi tumpuk per area (stack_height_m).
//          Luas = dari denah; volume = luas x tinggi. Kg tetap ada.
//   - "transfer untuk sementara approval di-skip tapi fasilitas approval dibuat saja dan bisa diaktifkan kapan saja"
//       -> inv_transfer.flow_status (SUBMITTED / REJECTED) untuk alur flow engine modul TF.
//   - "label palet ... wajib buat label setiap kedatangan dan setiap habis pemakaian dalam satu palet", "kode plate dari
//     supplier harus disertakan", "kertas, tinta pasti ada tanggal kedaluwarsa, pastikan ada tag"
//       -> inv_pallet (satu palet berlabel: item, lot, kode palet supplier, tanggal datang, kedaluwarsa, qty awal dan
//          sisa, bin sekarang) + inv_pallet_log (dibuat / dilabel ulang / dipindah / ditutup).
//          inv_transfer_line.pallet_id = baris transfer yang memindahkan satu palet.
// Semua kolom baru boleh kosong; relasi tm2inv -> tm2mst = logis.
return new class extends Migration
{
    private const CONSTANTS = [
        'WH_BIN_FULL_PCT' => [90, 'Warehouse Map: a bin counts as Full from this percent of its capacity (weight or volume, whichever is higher).'],
        'WH_PAPER_BULK_CM3_PER_G' => [1.3, 'Paper thickness estimate for the volume in a bin: thickness (micron) = grammage x this number. Used when the item has no thickness in its master.'],
        'WH_EXPIRY_SOON_DAYS' => [30, 'A pallet is tagged "Expires soon" when its expiry date is this many days away or less.'],
    ];

    public function up(): void
    {
        $mst = Schema::connection('tm2mst');
        $mst->table('wh_warehouse', function (Blueprint $t) use ($mst) {
            foreach (['height_f1_m', 'height_f2_m', 'height_f3_m'] as $col) {
                if (! $mst->hasColumn('wh_warehouse', $col)) {
                    $t->decimal($col, 5, 2)->nullable();   // tinggi bersih lantai itu (meter)
                }
            }
        });
        $mst->table('wh_zone', function (Blueprint $t) use ($mst) {
            if (! $mst->hasColumn('wh_zone', 'stack_height_m')) {
                $t->decimal('stack_height_m', 5, 2)->nullable();   // tinggi tumpuk / rak yang boleh dipakai di area ini; kosong = tinggi lantai
            }
        });

        $inv = Schema::connection('tm2inv');
        $inv->table('inv_transfer', function (Blueprint $t) use ($inv) {
            if (! $inv->hasColumn('inv_transfer', 'flow_status')) {
                $t->string('flow_status', 20)->nullable();   // SUBMITTED (menunggu approval) | REJECTED; kosong = tanpa approval
            }
        });
        $inv->table('inv_transfer_line', function (Blueprint $t) use ($inv) {
            if (! $inv->hasColumn('inv_transfer_line', 'pallet_id')) {
                $t->unsignedBigInteger('pallet_id')->nullable()->index();
            }
        });
        if (! $inv->hasTable('inv_pallet')) {
            $inv->create('inv_pallet', function (Blueprint $t) {
                $t->id('id_pallet');
                $t->string('pallet_no', 20)->unique();                   // PLyy#####
                $t->string('stock_class', 5);                            // RM | SFG | FG
                $t->unsignedBigInteger('item_id');
                $t->unsignedBigInteger('part_id')->nullable();
                $t->string('item_code', 60)->nullable();
                $t->string('item_name', 255)->nullable();
                $t->string('lot_no', 100)->nullable();
                $t->string('supplier_pallet_code', 60)->nullable();      // kode palet / kemasan dari supplier
                $t->string('ref_doc_no', 50)->nullable();                // dokumen penerimaan (SPB / Stock In)
                $t->string('uom_code', 50)->nullable();
                $t->decimal('qty_initial', 20, 4)->default(0);           // qty saat datang
                $t->decimal('qty', 20, 4)->default(0);                   // sisa sekarang (label terakhir)
                $t->unsignedBigInteger('location_id')->index();          // bin sekarang (tm2mst.wh_loc)
                $t->date('arrived_at')->nullable();
                $t->date('expiry_date')->nullable();
                $t->string('status', 10)->default('ACTIVE');             // ACTIVE | CLOSED (habis)
                $t->unsignedSmallInteger('label_count')->default(0);
                $t->timestamp('last_label_at')->nullable();
                $t->string('notes', 255)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index(['stock_class', 'item_id', 'location_id']);
            });
        }
        if (! $inv->hasTable('inv_pallet_log')) {
            $inv->create('inv_pallet_log', function (Blueprint $t) {
                $t->id('id_pallet_log');
                $t->foreignId('pallet_id')->constrained('inv_pallet', 'id_pallet')->cascadeOnDelete();
                $t->string('action', 12);                                // CREATED | RELABEL | MOVED | CLOSED
                $t->decimal('qty_before', 20, 4)->nullable();
                $t->decimal('qty_after', 20, 4)->nullable();
                $t->unsignedBigInteger('from_location_id')->nullable();
                $t->unsignedBigInteger('to_location_id')->nullable();
                $t->string('ref_no', 50)->nullable();                    // mis. nomor transfer
                $t->string('note', 255)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
            });
        }

        $sys = DB::connection('tm2sys');
        foreach (self::CONSTANTS as $key => [$value, $text]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'INVENTORY', 'constant_group' => 'WAREHOUSE', 'constant_number' => $value,
                    'description' => $text, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
        $inv = Schema::connection('tm2inv');
        $inv->dropIfExists('inv_pallet_log');
        $inv->dropIfExists('inv_pallet');
        $inv->table('inv_transfer_line', fn (Blueprint $t) => $inv->hasColumn('inv_transfer_line', 'pallet_id') ? $t->dropColumn('pallet_id') : null);
        $inv->table('inv_transfer', fn (Blueprint $t) => $inv->hasColumn('inv_transfer', 'flow_status') ? $t->dropColumn('flow_status') : null);
        $mst = Schema::connection('tm2mst');
        $mst->table('wh_zone', fn (Blueprint $t) => $mst->hasColumn('wh_zone', 'stack_height_m') ? $t->dropColumn('stack_height_m') : null);
        $mst->table('wh_warehouse', fn (Blueprint $t) => $mst->hasColumn('wh_warehouse', 'height_f1_m') ? $t->dropColumn(['height_f1_m', 'height_f2_m', 'height_f3_m']) : null);
    }
};
