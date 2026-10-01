<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Material Planning (docs/materialbooking_tier1.md §6-§8, keputusan user 2026-10-01): Booking dari CO + MRP/MPS BARU.
// Engine lama MRP/MPS tetap arsip -- prd_mrp_run / prd_mrp_requirement / prd_mts_forecast TIDAK dipakai (CLAUDE.md §6B).
// - tm2inv.rm_booking(_d) (sudah ada, 0 baris) dipakai ulang: 1 header per CO; header stat_confirm=1 = hard (view v_rm_available
//   sudah mengurangi qty_book - qty_issued dari header confirmed). + rm_booking_alloc: sumber penutup tiap kebutuhan.
// - tm2prd.prd_demand_forecast: forecast MPS per customer x product x periode (CO memakan forecast periode yang sama).
// - tm2mst.mat_usage_standard: standar pemakaian tinta/varnish/coating/film/lem (nilai awal = usulan, diubah user).
// - tm2mst.mat_category.lead_time_days: lead time beli per kategori (item mat_raw.lead_time_days = override).
return new class extends Migration
{
    public function up(): void
    {
        $inv = Schema::connection('tm2inv');
        $inv->table('rm_booking', function (Blueprint $t) {
            $t->unsignedBigInteger('co_id')->nullable()->after('booking_type')->comment('tm2sdi.co_order.id_order (logis)');
            $t->string('doc_status', 20)->default('DRAFT')->after('co_id')->comment('DRAFT / CONFIRMED / CLOSED / CANCELLED');
            $t->dateTime('last_run_at')->nullable()->after('doc_status');
            $t->string('co_fingerprint', 64)->nullable()->after('last_run_at')->comment('hash qty/tanggal CO saat Confirm; beda = CO berubah');
            $t->index('co_id');
        });
        $inv->table('rm_booking_d', function (Blueprint $t) {
            $t->unsignedBigInteger('co_delivery_id')->nullable()->after('co_detail_id');
            $t->unsignedBigInteger('part_id')->nullable()->after('product_id')->comment('tm2mst.prod_part');
            $t->string('material_role', 20)->nullable()->after('material_id')->comment('FG / SUBSTRATE / INK / VARNISH / COATING / FILM / GLUE');
            $t->date('need_date')->nullable()->after('qty_need');
            $t->string('line_status', 20)->nullable()->after('need_date')
                ->comment('STOCK / PEGGED_PO / LATE_PO / PEGGED_PR / SHORT / NEED_ITEM / NEED_LAYOUT / ISSUED / RELEASED');
            $t->string('calc_source', 20)->nullable()->after('line_status')->comment('LAYOUT / COSTING / BOM / USAGE_STD / FG_STOCK / MANUAL');
            $t->string('calc_note', 255)->nullable()->after('calc_source')->comment('rumus yang dipakai, tampil di layar');
            $t->string('spec_text', 150)->nullable()->after('calc_note')->comment('spesifikasi bila item belum dipilih');
            $t->decimal('qty_short', 20, 4)->default(0)->after('qty_purchase');
        });
        if (! $inv->hasTable('rm_booking_alloc')) {
            $inv->create('rm_booking_alloc', function (Blueprint $t) {
                $t->id('id_booking_alloc');
                $t->unsignedBigInteger('booking_detail_id');
                $t->string('source_type', 10)->comment('STOCK / FG / PO / PR');
                $t->unsignedBigInteger('source_id')->nullable()->comment('location_id (STOCK/FG) / po_line_id / pr_line_id');
                $t->string('source_no', 50)->nullable();
                $t->date('source_date')->nullable()->comment('tanggal tiba PO / butuh PR');
                $t->decimal('qty', 20, 4);
                $t->boolean('is_late')->default(false);
                $t->string('created_by', 100)->nullable();
                $t->timestamps();
                $t->foreign('booking_detail_id')->references('booking_detail_id')->on('rm_booking_d')->cascadeOnDelete();
            });
        }

        $prd = Schema::connection('tm2prd');
        if (! $prd->hasTable('prd_demand_forecast')) {
            $prd->create('prd_demand_forecast', function (Blueprint $t) {
                $t->id('id_forecast');
                $t->unsignedBigInteger('customer_id')->comment('tm2mst.cust_customer (logis)');
                $t->unsignedBigInteger('product_id')->comment('tm2mst.prod_product (logis)');
                $t->date('period_start')->comment('hari pertama bulan');
                $t->decimal('qty_forecast', 18, 2);
                $t->string('notes', 255)->nullable();
                $t->boolean('activate')->default(true);
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->timestamps();
                $t->unique(['customer_id', 'product_id', 'period_start']);
            });
        }

        $mst = Schema::connection('tm2mst');
        if (! $mst->hasTable('mat_usage_standard')) {
            $mst->create('mat_usage_standard', function (Blueprint $t) {
                $t->id('id_usage_standard');
                $t->string('usage_code', 30)->unique();
                $t->string('usage_name', 100);
                $t->string('material_role', 20)->comment('INK / VARNISH / COATING / FILM / GLUE');
                $t->string('basis', 30)->comment('PER_M2_FULL_COVERAGE / PER_M2 / PER_1000_PCS / PER_SHEET');
                $t->decimal('qty_per_basis', 18, 6);
                $t->string('uom', 20);
                $t->string('trigger_keyword', 100)->nullable()->comment('kata di prod_part.finishing / cfg yang memicu standar ini (pisah koma)');
                $t->unsignedBigInteger('default_material_id')->nullable()->comment('tm2mst.mat_raw');
                $t->string('notes', 255)->nullable();
                $t->boolean('activate')->default(true);
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->timestamps();
            });
            $now = now();
            // Nilai awal = usulan umum industri offset (bukan data pabrik) -> diubah PPIC di Master Data > Material Usage Standard.
            foreach ([
                ['INK_PROCESS', 'Process ink (per color)', 'INK', 'PER_M2_FULL_COVERAGE', 0.0015, 'KG', null, 'about 1.5 g per m2 at 100% coverage'],
                ['VARNISH_OPV', 'Overprint varnish', 'VARNISH', 'PER_M2', 0.004, 'KG', 'varnish,opv', 'about 4 g per m2'],
                ['UV_COATING', 'UV coating', 'COATING', 'PER_M2', 0.006, 'KG', 'uv,coating', 'about 6 g per m2'],
                ['LAMINATION_FILM', 'Lamination film', 'FILM', 'PER_M2', 1.05, 'M2', 'lamin,film,doff,glossy', '1 m2 film per m2 sheet + 5% waste'],
                ['GLUE_SIDE_SEAM', 'Side seam glue', 'GLUE', 'PER_1000_PCS', 0.25, 'KG', 'glue,lem', 'about 0.25 kg per 1000 boxes'],
            ] as [$code, $name, $role, $basis, $qty, $uom, $kw, $note]) {
                DB::connection('tm2mst')->table('mat_usage_standard')->insert(['usage_code' => $code, 'usage_name' => $name, 'material_role' => $role,
                    'basis' => $basis, 'qty_per_basis' => $qty, 'uom' => $uom, 'trigger_keyword' => $kw, 'notes' => $note, 'activate' => true,
                    'created_by' => 'system', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! $mst->hasColumn('mat_category', 'lead_time_days')) {
            $mst->table('mat_category', fn (Blueprint $t) => $t->unsignedSmallInteger('lead_time_days')->nullable()->after('stock_class')
                ->comment('lead time beli (hari kerja); mat_raw.lead_time_days = override per item'));
        }

        $sys = DB::connection('tm2sys');
        $now = now();
        foreach ([
            ['BOOKING_NEED_WORKDAYS', 3, 'Material must be ready at the latest this many working days after the CO date (or the due date if earlier)'],
            ['BOOKING_DEFAULT_WASTE_PCT', 5, 'Sheet waste % used when the layout has no waste % (costing / BOM source)'],
            ['BOOKING_INK_COVERAGE_PCT', 25, 'Ink coverage % per color used when the part has no ink coverage data'],
            ['PURCHASE_LEAD_DAYS_DEFAULT', 7, 'Purchase lead time (working days) when neither the item nor its category has one'],
            ['MRP_HORIZON_DAYS', 90, 'Material Planning (MRP) looks this many days ahead'],
        ] as [$key, $num, $desc]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'PRODUCTION', 'constant_group' => 'MATERIAL_PLANNING',
                    'constant_number' => $num, 'activate' => true, 'created_at' => $now, 'updated_at' => $now, 'created_by' => 'system', 'description' => $desc]);
            }
        }

        $upd = fn (string $code, array $v) => $sys->table('sys_app_menu')->where('code', $code)->update($v + ['target_kind' => 'app', 'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => $now]);
        $upd('PROD_MRP', ['label' => 'Material Planning (MRP)', 'target' => 'v2:material-planning', 'object_type' => 'grid', 'status_note' => 'v2 MRP: kebutuhan semua bahan dari CO + forecast, netting stok/PO/PR, usulan PR']);
        $upd('PROD_MTS_FORECAST', ['label' => 'Demand Forecast (MPS)', 'target' => 'v2:demand-forecast', 'object_type' => 'grid', 'status_note' => 'v2 MPS: forecast per customer x product x bulan']);
        $plan = $sys->table('sys_app_menu')->where('code', 'PROD_MRP')->first();
        $ins = function (string $code, ?object $near, array $v) use ($sys, $now) {
            if ($near && ! $sys->table('sys_app_menu')->where('code', $code)->exists()) {
                $sys->table('sys_app_menu')->insert($v + ['parent_id' => $near->parent_id, 'code' => $code, 'menu_type' => 'item', 'object_type' => 'grid',
                    'target_kind' => 'app', 'activate' => 1, 'created_at' => $now, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => $now]);
            }
        };
        $ins('PROD_BOOKING', $plan, ['label' => 'Material Booking', 'target' => 'v2:material-booking', 'sort_order' => 19, 'status_note' => 'v2 Booking bahan per CO (usulan -> Confirm = kunci)']);
        $matcat = $sys->table('sys_app_menu')->where('code', 'MD_MATCAT')->first();
        $ins('MD_USAGE_STD', $matcat, ['label' => 'Material Usage Standard', 'target' => 'v2:usage-standard', 'sort_order' => 46, 'status_note' => 'v2 standar pemakaian tinta/varnish/film/lem']);
        $ins('MD_LEAD_TIME', $matcat, ['label' => 'Purchase Lead Time', 'target' => 'v2:purchase-lead-time', 'sort_order' => 47, 'status_note' => 'v2 lead time beli per kategori bahan']);
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->whereIn('code', ['PROD_BOOKING', 'MD_USAGE_STD', 'MD_LEAD_TIME'])->delete();
        $sys->table('sys_constant')->where('constant_group', 'MATERIAL_PLANNING')->delete();
        Schema::connection('tm2inv')->dropIfExists('rm_booking_alloc');
        Schema::connection('tm2inv')->table('rm_booking_d', fn (Blueprint $t) => $t->dropColumn(['co_delivery_id', 'part_id', 'material_role', 'need_date',
            'line_status', 'calc_source', 'calc_note', 'spec_text', 'qty_short']));
        Schema::connection('tm2inv')->table('rm_booking', function (Blueprint $t) {
            $t->dropIndex(['co_id']);
            $t->dropColumn(['co_id', 'doc_status', 'last_run_at', 'co_fingerprint']);
        });
        Schema::connection('tm2prd')->dropIfExists('prd_demand_forecast');
        Schema::connection('tm2mst')->dropIfExists('mat_usage_standard');
        Schema::connection('tm2mst')->table('mat_category', fn (Blueprint $t) => $t->dropColumn('lead_time_days'));
    }
};
