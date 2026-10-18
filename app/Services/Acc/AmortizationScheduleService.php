<?php

/*
 * REMARK
 * Tujuan       : Jadwal amortisasi (biaya dibayar dimuka, aset tak berwujud, renovasi bangunan sewa) dari layar v2 acc-amortization-form: buat jadwal sekaligus catat jurnalnya dalam satu transaksi, opsi kategori dan akun, jadwal bulanan.
 * Sumber hulu  : Aturan form dipindah apa adanya dari halaman v1 pages::finance.amortization (diarsipkan di tmerp_unused/finance_amortization_v1_2026-10-18.blade.php); jurnal (Dr akun prabayar / tak berwujud, Cr bank atau hutang) dan rumus garis lurus tetap di AssetService; amortisasi bulanan di layar acc-depreciation-run (jenis amortization).
 * Input        : Isian form (nama, kategori, tanggal dokumen, mulai amortisasi, nilai, periode bulan, dokumen sumber, akun kredit), user.
 * Output       : tm2acc.fin_asset (kode AM-<kategori>-YYYY-NNNN, residu 0, metode SL, status ACTIVE) + voucher GEN kapitalisasi; ValidationException / abort 422 bila aturan dilanggar (jadwal tidak tertinggal setengah jadi).
 * Dipakai oleh : routes/v2_finance.php (v2.acc.amort.save), layar acc-amortization-form, tes AmortizationScheduleServiceTest.
 * Status       : Aktif. Jadwal baca-saja sesudah dibuat (sama seperti v1: tanpa edit dan tanpa pelepasan).
 */

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Sys\SecUser;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class AmortizationScheduleService
{
    public const TYPES = ['PREPAID', 'INTANGIBLE', 'LEASEHOLD'];

    public function __construct(private AssetService $assets, private FixedAssetService $fixed) {}

    /** Isian awal jadwal baru menurut kategori: periode dari kategori, akun kredit bawaan bank BCA 111200. */
    public function defaults(FinAssetCategory $c): array
    {
        return ['life' => (int) ($c->useful_life_months ?: 12), 'acq_date' => now()->toDateString(), 'start_date' => now()->startOfMonth()->toDateString(),
            'credit_account_id' => (int) FinAccount::where('account_code', '111200')->value('account_id')];
    }

    /** Buat jadwal dan catat jurnalnya sekaligus; gagal mencatat = jadwal ikut dibatalkan. */
    public function create(array $in, SecUser $user): FinAsset
    {
        $in['cost'] = Locale::input($in['cost'] ?? null) ?? ($in['cost'] ?? null);
        $d = Validator::make($in, [
            'asset_name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:tm2acc.fin_asset_category,category_id'],
            'acq_date' => ['required', 'date'],
            'start_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:1'],
            'life' => ['required', 'integer', 'min:1', 'max:600'],
            'credit_account_id' => ['required', 'exists:tm2acc.fin_account,account_id'],
            'source_ref' => ['nullable', 'string', 'max:150'],
        ], [], ['asset_name' => 'Schedule name', 'cost' => 'Amount', 'life' => 'Period (months)', 'acq_date' => 'Document date', 'start_date' => 'Amortization start',
            'credit_account_id' => 'Paid from / payable to'])->validate();

        $cat = FinAssetCategory::findOrFail($d['category_id']);
        abort_unless(in_array($cat->asset_type, self::TYPES, true), 422, 'This category is not an amortization category.');

        return DB::connection('tm2acc')->transaction(function () use ($d, $cat, $user) {
            $asset = FinAsset::create([
                'asset_code' => $this->assets->nextCode('AM-'.$cat->category_code, $d['acq_date']),
                'asset_name' => $d['asset_name'], 'category_id' => $cat->category_id, 'acquisition_date' => $d['acq_date'], 'depr_start_date' => $d['start_date'],
                'acquisition_cost' => $d['cost'], 'residual_value' => 0, 'useful_life_months' => (int) $d['life'], 'method' => 'SL',
                'source_ref' => ($d['source_ref'] ?? null) ?: null, 'credit_account_id' => (int) $d['credit_account_id'],
                'status' => 'DRAFT', 'created_by' => $user->login, 'updated_by' => $user->login,
            ]);
            // refresh(): data_origin diisi bawaan database (LIVE); AssetService::capitalize meneruskannya ke voucher dan menolak null.
            $this->assets->capitalize($asset->refresh(), $user->login);

            return $asset->refresh();
        });
    }

    /** Jadwal setengah jadi (Draft) peninggalan v1: catat jurnalnya. */
    public function capitalize(FinAsset $a, SecUser $user): void
    {
        $this->assets->capitalize($a, $user->login);
    }

    /** @return array{0: string, 1: ?string} [status panel DevLib, tahap] */
    public function formState(FinAsset $a): array
    {
        return match ($a->status) {
            'ACTIVE' => ['confirmed', 'Active'],
            'FULLY_DEPRECIATED' => ['closed', 'Fully amortized'],
            'DISPOSED' => ['closed', 'Disposed'],
            default => ['draft', null],
        };
    }

    public function monthly(FinAsset $a): float
    {
        return (float) ($this->assets->plan($a)[0] ?? 0);
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

    public function history(FinAsset $a): array
    {
        return $this->fixed->history($a);
    }

    /** @return array<int, string> category_id => nama (tipe): kategori amortisasi aktif */
    public function categoryOptions(?int $include = null): array
    {
        return FinAssetCategory::whereIn('asset_type', self::TYPES)->where(fn ($q) => $q->where('activate', true)->when($include, fn ($qq) => $qq->orWhere('category_id', $include)))
            ->orderBy('category_code')->get()->mapWithKeys(fn ($c) => [$c->category_id => $c->category_name.' ('.ucfirst(strtolower($c->asset_type)).')'])->all();
    }

    /** @return array<int, string> akun kredit jurnal: kas/bank (111), hutang (211) dan 215 */
    public function creditOptions(?int $include = null): array
    {
        return $this->fixed->creditOptions($include);
    }
}
