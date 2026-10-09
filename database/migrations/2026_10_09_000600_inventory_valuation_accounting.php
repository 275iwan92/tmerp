<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inventory v2.01, sisi valuasi dan akuntansi (D8, D17, D18; V-03, V-04, V-06, V-07, V-10):
//   tm2inv : inv_landed_cost (+_line), inv_repost_log, inv_reconcile_log, inv_obsolete_provision (+_line)
//   tm2sys : konstanta akun dan persentase provisi (Settings > Constants), baris menu Inventory > Valuation & Period
//   tm2acc : akun baru yang dipakai jurnal (hanya bila kodenya belum ada)
// Jurnalnya didokumentasikan di docs/md_for_systems/inventorywarehouse_v201_valuation_journals.md. Idempoten.
// Jalankan hanya dengan --env=mockup sampai rilis ke tm2*.
return new class extends Migration
{
    private const BY = 'claude (inventory v2.01 valuation)';

    // kode akun => [nama, tipe, saldo normal, kode induk, fs_line, cf_line]
    private const ACCOUNTS = [
        '211950' => ['Hutang Biaya Landed Cost (Freight/Duty/Insurance)', 'LIABILITY', 'CREDIT', null, 'AP', 'WC_AP'],
        '518000' => ['Selisih Landed Cost (Stok Sudah Keluar)', 'COGS', 'DEBIT', '500000', 'COGS', 'PROFIT'],
        '620000' => ['Beban Penyisihan Persediaan Usang', 'EXPENSE', 'DEBIT', '600000', 'GA', 'PROFIT'],
        '113900' => ['Penyisihan Persediaan Usang (Kontra Persediaan)', 'ASSET', 'CREDIT', '110000', 'INVENTORY', 'WC_INV'],
    ];

    // kunci => [nilai angka, nilai teks, keterangan]
    private const CONSTANTS = [
        'INV_OBSOLETE_PCT_SLOW' => [10, null, 'Provision percentage of the stock value for items classed Slow (no movement for INV_AGING_SLOW_DAYS or more).'],
        'INV_OBSOLETE_PCT_NONMOVING' => [25, null, 'Provision percentage of the stock value for items classed Non-moving (INV_AGING_NONMOVING_DAYS or more).'],
        'INV_OBSOLETE_PCT_DEAD' => [50, null, 'Provision percentage of the stock value for items classed Dead (INV_AGING_DEAD_DAYS or more, or never moved).'],
        'INV_ACC_LANDED_PAYABLE' => [null, '211950', 'Account code credited by a landed cost (freight, duty, insurance payable).'],
        'INV_ACC_LANDED_VARIANCE' => [null, '518000', 'Account code debited with the part of a late landed cost whose stock has already left (D17).'],
        'INV_ACC_LANDED_COGS' => [null, '511000', 'Account code that receives the landed cost variance when the period is closed to cost of goods sold.'],
        'INV_ACC_OBS_EXPENSE' => [null, '620000', 'Account code debited when the obsolete stock provision is posted.'],
        'INV_ACC_OBS_PROVISION' => [null, '113900', 'Account code credited when the obsolete stock provision is posted (contra inventory).'],
        'INV_RECON_ACCTS_RM' => [null, '113100,113200,113600', 'Inventory account codes (comma separated) that hold Raw Material value; compared with the stock card in Daily Reconciliation.'],
        'INV_RECON_ACCTS_SFG' => [null, '113400', 'Inventory account codes (comma separated) that hold Semi Finished Goods value.'],
        'INV_RECON_ACCTS_FG' => [null, '113500', 'Inventory account codes (comma separated) that hold Finished Goods value.'],
        'INV_RECON_TOLERANCE' => [1, null, 'A reconciliation difference above this amount (IDR) is flagged.'],
    ];

    // kode => [label, ikon, slug, jenis objek, urutan]
    private const MENU = [
        'INV_VAL_PERIOD' => ['Valuation & Period', 'fa-solid fa-scale-balanced', 'inv-valuation', 'form', 10],
        'INV_VAL_LANDED' => ['Landed Cost', 'fa-solid fa-truck-ramp-box', 'landed-cost', 'form', 20],
        'INV_VAL_OBS' => ['Obsolete Stock', 'fa-solid fa-hourglass-end', 'obsolete-stock', 'form', 30],
    ];

    private const REPORTS = [
        'INV_VAL_GRIR' => ['GR/IR Aging', 'fa-solid fa-file-circle-question', 'inv-grir-aging', 'report', 10],
        'INV_VAL_RECON' => ['Daily Reconciliation', 'fa-solid fa-scale-unbalanced', 'inv-reconcile', 'report', 20],
        'INV_VAL_REPOST' => ['Repost', 'fa-solid fa-clock-rotate-left', 'inv-repost', 'report', 30],
        'INV_VAL_AGING' => ['Stock Aging', 'fa-solid fa-hourglass-half', 'inv-stock-aging', 'report', 40],
    ];

    public function up(): void
    {
        $this->tables();
        $this->accounts();
        $this->constants();
        $this->menu();
    }

    private function tables(): void
    {
        $db = Schema::connection('tm2inv');

        if (! $db->hasTable('inv_landed_cost')) {
            $db->create('inv_landed_cost', function (Blueprint $t) {
                $t->id('id_landed_cost');
                $t->string('doc_no', 20)->nullable()->unique()->comment('lahir saat Confirm');
                $t->date('doc_date');
                $t->unsignedBigInteger('supplier_id')->nullable()->comment('LOGICAL tm2mst.sup_supplier: penagih biaya (forwarder, bea cukai)');
                $t->string('cost_type', 20)->default('FREIGHT')->comment('FREIGHT | DUTY | INSURANCE | OTHER');
                $t->string('description', 255)->nullable();
                $t->decimal('amount', 18, 2)->default(0)->comment('total biaya yang dibagi');
                $t->string('alloc_basis', 5)->default('VALUE')->comment('VALUE = menurut nilai baris, QTY = menurut qty');
                $t->decimal('amount_to_stock', 18, 2)->default(0)->comment('bagian yang menaikkan nilai persediaan');
                $t->decimal('amount_to_variance', 18, 2)->default(0)->comment('bagian untuk qty yang sudah keluar (akun Selisih Landed Cost)');
                $t->boolean('card_synced')->default(false)->comment('0 = nilai belum ditulis ke kartu stok (rantai Average belum punya baris khusus nilai)');
                $t->boolean('stat_confirm')->default(false);
                $t->boolean('stat_cancel')->default(false);
                $t->string('cancel_reason', 255)->nullable();
                $t->unsignedBigInteger('voucher_id')->nullable()->comment('LOGICAL tm2acc.fin_voucher jurnal landed cost');
                $t->unsignedBigInteger('variance_voucher_id')->nullable()->comment('LOGICAL tm2acc.fin_voucher penutupan selisih ke HPP');
                $t->string('notes', 255)->nullable();
                $t->dateTime('confirmed_at')->nullable();
                $t->string('confirmed_by', 100)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->index('doc_date');
            });
        }

        if (! $db->hasTable('inv_landed_cost_line')) {
            $db->create('inv_landed_cost_line', function (Blueprint $t) {
                $t->id('id_landed_cost_line');
                $t->unsignedBigInteger('id_landed_cost');
                $t->unsignedSmallInteger('line_no')->default(1);
                $t->unsignedBigInteger('pv_id')->comment('LOGICAL tm2pur.pv_receipt');
                $t->unsignedBigInteger('pv_line_id')->comment('LOGICAL tm2pur.pv_line');
                $t->unsignedBigInteger('item_id')->comment('LOGICAL tm2mst.mat_raw');
                $t->string('item_code', 60)->nullable();
                $t->string('item_name', 255)->nullable();
                $t->string('uom_code', 50)->nullable();
                $t->decimal('qty_received', 20, 4)->default(0);
                $t->decimal('base_value', 18, 2)->default(0)->comment('nilai penerimaan baris');
                $t->decimal('qty_on_hand', 20, 4)->nullable()->comment('saldo item saat Confirm');
                $t->decimal('allocated', 18, 2)->default(0);
                $t->decimal('to_stock', 18, 2)->default(0);
                $t->decimal('to_variance', 18, 2)->default(0);
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->unique(['id_landed_cost', 'pv_line_id'], 'uq_landed_line');
                $t->foreign('id_landed_cost', 'fk_landed_line_head')->references('id_landed_cost')->on('inv_landed_cost')->cascadeOnDelete();
            });
        }

        if (! $db->hasTable('inv_repost_log')) {
            $db->create('inv_repost_log', function (Blueprint $t) {
                $t->id('id_repost');
                $t->string('repost_kind', 12)->comment('CORRECTION | REVERSAL | REVALUATION');
                $t->string('source_type', 30)->nullable();
                $t->unsignedBigInteger('source_id')->nullable();
                $t->string('source_no', 40)->nullable();
                $t->unsignedBigInteger('original_voucher_id')->nullable()->comment('LOGICAL tm2acc.fin_voucher');
                $t->string('original_voucher_no', 50)->nullable();
                $t->unsignedBigInteger('correction_voucher_id')->nullable();
                $t->string('correction_voucher_no', 50)->nullable();
                $t->decimal('amount_before', 18, 2)->default(0);
                $t->decimal('amount_after', 18, 2)->default(0);
                $t->decimal('amount_delta', 18, 2)->default(0);
                $t->string('reason', 255)->nullable();
                $t->date('posted_on');
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->index(['source_type', 'source_id'], 'idx_repost_source');
                $t->index('original_voucher_id', 'idx_repost_original');
            });
        }

        if (! $db->hasTable('inv_reconcile_log')) {
            $db->create('inv_reconcile_log', function (Blueprint $t) {
                $t->id('id_reconcile');
                $t->date('recon_date');
                $t->string('stock_class', 5)->comment('RM | SFG | FG');
                $t->string('account_codes', 255)->nullable();
                $t->decimal('gl_value', 20, 2)->default(0)->comment('saldo akun persediaan di buku besar');
                $t->decimal('ledger_value', 20, 2)->default(0)->comment('nilai kartu stok (StockLedger)');
                $t->decimal('landed_pending', 20, 2)->default(0)->comment('landed cost yang sudah dijurnal tetapi belum ada di kartu');
                $t->decimal('difference', 20, 2)->default(0)->comment('gl - (ledger + landed_pending)');
                $t->string('status', 5)->default('OK')->comment('OK | DIFF');
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->unique(['recon_date', 'stock_class'], 'uq_reconcile_day_class');
            });
        }

        if (! $db->hasTable('inv_obsolete_provision')) {
            $db->create('inv_obsolete_provision', function (Blueprint $t) {
                $t->id('id_provision');
                $t->date('provision_date');
                $t->decimal('stock_value', 20, 2)->default(0)->comment('nilai stok yang dinilai usang');
                $t->decimal('provision_total', 20, 2)->default(0)->comment('saldo provisi yang dituju');
                $t->decimal('previous_total', 20, 2)->default(0)->comment('saldo provisi posting sebelumnya');
                $t->decimal('adjustment', 20, 2)->default(0)->comment('provision_total - previous_total = yang dijurnal');
                $t->unsignedBigInteger('voucher_id')->nullable()->comment('LOGICAL tm2acc.fin_voucher');
                $t->string('notes', 255)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->string('created_by', 100)->nullable();
                $t->index('provision_date');
            });
        }

        if (! $db->hasTable('inv_obsolete_provision_line')) {
            $db->create('inv_obsolete_provision_line', function (Blueprint $t) {
                $t->id('id_provision_line');
                $t->unsignedBigInteger('id_provision');
                $t->string('stock_class', 5);
                $t->unsignedBigInteger('item_id');
                $t->string('item_code', 60)->nullable();
                $t->string('item_name', 255)->nullable();
                $t->unsignedBigInteger('location_id')->nullable();
                $t->string('location_name', 100)->nullable();
                $t->decimal('qty', 20, 4)->default(0);
                $t->decimal('stock_value', 20, 2)->default(0);
                $t->integer('idle_days')->nullable();
                $t->string('aging_class', 12);
                $t->decimal('pct', 6, 2)->default(0);
                $t->decimal('provision', 20, 2)->default(0);
                $t->foreign('id_provision', 'fk_obs_line_head')->references('id_provision')->on('inv_obsolete_provision')->cascadeOnDelete();
            });
        }
    }

    private function accounts(): void
    {
        $acc = fn () => DB::connection('tm2acc')->table('fin_account');
        foreach (self::ACCOUNTS as $code => [$name, $type, $normal, $parentCode, $fs, $cf]) {
            if ($acc()->where('account_code', $code)->exists()) {
                continue;
            }
            $acc()->insert(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal,
                'parent_id' => $parentCode ? $acc()->where('account_code', $parentCode)->value('account_id') : null, 'is_recon' => 0, 'is_postable' => 1,
                'fs_line' => $fs, 'cf_line' => $cf, 'activate' => 1, 'created_by' => self::BY, 'created_at' => now(), 'data_origin' => 'LIVE']);
        }
    }

    private function constants(): void
    {
        $table = fn () => DB::connection('tm2sys')->table('sys_constant');
        foreach (self::CONSTANTS as $key => [$number, $text, $description]) {
            if ($table()->where('constant_key', $key)->exists()) {
                continue;
            }
            $table()->insert(['constant_key' => $key, 'category_code' => 'INVENTORY', 'constant_group' => 'INV', 'constant_number' => $number, 'constant_text' => $text,
                'description' => $description, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => self::BY, 'updated_by' => self::BY]);
        }
    }

    private function menu(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $inv = $menu()->where('code', 'INV')->where('menu_type', 'module')->first();
        if (! $inv) {
            return;
        }
        $row = fn (array $v) => $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now(), 'target_kind' => 'app'];
        $group = fn (int $parent, string $code, string $label, string $icon, int $sort) => $menu()->where('code', $code)->value('id')
            ?? $menu()->insertGetId($row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'group', 'sort_order' => $sort]));
        $items = function (int $parent, array $list) use ($menu, $row) {
            foreach ($list as $code => [$label, $icon, $slug, $type, $sort]) {
                if (! $menu()->where('code', $code)->exists()) {
                    $menu()->insert($row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $type, 'target' => 'v2:'.$slug, 'sort_order' => $sort]));
                }
            }
        };

        $val = $group($inv->id, 'INV_VAL', 'Valuation & Period', 'fa-solid fa-scale-balanced', 36);
        $items($val, self::MENU);
        $items($group($val, 'INV_VAL_RPT', 'Report', 'fa-solid fa-chart-line', 900), self::REPORTS);
    }

    public function down(): void
    {
        // Tabel, konstanta, akun dan menu dibiarkan (pola migration Inventory lain); nonaktifkan lewat Setup bila perlu.
    }
};
