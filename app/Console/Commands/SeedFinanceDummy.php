<?php

namespace App\Console\Commands;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinAssetDepr;
use App\Models\Acc\FinBankStatement;
use App\Models\Acc\FinCloseTask;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinPaymentAlloc;
use App\Models\Acc\FinPeriod;
use App\Models\Acc\FinPeriodLog;
use App\Models\Acc\FinTaxRegister;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Master\CustCustomer;
use App\Models\Master\SupSupplier;
use App\Services\Acc\ArApService;
use App\Services\Acc\AssetService;
use App\Services\Acc\BankReconService;
use App\Services\Acc\CloseService;
use App\Services\Acc\FinPeriodGuard;
use App\Services\Acc\JournalPostingService;
use App\Services\Acc\LedgerService;
use App\Services\Acc\TaxEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Dummy data modul Finance & Accounting untuk QC layar blueprint (permintaan
// user 2026-09-19: "buat dummy data nya akan saya check hasilnya").
// SEMUA baris ditandai data_origin=DUMMY + created_by=dummy_fin, jadi bisa
// disembunyikan/direset lewat Settings > Dummy & Legacy Data.
// Semua angka dibuat lewat service yang sama dengan layar (bukan INSERT mentah),
// supaya jurnal benar-benar balance dan laporan konsisten.
class SeedFinanceDummy extends Command
{
    protected $signature = 'fin:dummy {--purge : hapus semua data dummy Finance lalu keluar} {--year=2026}';

    protected $description = 'Seed (atau purge) dummy data Finance & Accounting: saldo awal, AR/AP, aset, pajak, rekonsiliasi bank, tutup buku';

    private const USER = 'dummy_fin';

    private int $year;

    private array $acct = [];

    public function handle(JournalPostingService $jp, ArApService $arap, AssetService $assets, TaxEngine $tax, BankReconService $bank, CloseService $close): int
    {
        $this->year = (int) $this->option('year');

        if ($this->option('purge')) {
            $this->purge();

            return self::SUCCESS;
        }

        if (FinVoucher::withoutGlobalScopes()->where('data_origin', 'DUMMY')->exists()) {
            $this->error('Dummy Finance sudah ada. Jalankan "php artisan fin:dummy --purge" dulu bila mau seed ulang.');

            return self::FAILURE;
        }

        $this->reopenPeriods();
        mt_srand(20260919);

        $this->line('1/9 Aset tetap & jadwal amortisasi (riwayat sebelum '.$this->year.')');
        $opening = $this->seedAssets($assets);

        $this->line('2/9 Saldo awal per 31 Des '.($this->year - 1));
        $this->seedOpening($jp, $opening);

        $this->line('3/9 Faktur penjualan, penerimaan, tagihan vendor & pembayaran');
        $this->seedArAp($arap);

        $this->line('4/9 HPP, payroll, overhead & angsuran bank per bulan');
        $this->seedOperations($jp);

        $this->line('5/9 Belanja modal '.$this->year);
        $this->seedCapex($assets);

        $this->line('6/9 Run penyusutan & amortisasi (bulan berjalan disisakan)');
        $this->runSchedules($assets);

        $this->line('7/9 Jurnal manual: draft, menunggu approval, akrual auto-reverse');
        $this->seedManualJournals($jp);

        $this->line('8/9 Rekening koran + auto-match + rekonsiliasi');
        $this->seedBank($bank);

        $this->line('9/9 Register pajak & tutup buku');
        $this->seedTaxAndClose($tax, $close);

        $this->info('Selesai. Voucher dummy: '.FinVoucher::withoutGlobalScopes()->where('data_origin', 'DUMMY')->count()
            .', faktur: '.FinInvoice::withoutGlobalScopes()->where('data_origin', 'DUMMY')->count()
            .', aset/jadwal: '.FinAsset::withoutGlobalScopes()->where('data_origin', 'DUMMY')->count());

        return self::SUCCESS;
    }

    private function acct(string $code): int
    {
        return $this->acct[$code] ??= (int) FinAccount::withoutGlobalScopes()->where('account_code', $code)->value('account_id');
    }

    private function purge(): void
    {
        DB::connection('tm2acc')->transaction(function () {
            $vouchers = FinVoucher::withoutGlobalScopes()->where('data_origin', 'DUMMY')->pluck('voucher_id');
            FinPaymentAlloc::withoutGlobalScopes()->whereIn('voucher_id', $vouchers)->orWhere('data_origin', 'DUMMY')->delete();
            FinAssetDepr::withoutGlobalScopes()->where('data_origin', 'DUMMY')->delete();
            FinAsset::withoutGlobalScopes()->where('data_origin', 'DUMMY')->delete();
            FinInvoice::withoutGlobalScopes()->where('data_origin', 'DUMMY')->delete();
            FinTaxRegister::withoutGlobalScopes()->where('data_origin', 'DUMMY')->delete();
            FinBankStatement::withoutGlobalScopes()->where('data_origin', 'DUMMY')->delete();
            DB::connection('tm2acc')->table('fin_bank_recon')->where('data_origin', 'DUMMY')->delete();
            DB::connection('tm2acc')->table('fin_voucher_line')->whereIn('voucher_id', $vouchers)->delete();
            FinVoucher::withoutGlobalScopes()->whereIn('voucher_id', $vouchers)->update(['reversed_by_id' => null]);
            FinVoucher::withoutGlobalScopes()->whereIn('voucher_id', $vouchers)->delete();
            FinCloseTask::withoutGlobalScopes()->where('data_origin', 'DUMMY')->delete();
            $this->reopenPeriods();
        });
        $this->info('Data dummy Finance dihapus & periode '.$this->year.' dibuka kembali.');
    }

    // Periode dummy yang sempat di-CLOSED dibuka lagi supaya seed berikutnya bisa
    // memposting. Hanya untuk periode yang ditutup oleh seeder ini (alasan dummy).
    private function reopenPeriods(): void
    {
        $ids = FinPeriodLog::withoutGlobalScopes()->where('reason', 'like', '%[dummy]%')->pluck('period_id')->unique();
        FinPeriod::withoutGlobalScopes()->whereIn('period_id', $ids)->where('status', 'CLOSED')
            ->update(['status' => 'OPEN', 'closed_by' => null, 'closed_at' => null]);
        FinPeriodLog::withoutGlobalScopes()->whereIn('period_id', $ids)->where('reason', 'like', '%[dummy]%')->delete();
    }

    /** Aset lama (sebelum tahun berjalan) + riwayat penyusutannya. @return array<string,float> saldo awal per kode akun */
    private function seedAssets(AssetService $svc): array
    {
        $rows = [
            ['MACH', 'Mesin Cetak Offset Komori Lithrone 4 Warna', '2019-03-15', 6_500_000_000, 'Pabrik — Ruang Cetak', 'PROD'],
            ['MACH', 'Mesin Cetak Offset Heidelberg SM 74', '2021-08-01', 3_850_000_000, 'Pabrik — Ruang Cetak', 'PROD'],
            ['FINISH', 'Die-Cutter Otomatis Bobst SP 102', '2020-06-10', 2_400_000_000, 'Pabrik — Finishing', 'PROD'],
            ['FINISH', 'Folder Gluer Jagenberg Diana', '2022-02-20', 1_250_000_000, 'Pabrik — Finishing', 'PROD'],
            ['VEH', 'Truk Box Hino Dutro 130 HD', '2022-11-05', 520_000_000, 'Ekspedisi', 'GA'],
            ['VEH', 'Toyota Innova Zenix (Direksi)', '2024-07-01', 628_500_000, 'Kantor', 'GA'],
            ['ITEQ', 'Server & Jaringan Kantor', '2023-05-12', 185_000_000, 'Kantor — Ruang IT', 'GA'],
            ['ITEQ', 'Perangkat Kerja Kantor (20 unit)', '2024-01-15', 240_000_000, 'Kantor', 'GA'],
            ['BLDG', 'Bangunan Pabrik & Gudang', '2018-01-10', 4_200_000_000, 'Jl. Raya Industri', 'GA'],
        ];
        $opening = [];
        $cutoff = Carbon::create($this->year - 1, 12, 31);

        foreach ($rows as [$catCode, $name, $date, $cost, $location, $cc]) {
            $cat = FinAssetCategory::withoutGlobalScopes()->where('category_code', $catCode)->firstOrFail();
            $start = Carbon::parse($date)->startOfMonth();
            $asset = FinAsset::create([
                'asset_code' => $svc->nextCode('FA-'.$catCode, $date),
                'asset_name' => $name, 'category_id' => $cat->category_id,
                'acquisition_date' => $date, 'depr_start_date' => $start->toDateString(),
                'acquisition_cost' => $cost, 'residual_value' => 0,
                'useful_life_months' => $cat->useful_life_months, 'method' => $cat->method,
                'location' => $location, 'source_ref' => 'Saldo awal '.$this->year,
                'status' => 'ACTIVE', 'next_review_date' => Carbon::parse($date)->addYears(Carbon::parse($date)->diffInYears(now()))->toDateString(),
                'data_origin' => 'DUMMY', 'created_by' => self::USER, 'updated_by' => self::USER,
            ]);

            // Riwayat penyusutan s.d. 31 Des tahun lalu: baris jadwal TANPA voucher
            // (nilainya sudah terkandung di saldo awal akumulasi penyusutan).
            $plan = $svc->plan($asset);
            $acc = 0.0;
            foreach ($plan as $i => $amount) {
                $m = $start->copy()->addMonths($i);
                if ($m->gt($cutoff)) {
                    break;
                }
                $acc = round($acc + $amount, 2);
                FinAssetDepr::create([
                    'asset_id' => $asset->asset_id, 'fiscal_year' => $m->year, 'period_no' => $m->month,
                    'amount' => $amount, 'accumulated' => $acc, 'book_value' => round($cost - $acc, 2),
                    'voucher_id' => null, 'data_origin' => 'DUMMY', 'created_by' => self::USER,
                ]);
            }
            $assetAcct = FinAccount::withoutGlobalScopes()->find($cat->asset_account_id)->account_code;
            $accumAcct = FinAccount::withoutGlobalScopes()->find($cat->accum_account_id)->account_code;
            $opening[$assetAcct] = ($opening[$assetAcct] ?? 0) + $cost;
            $opening[$accumAcct] = ($opening[$accumAcct] ?? 0) - $acc;
        }

        // Jadwal amortisasi berjalan: software (mulai Okt tahun lalu) & asuransi.
        foreach ([
            ['SOFT', 'Lisensi ERP & Desain Grafis 3 Tahun', ($this->year - 1).'-10-01', 2_592_000_000 / 24, 36],
            ['LHI', 'Renovasi Kantor Pemasaran', $this->year.'-04-01', 500_000_000, 60],
            ['PREPINS', 'Asuransi Pabrik & Kendaraan '.$this->year, $this->year.'-01-01', 342_000_000, 12],
        ] as [$catCode, $name, $date, $cost, $life]) {
            $cat = FinAssetCategory::withoutGlobalScopes()->where('category_code', $catCode)->firstOrFail();
            $start = Carbon::parse($date);
            $asset = FinAsset::create([
                'asset_code' => $svc->nextCode('AM-'.$catCode, $date),
                'asset_name' => $name, 'category_id' => $cat->category_id,
                'acquisition_date' => $date, 'depr_start_date' => $start->toDateString(),
                'acquisition_cost' => round($cost), 'residual_value' => 0,
                'useful_life_months' => $life, 'method' => 'SL',
                'source_ref' => 'Kontrak dummy', 'status' => 'ACTIVE',
                'data_origin' => 'DUMMY', 'created_by' => self::USER, 'updated_by' => self::USER,
            ]);
            $assetAcct = FinAccount::withoutGlobalScopes()->find($cat->asset_account_id)->account_code;
            if ($start->lte($cutoff)) {
                // Bagian yang sudah teramortisasi tahun lalu masuk saldo awal.
                $acc = 0.0;
                foreach ($svc->plan($asset) as $i => $amount) {
                    $m = $start->copy()->addMonths($i);
                    if ($m->gt($cutoff)) {
                        break;
                    }
                    $acc = round($acc + $amount, 2);
                    FinAssetDepr::create([
                        'asset_id' => $asset->asset_id, 'fiscal_year' => $m->year, 'period_no' => $m->month,
                        'amount' => $amount, 'accumulated' => $acc, 'book_value' => round($asset->acquisition_cost - $acc, 2),
                        'voucher_id' => null, 'data_origin' => 'DUMMY', 'created_by' => self::USER,
                    ]);
                }
                $opening[$assetAcct] = ($opening[$assetAcct] ?? 0) + (float) $asset->acquisition_cost;
                $accumAcct = FinAccount::withoutGlobalScopes()->find($cat->accum_account_id)->account_code;
                $opening[$accumAcct] = ($opening[$accumAcct] ?? 0) - $acc;
            } else {
                // Dibayar di tahun berjalan -> dikapitalisasi lewat engine (kas keluar).
                $asset->update(['status' => 'DRAFT', 'credit_account_id' => $this->acct('111200')]);
                $svc->capitalize($asset, self::USER);
            }
        }

        return $opening;
    }

    private function seedOpening(JournalPostingService $jp, array $assetOpening): void
    {
        $lines = [];
        $add = function (string $code, float $amount) use (&$lines) {
            if (abs($amount) < 1) {
                return;
            }
            $lines[] = ['account_id' => $this->acct($code), $amount > 0 ? 'debit' : 'credit' => abs($amount), 'narration' => 'Saldo awal'];
        };

        $add('111100', 145_000_000);          // kas operasional
        $add('111200', 4_850_000_000);        // bank BCA
        $add('113100', 845_000_000);          // kertas & board
        $add('113200', 168_000_000);          // tinta & chemical
        $add('113300', 96_000_000);           // tooling pisau
        $add('113400', 214_000_000);          // WIP
        $add('113500', 392_000_000);          // barang jadi
        $add('113600', 58_000_000);           // bahan pembantu
        foreach ($assetOpening as $code => $amount) {
            $add($code, $amount);
        }
        $add('215100', -1_450_000_000);       // hutang bank
        $add('311000', -8_000_000_000);       // modal disetor

        $balance = round(array_sum(array_map(fn ($l) => ($l['debit'] ?? 0) - ($l['credit'] ?? 0), $lines)), 2);
        $add('321000', -$balance);            // laba ditahan = penyeimbang

        $jp->post('GEN', 'OPENING_BALANCE', $this->year, 'Saldo awal pembukuan '.$this->year, $lines, self::USER,
            ($this->year - 1).'-12-31', ['journal_kind' => 'OPENING', 'data_origin' => 'DUMMY',
                'voucher_number' => 'JV-'.($this->year - 1).'-12-0001']);
    }

    /** Customer & supplier dummy diambil dari master yang benar-benar ada. */
    private function partners(): array
    {
        $customers = CustCustomer::withoutGlobalScopes()->where('activate', true)->orderBy('id_customer')->limit(8)->get();
        $suppliers = SupSupplier::withoutGlobalScopes()->where('activate', true)->orderBy('id_supplier')->limit(6)->get();
        abort_if($customers->count() < 3 || $suppliers->count() < 3, 422, 'Master customer/supplier terlalu sedikit untuk dummy.');

        return [$customers, $suppliers];
    }

    private function months(): array
    {
        $out = [];
        $last = now()->year > $this->year ? 12 : (int) now()->format('n');
        for ($m = 1; $m <= $last; $m++) {
            $out[] = $m;
        }

        return $out;
    }

    private function seedArAp(ArApService $svc): void
    {
        [$customers, $suppliers] = $this->partners();
        $products = ['Folding box food grade 5.000 pcs', 'Inner box duplex 12.000 pcs', 'Sleeve karton 8.000 pcs',
            'Master box kraft 3.500 pcs', 'Label kemasan 20.000 pcs', 'Hangtag art carton 15.000 pcs'];
        $revenueAccts = ['411000', '411000', '412000', '412000', '413000'];
        $prevInvoices = [];

        foreach ($this->months() as $m) {
            $days = Carbon::create($this->year, $m, 1)->daysInMonth;
            $monthInvoices = [];

            for ($i = 0; $i < 4; $i++) {
                $customer = $customers[($m + $i) % $customers->count()];
                $date = Carbon::create($this->year, $m, min(3 + $i * 7, $days));
                $inv = $svc->createCustomerInvoice([
                    'customer_id' => $customer->id_customer,
                    'invoice_date' => $date->toDateString(),
                    'due_date' => $date->copy()->addDays(30)->toDateString(),
                    'subtotal' => mt_rand(90, 420) * 1_000_000,
                    'ppn' => true,
                    'description' => $products[($m * 4 + $i) % count($products)],
                    'revenue_account_id' => $this->acct($revenueAccts[($m + $i) % count($revenueAccts)]),
                    'tax_invoice_no' => sprintf('010.000-%02d.%08d', $this->year % 100, $m * 100 + $i + 1),
                    'data_origin' => 'DUMMY',
                ], self::USER);
                $monthInvoices[] = $inv;
            }

            // Penerimaan: faktur bulan lalu dilunasi (satu dibiarkan terbuka agar
            // aging & overdue terlihat), satu dibayar sebagian.
            foreach ($prevInvoices as $idx => $inv) {
                if ($idx === 0) {
                    continue; // sengaja dibiarkan menunggak
                }
                $amount = $idx === 1 ? round($inv->outstanding() * 0.6, 2) : $inv->outstanding();
                $svc->settle('RECEIPT', $this->acct('111200'),
                    Carbon::create($this->year, $m, min(12 + $idx * 4, $days))->toDateString(),
                    [$inv->invoice_id => $amount], self::USER, 'RCV-'.sprintf('%04d%02d%02d', $this->year, $m, $idx));
            }

            // Tagihan lama (lebih dari 2 bulan) akhirnya tertagih -- tanpa ini
            // aging menumpuk tidak wajar.
            $cutoff = Carbon::create($this->year, $m, 1)->subMonthsNoOverflow(2);
            foreach (FinInvoice::withoutGlobalScopes()->where('invoice_type', 'CUSTOMER_INVOICE')
                ->where('data_origin', 'DUMMY')->where('payment_status', '<>', 'PAID')
                ->where('invoice_date', '<', $cutoff->toDateString())->orderBy('invoice_id')->get() as $k => $old) {
                $svc->settle('RECEIPT', $this->acct('111200'),
                    Carbon::create($this->year, $m, min(20 + $k, $days))->toDateString(),
                    [$old->invoice_id => $old->outstanding()], self::USER, 'RCV-LATE-'.$old->invoice_number);
            }

            $prevInvoices = $monthInvoices;

            // Tagihan vendor bulan ini (kertas, tinta, subkon dengan PPh 23).
            $bills = [];
            $bills[] = $svc->createVendorBill([
                'supplier_id' => $suppliers[0]->id_supplier, 'partner_ref' => sprintf('INV-KB-%04d%02d', $this->year, $m),
                'invoice_date' => Carbon::create($this->year, $m, 5)->toDateString(),
                'due_date' => Carbon::create($this->year, $m, 5)->addDays(30)->toDateString(),
                'subtotal' => mt_rand(180, 340) * 1_000_000, 'ppn' => true,
                'description' => 'Kertas Ivory & Duplex board', 'expense_account_id' => $this->acct('113100'),
                'tax_invoice_no' => sprintf('010.001-%02d.%08d', $this->year % 100, $m * 10 + 1), 'data_origin' => 'DUMMY',
            ], self::USER);
            $bills[] = $svc->createVendorBill([
                'supplier_id' => $suppliers[1]->id_supplier, 'partner_ref' => sprintf('INV-TC-%04d%02d', $this->year, $m),
                'invoice_date' => Carbon::create($this->year, $m, 8)->toDateString(),
                'due_date' => Carbon::create($this->year, $m, 8)->addDays(30)->toDateString(),
                'subtotal' => mt_rand(55, 95) * 1_000_000, 'ppn' => true,
                'description' => 'Tinta offset, varnish & chemical', 'expense_account_id' => $this->acct('113200'),
                'tax_invoice_no' => $m % 3 === 0 ? null : sprintf('010.002-%02d.%08d', $this->year % 100, $m * 10 + 2),
                'data_origin' => 'DUMMY',
            ], self::USER);
            $bills[] = $svc->createVendorBill([
                'supplier_id' => $suppliers[2]->id_supplier, 'partner_ref' => sprintf('JS-LAM-%04d%02d', $this->year, $m),
                'invoice_date' => Carbon::create($this->year, $m, 14)->toDateString(),
                'due_date' => Carbon::create($this->year, $m, 14)->addDays(21)->toDateString(),
                'subtotal' => mt_rand(38, 72) * 1_000_000, 'ppn' => true, 'pph_code' => 'PPH23',
                'description' => 'Jasa laminasi & UV spot (subkon)', 'expense_account_id' => $this->acct('513000'),
                'tax_invoice_no' => sprintf('010.003-%02d.%08d', $this->year % 100, $m * 10 + 3), 'data_origin' => 'DUMMY',
            ], self::USER);

            // Bayar tagihan bulan sebelumnya (kecuali bulan pertama & satu bill terakhir).
            if ($m > 1) {
                foreach (FinInvoice::withoutGlobalScopes()->where('invoice_type', 'SUPPLIER_BILL')->where('data_origin', 'DUMMY')
                    ->where('payment_status', 'UNPAID')->whereMonth('invoice_date', $m - 1)->whereYear('invoice_date', $this->year)
                    ->orderBy('invoice_id')->get()->take(2) as $k => $bill) {
                    $svc->settle('PAYMENT', $this->acct('111200'),
                        Carbon::create($this->year, $m, min(6 + $k * 3, $days))->toDateString(),
                        [$bill->invoice_id => $bill->outstanding()], self::USER, 'TRF-'.sprintf('%04d%02d%02d', $this->year, $m, $k));
                }
            }
        }
    }

    // HPP pemakaian bahan, payroll (termasuk PPh 21), overhead & angsuran bank.
    private function seedOperations(JournalPostingService $jp): void
    {
        foreach ($this->months() as $m) {
            $end = Carbon::create($this->year, $m, 1)->endOfMonth();
            $revenue = (float) FinInvoice::withoutGlobalScopes()->where('invoice_type', 'CUSTOMER_INVOICE')
                ->where('data_origin', 'DUMMY')->whereYear('invoice_date', $this->year)->whereMonth('invoice_date', $m)->sum('subtotal');

            $paper = round($revenue * 0.26, -3);
            $ink = round($revenue * 0.06, -3);
            $aux = round($revenue * 0.02, -3);
            $jp->post('USG', 'MATERIAL_USAGE', $m, 'Pemakaian bahan produksi '.$end->translatedFormat('F Y'), [
                ['account_id' => $this->acct('511000'), 'debit' => $paper, 'narration' => 'Kertas & board'],
                ['account_id' => $this->acct('113100'), 'credit' => $paper],
                ['account_id' => $this->acct('512000'), 'debit' => $ink, 'narration' => 'Tinta & chemical'],
                ['account_id' => $this->acct('113200'), 'credit' => $ink],
                ['account_id' => $this->acct('517000'), 'debit' => $aux, 'narration' => 'Bahan pembantu & kemasan'],
                ['account_id' => $this->acct('113600'), 'credit' => $aux],
            ], self::USER, $end->toDateString(), ['data_origin' => 'DUMMY']);

            $direct = 118_000_000;
            $admin = 92_000_000;
            $pph21 = 6_450_000;
            $jp->post('GEN', 'PAYROLL', $m, 'Beban gaji & upah '.$end->translatedFormat('F Y'), [
                ['account_id' => $this->acct('514000'), 'debit' => $direct, 'narration' => 'Tenaga kerja langsung'],
                ['account_id' => $this->acct('611000'), 'debit' => $admin, 'narration' => 'Gaji mgmt, sales & admin'],
                ['account_id' => $this->acct('214100'), 'credit' => $pph21, 'narration' => 'PPh 21 dipotong'],
                ['account_id' => $this->acct('111200'), 'credit' => $direct + $admin - $pph21, 'narration' => 'Transfer payroll'],
            ], self::USER, $end->toDateString(), ['data_origin' => 'DUMMY']);

            $power = 44_500_000;
            $rent = 26_000_000;
            $marketing = mt_rand(12, 24) * 1_000_000;
            $qc = 7_500_000;
            $jp->post('GEN', 'OVERHEAD', $m, 'Overhead pabrik & kantor '.$end->translatedFormat('F Y'), [
                ['account_id' => $this->acct('516000'), 'debit' => $power, 'narration' => 'Listrik & daya pabrik'],
                ['account_id' => $this->acct('619000'), 'debit' => $rent, 'narration' => 'Sewa & utilitas kantor'],
                ['account_id' => $this->acct('614000'), 'debit' => $marketing, 'narration' => 'Pemasaran & komisi sales'],
                ['account_id' => $this->acct('612000'), 'debit' => $qc, 'narration' => 'QC & lab warna'],
                ['account_id' => $this->acct('111200'), 'credit' => $power + $rent + $marketing + $qc],
            ], self::USER, $end->toDateString(), ['data_origin' => 'DUMMY']);

            $principal = 25_000_000;
            $interest = 9_200_000;
            $adminFee = 450_000;
            $jp->post('BNK', 'LOAN_INSTALMENT', $m, 'Angsuran kredit investasi & biaya bank '.$end->translatedFormat('F Y'), [
                ['account_id' => $this->acct('215100'), 'debit' => $principal, 'narration' => 'Pokok pinjaman'],
                ['account_id' => $this->acct('812000'), 'debit' => $interest + $adminFee, 'narration' => 'Bunga & administrasi bank'],
                ['account_id' => $this->acct('111200'), 'credit' => $principal + $interest + $adminFee],
            ], self::USER, $end->copy()->day(min(25, $end->day))->toDateString(),
                ['voucher_type' => 'BBK', 'main_account_id' => $this->acct('111200'), 'data_origin' => 'DUMMY',
                    'voucher_number' => $jp->nextInSeries(sprintf('BBK/%04d/%02d/', $this->year, $m), 3)]);
        }
    }

    private function seedCapex(AssetService $svc): void
    {
        $months = $this->months();
        foreach ([
            ['ITEQ', 'Server Dell PowerEdge R760', 385_000_000, 8],
            ['FINISH', 'Mesin Pond Semi-Otomatis (tambahan)', 620_000_000, max(1, count($months) - 2)],
        ] as [$catCode, $name, $cost, $month]) {
            if (! in_array($month, $months, true)) {
                continue;
            }
            $cat = FinAssetCategory::withoutGlobalScopes()->where('category_code', $catCode)->firstOrFail();
            $date = Carbon::create($this->year, $month, 15);
            $asset = FinAsset::create([
                'asset_code' => $svc->nextCode('FA-'.$catCode, $date->toDateString()),
                'asset_name' => $name, 'category_id' => $cat->category_id,
                'acquisition_date' => $date->toDateString(), 'depr_start_date' => $date->copy()->startOfMonth()->toDateString(),
                'acquisition_cost' => $cost, 'residual_value' => 0,
                'useful_life_months' => $cat->useful_life_months, 'method' => $cat->method,
                'location' => $catCode === 'ITEQ' ? 'Kantor — Ruang IT' : 'Pabrik — Finishing',
                'source_ref' => 'Pembelian '.$date->format('M Y'), 'credit_account_id' => $this->acct('111200'),
                'status' => 'DRAFT', 'next_review_date' => $date->copy()->addYear()->toDateString(),
                'data_origin' => 'DUMMY', 'created_by' => self::USER, 'updated_by' => self::USER,
            ]);
            $svc->capitalize($asset, self::USER);
        }

        // Satu aset dibiarkan DRAFT supaya kartu "Due for Review" ada isinya.
        $cat = FinAssetCategory::withoutGlobalScopes()->where('category_code', 'ITEQ')->firstOrFail();
        FinAsset::create([
            'asset_code' => $svc->nextCode('FA-ITEQ', now()->toDateString()),
            'asset_name' => 'Printer Proofing Epson (menunggu kapitalisasi)', 'category_id' => $cat->category_id,
            'acquisition_date' => now()->toDateString(), 'depr_start_date' => now()->startOfMonth()->toDateString(),
            'acquisition_cost' => 74_500_000, 'residual_value' => 0,
            'useful_life_months' => $cat->useful_life_months, 'method' => 'SL',
            'location' => 'Prepress', 'source_ref' => 'Tagihan belum lengkap',
            'credit_account_id' => $this->acct('211900'), 'status' => 'DRAFT',
            'data_origin' => 'DUMMY', 'created_by' => self::USER, 'updated_by' => self::USER,
        ]);
    }

    // Penyusutan & amortisasi diposting s.d. bulan lalu; bulan berjalan sengaja
    // disisakan supaya "Pending Runs" di layar ada isinya.
    private function runSchedules(AssetService $svc): void
    {
        foreach ($this->months() as $m) {
            if ($m === (int) now()->format('n') && now()->year === $this->year) {
                continue;
            }
            $svc->run($this->year, $m, self::USER, ['FIXED'], 'DUMMY');
            $svc->run($this->year, $m, self::USER, ['PREPAID', 'INTANGIBLE', 'LEASEHOLD'], 'DUMMY');
        }
    }

    private function seedManualJournals(JournalPostingService $jp): void
    {
        $genId = FinJournal::where('journal_code', 'GEN')->value('journal_id');
        $today = now();

        // 1. Draft (belum diajukan).
        $jp->saveDraft(null, [
            'voucher_number' => $jp->nextJournalNumber($today->toDateString()),
            'journal_id' => $genId, 'journal_kind' => 'ACCRUAL', 'entry_date' => $today->toDateString(),
            'posting_date' => $today->toDateString(), 'source_type' => 'MANUAL',
            'description' => 'Akrual listrik pabrik '.$today->translatedFormat('F Y'),
            'auto_reverse_date' => $today->copy()->addMonth()->startOfMonth()->toDateString(),
            'data_origin' => 'DUMMY', 'created_by' => self::USER,
        ], [
            ['account_id' => $this->acct('516000'), 'debit' => 43_800_000],
            ['account_id' => $this->acct('213100'), 'credit' => 43_800_000],
        ], self::USER);

        // 2. Menunggu persetujuan.
        $pending = $jp->saveDraft(null, [
            'voucher_number' => $jp->nextJournalNumber($today->toDateString()),
            'journal_id' => $genId, 'journal_kind' => 'MANUAL', 'entry_date' => $today->copy()->subDays(3)->toDateString(),
            'posting_date' => $today->copy()->subDays(3)->toDateString(), 'source_type' => 'MANUAL',
            'description' => 'Reklasifikasi beban perbaikan mesin ke suku cadang',
            'data_origin' => 'DUMMY', 'created_by' => self::USER,
        ], [
            ['account_id' => $this->acct('616000'), 'debit' => 68_400_000],
            ['account_id' => $this->acct('113700'), 'credit' => 68_400_000],
        ], self::USER);
        $jp->submit($pending, self::USER);

        // 3. Akrual bulan lalu yang sudah diposting + dijadwalkan auto-reverse.
        $prev = $today->copy()->subMonthNoOverflow()->endOfMonth();
        $accrual = $jp->post('GEN', 'MANUAL', 0, 'Akrual jasa subkon finishing '.$prev->translatedFormat('F Y'), [
            ['account_id' => $this->acct('513000'), 'debit' => 57_500_000],
            ['account_id' => $this->acct('213100'), 'credit' => 57_500_000],
        ], self::USER, $prev->toDateString(), [
            'voucher_number' => $jp->nextJournalNumber($prev->toDateString()),
            'journal_kind' => 'ACCRUAL', 'auto_reverse_date' => $prev->copy()->addDay()->toDateString(),
            'data_origin' => 'DUMMY',
        ]);
        $jp->reverse($accrual, $prev->copy()->addDay()->toDateString(), self::USER, 'auto-reversal akrual');

        // 4. Jurnal berulang (template) yang sudah diposting.
        $jp->post('GEN', 'MANUAL', 0, 'Alokasi beban sewa gudang bulanan', [
            ['account_id' => $this->acct('619000'), 'debit' => 18_000_000],
            ['account_id' => $this->acct('111200'), 'credit' => 18_000_000],
        ], self::USER, $prev->toDateString(), [
            'voucher_number' => $jp->nextJournalNumber($prev->toDateString()),
            'journal_kind' => 'RECURRING', 'data_origin' => 'DUMMY',
        ]);
    }

    // Rekening koran BCA 2 bulan terakhir: mutasi yang match dengan buku +
    // 3 mutasi khas bank yang tidak punya pasangan (biaya admin, bunga, transfer
    // masuk belum teridentifikasi). Lewat importCsv supaya parser ikut teruji.
    private function seedBank(BankReconService $bank): void
    {
        $accountId = $this->acct('111200');
        $from = now()->copy()->subMonthNoOverflow()->startOfMonth();
        $lines = FinVoucherLine::withoutGlobalScopes()->with('voucher')
            ->where('account_id', $accountId)
            ->whereHas('voucher', fn ($q) => $q->where('status', 'POSTED')->where('data_origin', 'DUMMY')->where('entry_date', '>=', $from->toDateString()))
            ->get()->sortBy(fn ($l) => $l->voucher->entry_date->format('Ymd'));

        $rows = [];
        $balance = 0.0;
        foreach ($lines as $l) {
            $rows[] = [
                'date' => $l->voucher->entry_date->format('d/m/Y'),
                'ref' => $l->voucher->reference_no ?: $l->voucher->voucher_number,
                'desc' => Str::limit($l->voucher->description, 60, ''),
                'in' => (float) $l->debit, 'out' => (float) $l->credit,
            ];
        }
        // Mutasi khas bank tanpa pasangan di buku.
        $rows[] = ['date' => now()->copy()->subDays(6)->format('d/m/Y'), 'ref' => 'ADM-BCA', 'desc' => 'Biaya administrasi rekening', 'in' => 0, 'out' => 35_000];
        $rows[] = ['date' => now()->copy()->subDays(5)->format('d/m/Y'), 'ref' => 'JASA-GIRO', 'desc' => 'Jasa giro bulan ini', 'in' => 1_250_000, 'out' => 0];
        $rows[] = ['date' => now()->copy()->subDays(2)->format('d/m/Y'), 'ref' => 'TRF-IN-88213', 'desc' => 'Transfer masuk belum teridentifikasi', 'in' => 42_500_000, 'out' => 0];

        usort($rows, fn ($a, $b) => Carbon::createFromFormat('d/m/Y', $a['date'])->timestamp <=> Carbon::createFromFormat('d/m/Y', $b['date'])->timestamp);

        // Saldo awal rekening koran = saldo buku sebelum periode mutasi.
        $balance = app(LedgerService::class)->accountBalance('111200', $from->copy()->subDay()->toDateString());
        $csv = "tanggal;referensi;keterangan;kredit;debet;saldo\n";
        foreach ($rows as $r) {
            $balance += $r['in'] - $r['out'];
            $csv .= implode(';', [$r['date'], $r['ref'], $r['desc'],
                $r['in'] ? number_format($r['in'], 2, ',', '.') : '',
                $r['out'] ? number_format($r['out'], 2, ',', '.') : '',
                number_format($balance, 2, ',', '.')])."\n";
        }
        $path = storage_path('app/dummy-bank-statement.csv');
        file_put_contents($path, $csv);
        $n = $bank->importCsv($accountId, $path, self::USER, 'DUMMY');
        @unlink($path);
        $matched = $bank->autoMatch($accountId, self::USER);
        $bank->reconcile($accountId, self::USER, 'DUMMY');
        $this->line("      {$n} mutasi diimpor, {$matched} cocok otomatis");
    }

    private function seedTaxAndClose(TaxEngine $tax, CloseService $close): void
    {
        $months = $this->months();
        $lastFullMonth = max(1, count($months) - 1);

        foreach ($months as $m) {
            $regs = $tax->generateMonth($this->year, $m, self::USER, 'DUMMY');
            if ($m >= $lastFullMonth) {
                continue; // masa terbaru dibiarkan Draft/Review supaya ada pekerjaan
            }
            foreach ($regs as $reg) {
                if ((float) $reg->payable_amount <= 0) {
                    continue;
                }
                $pay = Carbon::create($this->year, $m, 1)->addMonth()->day(min(12, 28));
                $tax->pay($reg, $pay->toDateString(), sprintf('%016d', mt_rand(1, 999999999) * 100 + $m), $this->acct('111200'), self::USER);
                $tax->file($reg, 'BPE-'.sprintf('%010d', mt_rand(1, 999999999)), self::USER);
            }
        }
        $tax->provisionCit($this->year, Carbon::create($this->year, $lastFullMonth, 1)->endOfMonth()->toDateString(), self::USER, 'DUMMY');

        // Tahun sebelumnya (berisi voucher saldo awal) ditutup penuh -- kalau
        // dibiarkan terbuka, "periode tutup buku berjalan" nyangkut di Des tahun lalu.
        foreach (FinPeriod::withoutGlobalScopes()->where('fiscal_year', $this->year - 1)->where('is_adjustment', false)->get() as $prevPeriod) {
            if ($prevPeriod->status === 'CLOSED') {
                continue;
            }
            $prevPeriod->update(['status' => 'CLOSED', 'closed_by' => self::USER, 'closed_at' => now(), 'updated_by' => self::USER]);
            FinPeriodLog::create(['period_id' => $prevPeriod->period_id, 'from_status' => 'OPEN', 'to_status' => 'CLOSED',
                'reason' => 'Tutup buku tahun lalu [dummy] seed UAT', 'changed_by' => self::USER, 'changed_at' => now()]);
        }

        // Tutup buku: bulan-bulan lama ditutup, bulan terakhir yang sudah lewat
        // dibuatkan checklist dengan sebagian kontrol selesai (seperti mockup).
        $guard = app(FinPeriodGuard::class);
        foreach ($months as $m) {
            $period = FinPeriod::withoutGlobalScopes()->where('fiscal_year', $this->year)->where('period_no', $m)->first();
            if (! $period) {
                continue;
            }
            if ($m < $lastFullMonth) {
                $close->start($period, self::USER, 'DUMMY');
                foreach ($period->tasks as $t) {
                    $close->setTask($t, 'COMPLETE', self::USER, 'Dummy UAT');
                }
                // Bypass gate Inventory: data dummy, dicatat jelas di alasan [dummy].
                $period->update(['status' => 'CLOSED', 'closed_by' => self::USER, 'closed_at' => now(), 'updated_by' => self::USER]);
                FinPeriodLog::create(['period_id' => $period->period_id, 'from_status' => 'OPEN', 'to_status' => 'CLOSED',
                    'reason' => 'Tutup buku [dummy] seed UAT', 'changed_by' => self::USER, 'changed_at' => now()]);
            } elseif ($m === $lastFullMonth) {
                $close->start($period, self::USER, 'DUMMY');
                foreach ($period->tasks->take(4) as $t) {
                    $close->setTask($t, 'COMPLETE', self::USER, 'Dummy UAT');
                }
            }
        }
        unset($guard);
    }
}
