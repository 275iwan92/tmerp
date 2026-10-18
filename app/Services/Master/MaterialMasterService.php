<?php

/*
 * REMARK
 * Tujuan       : Master Raw Material dari layar v2 md-raw-form: simpan item (kategori yang menyusun kode item, spesifikasi dan QC, UoM dan pengadaan, stok dan lokasi, akun GL), Confirm, aktif / nonaktif, Delete (atau nonaktif bila masih dipakai).
 * Sumber hulu  : Aturan dipindah apa adanya dari halaman v1 pages::master.raw-material.entry (diarsipkan di tmerp_unused/master_raw_material_entry_v1_2026-10-18.blade.php): kode = prefix kategori 1..5 digabung (+ angka urut bila kembar), kategori dan kode terkunci sesudah Confirm, Confirm wajib 3 akun (Inventory Asset, COGS, GR/IR). Rantai kategori dari App\Services\Master\MaterialCategoryService.
 * Input        : Isian form (header + 5 tab), id terdalam rantai kategori (category), user.
 * Output       : tm2mst.mat_raw berubah (mat_raw.category1..5 menyimpan NAMA rantai, material_code menyimpan kode); ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_master.php (v2.md.raw.*), layar md-raw-form, tes MaterialMasterServiceTest.
 * Status       : Aktif. Item lama tanpa kategori (legacy) tetap utuh selama rantai tidak diubah; kategori yang dikosongkan di item lama ditolak, tidak lagi menghapus nama kategori diam-diam. Delete permanen hanya bila tidak ada stok dan tidak ada yang menunjuk item (MaterialGuardService).
 */

namespace App\Services\Master;

use App\Models\Acc\FinCostCenter;
use App\Models\Acc\FinProfitCenter;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\UomUnit;
use App\Models\Sys\SecUser;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MaterialMasterService
{
    private const NUMBERS = ['gram_weight', 'cm_length', 'cm_width', 'mm_thickness', 'shelf_life_days', 'moisture_pct', 'conversion_to_stock', 'conversion_to_usage',
        'lead_time_days', 'tol_purchase', 'min_stock', 'safety_stock', 'reorder_point', 'max_stock'];

    private const FLAGS = ['purchasable', 'qc_required', 'is_fx_sensitive'];

    public function __construct(private MaterialCategoryService $categories, private MaterialGuardService $guard) {}

    public function save(?MatRaw $m, array $in, SecUser $user): MatRaw
    {
        foreach (self::NUMBERS as $k) {
            $in[$k] = Locale::input($in[$k] ?? null);
        }
        foreach (self::FLAGS as $k) {
            $in[$k] = ! empty($in[$k]);
        }
        $in['conversion_to_stock'] = $in['conversion_to_stock'] ?: 1;

        $d = Validator::make($in, [
            'material_name' => ['required', 'string', 'max:200'],
            'item_type' => ['required', 'in:MATERIAL,SERVICE,FREE_SAMPLE'],
            'hs_code' => ['nullable', 'string', 'max:50'],
            'old_item_code' => ['nullable', 'string', 'max:100'],
            'old_item_name' => ['nullable', 'string', 'max:255'],
            'gram_weight' => ['nullable', 'numeric', 'min:0'],
            'cm_length' => ['nullable', 'numeric', 'min:0'],
            'cm_width' => ['nullable', 'numeric', 'min:0'],
            'mm_thickness' => ['nullable', 'numeric', 'min:0'],
            'brand' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0'],
            'moisture_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'uom_stock' => ['nullable', 'string', 'max:50'],
            'uom_purchase' => ['nullable', 'string', 'max:50'],
            'uom_usage' => ['nullable', 'string', 'max:50'],
            'conversion_to_stock' => ['required', 'numeric', 'gt:0'],
            'conversion_to_usage' => ['nullable', 'numeric', 'gt:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'tol_purchase' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'purchase_tax_code' => ['required', 'in:PPN,NON_PPN'],
            'suggest_supplier_id' => ['nullable', 'integer', 'exists:tm2mst.sup_supplier,id_supplier'],
            'costing_method' => ['required', 'in:AVG,FIFO,LIFO,STANDARD'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'def_location_id' => ['nullable', 'string', 'max:50'],
            'valuation_class' => ['nullable', 'string', 'max:100'],
            'stock_asset_account_id' => ['nullable', 'integer', 'exists:tm2acc.fin_account,account_id'],
            'cogs_account_id' => ['nullable', 'integer', 'exists:tm2acc.fin_account,account_id'],
            'gr_ir_account_id' => ['nullable', 'integer', 'exists:tm2acc.fin_account,account_id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:tm2acc.fin_cost_center,cc_id'],
            'profit_center_id' => ['nullable', 'integer', 'exists:tm2acc.fin_profit_center,pc_id'],
        ], [], ['material_name' => 'Item name', 'item_type' => 'Item type', 'conversion_to_stock' => 'Purchase to stock conversion', 'conversion_to_usage' => 'Stock to usage conversion'])->validate();

        $data = array_map(fn ($v) => $v === '' ? null : $v, $d) + array_intersect_key($in, array_flip(self::FLAGS)) + ['update_by' => $user->login];
        // Kategori dan kode hanya berubah selama item belum Confirm.
        if (! $m || ! $m->stat_confirm) {
            $data += $this->categoryData($m, (int) ($in['category'] ?? 0));
        }

        if ($m) {
            $m->update($data);

            return $m;
        }

        return MatRaw::create($data + ['activate' => true, 'create_by' => $user->login]);
    }

    /** Confirm = simpan isian form, lalu wajib 3 akun terisi; setelah itu kategori dan kode terkunci. */
    public function confirm(MatRaw $m, array $in, SecUser $user): MatRaw
    {
        return DB::connection('tm2mst')->transaction(function () use ($m, $in, $user) {
            $m = $this->save($m, $in, $user);
            if (! $m->stock_asset_account_id || ! $m->cogs_account_id || ! $m->gr_ir_account_id) {
                throw ValidationException::withMessages(['confirm' => 'Fill in all three Accounts (Inventory Asset, COGS, GR/IR) in the Accounting tab before Confirm.']);
            }
            $m->update(['stat_confirm' => true, 'confirmed_at' => now(), 'confirmed_by' => $user->login, 'update_by' => $user->login]);

            return $m;
        });
    }

    public function setActive(MatRaw $m, bool $active, SecUser $user): void
    {
        $m->update(['activate' => $active, 'update_by' => $user->login]);
    }

    /**
     * Delete permanen bila stok nol dan tidak ada yang menunjuk item; kalau ada, nonaktifkan.
     *
     * @return list<string> alasan yang menahan penghapusan (kosong = sudah dihapus)
     */
    public function delete(MatRaw $m, SecUser $user): array
    {
        $reasons = $this->guard->blockingReasons($m->material_id);
        if ((float) $m->cur_stock != 0.0) {
            array_unshift($reasons, 'stock on hand');
        }
        if ($reasons !== []) {
            $m->update(['activate' => false, 'update_by' => $user->login]);

            return $reasons;
        }
        $m->delete();

        return [];
    }

    /** @return array<string, int> kelompok => jumlah baris yang menunjuk item (strip "Used in") */
    public function usage(MatRaw $m): array
    {
        return $this->guard->usage($m->material_id);
    }

    /** Id terdalam rantai kategori dari NAMA yang tersimpan di mat_raw.category1..5 (0 = tidak ada / tidak cocok). */
    public function chainId(MatRaw $m): int
    {
        $node = null;
        foreach ([1 => $m->category1, 2 => $m->category2, 3 => $m->category3, 4 => $m->category4, 5 => $m->category5] as $level => $name) {
            if (! $name) {
                break;
            }
            $next = MatCategory::level($level)->where('category_name', $name)
                ->when($node, fn ($q) => $q->where('parent_id', $node->id_category))->first();
            if (! $next) {
                break;
            }
            $node = $next;
        }

        return (int) $node?->id_category;
    }

    /** @return array<string, string> code => "CODE — name" */
    public function uomOptions(): array
    {
        return UomUnit::where('activate', true)->orderBy('uom_code')->get()->mapWithKeys(fn ($u) => [$u->uom_code => $u->uom_code.' — '.$u->uom_name])->all();
    }

    /** @return array<int, string> */
    public function supplierOptions(): array
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('activate', 1)->orderBy('supplier_name')->pluck('supplier_name', 'id_supplier')->all();
    }

    /** @return array<int, string> */
    public function accountOptions(): array
    {
        return DB::connection('tm2acc')->table('fin_account')->where('is_postable', 1)->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' - '.$a->account_name])->all();
    }

    /** @return array<int, string> */
    public function costCenterOptions(): array
    {
        return FinCostCenter::where('activate', true)->orderBy('cc_code')->get()->mapWithKeys(fn ($c) => [$c->cc_id => $c->cc_code.' — '.$c->cc_name])->all();
    }

    /** @return array<int, string> */
    public function profitCenterOptions(): array
    {
        return FinProfitCenter::where('activate', true)->orderBy('pc_code')->get()->mapWithKeys(fn ($c) => [$c->pc_id => $c->pc_code.' — '.$c->pc_name])->all();
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(MatRaw $m): array
    {
        $rows = [['at' => $m->create_log?->toDateTimeString(), 'by' => $m->create_by, 'action' => 'Created', 'note' => '']];
        if ($m->update_log && $m->update_log != $m->create_log) {
            $rows[] = ['at' => $m->update_log->toDateTimeString(), 'by' => $m->update_by, 'action' => 'Last modified', 'note' => ''];
        }
        if ($m->stat_confirm) {
            $rows[] = ['at' => $m->confirmed_at?->toDateTimeString(), 'by' => $m->confirmed_by, 'action' => 'Confirmed', 'note' => 'Category and item code locked'];
        }

        return array_reverse($rows);
    }

    /** category1..5 + material_code untuk item baru atau yang rantainya diubah; item yang rantainya tidak diubah dibiarkan utuh. */
    private function categoryData(?MatRaw $m, int $picked): array
    {
        $current = $m ? $this->chainId($m) : 0;
        if ($m && $picked === $current) {
            return [];
        }
        $node = $picked ? MatCategory::find($picked) : null;
        if (! $node || ! $this->categories->complete($node)) {
            throw ValidationException::withMessages(['category' => 'Pick Category 1 (and the levels below it) to build the item code.']);
        }

        $names = $this->categories->names($node);
        $base = $this->categories->code($node);
        $code = $base;
        for ($suffix = 2; MatRaw::where('material_code', $code)->when($m, fn ($q) => $q->whereKeyNot($m->material_id))->exists(); $suffix++) {
            $code = $base.$suffix;
        }

        return ['material_code' => $code, 'category1' => $names[0] ?? null, 'category2' => $names[1] ?? null, 'category3' => $names[2] ?? null,
            'category4' => $names[3] ?? null, 'category5' => $names[4] ?? null];
    }
}
