<?php

namespace Database\Seeders;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinCostCenter;
use App\Models\Acc\FinJournal;
use Illuminate\Database\Seeder;

// TIER_3A_FINANCE.sql §9 — seed jurnal + cost center + COA (54 akun) supaya
// Master COA tidak kosong & voucher fase-2 punya jurnal/cost-center dasar
// untuk dipilih. FIN_TIER_4.md §7 checklist: "Seeder jalankan TIER_3A_FINANCE.sql".
class FinanceMasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedJournals();
        $this->seedCostCenters();
        $this->seedAccounts();
        $this->mapDefaultCostCenters();
    }

    private function seedJournals(): void
    {
        $journals = [
            ['journal_code' => 'CSH', 'journal_name' => 'Jurnal Kas Operasional Pabrik', 'journal_type' => 'CASH_BANK'],
            ['journal_code' => 'BNK', 'journal_name' => 'Jurnal Bank Utama & Ekspor', 'journal_type' => 'CASH_BANK'],
            ['journal_code' => 'SAL', 'journal_name' => 'Jurnal Penjualan Packaging', 'journal_type' => 'SALES'],
            ['journal_code' => 'PUR', 'journal_name' => 'Jurnal Pembelian Material', 'journal_type' => 'PURCHASE'],
            ['journal_code' => 'GEN', 'journal_name' => 'Jurnal Umum & Penyesuaian', 'journal_type' => 'GENERAL'],
        ];

        foreach ($journals as $j) {
            FinJournal::query()->updateOrCreate(['journal_code' => $j['journal_code']], $j);
        }
    }

    private function seedCostCenters(): void
    {
        $centers = [
            ['cc_code' => 'CC-PROD-PRINT', 'cc_name' => 'Produksi - Cetak Offset', 'cc_group' => 'PRODUCTION'],
            ['cc_code' => 'CC-PROD-FINISH', 'cc_name' => 'Produksi - Finishing (Pond/Lem)', 'cc_group' => 'PRODUCTION'],
            ['cc_code' => 'CC-PRODUCTION', 'cc_name' => 'Produksi - Umum', 'cc_group' => 'PRODUCTION'],
            ['cc_code' => 'CC-PREPRESS', 'cc_name' => 'Prepress / CTP', 'cc_group' => 'PREPRESS'],
            ['cc_code' => 'CC-TOOLING', 'cc_name' => 'Tooling / Pisau Pond', 'cc_group' => 'PRODUCTION'],
            ['cc_code' => 'CC-QC-LAB', 'cc_name' => 'QC & Lab Warna', 'cc_group' => 'QC'],
            ['cc_code' => 'CC-WH-PAPER', 'cc_name' => 'Gudang Kertas & Board', 'cc_group' => 'WAREHOUSE'],
            ['cc_code' => 'CC-WH-INK', 'cc_name' => 'Gudang Tinta & Chemical', 'cc_group' => 'WAREHOUSE'],
            ['cc_code' => 'CC-WH-FG', 'cc_name' => 'Gudang Barang Jadi', 'cc_group' => 'WAREHOUSE'],
            ['cc_code' => 'CC-SALES', 'cc_name' => 'Penjualan', 'cc_group' => 'SALES'],
            ['cc_code' => 'CC-PURCHASING', 'cc_name' => 'Pembelian', 'cc_group' => 'PURCHASING'],
            ['cc_code' => 'CC-FINANCE', 'cc_name' => 'Keuangan', 'cc_group' => 'FINANCE'],
            ['cc_code' => 'CC-TAX', 'cc_name' => 'Perpajakan', 'cc_group' => 'TAX'],
        ];

        foreach ($centers as $c) {
            FinCostCenter::query()->updateOrCreate(['cc_code' => $c['cc_code']], $c);
        }
    }

    private function seedAccounts(): void
    {
        // account_id manual & stabil (dipakai parent_id anak) — persis TIER_3A_FINANCE.sql §9.
        // account_type/normal_balance/parent_id/is_recon/is_postable/tax_scheme_code per kolom.
        $accounts = [
            // ASET
            [100, '100000', 'ASET (ASSETS)', 'ASSET', 'DEBIT', null, 0, 0, null],
            [110, '110000', 'ASET LANCAR', 'ASSET', 'DEBIT', 100, 0, 0, null],
            [111, '111100', 'Kas Operasional Pabrik', 'ASSET', 'DEBIT', 110, 0, 1, null],
            [112, '111200', 'Bank BCA - Rekening Utama IDR', 'ASSET', 'DEBIT', 110, 0, 1, null],
            [113, '111300', 'Bank Mandiri - USD (Ekspor)', 'ASSET', 'DEBIT', 110, 0, 1, null],
            [121, '112100', 'Piutang Usaha - Customer Lokal', 'ASSET', 'DEBIT', 110, 1, 1, null],
            [122, '112200', 'Piutang Usaha - Customer Ekspor', 'ASSET', 'DEBIT', 110, 1, 1, null],
            [131, '113100', 'Persediaan Bahan Baku - Kertas & Board', 'ASSET', 'DEBIT', 110, 0, 1, null],
            [132, '113200', 'Persediaan Bahan Baku - Tinta & Chemical', 'ASSET', 'DEBIT', 110, 0, 1, null],
            [133, '113300', 'Persediaan Tooling - Pisau Pond & Deboss', 'ASSET', 'DEBIT', 110, 0, 1, null],
            [134, '113400', 'Persediaan WIP (Cetak/Finishing)', 'ASSET', 'DEBIT', 110, 0, 1, null],
            [135, '113500', 'Persediaan Barang Jadi (Packaging)', 'ASSET', 'DEBIT', 110, 0, 1, null],
            [141, '114100', 'PPN Masukan (Input VAT)', 'ASSET', 'DEBIT', 110, 0, 1, 'PPN_IN'],
            [142, '114200', 'Uang Muka PPh Pasal 22 - Kertas Mill', 'ASSET', 'DEBIT', 110, 0, 1, 'PPH22'],
            [143, '114300', 'Uang Muka Pembelian ke Supplier', 'ASSET', 'DEBIT', 110, 1, 1, null],
            [150, '120000', 'ASET TETAP PABRIK', 'ASSET', 'DEBIT', 100, 0, 0, null],
            [151, '121100', 'Mesin Cetak Offset', 'ASSET', 'DEBIT', 150, 0, 1, null],
            [152, '121200', 'Mesin Finishing (Die-Cutter/Folder Gluer)', 'ASSET', 'DEBIT', 150, 0, 1, null],
            [159, '121900', 'Akumulasi Penyusutan Mesin', 'ASSET', 'CREDIT', 150, 0, 1, null],
            // KEWAJIBAN
            [200, '200000', 'KEWAJIBAN (LIABILITIES)', 'LIABILITY', 'CREDIT', null, 0, 0, null],
            [210, '210000', 'KEWAJIBAN JANGKA PENDEK', 'LIABILITY', 'CREDIT', 200, 0, 0, null],
            [211, '211100', 'Hutang Dagang - Supplier Kertas & Board', 'LIABILITY', 'CREDIT', 210, 1, 1, null],
            [212, '211200', 'Hutang Dagang - Supplier Tinta & Chemical', 'LIABILITY', 'CREDIT', 210, 1, 1, null],
            [213, '211300', 'Hutang Jasa Subkon Finishing (Laminasi/UV)', 'LIABILITY', 'CREDIT', 210, 1, 1, null],
            [221, '212100', 'Hutang PPN Keluaran (Output VAT)', 'LIABILITY', 'CREDIT', 210, 0, 1, 'PPN_OUT'],
            [222, '212200', 'Hutang PPh Pasal 23 (Jasa Subkon)', 'LIABILITY', 'CREDIT', 210, 0, 1, 'PPH23'],
            [223, '212300', 'Hutang PPh Pasal 22 (Industri Kertas)', 'LIABILITY', 'CREDIT', 210, 0, 1, 'PPH22'],
            [231, '213100', 'Beban Akrual / YMH Dibayar', 'LIABILITY', 'CREDIT', 210, 0, 1, null],
            [232, '213200', 'Uang Muka Penjualan dari Pelanggan', 'LIABILITY', 'CREDIT', 210, 1, 1, null],
            // EKUITAS
            [300, '300000', 'EKUITAS (EQUITY)', 'EQUITY', 'CREDIT', null, 0, 0, null],
            [311, '311000', 'Modal Disetor', 'EQUITY', 'CREDIT', 300, 0, 1, null],
            [321, '321000', 'Laba Ditahan', 'EQUITY', 'CREDIT', 300, 0, 1, null],
            [322, '322000', 'Laba Tahun Berjalan', 'EQUITY', 'CREDIT', 300, 0, 1, null],
            // PENDAPATAN
            [400, '400000', 'PENDAPATAN (REVENUE)', 'REVENUE', 'CREDIT', null, 0, 0, null],
            [411, '411000', 'Penjualan Packaging - Food Grade Box', 'REVENUE', 'CREDIT', 400, 0, 1, 'PPN_OUT'],
            [412, '412000', 'Penjualan Packaging - Non-Food/Commercial', 'REVENUE', 'CREDIT', 400, 0, 1, 'PPN_OUT'],
            [413, '413000', 'Pendapatan Jasa Maklon / Subkon', 'REVENUE', 'CREDIT', 400, 0, 1, 'PPN_OUT'],
            [421, '421000', 'Diskon Penjualan & Retur', 'REVENUE', 'DEBIT', 400, 0, 1, null],
            // HPP / COGS
            [500, '500000', 'BEBAN POKOK PENJUALAN (COGS)', 'COGS', 'DEBIT', null, 0, 0, null],
            [511, '511000', 'HPP - Bahan Baku Kertas & Board', 'COGS', 'DEBIT', 500, 0, 1, null],
            [512, '512000', 'HPP - Tinta, Varnish & Chemical', 'COGS', 'DEBIT', 500, 0, 1, null],
            [513, '513000', 'HPP - Jasa Subkon Finishing External', 'COGS', 'DEBIT', 500, 0, 1, null],
            [514, '514000', 'HPP - Tenaga Kerja Langsung', 'COGS', 'DEBIT', 500, 0, 1, null],
            [515, '515000', 'HPP - Penyusutan Mesin Produksi', 'COGS', 'DEBIT', 500, 0, 1, null],
            [516, '516000', 'HPP - Listrik & Daya Pabrik', 'COGS', 'DEBIT', 500, 0, 1, null],
            // BEBAN OPERASIONAL
            [600, '600000', 'BEBAN OPERASIONAL (EXPENSES)', 'EXPENSE', 'DEBIT', null, 0, 0, null],
            [611, '611000', 'Beban Gaji Mgmt, Sales & Admin', 'EXPENSE', 'DEBIT', 600, 0, 1, null],
            [612, '612000', 'Beban QC & Lab Warna (Delta E)', 'EXPENSE', 'DEBIT', 600, 0, 1, null],
            [613, '613000', 'Beban Amortisasi Pelat CTP & Artwork', 'EXPENSE', 'DEBIT', 600, 0, 1, null],
            [614, '614000', 'Beban Pemasaran & Komisi Sales', 'EXPENSE', 'DEBIT', 600, 0, 1, null],
            [615, '615000', 'Beban Kerugian Kertas Rusak (Waste)', 'EXPENSE', 'DEBIT', 600, 0, 1, null],
            // LAIN-LAIN
            [800, '800000', 'PENDAPATAN & BEBAN LAIN-LAIN', 'OTHER_INCOME', 'CREDIT', null, 0, 0, null],
            [811, '811000', 'Selisih Kurs Transaksi', 'OTHER_INCOME', 'CREDIT', 800, 0, 1, null],
            [812, '812000', 'Beban Administrasi Bank & Bunga', 'OTHER_EXPENSE', 'DEBIT', 800, 0, 1, null],
        ];

        // Urutan array = urutan insert TIER_3A_FINANCE.sql §9: header (parent)
        // selalu lebih dulu dari anaknya, FK parent_id selalu terpenuhi.
        foreach ($accounts as [$id, $code, $name, $type, $balance, $parentId, $isRecon, $isPostable, $taxScheme]) {
            FinAccount::query()->updateOrCreate(
                ['account_id' => $id],
                [
                    'account_code' => $code,
                    'account_name' => $name,
                    'account_type' => $type,
                    'normal_balance' => $balance,
                    'parent_id' => $parentId,
                    'is_recon' => (bool) $isRecon,
                    'is_postable' => (bool) $isPostable,
                    'tax_scheme_code' => $taxScheme,
                ]
            );
        }
    }

    private function mapDefaultCostCenters(): void
    {
        $map = [
            'CC-PROD-PRINT' => ['511000', '512000', '515000', '516000', '615000'],
            'CC-PROD-FINISH' => ['513000'],
            'CC-PREPRESS' => ['613000'],
            'CC-QC-LAB' => ['612000'],
            'CC-TAX' => ['114100', '114200', '212100', '212200', '212300'],
        ];

        foreach ($map as $ccCode => $accountCodes) {
            $ccId = FinCostCenter::where('cc_code', $ccCode)->value('cc_id');

            if ($ccId) {
                FinAccount::whereIn('account_code', $accountCodes)->update(['default_cc_id' => $ccId]);
            }
        }
    }
}
