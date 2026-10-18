<?php

/*
 * REMARK
 * Tujuan       : Aset tetap dari layar v2 acc-fixed-asset-form dan Depreciation Run: simpan draft aset, kapitalisasi, pelepasan, jadwal penyusutan, pratinjau dan jalankan penyusutan bulanan; opsi kategori, akun, periode.
 * Sumber hulu  : Aturan form dipindah apa adanya dari halaman v1 pages::finance.assets (diarsipkan di tmerp_unused/finance_assets_v1_2026-10-18.blade.php); rumus (garis lurus, saldo menurun ganda), jurnal kapitalisasi / penyusutan / pelepasan tetap di AssetService.
 * Input        : Isian form aset, kategori, tanggal pelepasan + hasil jual + akun penerima, periode run, user.
 * Output       : tm2acc.fin_asset (DRAFT > ACTIVE > FULLY_DEPRECIATED > DISPOSED), fin_asset_depr, voucher GEN berubah; ValidationException / abort 422 bila aturan dilanggar.
 * Dipakai oleh : routes/v2_finance.php (v2.acc.asset.*, v2.acc.depr.*), layar acc-fixed-asset-form dan acc-depreciation-run, tes FixedAssetServiceTest.
 * Status       : Aktif. Kode aset FA-<kategori>-YYYY-NNNN terbit saat Save pertama; tanggal review = tanggal perolehan + 1 tahun. Layar Amortization (T3f) memakai Depreciation Run yang sama dengan jenis 'amortization'.
 */

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinAssetDepr;
use App\Models\Acc\FinCostCenter;
use App\Models\Acc\FinVoucher;
use App\Models\Sys\SecUser;
use Carbon\Carbon;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

final class FixedAssetService
{
    public const METHODS = ['SL' => 'Straight line', 'DDB' => 'Double declining balance'];

    /** jenis run => asset_type kategori yang disusutkan / diamortisasi */
    public const RUN_KINDS = ['fixed' => ['FIXED'], 'amortization' => ['PREPAID', 'INTANGIBLE', 'LEASEHOLD']];

    public const RUN_LABELS = ['fixed' => 'Fixed assets', 'amortization' => 'Prepaid, intangible and leasehold'];

    public function __construct(private AssetService $assets) {}

    /** Isian awal aset baru menurut kategori: umur dan metode dari kategori, akun lawan GR/IR 211900, tanggal hari ini. */
    public function defaults(FinAssetCategory $c): array
    {
        return ['life' => (int) ($c->useful_life_months ?: 48), 'method' => $c->method ?: 'SL', 'acq_date' => now()->toDateString(), 'start_date' => now()->startOfMonth()->toDateString(),
            'credit_account_id' => (int) FinAccount::where('account_code', '211900')->value('account_id'), 'residual' => 0];
    }

    public function save(?FinAsset $a, array $in, SecUser $user): FinAsset
    {
        abort_if($a && $a->status !== 'DRAFT', 422, 'Only a DRAFT asset can be changed.');
        foreach (['cost', 'residual'] as $k) {
            $in[$k] = Locale::input($in[$k] ?? null) ?? ($in[$k] ?? null);
        }
        $d = Validator::make($in, [
            'asset_name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:tm2acc.fin_asset_category,category_id'],
            'acq_date' => ['required', 'date'],
            'start_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:1'],
            'residual' => ['required', 'numeric', 'min:0', 'lt:cost'],
            'life' => ['required', 'integer', 'min:1', 'max:600'],
            'method' => ['required', 'in:'.implode(',', array_keys(self::METHODS))],
            'credit_account_id' => ['required', 'exists:tm2acc.fin_account,account_id'],
            'cc_id' => ['nullable', 'integer', 'exists:tm2acc.fin_cost_center,cc_id'],
            'location' => ['nullable', 'string', 'max:150'],
            'source_ref' => ['nullable', 'string', 'max:150'],
        ], [], ['asset_name' => 'Asset name', 'cost' => 'Acquisition cost', 'life' => 'Useful life (months)', 'acq_date' => 'Acquisition date', 'start_date' => 'Depreciation start',
            'credit_account_id' => 'Capitalization credit account'])->validate();

        $data = ['asset_name' => $d['asset_name'], 'category_id' => (int) $d['category_id'], 'acquisition_date' => $d['acq_date'], 'depr_start_date' => $d['start_date'],
            'acquisition_cost' => $d['cost'], 'residual_value' => $d['residual'], 'useful_life_months' => (int) $d['life'], 'method' => $d['method'],
            'cc_id' => ($d['cc_id'] ?? null) ?: null, 'location' => ($d['location'] ?? null) ?: null, 'source_ref' => ($d['source_ref'] ?? null) ?: null,
            'credit_account_id' => (int) $d['credit_account_id'], 'next_review_date' => Carbon::parse($d['acq_date'])->addYear()->toDateString(), 'updated_by' => $user->login];

        if ($a) {
            $a->update($data);

            return $a;
        }
        $cat = FinAssetCategory::findOrFail($d['category_id']);

        return FinAsset::create($data + ['asset_code' => $this->assets->nextCode('FA-'.$cat->category_code, $d['acq_date']), 'status' => 'DRAFT', 'created_by' => $user->login]);
    }

    public function capitalize(FinAsset $a, SecUser $user): void
    {
        $this->assets->capitalize($a, $user->login);
    }

    public function dispose(FinAsset $a, array $in, SecUser $user): void
    {
        $in['disp_amount'] = Locale::input($in['disp_amount'] ?? null) ?? ($in['disp_amount'] ?? 0);
        $d = Validator::make($in, ['disp_date' => ['required', 'date'], 'disp_amount' => ['required', 'numeric', 'min:0'], 'disp_bank' => ['required', 'exists:tm2acc.fin_account,account_id']],
            [], ['disp_date' => 'Disposal date', 'disp_amount' => 'Proceeds', 'disp_bank' => 'Proceeds to'])->validate();
        $this->assets->dispose($a, $d['disp_date'], (float) $d['disp_amount'], (int) $d['disp_bank'], $user->login);
    }

    /** @return array{0: string, 1: ?string} [status panel DevLib, tahap] */
    public function formState(FinAsset $a): array
    {
        return match ($a->status) {
            'ACTIVE' => ['confirmed', 'Active'],
            'FULLY_DEPRECIATED' => ['confirmed', 'Fully depreciated'],
            'DISPOSED' => ['closed', 'Disposed'],
            default => ['draft', null],
        };
    }

    public function accumulated(FinAsset $a): float
    {
        return $this->assets->accumulated($a);
    }

    /** @return array<int, array{month: string, amount: float, accumulated: float, nbv: float, status: string}> */
    public function schedule(FinAsset $a): array
    {
        return $this->assets->schedule($a);
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(FinAsset $a): array
    {
        $voucher = fn (?int $id) => $id ? FinVoucher::withoutGlobalScopes()->find($id) : null;
        $rows = [['at' => $a->created_at?->toDateTimeString(), 'by' => $a->created_by, 'action' => 'Created', 'note' => $a->asset_code]];
        if ($v = $voucher($a->capitalize_voucher_id)) {
            $rows[] = ['at' => $v->posted_at?->toDateTimeString(), 'by' => $v->posted_by, 'action' => 'Capitalized', 'note' => $v->voucher_number];
        }
        $posted = FinAssetDepr::withoutGlobalScopes()->where('asset_id', $a->asset_id)->count();
        if ($posted) {
            $rows[] = ['at' => FinAssetDepr::withoutGlobalScopes()->where('asset_id', $a->asset_id)->max('created_at'), 'by' => '', 'action' => 'Depreciated', 'note' => $posted.' months posted'];
        }
        if ($v = $voucher($a->disposal_voucher_id)) {
            $rows[] = ['at' => $v->posted_at?->toDateTimeString(), 'by' => $v->posted_by, 'action' => 'Disposed', 'note' => $v->voucher_number];
        }

        return array_reverse($rows);
    }

    /** @return array<int, string> category_id => "nama (kelompok pajak)": kategori aset tetap aktif */
    public function categoryOptions(?int $include = null): array
    {
        return FinAssetCategory::whereIn('asset_type', self::RUN_KINDS['fixed'])->where(fn ($q) => $q->where('activate', true)->when($include, fn ($qq) => $qq->orWhere('category_id', $include)))
            ->orderBy('category_code')->get()->mapWithKeys(fn ($c) => [$c->category_id => $c->category_name.' ('.$c->tax_group.')'])->all();
    }

    /** @return array<int, string> */
    public function costCenterOptions(): array
    {
        return FinCostCenter::where('activate', true)->orderBy('cc_code')->pluck('cc_name', 'cc_id')->all();
    }

    /** @return array<int, string> akun lawan kapitalisasi: kas/bank (111), hutang (211) dan 215 */
    public function creditOptions(?int $include = null): array
    {
        return FinAccount::where('is_postable', true)->where(fn ($q) => $q->where('account_code', 'like', '111%')->orWhere('account_code', 'like', '211%')->orWhere('account_code', 'like', '215%')
            ->when($include, fn ($qq) => $qq->orWhere('account_id', $include)))->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    /** @return array<int, string> akun kas / bank penerima hasil pelepasan */
    public function bankOptions(): array
    {
        return FinAccount::where('is_postable', true)->where('activate', true)->where('account_code', 'like', '111%')->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    /** @return array<string, string> 'YYYY-MM' => 'Bulan Tahun': 12 bulan terakhir */
    public function periodOptions(): array
    {
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $d = now()->startOfMonth()->subMonths($i);
            $out[$d->format('Y-m')] = $d->format('F Y');
        }

        return $out;
    }

    /** Pratinjau run (tanpa menulis): aset yang belum disusutkan pada periode itu. @return Collection<int, object{asset: FinAsset, amount: float}> */
    public function runPreview(string $period, string $kind): Collection
    {
        [$year, $month] = $this->period($period);

        return $this->assets->preview($year, $month, $this->types($kind));
    }

    /** Jalankan penyusutan bulanan. @return ?int id voucher GEN, null bila tidak ada yang perlu disusutkan */
    public function run(string $period, string $kind, SecUser $user): ?int
    {
        [$year, $month] = $this->period($period);

        return $this->assets->run($year, $month, $user->login, $this->types($kind));
    }

    /** @return array<int, string> account_id => kode akun beban, untuk tabel pratinjau */
    public function expenseCodes(Collection $preview): array
    {
        $ids = $preview->map(fn ($p) => $p->asset->category?->expense_account_id)->filter()->unique()->all();

        return FinAccount::withoutGlobalScopes()->whereIn('account_id', $ids)->pluck('account_code', 'account_id')->all();
    }

    /** @return array{0: int, 1: int} */
    private function period(string $period): array
    {
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1, 422, 'The period must be a month, for example 2026-10.');

        return [(int) substr($period, 0, 4), (int) substr($period, 5, 2)];
    }

    /** @return array<int, string> */
    private function types(string $kind): array
    {
        return self::RUN_KINDS[$kind] ?? abort(422, 'Unknown asset kind.');
    }
}
