<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu sidebar Finance & Accounting mengikuti mockup blueprint (CONTROL CENTER /
// TRANSACTION FLOWS / ACCOUNTING / CONTROLS & TAX / FINANCIAL REPORTS) + seed
// kategori aset & amortisasi (umur ekonomis & akun per kelompok pajak).
// Label memakai bahasa Inggris sesuai mockup (lihat project_english_menu_policy).
return new class extends Migration
{
    private const GROUPS = [
        ['ACC_CC', 'Control Center', 5],
        ['ACC_CTRL', 'Controls & Tax', 30],
        ['ACC_RPT', 'Financial Reports', 40],
    ];

    // code => [parent group code, label, sort, status]
    private const ITEMS = [
        'FIN_ENGINE' => ['ACC_CC', 'Process Engine', 10, 'READY_FOR_QC'],
        'FIN_DASH' => ['ACC_CC', 'Dashboard', 20, 'READY_FOR_QC'],
        'FIN_BANK' => ['FIN', 'Bank & Reconciliation', 12, 'READY_FOR_QC'],
        'ACC_AMORT' => ['ACC', 'Amortization', 8, 'READY_FOR_QC'],
        'ACC_CLOSE' => ['ACC_CTRL', 'Period Closing', 10, 'READY_FOR_QC'],
        'ACC_TAX' => ['ACC_CTRL', 'Indonesian Tax', 20, 'READY_FOR_QC'],
        'ACC_FS' => ['ACC_RPT', 'Financial Statements', 10, 'READY_FOR_QC'],
        'ACC_TB' => ['ACC_RPT', 'Trial Balance', 50, 'READY_FOR_QC'],
        'ACC_CF' => ['ACC_RPT', 'Cash Flow', 40, 'READY_FOR_QC'],
    ];

    // Item lama yang hanya di-relabel / dipindah + dinyalakan statusnya.
    private const RELABEL = [
        'FIN_AR' => ['FIN', 'Order to Cash', 20, 'READY_FOR_QC'],
        'FIN_AP' => ['FIN', 'Procure to Pay', 30, 'READY_FOR_QC'],
        'FIN_CASH' => ['FIN', 'Cashier (Cash & Bank)', 10, 'READY_FOR_QC'],
        'FIN_DP' => ['FIN', 'Down Payment', 15, 'READY_FOR_QC'],
        'ACC_COA' => ['ACC', 'Master of Accounts', 5, 'READY_FOR_QC'],
        'ACC_JRN' => ['ACC', 'Journal Entries', 10, 'READY_FOR_QC'],
        'ACC_GL' => ['ACC', 'General Ledger', 20, 'READY_FOR_QC'],
        'ACC_FIXEDASSET' => ['ACC', 'Fixed Assets', 7, 'READY_FOR_QC'],
        'ACC_BS' => ['ACC_RPT', 'Balance Sheet', 20, 'READY_FOR_QC'],
        'ACC_PL' => ['ACC_RPT', 'Income Statement', 30, 'READY_FOR_QC'],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $finacc = $sys->table('sys_app_menu')->where('code', 'FINACC')->value('id');
        if (! $finacc) {
            return;
        }

        $sys->table('sys_app_menu')->where('code', 'FIN')->update(['label' => 'Transaction Flows', 'sort_order' => 10]);
        $sys->table('sys_app_menu')->where('code', 'ACC')->update(['label' => 'Accounting', 'sort_order' => 20]);

        foreach (self::GROUPS as [$code, $label, $sort]) {
            if ($sys->table('sys_app_menu')->where('code', $code)->exists()) {
                continue;
            }
            $sys->table('sys_app_menu')->insert([
                'id' => (int) $sys->table('sys_app_menu')->max('id') + 1,
                'parent_id' => $finacc, 'code' => $code, 'label' => $label,
                'menu_type' => 'group', 'target_kind' => 'app', 'sort_order' => $sort,
                'activate' => 1, 'status_code' => 'READY_FOR_QC', 'created_at' => now(),
            ]);
        }

        foreach (self::ITEMS as $code => [$parent, $label, $sort, $status]) {
            $parentId = $sys->table('sys_app_menu')->where('code', $parent)->value('id');
            if (! $parentId || $sys->table('sys_app_menu')->where('code', $code)->exists()) {
                continue;
            }
            $sys->table('sys_app_menu')->insert([
                'id' => (int) $sys->table('sys_app_menu')->max('id') + 1,
                'parent_id' => $parentId, 'code' => $code, 'label' => $label,
                'menu_type' => 'item', 'object_type' => 'grid', 'target_kind' => 'app',
                'sort_order' => $sort, 'activate' => 1, 'status_code' => $status, 'created_at' => now(),
            ]);
        }

        foreach (self::RELABEL as $code => [$parent, $label, $sort, $status]) {
            $parentId = $sys->table('sys_app_menu')->where('code', $parent)->value('id');
            if (! $parentId) {
                continue;
            }
            // target lama menunjuk file ScriptCase (form_ar.php dst) -- dikosongkan
            // karena rute sekarang dipetakan lewat main-menu.blade.php itemRoutes().
            $sys->table('sys_app_menu')->where('code', $code)->update([
                'parent_id' => $parentId, 'label' => $label, 'sort_order' => $sort,
                'status_code' => $status, 'activate' => 1, 'target' => null,
            ]);
        }

        $sys->table('sys_app_menu')->where('code', 'FINACC')->update(['status_code' => 'READY_FOR_QC']);

        // Akses: Direksi sudah punya; tambah grup Finance/Accounting bila ada.
        $finaccModule = $sys->table('sec_module')->where('module_code', 'FINACC')->value('id_module');
        foreach (['DIREKSI', 'MGR_ACC'] as $groupCode) {
            $groupId = $sys->table('sec_group')->where('group_code', $groupCode)->value('id_group');
            if ($groupId && $finaccModule) {
                $sys->table('sec_group_permission')->updateOrInsert(
                    ['group_id' => $groupId, 'module_id' => $finaccModule],
                    ['can_access' => true, 'can_input' => true, 'can_approve' => true, 'can_export' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        $this->seedAssetCategories();
    }

    // Umur ekonomis mengikuti kelompok harta PMK 72/2023 (Kel 1 = 4 th,
    // Kel 2 = 8 th, Kel 3 = 16 th, bangunan permanen = 20 th); metode bisa
    // diubah per aset di layar.
    private function seedAssetCategories(): void
    {
        $acc = DB::connection('tm2acc');
        $id = fn (string $code) => (int) $acc->table('fin_account')->where('account_code', $code)->value('account_id');
        $rows = [
            ['MACH', 'Mesin Cetak Offset', 'FIXED', 'Kelompok 3', 192, 'SL', '121100', '121900', '515000'],
            ['FINISH', 'Mesin Finishing (Die-Cutter/Folder Gluer)', 'FIXED', 'Kelompok 2', 96, 'SL', '121200', '121900', '515000'],
            ['VEH', 'Kendaraan', 'FIXED', 'Kelompok 2', 96, 'DDB', '121300', '121910', '617000'],
            ['ITEQ', 'Peralatan & Inventaris Kantor', 'FIXED', 'Kelompok 1', 48, 'SL', '121400', '121920', '617000'],
            ['BLDG', 'Bangunan Pabrik (permanen)', 'FIXED', 'Bangunan', 240, 'SL', '121500', '121930', '617000'],
            ['SOFT', 'Software & Lisensi', 'INTANGIBLE', 'Kelompok 1', 48, 'SL', '131100', '131900', '618000'],
            ['LHI', 'Renovasi Bangunan Sewa', 'LEASEHOLD', '—', 60, 'SL', '131200', '131900', '618000'],
            ['PREPINS', 'Asuransi Dibayar Dimuka', 'PREPAID', '—', 12, 'SL', '115100', '115100', '618000'],
            ['PREPRENT', 'Sewa Dibayar Dimuka', 'PREPAID', '—', 12, 'SL', '115100', '115100', '619000'],
        ];
        foreach ($rows as [$code, $name, $type, $tax, $life, $method, $asset, $accum, $expense]) {
            if (! $id($asset) || ! $id($accum) || ! $id($expense)) {
                continue;
            }
            $acc->table('fin_asset_category')->updateOrInsert(['category_code' => $code], [
                'category_name' => $name, 'asset_type' => $type, 'tax_group' => $tax,
                'useful_life_months' => $life, 'method' => $method,
                'asset_account_id' => $id($asset), 'accum_account_id' => $id($accum), 'expense_account_id' => $id($expense),
                'activate' => 1, 'created_by' => '_system', 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_app_menu')->whereIn('code', array_keys(self::ITEMS))->delete();
        $sys->table('sys_app_menu')->whereIn('code', array_column(self::GROUPS, 0))->delete();
        DB::connection('tm2acc')->table('fin_asset_category')->whereIn('category_code', ['MACH', 'FINISH', 'VEH', 'ITEQ', 'BLDG', 'SOFT', 'LHI', 'PREPINS', 'PREPRENT'])->delete();
    }
};
