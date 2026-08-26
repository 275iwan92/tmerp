<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// inventory_tier3.md §3-8 -- delta header/detail/kartu stok RM+SFG+FG, tabel baru
// tm2inv, seed sys_constant. Dua penyimpangan sadar dari teks Tier 3 asli
// (diverifikasi langsung ke DB, sama pola dgn temuan PROC-SYSCONST-COL):
// 1. `sys_sequence` TIDAK ADA di project ini -- penomoran dokumen di seluruh
//    codebase (PR/PO/PV) pakai pola static nextDocNo() per model (scan MAX
//    existing + increment), bukan tabel sequence. Nomor dokumen INV
//    (IN/OUT/ADJ/OPN/RTP) akan memakai pola yang sama saat service dibangun --
//    TIDAK dibuatkan tabel sys_sequence baru.
// 2. `sys_constant` ada di **tm2mst**, bukan tm2sys (App\Models\Master\SysConstant),
//    kolomnya constant_key/constant_group/constant_number/constant_text/
//    description/is_active -- disesuaikan dari 'const_code'/'const_value' di
//    teks asli.
return new class extends Migration
{
    protected function hasIndex(string $connection, string $table, string $indexName): bool
    {
        return collect(\Illuminate\Support\Facades\DB::connection($connection)->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
    }

    public function up(): void
    {
        $inv = Schema::connection('tm2inv');
        $mst = Schema::connection('tm2mst');

        // ============ §3-4-5: delta rm_stock / rm_stock_d / rm_stockcard ============
        // posted_at = kolom TERAKHIR yang ditambahkan blok ini -- dipakai sbg penanda
        // "seluruh kolom+index rm_stock sudah dipasang" supaya migration aman diulang
        // (indeks/unique di bawah tidak punya guard hasIndex bawaan Laravel).
        if (! $inv->hasColumn('rm_stock', 'posted_at')) {
            $inv->table('rm_stock', function (Blueprint $table) use ($inv) {
                if (! $inv->hasColumn('rm_stock', 'ref_doc_type')) {
                    $table->string('ref_doc_type', 20)->nullable()->default('MANUAL')->comment('PV|WO|OUT|MANUAL')->after('doc_cat');
                    $table->unsignedBigInteger('ref_doc_id')->nullable()->comment('LOGICAL FK dokumen sumber')->after('ref_doc_type');
                    $table->string('ref_doc_no', 50)->nullable()->after('ref_doc_id');
                }
                if (! $inv->hasColumn('rm_stock', 'issued_to_dept')) {
                    $table->string('issued_to_dept', 100)->nullable()->after('warehouse_id');
                    $table->string('process_code', 20)->nullable()->after('issued_to_dept');
                    $table->string('reason_code', 30)->nullable()->comment('wajib utk AD/OP/RETURN; daftar di sys_constant INV_REASON_*')->after('process_code');
                    $table->string('attachment_path', 255)->nullable()->after('reason_code');
                }
                if (! $inv->hasColumn('rm_stock', 'opn_witness')) {
                    $table->string('opn_witness', 200)->nullable()->after('opn_officer');
                    $table->unsignedTinyInteger('full_scope')->default(0)->comment('Opname: 1 = seluruh item cakupan wajib masuk daftar')->after('opn_witness');
                }
                $table->unsignedBigInteger('period_id')->nullable()->after('post_date');
                $table->string('flow_status', 30)->default('DRAFT')->comment('DRAFT|SUBMITTED|APPROVED_L1..Ln|REVISION_NEEDED|POSTED|CANCELLED')->after('period_id');
                $table->string('posted_by', 100)->nullable()->after('flow_status');
                $table->dateTime('posted_at')->nullable()->after('posted_by');
                $table->index(['flow_status', 'doc_cat'], 'idx_rm_s_flow');
                $table->index(['ref_doc_type', 'ref_doc_id'], 'idx_rm_s_ref');
                $table->index('period_id', 'idx_rm_s_period');
                $table->unique('doc_no', 'uq_rm_s_docno');
            });
        }

        $inv->getConnection()->statement("ALTER TABLE rm_stock MODIFY COLUMN wo_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'LOGICAL FK tm2prd.prd_wo'");
        // 3 baris legacy doc_date NULL -- dibackfill dari create_log (tanggal dibuat)
        // supaya jujur (bukan tanggal karangan), bukan dihapus atau diberi tanggal hari ini.
        $inv->getConnection()->statement('UPDATE rm_stock SET doc_date = create_log WHERE doc_date IS NULL');
        $inv->getConnection()->statement('ALTER TABLE rm_stock MODIFY COLUMN doc_date DATETIME NOT NULL');

        if (! $inv->hasColumn('rm_stock_d', 'qty_physical')) {
            $inv->table('rm_stock_d', function (Blueprint $table) use ($inv) {
                if (! $inv->hasColumn('rm_stock_d', 'seq_no')) {
                    $table->unsignedInteger('seq_no')->default(0)->after('doc_id');
                }
                if (! $inv->hasColumn('rm_stock_d', 'return_condition')) {
                    $table->string('return_condition', 10)->nullable()->comment('GOOD|SCRAP -- hanya doc_cat=RETURN')->after('action_type');
                    $table->unsignedBigInteger('source_detail_id')->nullable()->comment('FK self -> rm_stock_d.doc_detail_id baris OUT yang diretur')->after('return_condition');
                }
                if (! $inv->hasColumn('rm_stock_d', 'qty_returned')) {
                    $table->decimal('qty_returned', 20, 4)->default(0)->after('qty');
                }
                $table->decimal('qty_system', 20, 4)->nullable()->comment('snapshot saldo saat baris ditarik, TIDAK dibaca ulang saat posting')->after('qty_booked');
                $table->decimal('qty_physical', 20, 4)->nullable()->after('qty_system');
                $table->index('source_detail_id', 'idx_rm_d_source');
                $table->index('action_type', 'idx_rm_d_action');
            });
        }

        $inv->getConnection()->statement("ALTER TABLE rm_stock_d MODIFY COLUMN worm_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'LOGICAL FK tm2prd.prd_wo_material'");
        $inv->getConnection()->statement('ALTER TABLE rm_stock_d MODIFY COLUMN location_id BIGINT UNSIGNED NOT NULL');

        if (! $inv->hasColumn('rm_stockcard', 'period_id')) {
            $inv->table('rm_stockcard', function (Blueprint $table) use ($inv) {
                if (! $inv->hasColumn('rm_stockcard', 'reference_name')) {
                    $table->string('reference_name', 100)->nullable()->comment("'tm2inv.rm_stock_d' | 'tm2pur.pv_line'")->after('reference_detail_id');
                }
                if (! $inv->hasColumn('rm_stockcard', 'value_bb')) {
                    $table->decimal('value_bb', 20, 4)->nullable();
                    $table->decimal('value_in', 20, 4)->nullable();
                    $table->decimal('value_out', 20, 4)->nullable();
                    $table->decimal('value_adj', 20, 4)->nullable();
                    $table->decimal('value_eb', 20, 4)->nullable();
                    $table->decimal('avg_unitprice', 30, 5)->nullable();
                }
                $table->unsignedBigInteger('period_id')->nullable();
                $table->unique(['reference_name', 'reference_detail_id'], 'uq_rm_sc_ref');
                $table->index(['material_id', 'location_id', 'doc_date', 'doc_id'], 'idx_rm_sc_saldo');
                $table->index('period_id', 'idx_rm_sc_period');
            });
        }

        // 1 baris legacy doc_date NULL (doc_id=27) -- dibackfill dari create_log, sama alasan dgn rm_stock di atas.
        $inv->getConnection()->statement('UPDATE rm_stockcard SET doc_date = create_log WHERE doc_date IS NULL');
        $inv->getConnection()->statement('ALTER TABLE rm_stockcard MODIFY COLUMN doc_date DATETIME NOT NULL');

        // 7 baris legacy location_id NULL/0 -- dibackfill ke lokasi fallback yang sama
        // dgn rm_stock_d ("UNKNOWN-LEGACY", dibuat migration 2026_08_21_000009).
        $fallbackLocId = $mst->getConnection()->table('m_wh_loc')->where('location_code', 'UNKNOWN-LEGACY')->value('location_id');
        $inv->getConnection()->table('rm_stockcard')->where(function ($q) {
            $q->whereNull('location_id')->orWhere('location_id', 0);
        })->update(['location_id' => $fallbackLocId]);
        $inv->getConnection()->statement('ALTER TABLE rm_stockcard MODIFY COLUMN location_id BIGINT UNSIGNED NOT NULL');

        // reference_name kolom BARU (default NULL) -- baris kartu stok historis dari
        // dump legacy semua berasal dari rm_stock_d (bukan pv_line, itu jalur baru yg
        // belum ada saat dump diambil). Dibackfill supaya v_inv_movement (§9) bisa
        // JOIN balik ke rm_stock utk doc_no/doc_cat -- tanpa ini semua NULL.
        $inv->getConnection()->statement("UPDATE rm_stockcard SET reference_name = 'tm2inv.rm_stock_d' WHERE reference_name IS NULL AND reference_detail_id IS NOT NULL");

        // ============ §6: delta sfg_* / fg_* ============
        foreach (['sfg_stock', 'fg_stock'] as $t) {
            // Dedup doc_no dulu (fg_stock: '26DN00001' x2) -- sama pola dgn rm_stock di
            // migration 2026_08_21_000009, sebelum unique index dipasang.
            $dups = $inv->getConnection()->table($t)->select('doc_no')->whereNotNull('doc_no')
                ->groupBy('doc_no')->havingRaw('COUNT(*) > 1')->pluck('doc_no');
            foreach ($dups as $docNo) {
                $rows = $inv->getConnection()->table($t)->where('doc_no', $docNo)->orderBy('doc_id')->get(['doc_id']);
                $n = 1;
                foreach ($rows as $row) {
                    if ($n > 1) {
                        $inv->getConnection()->table($t)->where('doc_id', $row->doc_id)->update(['doc_no' => "{$docNo}-DUP{$n}"]);
                    }
                    $n++;
                }
            }

            if (! $inv->hasColumn($t, 'posted_at')) {
                $inv->table($t, function (Blueprint $table) use ($inv, $t) {
                    if (! $inv->hasColumn($t, 'ref_doc_type')) {
                        $table->string('ref_doc_type', 20)->nullable()->default('MANUAL')->after('doc_cat');
                        $table->unsignedBigInteger('ref_doc_id')->nullable()->after('ref_doc_type');
                        $table->string('ref_doc_no', 50)->nullable()->after('ref_doc_id');
                        $table->string('issued_to_dept', 100)->nullable()->after('warehouse_id');
                        $table->string('process_code', 20)->nullable()->after('issued_to_dept');
                        $table->string('reason_code', 30)->nullable()->after('process_code');
                        $table->string('attachment_path', 255)->nullable()->after('reason_code');
                        $table->string('opn_witness', 200)->nullable()->after('opn_officer');
                        $table->unsignedTinyInteger('full_scope')->default(0)->after('opn_witness');
                    }
                    $table->unsignedBigInteger('period_id')->nullable()->after('post_date');
                    $table->string('flow_status', 30)->default('DRAFT')->after('period_id');
                    $table->string('posted_by', 100)->nullable()->after('flow_status');
                    $table->dateTime('posted_at')->nullable()->after('posted_by');
                });
            }
            $inv->table($t, function (Blueprint $table) use ($inv, $t) {
                if (! $this->hasIndex('tm2inv', $t, "idx_{$t}_flow")) {
                    $table->index(['flow_status', 'doc_cat'], "idx_{$t}_flow");
                }
                if (! $this->hasIndex('tm2inv', $t, "uq_{$t}_docno")) {
                    $table->unique('doc_no', "uq_{$t}_docno");
                }
            });
            $inv->getConnection()->statement("ALTER TABLE {$t} MODIFY COLUMN doc_date DATETIME NOT NULL");
        }

        foreach (['sfg_stock_d', 'fg_stock_d'] as $t) {
            if (! $inv->hasColumn($t, 'qty_physical')) {
                $inv->table($t, function (Blueprint $table) use ($inv, $t) {
                    if (! $inv->hasColumn($t, 'seq_no')) {
                        $table->unsignedInteger('seq_no')->default(0)->after('doc_id');
                        $table->string('action_type', 20)->nullable()->after('qty');
                        $table->string('return_condition', 10)->nullable()->after('action_type');
                        $table->unsignedBigInteger('source_detail_id')->nullable()->after('return_condition');
                        $table->decimal('qty_returned', 20, 4)->default(0)->after('qty');
                    }
                    $table->decimal('qty_system', 20, 4)->nullable()->after('qty_returned');
                    $table->decimal('qty_physical', 20, 4)->nullable()->after('qty_system');
                });
            }
            if (! $this->hasIndex('tm2inv', $t, "idx_{$t}_source")) {
                $inv->table($t, function (Blueprint $table) use ($t) {
                    $table->index('source_detail_id', "idx_{$t}_source");
                });
            }
            $inv->getConnection()->statement("ALTER TABLE {$t} MODIFY COLUMN location_id BIGINT UNSIGNED NOT NULL");
        }

        foreach (['sfg_stockcard', 'fg_stockcard'] as $t) {
            if (! $inv->hasColumn($t, 'period_id')) {
                $inv->table($t, function (Blueprint $table) use ($inv, $t) {
                    if (! $inv->hasColumn($t, 'reference_name')) {
                        $table->string('reference_name', 100)->nullable();
                        $table->decimal('value_bb', 20, 4)->nullable();
                        $table->decimal('value_in', 20, 4)->nullable();
                        $table->decimal('value_out', 20, 4)->nullable();
                        $table->decimal('value_adj', 20, 4)->nullable();
                        $table->decimal('value_eb', 20, 4)->nullable();
                        $table->decimal('avg_unitprice', 30, 5)->nullable();
                    }
                    $table->unsignedBigInteger('period_id')->nullable();
                });
            }
            if (! $this->hasIndex('tm2inv', $t, "uq_{$t}_ref")) {
                $inv->table($t, function (Blueprint $table) use ($t) {
                    $table->unique(['reference_name', 'reference_detail_id'], "uq_{$t}_ref");
                });
            }
            $inv->getConnection()->statement("ALTER TABLE {$t} MODIFY COLUMN doc_date DATETIME NOT NULL");
            $inv->getConnection()->statement("ALTER TABLE {$t} MODIFY COLUMN location_id BIGINT UNSIGNED NOT NULL");
        }

        // fg_stockcard: kunci saldo generated column (product_id + parts_id, NULL->0)
        if (! $inv->hasColumn('fg_stockcard', 'c_sku_key')) {
            $inv->getConnection()->statement("ALTER TABLE fg_stockcard ADD COLUMN c_sku_key VARCHAR(64) GENERATED ALWAYS AS (CONCAT(IFNULL(product_id,0),'-',IFNULL(parts_id,0))) STORED COMMENT 'Kunci saldo FG: produk+komponen, NULL dinormalkan ke 0'");
            $inv->table('fg_stockcard', function (Blueprint $table) {
                $table->index('c_sku_key', 'idx_fg_sc_sku');
            });
        }

        // ============ §7: tabel baru tm2inv ============
        if (! $inv->hasTable('inv_period')) {
            $inv->create('inv_period', function (Blueprint $table) {
                $table->id('period_id');
                $table->string('i_period_code', 6)->unique()->comment('YYYYMM');
                $table->date('i_date_start');
                $table->date('i_date_end');
                $table->string('i_status', 10)->default('OPEN')->comment('OPEN|CLOSING|CLOSED');
                $table->string('c_closed_by', 100)->nullable();
                $table->dateTime('c_closed_at')->nullable();
                $table->string('i_notes', 200)->nullable();
                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->index(['i_date_start', 'i_date_end'], 'idx_inv_period_range');
            });
        }

        if (! $inv->hasTable('inv_stock_layer')) {
            $inv->create('inv_stock_layer', function (Blueprint $table) {
                $table->id('layer_id');
                $table->string('i_stock_class', 5)->comment('RM|SFG|FG');
                $table->unsignedBigInteger('i_item_id');
                $table->unsignedBigInteger('i_location_id');
                $table->unsignedBigInteger('i_warehouse_id')->nullable();
                $table->string('i_lot_no', 100)->nullable();
                $table->decimal('i_qty_in', 20, 4);
                $table->decimal('c_qty_remaining', 20, 4)->comment('dikurangi saat OUT sesuai FIFO/LIFO');
                $table->decimal('i_unit_cost', 30, 5);
                $table->string('i_uom_code', 50)->nullable();
                $table->dateTime('i_received_at')->comment('dasar urutan FIFO/LIFO');
                $table->unsignedBigInteger('i_reference_id')->nullable();
                $table->string('i_reference_name', 100)->nullable();
                $table->boolean('c_is_exhausted')->default(false);
                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->string('updated_by', 100)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->index(['i_stock_class', 'i_item_id', 'i_location_id', 'c_is_exhausted', 'i_received_at'], 'idx_layer_pick');
                $table->index(['i_reference_name', 'i_reference_id'], 'idx_layer_ref');
            });
        }

        if (! $inv->hasTable('inv_layer_consumption')) {
            $inv->create('inv_layer_consumption', function (Blueprint $table) {
                $table->id('consumption_id');
                $table->unsignedBigInteger('i_layer_id');
                $table->unsignedBigInteger('i_doc_detail_id');
                $table->string('i_reference_name', 100);
                $table->decimal('i_qty', 20, 4);
                $table->decimal('i_unit_cost', 30, 5)->comment('snapshot harga layer saat dikonsumsi');
                $table->decimal('c_value', 20, 4)->comment('i_qty * i_unit_cost');
                $table->string('created_by', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('i_layer_id', 'idx_cons_layer');
                $table->index(['i_reference_name', 'i_doc_detail_id'], 'idx_cons_doc');
                $table->foreign('i_layer_id')->references('layer_id')->on('inv_stock_layer')->restrictOnDelete();
            });
        }

        // ============ §8.2 (disesuaikan): sys_constant di tm2mst ============
        // PENTING: setiap baris array WAJIB kunci yang sama persis (constant_number
        // DAN constant_text selalu ada, null bila tak dipakai) -- query builder
        // insert() memakai kolom dari baris PERTAMA utk seluruh batch; kalau baris
        // lain punya kunci berbeda, nilainya diam-diam hilang/jadi 0 (ketemu saat verify).
        $constants = [
            ['constant_key' => 'INV_ADJ_LIMIT_L1', 'constant_group' => 'INV', 'constant_number' => 5000000, 'constant_text' => null, 'description' => 'Batas nilai Adjustment Kepala Gudang', 'is_active' => true],
            ['constant_key' => 'INV_ADJ_LIMIT_L2', 'constant_group' => 'INV', 'constant_number' => 25000000, 'constant_text' => null, 'description' => 'Batas nilai Adjustment Manajer Operasional', 'is_active' => true],
            ['constant_key' => 'INV_ALLOW_NEGATIVE', 'constant_group' => 'INV', 'constant_number' => 0, 'constant_text' => null, 'description' => '0 = posting OUT ditolak bila saldo jadi negatif', 'is_active' => true],
            ['constant_key' => 'INV_BACKDATE_DAYS', 'constant_group' => 'INV', 'constant_number' => 7, 'constant_text' => null, 'description' => 'Maksimum hari mundur doc_date dalam periode OPEN', 'is_active' => true],
            ['constant_key' => 'INV_DEFAULT_COSTING', 'constant_group' => 'INV', 'constant_number' => null, 'constant_text' => 'AVG', 'description' => 'Metode harga perolehan default bila item belum diatur', 'is_active' => true],
            ['constant_key' => 'INV_REASON_ADJ', 'constant_group' => 'INV', 'constant_number' => null, 'constant_text' => 'OPNAME_SCHEDULED,DAMAGED,SHRINKAGE,WRONG_ENTRY,LOST,FOUND,MIGRATION', 'description' => 'Alasan Adjustment/Opname', 'is_active' => true],
            ['constant_key' => 'INV_REASON_RTP', 'constant_group' => 'INV', 'constant_number' => null, 'constant_text' => 'SURPLUS,WO_CANCELLED,WRONG_ITEM,DEFECT_FOUND,SPEC_CHANGED', 'description' => 'Alasan Return from Production', 'is_active' => true],
            ['constant_key' => 'INV_PROCESS_CODE', 'constant_group' => 'INV', 'constant_number' => null, 'constant_text' => 'BELAH,CETAK,COATING,LAMINASI,PLONG,LEM,KUPAS,PACKING', 'description' => 'Urutan proses produksi', 'is_active' => true],
        ];
        foreach ($constants as $c) {
            $mst->getConnection()->table('sys_constant')->updateOrInsert(['constant_key' => $c['constant_key']], $c);
        }

        // ============ inv_period: periode berjalan ============
        $inv->getConnection()->table('inv_period')->insertOrIgnore([
            'i_period_code' => now()->format('Ym'),
            'i_date_start' => now()->startOfMonth()->toDateString(),
            'i_date_end' => now()->endOfMonth()->toDateString(),
            'i_status' => 'OPEN',
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);
    }

    public function down(): void
    {
        // Skema pondasi Inventory -- tidak didesain untuk rollback aman
        // (banyak MODIFY NOT NULL di atas data riil). Revert manual bila perlu.
    }
};
