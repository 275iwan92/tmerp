<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// PO Kontan + Kas Kecil (imprest), keputusan user 2026-10-01:
// - 2 mode: KASBON (uang diambil dulu, lalu pertanggungjawaban nota) dan REIMBURSE (beli dulu, lalu diganti dari kas kecil).
// - Batas nilai PO Kontan = sys_constant PO_CASH_MAX (Rp 2.000.000, diubah lewat Settings > Constants bila direksi menaikkan).
// - Approval PO Kontan cukup Direktur F&A -> flow_config module POC (terpisah dari PO biasa).
// - Saat ini 1 kas kecil, dipegang Mia (F&A SPV); akun COA 111400 Kas Kecil + jurnal PTY dibuat di sini.
return new class extends Migration
{
    public function up(): void
    {
        $acc = DB::connection('tm2acc');
        $now = now();

        if (! $acc->table('fin_account')->where('account_code', '111400')->exists()) {
            $acc->table('fin_account')->insert(['account_code' => '111400', 'account_name' => 'Kas Kecil (Petty Cash)', 'account_type' => 'ASSET',
                'normal_balance' => 'DEBIT', 'parent_id' => $acc->table('fin_account')->where('account_code', '110000')->value('account_id'),
                'is_recon' => 0, 'is_postable' => 1, 'fs_line' => 'CASH', 'cf_line' => 'CASH', 'activate' => 1, 'created_by' => 'migration', 'created_at' => $now,
                'updated_by' => 'migration', 'updated_at' => $now]);
        }
        if (! $acc->table('fin_journal')->where('journal_code', 'PTY')->exists()) {
            $acc->table('fin_journal')->insert(['journal_code' => 'PTY', 'journal_name' => 'Jurnal Kas Kecil', 'journal_type' => 'CASH_BANK', 'activate' => 1,
                'created_by' => 'migration', 'created_at' => $now, 'updated_by' => 'migration', 'updated_at' => $now]);
        }

        Schema::connection('tm2acc')->create('fin_petty_cash', function (Blueprint $t) {
            $t->id('id_petty_cash');
            $t->string('petty_code', 20)->unique();
            $t->string('petty_name', 100);
            $t->unsignedBigInteger('custodian_user_id')->nullable()->comment('pemegang kas -> tm2sys.sec_user');
            $t->unsignedBigInteger('account_id')->comment('akun kas kecil -> fin_account');
            $t->decimal('imprest_amount', 18, 2)->nullable()->comment('plafon kas kecil (diisi user)');
            $t->boolean('activate')->default(true);
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->timestamps();
        });
        Schema::connection('tm2acc')->create('fin_petty_cash_tx', function (Blueprint $t) {
            $t->id('id_petty_tx');
            $t->unsignedBigInteger('petty_cash_id')->index();
            $t->string('tx_no', 30)->unique();
            $t->date('tx_date');
            $t->string('tx_type', 12)->comment('TOPUP | ADVANCE | SETTLE | REIMBURSE');
            $t->unsignedBigInteger('po_id')->nullable()->index()->comment('tm2pur.po_order (PO Kontan)');
            $t->string('po_no', 30)->nullable();
            $t->decimal('advance_amount', 18, 2)->default(0)->comment('kasbon yang dipertanggungjawabkan');
            $t->decimal('actual_amount', 18, 2)->default(0)->comment('nilai nota riil');
            $t->decimal('cash_effect', 18, 2)->comment('+ kas masuk / - kas keluar');
            $t->unsignedBigInteger('counter_account_id')->nullable()->comment('TOPUP: akun bank sumber');
            $t->unsignedBigInteger('voucher_id')->nullable()->comment('tm2acc.fin_voucher');
            $t->string('receiver_name', 100)->nullable()->comment('penerima uang (pembeli)');
            $t->string('attachment_path', 255)->nullable()->comment('foto nota');
            $t->string('notes', 255)->nullable();
            $t->string('status', 12)->default('POSTED');
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->timestamps();
        });
        $acc->table('fin_petty_cash')->insert(['petty_code' => 'KK-01', 'petty_name' => 'Kas Kecil', 'custodian_user_id' => DB::connection('tm2sys')->table('sec_user')->where('login', 'mia')->value('id_user'),
            'account_id' => $acc->table('fin_account')->where('account_code', '111400')->value('account_id'), 'imprest_amount' => null, 'activate' => 1,
            'created_by' => 'migration', 'updated_by' => 'migration', 'created_at' => $now, 'updated_at' => $now]);

        Schema::connection('tm2pur')->table('po_order', function (Blueprint $t) {
            $t->string('i_cash_mode', 10)->nullable()->after('i_procure_type')->comment('PO Kontan: ADVANCE (kasbon) | REIMBURSE (beli dulu)');
            $t->unsignedBigInteger('i_petty_cash_id')->nullable()->after('i_cash_mode')->comment('tm2acc.fin_petty_cash');
            $t->string('i_shop_name', 150)->nullable()->after('i_petty_cash_id')->comment('nama toko (PO Kontan ke TOKO KONTAN)');
        });

        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_constant')->where('constant_key', 'PO_CASH_MAX')->exists()) {
            $sys->table('sys_constant')->insert(['constant_key' => 'PO_CASH_MAX', 'category_code' => 'PROCUREMENT', 'constant_group' => 'PROCUREMENT', 'constant_number' => 2000000,
                'description' => 'Batas nilai maksimal satu PO Kontan (Rp). Di atas ini wajib PO biasa.', 'activate' => 1, 'created_at' => $now, 'updated_at' => $now,
                'created_by' => 'migration', 'updated_by' => 'migration']);
        }

        $mst = DB::connection('tm2mst');
        if (! $mst->table('flow_config')->where('module_code', 'POC')->exists()) {
            $cfg = $mst->table('flow_config')->insertGetId(['module_code' => 'POC', 'total_step' => 1, 'final_status' => 'APPROVED', 'reject_status' => 'REJECTED',
                'notes' => 'PO Kontan: approval cukup Direktur F&A (user 2026-10-01)', 'activate' => 1, 'created_at' => $now, 'updated_at' => $now,
                'created_by' => 'migration', 'updated_by' => 'migration', 'data_origin' => 'LIVE', 'data_ready' => 1]);
            $mst->table('flow_config_step')->insert(['flow_config_id' => $cfg, 'step_no' => 1, 'step_type' => 'APPROVAL', 'status_from' => 'SUBMITTED', 'status_to' => 'SUBMITTED',
                'status_to_reject' => 'REJECTED', 'position_id' => 61, 'min_amount' => 0, 'print_label' => 'Menyetujui', 'activate' => 1, 'created_at' => $now,
                'updated_at' => $now, 'created_by' => 'migration', 'updated_by' => 'migration', 'data_origin' => 'LIVE', 'data_ready' => 1]);
        }

        $grp = $sys->table('sys_app_menu')->where('code', 'FIN')->value('id');
        if ($grp && ! $sys->table('sys_app_menu')->where('code', 'FIN_PETTY')->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => $grp, 'code' => 'FIN_PETTY', 'label' => 'Kas Kecil (Petty Cash)', 'icon' => 'fa-solid fa-wallet',
                'menu_type' => 'item', 'object_type' => 'form', 'target' => 'v2:petty-cash', 'target_kind' => 'app', 'sort_order' => 15, 'activate' => 1,
                'required_object' => 'FINACC', 'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 Kas Kecil + PO Kontan', 'status_updated_by' => 'claude (v2 build)',
                'status_updated_at' => $now, 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'FIN_PETTY')->delete();
        $mst = DB::connection('tm2mst');
        $cfg = $mst->table('flow_config')->where('module_code', 'POC')->value('flow_config_id');
        $mst->table('flow_config_step')->where('flow_config_id', $cfg)->delete();
        $mst->table('flow_config')->where('flow_config_id', $cfg)->delete();
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'PO_CASH_MAX')->delete();
        Schema::connection('tm2pur')->table('po_order', fn (Blueprint $t) => $t->dropColumn(['i_cash_mode', 'i_petty_cash_id', 'i_shop_name']));
        Schema::connection('tm2acc')->dropIfExists('fin_petty_cash_tx');
        Schema::connection('tm2acc')->dropIfExists('fin_petty_cash');
        DB::connection('tm2acc')->table('fin_journal')->where('journal_code', 'PTY')->delete();
        DB::connection('tm2acc')->table('fin_account')->where('account_code', '111400')->delete();
    }
};
