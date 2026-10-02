<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inventory & Warehouse (user 2026-10-02: "warehouse mapping", "input moving item antar warehouse", "capacity label dan
// barcode / QR code", "warna merah untuk DEAD stock", "Facility for QR Code, Labelling"). Rancangan: docs/warehouselayout_tier1.md.
//   Gudang = GEDUNG nyata (A5, A6, A10, ...): ukuran dalam meter, bentuk (kotak / huruf L / garis luar sendiri), 1-3 lantai.
//   wh_zone  = area penyimpanan di satu lantai gedung (Receiving, Paper, Ink, Finished Goods, ...), persegi dalam meter.
//   wh_loc   = bin (tempat stok menempel; sudah dirujuk semua tabel stok) -- hanya ditambah kolom.
//   inv_transfer(+_line) = dokumen pindah stok antar bin / antar gudang (RM, SFG, FG dalam satu dokumen); posting menulis
//     dua baris kartu stok per baris (keluar dari asal, masuk ke tujuan) lewat App\Services\Inventory\StockTransferService.
//   inv_label_print = riwayat cetak label (lokasi / item).
// Relasi tm2inv -> tm2mst = logis (beda database). Semua kolom baru boleh kosong: data & layar lama tidak berubah.
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');
        $mst->table('wh_warehouse', function (Blueprint $t) use ($mst) {
            foreach ([
                'length_m' => fn () => $t->decimal('length_m', 7, 2)->nullable(),          // sisi panjang gedung (sumbu X denah)
                'width_m' => fn () => $t->decimal('width_m', 7, 2)->nullable(),            // sisi lebar gedung (sumbu Y denah)
                'floor_count' => fn () => $t->unsignedTinyInteger('floor_count')->default(1),
                'shape' => fn () => $t->string('shape', 10)->default('RECT'),              // RECT | L | CUSTOM
                'notch_length_m' => fn () => $t->decimal('notch_length_m', 7, 2)->nullable(), // huruf L: potongan sudut, searah panjang
                'notch_width_m' => fn () => $t->decimal('notch_width_m', 7, 2)->nullable(),   // huruf L: potongan sudut, searah lebar
                'notch_corner' => fn () => $t->string('notch_corner', 2)->nullable(),      // NE | NW | SE | SW (sudut yang dipotong)
                'outline' => fn () => $t->string('outline', 500)->nullable(),              // CUSTOM: "0,0; 30,0; 30,12; 0,12" (meter)
                'address' => fn () => $t->string('address', 255)->nullable(),
            ] as $col => $add) {
                if (! $mst->hasColumn('wh_warehouse', $col)) {
                    $add();
                }
            }
        });

        if (! $mst->hasTable('wh_zone')) {
            $mst->create('wh_zone', function (Blueprint $t) {
                $t->id('id_zone');
                $t->unsignedBigInteger('warehouse_id');
                $t->foreign('warehouse_id')->references('id_warehouse')->on('wh_warehouse');
                $t->unsignedTinyInteger('floor_no')->default(1);
                $t->string('zone_code', 10);
                $t->string('zone_name', 100);
                $t->string('zone_type', 20)->default('STORAGE');   // RECEIVING | QC_HOLD | STORAGE | STAGING | SHIPPING | REJECT | CONSIGN | TOOLING
                $t->string('storage_mode', 10)->default('FLOOR');  // RACK | FLOOR | AREA
                $t->string('stock_class', 5)->default('ANY');      // RM | SFG | FG | ANY
                $t->decimal('map_x', 7, 2)->default(0);            // posisi sudut kiri-atas di denah lantai (meter)
                $t->decimal('map_y', 7, 2)->default(0);
                $t->decimal('map_w', 7, 2)->default(5);            // ukuran area (meter)
                $t->decimal('map_h', 7, 2)->default(5);
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->unique(['warehouse_id', 'zone_code'], 'uq_wh_zone_code');
            });
        }

        $mst->table('wh_loc', function (Blueprint $t) use ($mst) {
            if (! $mst->hasColumn('wh_loc', 'zone_id')) {
                $t->unsignedBigInteger('zone_id')->nullable()->index();
                $t->foreign('zone_id', 'fk_wh_loc_zone')->references('id_zone')->on('wh_zone')->nullOnDelete();
            }
            foreach ([
                'capacity_pallet' => fn () => $t->unsignedSmallInteger('capacity_pallet')->nullable(),
                'map_x' => fn () => $t->decimal('map_x', 7, 2)->nullable(),   // posisi bin di denah lantai (meter); kosong = disusun otomatis di dalam areanya
                'map_y' => fn () => $t->decimal('map_y', 7, 2)->nullable(),
                'block_reason' => fn () => $t->string('block_reason', 255)->nullable(), // alasan is_usable = 0
            ] as $col => $add) {
                if (! $mst->hasColumn('wh_loc', $col)) {
                    $add();
                }
            }
        });

        $inv = Schema::connection('tm2inv');
        if (! $inv->hasTable('inv_transfer')) {
            $inv->create('inv_transfer', function (Blueprint $t) {
                $t->id('id_transfer');
                $t->string('transfer_no', 20)->nullable()->unique();     // lahir saat Confirm: TFyy#####
                $t->date('transfer_date');
                $t->unsignedBigInteger('from_warehouse_id')->index();    // tm2mst.wh_warehouse
                $t->unsignedBigInteger('to_warehouse_id')->index();
                $t->string('reason', 30)->nullable();                    // REPLENISH | CONSOLIDATE | TO_PRODUCTION | DEAD_STOCK | OTHER
                $t->string('carried_by', 80)->nullable();
                $t->string('notes', 255)->nullable();
                $t->boolean('stat_confirm')->default(false);
                $t->boolean('stat_cancel')->default(false);
                $t->string('cancel_reason', 255)->nullable();
                $t->string('confirmed_by', 100)->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
            });
        }
        if (! $inv->hasTable('inv_transfer_line')) {
            $inv->create('inv_transfer_line', function (Blueprint $t) {
                $t->id('id_transfer_line');
                $t->foreignId('transfer_id')->constrained('inv_transfer', 'id_transfer')->cascadeOnDelete();
                $t->unsignedInteger('line_no')->default(1);
                $t->string('stock_class', 5);                            // RM | SFG | FG
                $t->unsignedBigInteger('item_id');                       // mat_raw / prod_part / prod_product
                $t->unsignedBigInteger('part_id')->nullable();           // FG: part dari product multi-part (kunci saldo FG = product + part)
                $t->string('item_code', 60)->nullable();                 // salinan saat Save
                $t->string('item_name', 255)->nullable();
                $t->unsignedBigInteger('from_location_id');              // tm2mst.wh_loc
                $t->unsignedBigInteger('to_location_id')->nullable();
                $t->string('lot_no', 100)->nullable();
                $t->decimal('qty', 20, 4)->default(0);
                $t->string('uom_code', 50)->nullable();
                $t->decimal('unit_cost', 30, 5)->nullable();             // harga rata-rata asal saat posting (nilai ikut pindah)
                $t->boolean('was_dead_stock')->default(false);           // saat posting: item ini sudah tergolong dead stock
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index(['stock_class', 'item_id']);
            });
        }
        if (! $inv->hasTable('inv_label_print')) {
            $inv->create('inv_label_print', function (Blueprint $t) {
                $t->id('id_label_print');
                $t->string('label_type', 10);                            // LOCATION | ITEM
                $t->string('ref_code', 100);                             // kode bin / kode item
                $t->string('ref_name', 255)->nullable();
                $t->string('stock_class', 5)->nullable();
                $t->unsignedBigInteger('ref_id')->nullable();
                $t->string('lot_no', 100)->nullable();
                $t->decimal('qty', 20, 4)->nullable();
                $t->string('uom_code', 50)->nullable();
                $t->unsignedSmallInteger('copies')->default(1);
                $t->string('label_size', 10)->nullable();
                $t->timestamp('printed_at')->nullable();
                $t->string('printed_by', 100)->nullable();
                $t->index(['label_type', 'ref_code']);
            });
        }

        // Batas "dead stock" di peta dan transfer = konstanta yang sama dengan jawaban asisten (ASSISTANT_DEADSTOCK_MONTHS, sudah ada).
        // Nomor transfer mengikuti pola dokumen v2 lain (awalan + 2 digit tahun + 5 digit urut).
        $sys = DB::connection('tm2sys');
        foreach ([
            'SIGN_TF_APPROVER' => 'Name printed under "Approved By" on the Stock Transfer print. Empty = blank line to sign by hand.',
            'SIGN_TF_ACKNOWLEDGER' => 'Name printed under "Acknowledged By" on the Stock Transfer print. Empty = blank line to sign by hand.',
        ] as $key => $text) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRINT', 'constant_group' => 'SIGNER', 'constant_text' => null,
                    'description' => $text, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['SIGN_TF_APPROVER', 'SIGN_TF_ACKNOWLEDGER'])->delete();
        $inv = Schema::connection('tm2inv');
        $inv->dropIfExists('inv_label_print');
        $inv->dropIfExists('inv_transfer_line');
        $inv->dropIfExists('inv_transfer');
        $mst = Schema::connection('tm2mst');
        $mst->table('wh_loc', function (Blueprint $t) use ($mst) {
            if ($mst->hasColumn('wh_loc', 'zone_id')) {
                $t->dropForeign('fk_wh_loc_zone');
                $t->dropColumn(['zone_id', 'capacity_pallet', 'map_x', 'map_y', 'block_reason']);
            }
        });
        $mst->dropIfExists('wh_zone');
        $mst->table('wh_warehouse', function (Blueprint $t) use ($mst) {
            if ($mst->hasColumn('wh_warehouse', 'length_m')) {
                $t->dropColumn(['length_m', 'width_m', 'floor_count', 'shape', 'notch_length_m', 'notch_width_m', 'notch_corner', 'outline', 'address']);
            }
        });
    }
};
