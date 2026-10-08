<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inventory & Warehouse v2.01 "Transit, hitung stok, subkon, pemilik" (docs/md_for_systems/inventorywarehouse_v201_technical_plan.md):
//   inv_transfer_line  : penyelesaian selisih In Transit (LOSS / RETURN)
//   wh_task            : kolom tugas COUNT (qty sistem, qty hitung, area, dokumen penyesuaian)
//   inv_subcon_issue (+ _line) : kirim bahan ke subkon, induk = PO Subkon (D22), stok pindah ke bin SUBCON vendor
//   inv_tool           : register die / plat / klise (lokasi + status)
//   sys_app_menu       : Warehouse > In Transit, Count Tasks, Send to Subcontractor; Report > Stock at Vendor, Customer Material; Inventory > Tools
// Dijalankan di ddt* dulu: php artisan migrate --env=mockup --path=database/migrations/2026_10_09_000210_inventory_transit_count_subcon.php --force
return new class extends Migration
{
    private const BY = 'claude (inventory v2.01 transit)';

    public function up(): void
    {
        $inv = Schema::connection('tm2inv');

        $inv->table('inv_transfer_line', function (Blueprint $t) use ($inv) {
            foreach (['diff_resolution' => fn () => $t->string('diff_resolution', 10)->nullable()->comment('LOSS / RETURN'),
                'diff_resolved_by' => fn () => $t->string('diff_resolved_by', 100)->nullable(),
                'diff_resolved_at' => fn () => $t->timestamp('diff_resolved_at')->nullable()] as $col => $add) {
                if (! $inv->hasColumn('inv_transfer_line', $col)) {
                    $add();
                }
            }
        });

        $inv->table('wh_task', function (Blueprint $t) use ($inv) {
            foreach (['system_qty' => fn () => $t->decimal('system_qty', 20, 4)->nullable()->comment('saldo sistem saat tugas hitung dibuat'),
                'counted_qty' => fn () => $t->decimal('counted_qty', 20, 4)->nullable(),
                'count_zone_id' => fn () => $t->unsignedBigInteger('count_zone_id')->nullable()->index()->comment('tm2mst.wh_zone (logis)'),
                'adjustment_doc_id' => fn () => $t->unsignedBigInteger('adjustment_doc_id')->nullable()->comment('dokumen penyesuaian (rm_/sfg_/fg_stock, sesuai stock_class)'),
                'adjustment_doc_no' => fn () => $t->string('adjustment_doc_no', 50)->nullable()] as $col => $add) {
                if (! $inv->hasColumn('wh_task', $col)) {
                    $add();
                }
            }
        });

        if (! $inv->hasTable('inv_subcon_issue')) {
            $inv->create('inv_subcon_issue', function (Blueprint $t) {
                $t->id('id_subcon_issue');
                $t->string('issue_no', 20)->unique();
                $t->date('issue_date');
                $t->unsignedBigInteger('po_id')->comment('tm2pur.po_order, PO Subkon (logis)');
                $t->string('po_no', 30)->nullable();
                $t->unsignedBigInteger('supplier_id')->comment('tm2mst.sup_supplier (logis)');
                $t->date('due_return_date')->nullable();
                $t->string('status', 10)->default('SENT')->comment('SENT / CLOSED / CANCELLED');
                $t->unsignedBigInteger('transfer_id')->nullable()->comment('dokumen Stock Transfer yang memindahkan stok ke bin vendor');
                $t->string('notes', 255)->nullable();
                $t->string('cancel_reason', 255)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index('po_id');
                $t->index('supplier_id');
                $t->foreign('transfer_id', 'fk_subcon_issue_transfer')->references('id_transfer')->on('inv_transfer')->nullOnDelete();
            });
        }
        if (! $inv->hasTable('inv_subcon_issue_line')) {
            $inv->create('inv_subcon_issue_line', function (Blueprint $t) {
                $t->id('id_subcon_issue_line');
                $t->unsignedBigInteger('issue_id');
                $t->unsignedInteger('line_no');
                $t->unsignedBigInteger('po_line_id')->nullable()->comment('tm2pur.po_line (logis)');
                $t->string('stock_class', 5)->default('RM');
                $t->unsignedBigInteger('item_id')->comment('tm2mst.mat_raw (logis)');
                $t->string('item_code', 60)->nullable();
                $t->string('item_name', 255)->nullable();
                $t->unsignedBigInteger('from_location_id')->comment('bin asal (logis)');
                $t->unsignedBigInteger('vendor_location_id')->comment('bin SUBCON vendor (logis)');
                $t->string('uom_code', 50)->nullable();
                $t->decimal('qty_sent', 20, 4)->default(0);
                $t->decimal('qty_consumed', 20, 4)->default(0)->comment('terpakai di vendor (hasil sudah diterima)');
                $t->decimal('qty_returned', 20, 4)->default(0)->comment('dikembalikan belum terpakai');
                $t->decimal('unit_cost', 30, 5)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index('po_line_id');
                $t->foreign('issue_id', 'fk_subcon_line_issue')->references('id_subcon_issue')->on('inv_subcon_issue')->cascadeOnDelete();
            });
        }

        if (! $inv->hasTable('inv_tool')) {
            $inv->create('inv_tool', function (Blueprint $t) {
                $t->id('id_tool');
                $t->string('tool_no', 30)->unique();
                $t->string('tool_type', 10)->default('DIE')->comment('DIE / PLATE / BLOCK');
                $t->string('description', 255)->nullable();
                $t->string('status', 12)->default('AVAILABLE')->comment('AVAILABLE / IN_USE / AT_VENDOR / REPAIR / RETIRED');
                $t->unsignedBigInteger('location_id')->nullable()->comment('tm2mst.wh_loc (logis)');
                $t->unsignedInteger('impressions')->default(0);
                $t->string('notes', 255)->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index('status');
            });
        }

        $this->menu();
    }

    private function menu(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $row = fn (array $v) => $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now(), 'target_kind' => 'app'];
        $inv = $menu()->where('code', 'INV')->where('menu_type', 'module')->value('id');
        $wh = $menu()->where('code', 'INV_WH')->value('id');
        $rpt = $menu()->where('code', 'INV_WH_RPT')->value('id');
        if (! $inv || ! $wh) {
            return;
        }
        // kode => [induk, label, ikon, slug, jenis, urutan]
        $items = [
            'INV_WH_TRANSIT' => [$wh, 'In Transit', 'fa-solid fa-truck-ramp-box', 'wh-in-transit', 'form', 17],
            'INV_WH_COUNT' => [$wh, 'Count Tasks', 'fa-solid fa-clipboard-list', 'wh-count-tasks', 'form', 18],
            'INV_WH_SUBCON' => [$wh, 'Send to Subcontractor', 'fa-solid fa-dolly', 'subcon-send', 'form', 27],
            'INV_WH_RPT_VENDOR' => [$rpt, 'Stock at Vendor', 'fa-solid fa-industry', 'stock-at-vendor', 'report', 80],
            'INV_WH_RPT_CUSTMAT' => [$rpt, 'Customer Material', 'fa-solid fa-user-tag', 'customer-material', 'report', 90],
        ];
        $tools = $menu()->where('code', 'INV_TOOLS')->value('id')
            ?? $menu()->insertGetId($row(['parent_id' => $inv, 'code' => 'INV_TOOLS', 'label' => 'Tools', 'icon' => 'fa-solid fa-screwdriver-wrench', 'menu_type' => 'group', 'sort_order' => 8]));
        $items['INV_TOOLS_REG'] = [$tools, 'Tool Register', 'fa-solid fa-ruler-combined', 'inv-tools', 'grid', 10];
        foreach ($items as $code => [$parent, $label, $icon, $slug, $type, $sort]) {
            if ($parent && ! $menu()->where('code', $code)->exists()) {
                $menu()->insert($row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $type, 'target' => 'v2:'.$slug,
                    'sort_order' => $sort, 'doc_slug' => 'warehouselayout']));
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', ['INV_WH_TRANSIT', 'INV_WH_COUNT', 'INV_WH_SUBCON', 'INV_WH_RPT_VENDOR', 'INV_WH_RPT_CUSTMAT', 'INV_TOOLS_REG', 'INV_TOOLS'])->delete();
        $inv = Schema::connection('tm2inv');
        $inv->dropIfExists('inv_tool');
        $inv->dropIfExists('inv_subcon_issue_line');
        $inv->dropIfExists('inv_subcon_issue');
    }
};
