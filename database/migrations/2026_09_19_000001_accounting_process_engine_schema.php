<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Skema Accounting Process Engine -- realisasi mockup
// docs/mockups/MODULE_accounting-process-engine-blueprint.html +
// MODULE_accounting-mockup-blueprint.html (permintaan user 2026-09-19).
//
// - fin_period / fin_period_log persis financeaccounting_delta_period_close.md §2-3.
// - fin_voucher: lifecycle blueprint Draft -> Submitted -> Approved -> Posted
//   (-> Reversed lewat jurnal pembalik, posted tak pernah diedit).
// - fin_account.fs_line / cf_line: pemetaan baris laporan (Neraca, Laba Rugi,
//   Arus Kas metode tidak langsung) supaya laporan disusun dari GL, bukan hardcode.
// - Aset tetap + amortisasi, rekonsiliasi bank, register pajak, checklist tutup
//   buku, aturan approval: tabel baru, semua di tm2acc (FK fisik dalam-DB saja).
// - fin_tax_scheme di tm2mst (dependency yang disebut financeaccounting_rules.yaml
//   tapi belum pernah dibuat).
return new class extends Migration
{
    private function origin(Blueprint $t): void
    {
        $t->string('data_origin', 10)->default('LIVE')->index()->comment('LIVE | DUMMY | LEGACY');
        $t->boolean('data_ready')->default(true);
        $t->boolean('data_origin_manual')->default(false);
    }

    private function audit(Blueprint $t): void
    {
        $t->string('created_by', 50)->nullable();
        $t->timestamp('created_at')->useCurrent();
        $t->string('updated_by', 50)->nullable();
        $t->timestamp('updated_at')->nullable();
    }

    public function up(): void
    {
        $acc = Schema::connection('tm2acc');
        $db = DB::connection('tm2acc');

        // ---------- periode ----------
        if (! $acc->hasTable('fin_period')) {
            $acc->create('fin_period', function (Blueprint $t) {
                $t->bigIncrements('period_id');
                $t->unsignedSmallInteger('fiscal_year');
                $t->unsignedTinyInteger('period_no')->comment('1-12 bulan, 13 = adjustment');
                $t->string('period_name', 30);
                $t->date('start_date');
                $t->date('end_date');
                $t->boolean('is_adjustment')->default(false);
                $t->enum('status', ['OPEN', 'ON_HOLD', 'CLOSED'])->default('OPEN');
                $t->string('closed_by', 100)->nullable();
                $t->dateTime('closed_at')->nullable();
                $this->audit($t);
                $this->origin($t);
                $t->unique(['fiscal_year', 'period_no'], 'uq_fin_period');
                $t->index(['start_date', 'end_date']);
            });
        }

        if (! $acc->hasTable('fin_period_log')) {
            $acc->create('fin_period_log', function (Blueprint $t) {
                $t->bigIncrements('log_id');
                $t->unsignedBigInteger('period_id')->index();
                $t->enum('from_status', ['OPEN', 'ON_HOLD', 'CLOSED'])->nullable();
                $t->enum('to_status', ['OPEN', 'ON_HOLD', 'CLOSED']);
                $t->string('reason', 255);
                $t->string('changed_by', 100);
                $t->dateTime('changed_at')->useCurrent();
                $t->foreign('period_id', 'fk_periodlog_period')->references('period_id')->on('fin_period')->cascadeOnDelete();
            });
        }

        if (! $acc->hasTable('fin_close_task')) {
            $acc->create('fin_close_task', function (Blueprint $t) {
                $t->bigIncrements('task_id');
                $t->unsignedBigInteger('period_id');
                $t->unsignedTinyInteger('step_no');
                $t->string('task_code', 30);
                $t->string('title', 120);
                $t->string('description', 255)->nullable();
                $t->string('owner', 100)->nullable();
                $t->enum('status', ['PENDING', 'IN_PROGRESS', 'COMPLETE', 'EXCEPTION'])->default('PENDING');
                $t->string('evidence_note', 255)->nullable();
                $t->string('completed_by', 50)->nullable();
                $t->dateTime('completed_at')->nullable();
                $this->audit($t);
                $this->origin($t);
                $t->unique(['period_id', 'task_code'], 'uq_close_task');
                $t->foreign('period_id', 'fk_closetask_period')->references('period_id')->on('fin_period')->cascadeOnDelete();
            });
        }

        // ---------- voucher lifecycle ----------
        $db->statement("ALTER TABLE fin_voucher MODIFY status ENUM('DRAFT','SUBMITTED','APPROVED','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT'");
        $acc->table('fin_voucher', function (Blueprint $t) use ($acc) {
            if (! $acc->hasColumn('fin_voucher', 'journal_kind')) {
                $t->string('journal_kind', 20)->nullable()->after('voucher_type')->comment('MANUAL|ACCRUAL|RECURRING|ADJUSTMENT|REVERSAL|OPENING|CLOSING|SYSTEM');
                $t->string('submitted_by', 50)->nullable()->after('status');
                $t->dateTime('submitted_at')->nullable()->after('submitted_by');
                $t->string('approved_by', 50)->nullable()->after('submitted_at');
                $t->dateTime('approved_at')->nullable()->after('approved_by');
                $t->unsignedBigInteger('reversal_of_id')->nullable()->index()->after('approved_at');
                $t->unsignedBigInteger('reversed_by_id')->nullable()->after('reversal_of_id');
                $t->date('auto_reverse_date')->nullable()->after('reversed_by_id');
            }
        });

        // ---------- COA: pemetaan laporan ----------
        $acc->table('fin_account', function (Blueprint $t) use ($acc) {
            if (! $acc->hasColumn('fin_account', 'fs_line')) {
                $t->string('fs_line', 20)->nullable()->after('tax_scheme_code')->comment('baris Neraca/Laba Rugi');
                $t->string('cf_line', 20)->nullable()->after('fs_line')->comment('baris Arus Kas (indirect)');
            }
        });

        // ---------- AR/AP ----------
        $db->statement("ALTER TABLE fin_invoice MODIFY payment_status ENUM('UNPAID','PARTIAL','PAID') NOT NULL DEFAULT 'UNPAID'");
        $acc->table('fin_invoice', function (Blueprint $t) use ($acc) {
            if (! $acc->hasColumn('fin_invoice', 'doc_status')) {
                $t->enum('doc_status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'POSTED', 'CANCELLED'])->default('POSTED')->after('payment_status');
                $t->string('description', 255)->nullable()->after('co_id');
                $t->string('partner_ref', 50)->nullable()->after('description')->comment('no. tagihan pihak luar (vendor bill)');
                $t->string('tax_invoice_no', 50)->nullable()->after('partner_ref')->comment('No. Faktur Pajak (e-Faktur)');
                $t->boolean('tax_validated')->default(false)->after('tax_invoice_no');
                $t->decimal('pph_amount', 18, 4)->default(0)->after('tax_amount');
                $t->string('pph_code', 20)->nullable()->after('pph_amount');
                $t->unsignedInteger('revenue_account_id')->nullable()->after('pph_code')->comment('akun lawan: pendapatan (AR) / beban-persediaan-GRIR (AP)');
            }
        });

        if (! $acc->hasTable('fin_payment_alloc')) {
            $acc->create('fin_payment_alloc', function (Blueprint $t) {
                $t->bigIncrements('alloc_id');
                $t->unsignedBigInteger('voucher_id');
                $t->unsignedBigInteger('invoice_id');
                $t->decimal('amount', 18, 4);
                $t->date('alloc_date');
                $this->audit($t);
                $this->origin($t);
                $t->foreign('voucher_id', 'fk_palloc_voucher')->references('voucher_id')->on('fin_voucher');
                $t->foreign('invoice_id', 'fk_palloc_invoice')->references('invoice_id')->on('fin_invoice');
            });
        }

        // ---------- approval rule (tombol "Create Workflow Rule") ----------
        if (! $acc->hasTable('fin_approval_rule')) {
            $acc->create('fin_approval_rule', function (Blueprint $t) {
                $t->bigIncrements('rule_id');
                $t->string('rule_name', 120);
                $t->enum('process_code', ['JOURNAL', 'CUSTOMER_INVOICE', 'VENDOR_BILL', 'BANK_ADJUSTMENT', 'ASSET']);
                $t->decimal('min_amount', 18, 4)->default(0);
                $t->string('approver_group', 30)->default('DIREKSI')->comment('sec_group.group_code');
                $t->boolean('segregation_of_duties')->default(true)->comment('approver != pembuat');
                $t->boolean('activate')->default(true);
                $this->audit($t);
                $this->origin($t);
            });
        }

        // ---------- bank ----------
        if (! $acc->hasTable('fin_bank_statement')) {
            $acc->create('fin_bank_statement', function (Blueprint $t) {
                $t->bigIncrements('stmt_line_id');
                $t->unsignedInteger('account_id')->comment('akun GL bank');
                $t->date('txn_date');
                $t->date('value_date')->nullable();
                $t->string('reference', 60)->nullable();
                $t->string('description', 255)->nullable();
                $t->string('counterparty', 150)->nullable();
                $t->decimal('amount_in', 18, 4)->default(0);
                $t->decimal('amount_out', 18, 4)->default(0);
                $t->decimal('running_balance', 18, 4)->nullable()->comment('saldo per mutasi bank');
                $t->enum('match_status', ['UNMATCHED', 'MATCHED', 'ADJUSTED', 'IGNORED'])->default('UNMATCHED');
                $t->unsignedBigInteger('matched_voucher_id')->nullable()->index();
                $t->string('import_batch', 40)->nullable();
                $this->audit($t);
                $this->origin($t);
                $t->index(['account_id', 'txn_date']);
                $t->foreign('account_id', 'fk_bstmt_account')->references('account_id')->on('fin_account');
            });
        }

        if (! $acc->hasTable('fin_bank_recon')) {
            $acc->create('fin_bank_recon', function (Blueprint $t) {
                $t->bigIncrements('recon_id');
                $t->unsignedInteger('account_id');
                $t->date('statement_date');
                $t->decimal('statement_balance', 18, 4);
                $t->decimal('book_balance', 18, 4);
                $t->decimal('difference', 18, 4);
                $t->unsignedInteger('unmatched_count')->default(0);
                $t->enum('status', ['RECONCILED', 'WITH_DIFFERENCE'])->default('RECONCILED');
                $t->string('note', 255)->nullable();
                $this->audit($t);
                $this->origin($t);
                $t->foreign('account_id', 'fk_brecon_account')->references('account_id')->on('fin_account');
            });
        }

        // ---------- aset & amortisasi ----------
        if (! $acc->hasTable('fin_asset_category')) {
            $acc->create('fin_asset_category', function (Blueprint $t) {
                $t->bigIncrements('category_id');
                $t->string('category_code', 20)->unique();
                $t->string('category_name', 100);
                $t->enum('asset_type', ['FIXED', 'INTANGIBLE', 'PREPAID', 'LEASEHOLD']);
                $t->string('tax_group', 30)->nullable()->comment('Kelompok 1-4 / Bangunan (PMK 72/2023)');
                $t->unsignedSmallInteger('useful_life_months');
                $t->enum('method', ['SL', 'DDB'])->default('SL');
                $t->unsignedInteger('asset_account_id');
                $t->unsignedInteger('accum_account_id')->comment('akumulasi penyusutan, atau akun prabayar itu sendiri');
                $t->unsignedInteger('expense_account_id');
                $t->boolean('activate')->default(true);
                $this->audit($t);
                $this->origin($t);
            });
        }

        if (! $acc->hasTable('fin_asset')) {
            $acc->create('fin_asset', function (Blueprint $t) {
                $t->bigIncrements('asset_id');
                $t->string('asset_code', 30)->unique();
                $t->string('asset_name', 150);
                $t->unsignedBigInteger('category_id');
                $t->date('acquisition_date');
                $t->date('depr_start_date');
                $t->decimal('acquisition_cost', 18, 4);
                $t->decimal('residual_value', 18, 4)->default(0);
                $t->unsignedSmallInteger('useful_life_months');
                $t->enum('method', ['SL', 'DDB'])->default('SL');
                $t->unsignedBigInteger('cc_id')->nullable();
                $t->string('location', 100)->nullable();
                $t->string('source_ref', 60)->nullable()->comment('no. tagihan / kontrak asal');
                $t->unsignedInteger('credit_account_id')->nullable()->comment('lawan kapitalisasi: Hutang/Bank/GR-IR');
                $t->unsignedBigInteger('capitalize_voucher_id')->nullable();
                $t->enum('status', ['DRAFT', 'ACTIVE', 'FULLY_DEPRECIATED', 'DISPOSED'])->default('DRAFT');
                $t->date('next_review_date')->nullable();
                $t->date('disposal_date')->nullable();
                $t->decimal('disposal_amount', 18, 4)->nullable();
                $t->unsignedBigInteger('disposal_voucher_id')->nullable();
                $this->audit($t);
                $this->origin($t);
                $t->foreign('category_id', 'fk_asset_category')->references('category_id')->on('fin_asset_category');
            });
        }

        if (! $acc->hasTable('fin_asset_depr')) {
            $acc->create('fin_asset_depr', function (Blueprint $t) {
                $t->bigIncrements('depr_id');
                $t->unsignedBigInteger('asset_id');
                $t->unsignedSmallInteger('fiscal_year');
                $t->unsignedTinyInteger('period_no');
                $t->decimal('amount', 18, 4);
                $t->decimal('accumulated', 18, 4);
                $t->decimal('book_value', 18, 4);
                $t->unsignedBigInteger('voucher_id')->nullable();
                $this->audit($t);
                $this->origin($t);
                $t->unique(['asset_id', 'fiscal_year', 'period_no'], 'uq_asset_depr');
                $t->foreign('asset_id', 'fk_depr_asset')->references('asset_id')->on('fin_asset')->cascadeOnDelete();
            });
        }

        // ---------- pajak ----------
        if (! Schema::connection('tm2mst')->hasTable('fin_tax_scheme')) {
            Schema::connection('tm2mst')->create('fin_tax_scheme', function (Blueprint $t) {
                $t->string('tax_code', 20)->primary();
                $t->string('tax_name', 100);
                $t->enum('tax_kind', ['PPN', 'PPH']);
                $t->decimal('rate', 8, 4)->comment('persen');
                $t->decimal('dpp_factor', 10, 6)->default(1)->comment('PPN 12% x DPP 11/12 = efektif 11%');
                $t->string('account_code', 20)->nullable()->comment('akun GL penampung (tm2acc.fin_account)');
                $t->unsignedTinyInteger('payment_due_day')->nullable()->comment('tgl bulan berikutnya; NULL = tahunan');
                $t->unsignedTinyInteger('filing_due_day')->nullable()->comment('0 = akhir bulan berikutnya');
                $t->boolean('activate')->default(true);
                $this->audit($t);
                $this->origin($t);
            });
        }

        if (! $acc->hasTable('fin_tax_register')) {
            $acc->create('fin_tax_register', function (Blueprint $t) {
                $t->bigIncrements('tax_reg_id');
                $t->string('register_code', 30)->unique();
                $t->string('tax_code', 20)->comment('tm2mst.fin_tax_scheme (logical)');
                $t->unsignedSmallInteger('fiscal_year');
                $t->unsignedTinyInteger('period_no')->nullable()->comment('NULL = tahunan');
                $t->string('description', 150);
                $t->decimal('base_amount', 18, 4)->default(0)->comment('keluaran / dasar');
                $t->decimal('credit_amount', 18, 4)->default(0)->comment('masukan / kredit pajak');
                $t->decimal('payable_amount', 18, 4)->default(0);
                $t->date('payment_due')->nullable();
                $t->date('filing_due')->nullable();
                $t->enum('status', ['DRAFT', 'REVIEW', 'PAYMENT_PENDING', 'PAID', 'FILED', 'ACCRUED'])->default('DRAFT');
                $t->string('ntpn', 30)->nullable();
                $t->string('filing_ref', 50)->nullable();
                $t->dateTime('paid_at')->nullable();
                $t->dateTime('filed_at')->nullable();
                $t->unsignedBigInteger('voucher_id')->nullable();
                $t->text('workpaper_note')->nullable();
                $this->audit($t);
                $this->origin($t);
                $t->unique(['tax_code', 'fiscal_year', 'period_no'], 'uq_tax_period');
            });
        }

        $this->seedAccounts();
        $this->seedTaxSchemes();
        $this->seedPeriods();
        $this->seedApprovalRules();
    }

    // Akun yang dibutuhkan mockup (aset tetap non-mesin, tak berwujud, prabayar,
    // PPh 21/Badan, hutang bank, beban penyusutan/amortisasi, pajak penghasilan).
    // account_id manual (fin_account bukan AUTO_INCREMENT, lihat coa.blade.php).
    private function seedAccounts(): void
    {
        $db = DB::connection('tm2acc');
        $new = [
            // id, code, name, type, normal, parent, postable
            [144, '115100', 'Biaya Dibayar Dimuka (Prepaid)', 'ASSET', 'DEBIT', 110, 1],
            [153, '121300', 'Kendaraan', 'ASSET', 'DEBIT', 150, 1],
            [154, '121400', 'Peralatan & Inventaris Kantor', 'ASSET', 'DEBIT', 150, 1],
            [155, '121500', 'Bangunan Pabrik', 'ASSET', 'DEBIT', 150, 1],
            [156, '121910', 'Akumulasi Penyusutan Kendaraan', 'ASSET', 'CREDIT', 150, 1],
            [157, '121920', 'Akumulasi Penyusutan Peralatan Kantor', 'ASSET', 'CREDIT', 150, 1],
            [158, '121930', 'Akumulasi Penyusutan Bangunan', 'ASSET', 'CREDIT', 150, 1],
            [160, '130000', 'ASET TAK BERWUJUD', 'ASSET', 'DEBIT', 100, 0],
            [161, '131100', 'Software & Lisensi', 'ASSET', 'DEBIT', 160, 1],
            [162, '131200', 'Renovasi Bangunan Sewa (Leasehold)', 'ASSET', 'DEBIT', 160, 1],
            [169, '131900', 'Akumulasi Amortisasi Aset Tak Berwujud', 'ASSET', 'CREDIT', 160, 1],
            [224, '214100', 'Hutang PPh Pasal 21', 'LIABILITY', 'CREDIT', 210, 1],
            [225, '214200', 'Hutang PPh Badan (Pasal 29)', 'LIABILITY', 'CREDIT', 210, 1],
            [233, '215100', 'Hutang Bank', 'LIABILITY', 'CREDIT', 210, 1],
            [617, '617000', 'Beban Penyusutan Non-Produksi', 'EXPENSE', 'DEBIT', 600, 1],
            [618, '618000', 'Beban Amortisasi Prabayar & Tak Berwujud', 'EXPENSE', 'DEBIT', 600, 1],
            [619, '619000', 'Beban Sewa & Utilitas Kantor', 'EXPENSE', 'DEBIT', 600, 1],
            [900, '900000', 'BEBAN PAJAK PENGHASILAN', 'OTHER_EXPENSE', 'DEBIT', null, 0],
            [911, '911000', 'Beban Pajak Penghasilan Badan', 'OTHER_EXPENSE', 'DEBIT', 900, 1],
        ];
        foreach ($new as [$id, $code, $name, $type, $normal, $parent, $postable]) {
            if ($db->table('fin_account')->where('account_code', $code)->exists() || $db->table('fin_account')->where('account_id', $id)->exists()) {
                continue;
            }
            $db->table('fin_account')->insert([
                'account_id' => $id, 'account_code' => $code, 'account_name' => $name,
                'account_type' => $type, 'normal_balance' => $normal, 'parent_id' => $parent,
                'is_postable' => $postable, 'is_recon' => 0, 'activate' => 1,
                'created_by' => '_system', 'created_at' => now(),
            ]);
        }
        $db->table('fin_account')->where('account_code', '214100')->update(['tax_scheme_code' => 'PPH21']);
        $db->table('fin_account')->where('account_code', '214200')->update(['tax_scheme_code' => 'PPH_BADAN']);

        // Pemetaan baris laporan: prefix kode terpanjang menang.
        $fs = [
            '111' => ['CASH', 'CASH'], '112' => ['AR', 'WC_AR'], '113' => ['INVENTORY', 'WC_INV'],
            '114' => ['OTHER_CA', 'WC_OTHER_CA'], '115' => ['OTHER_CA', 'WC_OTHER_CA'],
            '1211' => ['PPE', 'INV_PPE'], '1212' => ['PPE', 'INV_PPE'], '1213' => ['PPE', 'INV_PPE'],
            '1214' => ['PPE', 'INV_PPE'], '1215' => ['PPE', 'INV_PPE'], '1219' => ['PPE', 'DA'],
            '1311' => ['INTANGIBLE', 'INV_PPE'], '1312' => ['INTANGIBLE', 'INV_PPE'], '1319' => ['INTANGIBLE', 'DA'],
            '211' => ['AP', 'WC_AP'], '212' => ['ACCRUED', 'WC_OTHER_CL'], '213' => ['ACCRUED', 'WC_OTHER_CL'],
            '2141' => ['ACCRUED', 'WC_OTHER_CL'], '2142' => ['ACCRUED', 'TAX'], '2151' => ['BANK_LOAN', 'FIN_LOAN'],
            '311' => ['SHARE_CAPITAL', 'FIN_EQUITY'], '321' => ['RETAINED', 'FIN_EQUITY'], '322' => ['RETAINED', 'FIN_EQUITY'],
            '4' => ['REVENUE', 'PROFIT'], '5' => ['COGS', 'PROFIT'],
            '611' => ['EMPLOYEE', 'PROFIT'], '614' => ['SELLING', 'PROFIT'], '6' => ['GA', 'PROFIT'],
            '811' => ['OTHER', 'PROFIT'], '812' => ['OTHER', 'PROFIT'], '9' => ['INCOME_TAX', 'TAX'],
        ];
        uksort($fs, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($db->table('fin_account')->get(['account_id', 'account_code']) as $a) {
            foreach ($fs as $prefix => [$fsLine, $cfLine]) {
                if (str_starts_with($a->account_code, (string) $prefix)) {
                    $db->table('fin_account')->where('account_id', $a->account_id)->update(['fs_line' => $fsLine, 'cf_line' => $cfLine]);
                    break;
                }
            }
        }
    }

    // Tarif per UU HPP & PMK 131/2024 (PPN 12% x DPP nilai lain 11/12) dan
    // PMK 81/2024 (bayar tgl 15, lapor PPh tgl 20, lapor PPN akhir bulan).
    // Nilai bisa diubah di tabel -- engine pajak membaca dari sini.
    private function seedTaxSchemes(): void
    {
        $mst = DB::connection('tm2mst');
        $rows = [
            ['PPN_OUT', 'PPN Keluaran', 'PPN', 12, 0.916667, '212100', 15, 0],
            ['PPN_IN', 'PPN Masukan', 'PPN', 12, 0.916667, '114100', 15, 0],
            ['PPH21', 'PPh Pasal 21 Karyawan', 'PPH', 0, 1, '214100', 15, 20],
            ['PPH22', 'PPh Pasal 22', 'PPH', 1.5, 1, '212300', 15, 20],
            ['PPH23', 'PPh Pasal 23 Jasa', 'PPH', 2, 1, '212200', 15, 20],
            ['PPH_BADAN', 'PPh Badan (Pasal 25/29)', 'PPH', 22, 1, '214200', null, null],
        ];
        foreach ($rows as [$code, $name, $kind, $rate, $dpp, $acct, $pay, $file]) {
            $mst->table('fin_tax_scheme')->updateOrInsert(['tax_code' => $code], [
                'tax_name' => $name, 'tax_kind' => $kind, 'rate' => $rate, 'dpp_factor' => $dpp,
                'account_code' => $acct, 'payment_due_day' => $pay, 'filing_due_day' => $file,
                'activate' => 1, 'created_by' => '_system', 'created_at' => now(),
            ]);
        }
    }

    // financeaccounting_delta_period_close.md §6: 12 bulan + periode 13 (ON_HOLD).
    // 2025-2027 supaya posting otomatis PV/Stock Out tidak pernah jatuh ke tanggal tanpa periode.
    private function seedPeriods(): void
    {
        $db = DB::connection('tm2acc');
        foreach ([2025, 2026, 2027] as $year) {
            if ($db->table('fin_period')->where('fiscal_year', $year)->exists()) {
                continue;
            }
            for ($m = 1; $m <= 13; $m++) {
                $start = $m <= 12 ? Carbon::create($year, $m, 1) : Carbon::create($year, 12, 31);
                $id = $db->table('fin_period')->insertGetId([
                    'fiscal_year' => $year, 'period_no' => $m,
                    'period_name' => $m <= 12 ? $start->translatedFormat('F').' '.$year : 'Penyesuaian '.$year,
                    'start_date' => $start->toDateString(),
                    'end_date' => $m <= 12 ? $start->copy()->endOfMonth()->toDateString() : $start->toDateString(),
                    'is_adjustment' => $m === 13, 'status' => $m === 13 ? 'ON_HOLD' : 'OPEN',
                    'created_by' => '_system', 'created_at' => now(),
                ]);
                $db->table('fin_period_log')->insert([
                    'period_id' => $id, 'from_status' => null, 'to_status' => $m === 13 ? 'ON_HOLD' : 'OPEN',
                    'reason' => 'Periode dibuat (generate tahun fiskal)', 'changed_by' => '_system',
                ]);
            }
        }
    }

    private function seedApprovalRules(): void
    {
        $db = DB::connection('tm2acc');
        if ($db->table('fin_approval_rule')->exists()) {
            return;
        }
        $db->table('fin_approval_rule')->insert([
            ['rule_name' => 'Semua jurnal manual wajib disetujui', 'process_code' => 'JOURNAL', 'min_amount' => 0, 'approver_group' => 'DIREKSI', 'segregation_of_duties' => 1, 'activate' => 1, 'created_by' => '_system', 'created_at' => now()],
            ['rule_name' => 'Tagihan vendor >= Rp 100 jt', 'process_code' => 'VENDOR_BILL', 'min_amount' => 100000000, 'approver_group' => 'DIREKSI', 'segregation_of_duties' => 1, 'activate' => 1, 'created_by' => '_system', 'created_at' => now()],
            ['rule_name' => 'Jurnal penyesuaian bank', 'process_code' => 'BANK_ADJUSTMENT', 'min_amount' => 0, 'approver_group' => 'DIREKSI', 'segregation_of_duties' => 1, 'activate' => 1, 'created_by' => '_system', 'created_at' => now()],
        ]);
    }

    public function down(): void
    {
        $acc = Schema::connection('tm2acc');
        foreach (['fin_tax_register', 'fin_asset_depr', 'fin_asset', 'fin_asset_category', 'fin_bank_recon', 'fin_bank_statement', 'fin_approval_rule', 'fin_payment_alloc', 'fin_close_task', 'fin_period_log', 'fin_period'] as $t) {
            $acc->dropIfExists($t);
        }
        Schema::connection('tm2mst')->dropIfExists('fin_tax_scheme');
        $acc->table('fin_invoice', fn (Blueprint $t) => $t->dropColumn(['doc_status', 'description', 'partner_ref', 'tax_invoice_no', 'tax_validated', 'pph_amount', 'pph_code', 'revenue_account_id']));
        $acc->table('fin_account', fn (Blueprint $t) => $t->dropColumn(['fs_line', 'cf_line']));
        $acc->table('fin_voucher', fn (Blueprint $t) => $t->dropColumn(['journal_kind', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'reversal_of_id', 'reversed_by_id', 'auto_reverse_date']));
        DB::connection('tm2acc')->statement("ALTER TABLE fin_voucher MODIFY status ENUM('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT'");
    }
};
