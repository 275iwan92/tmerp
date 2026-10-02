<?php

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetDepr;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Assets & Amortization (blueprint): kapitalisasi -> jadwal -> run bulanan ->
// jurnal Dr beban / Cr akumulasi (atau Cr akun prabayar). Satu engine dipakai
// aset tetap, aset tak berwujud, leasehold, dan biaya dibayar dimuka.
//  SL  : (harga perolehan - residu) / umur bulan; bulan terakhir ambil sisa.
//  DDB : saldo menurun 2x tarif garis lurus atas nilai buku awal tahun
//        (pola PMK 72/2023), tahun terakhir disusutkan habis.
class AssetService
{
    public function __construct(private JournalPostingService $journal) {}

    public function nextCode(string $prefix, string $date): string
    {
        $p = $prefix.'-'.date('Y', strtotime($date)).'-';
        $last = FinAsset::withoutGlobalScopes()->where('asset_code', 'like', $p.'%')->orderByDesc('asset_code')->value('asset_code');

        return $p.str_pad((string) (($last ? (int) substr($last, strlen($p)) : 0) + 1), 4, '0', STR_PAD_LEFT);
    }

    // Kapitalisasi: Dr akun aset / prabayar, Cr lawan (Hutang/Bank/GR-IR).
    public function capitalize(FinAsset $asset, string $user): FinAsset
    {
        abort_unless($asset->status === 'DRAFT', 422, t('The asset has already been capitalized.'));
        $asset->load('category');
        abort_unless($asset->credit_account_id, 422, t('The capitalization contra account (Payable/Bank) has not been chosen.'));

        $v = $this->journal->post('GEN', 'ASSET_CAPITALIZE', $asset->asset_id, 'Kapitalisasi '.$asset->asset_code.' — '.$asset->asset_name, [
            ['account_id' => $asset->category->asset_account_id, 'debit' => $asset->acquisition_cost, 'cc_id' => $asset->cc_id, 'narration' => $asset->asset_name],
            ['account_id' => $asset->credit_account_id, 'credit' => $asset->acquisition_cost, 'narration' => $asset->source_ref],
        ], $user, $asset->acquisition_date->toDateString(), ['journal_kind' => 'SYSTEM', 'reference_no' => $asset->asset_code, 'data_origin' => $asset->data_origin]);

        $asset->update(['status' => 'ACTIVE', 'capitalize_voucher_id' => $v->voucher_id, 'updated_by' => $user]);

        return $asset;
    }

    public function accumulated(FinAsset $asset, ?int $year = null, ?int $month = null): float
    {
        return round((float) FinAssetDepr::withoutGlobalScopes()->where('asset_id', $asset->asset_id)
            ->when($year, fn ($q) => $q->whereRaw('(fiscal_year * 100 + period_no) <= ?', [$year * 100 + $month]))
            ->sum('amount'), 2);
    }

    public function bookValue(FinAsset $asset): float
    {
        return round((float) $asset->acquisition_cost - $this->accumulated($asset), 2);
    }

    // Rencana beban per indeks bulan (0 = bulan mulai penyusutan).
    public function plan(FinAsset $asset): array
    {
        $life = (int) $asset->useful_life_months;
        $base = (float) $asset->acquisition_cost - (float) $asset->residual_value;
        $amounts = [];
        $acc = 0.0;
        for ($i = 0; $i < $life; $i++) {
            $remaining = round($base - $acc, 2);
            if ($i === $life - 1) {
                $amt = $remaining;
            } elseif ($asset->method === 'DDB') {
                $monthsLeft = $life - $i;
                if ($monthsLeft <= 12 - ($i % 12) && $life - ($i - $i % 12) <= 12) {
                    $amt = $remaining / $monthsLeft; // tahun terakhir: habiskan rata
                } else {
                    $yearStartAcc = array_sum(array_slice($amounts, 0, $i - $i % 12));
                    $amt = ((float) $asset->acquisition_cost - $yearStartAcc) * (2 / ($life / 12)) / 12;
                }
            } else {
                $amt = $base / $life;
            }
            $amt = round(max(0, min($amt, $remaining)), 2);
            $amounts[] = $amt;
            $acc += $amt;
        }

        return $amounts;
    }

    // Beban bulan (year, month) utk 1 aset, 0 bila belum mulai/sudah habis.
    // Dibatasi sisa nilai tersusutkan aktual (bila ada run yang terlewat/diubah).
    public function monthlyAmount(FinAsset $asset, int $year, int $month): float
    {
        $start = Carbon::parse($asset->depr_start_date)->startOfMonth();
        $target = Carbon::create($year, $month, 1);
        if ($target->lt($start) || in_array($asset->status, ['DRAFT', 'DISPOSED'], true)) {
            return 0.0;
        }
        $i = (int) $start->diffInMonths($target, true);
        $plan = $this->plan($asset);
        if ($i >= count($plan)) {
            return 0.0;
        }
        $remaining = round((float) $asset->acquisition_cost - (float) $asset->residual_value - $this->accumulated($asset), 2);

        return round(max(0, min($plan[$i], $remaining)), 2);
    }

    /** Pratinjau run (tanpa menulis). @return Collection<int, object> */
    public function preview(int $year, int $month, ?array $types = null): Collection
    {
        return FinAsset::withoutGlobalScopes()->with('category')->where('status', 'ACTIVE')
            ->when($types, fn ($q) => $q->whereHas('category', fn ($c) => $c->whereIn('asset_type', $types)))
            ->whereNotIn('asset_id', FinAssetDepr::withoutGlobalScopes()->where('fiscal_year', $year)->where('period_no', $month)->pluck('asset_id'))
            ->get()
            ->map(fn ($a) => (object) ['asset' => $a, 'amount' => $this->monthlyAmount($a, $year, $month)])
            ->filter(fn ($r) => $r->amount > 0)->values();
    }

    // Run bulanan: 1 voucher GEN berisi semua aset, lalu fin_asset_depr per aset.
    public function run(int $year, int $month, string $user, ?array $types = null, string $origin = 'LIVE'): ?int
    {
        $rows = $this->preview($year, $month, $types);
        if ($rows->isEmpty()) {
            return null;
        }
        $date = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        return DB::connection('tm2acc')->transaction(function () use ($rows, $year, $month, $user, $date, $origin) {
            $lines = [];
            foreach ($rows as $r) {
                $c = $r->asset->category;
                $lines[] = ['account_id' => $c->expense_account_id, 'debit' => $r->amount, 'cc_id' => $r->asset->cc_id, 'narration' => $r->asset->asset_code.' '.$r->asset->asset_name];
                $lines[] = ['account_id' => $c->accum_account_id, 'credit' => $r->amount, 'narration' => $r->asset->asset_code];
            }
            $v = $this->journal->post('GEN', 'DEPRECIATION_RUN', (int) ($year * 100 + $month),
                'Penyusutan & amortisasi '.Carbon::create($year, $month, 1)->translatedFormat('F Y'), $lines, $user, $date,
                ['journal_kind' => 'SYSTEM', 'reference_no' => sprintf('DEPR-%04d-%02d', $year, $month), 'data_origin' => $origin]);

            foreach ($rows as $r) {
                $acc = round($this->accumulated($r->asset) + $r->amount, 2);
                FinAssetDepr::create([
                    'asset_id' => $r->asset->asset_id, 'fiscal_year' => $year, 'period_no' => $month,
                    'amount' => $r->amount, 'accumulated' => $acc,
                    'book_value' => round((float) $r->asset->acquisition_cost - $acc, 2),
                    'voucher_id' => $v->voucher_id, 'data_origin' => $r->asset->data_origin, 'created_by' => $user,
                ]);
                if ($acc + 0.01 >= (float) $r->asset->acquisition_cost - (float) $r->asset->residual_value) {
                    $r->asset->update(['status' => 'FULLY_DEPRECIATED', 'updated_by' => $user]);
                }
            }

            return $v->voucher_id;
        });
    }

    // Pelepasan: hapus harga perolehan & akumulasi, selisih ke laba/rugi lain-lain.
    public function dispose(FinAsset $asset, string $date, float $proceeds, int $cashAccountId, string $user): void
    {
        abort_unless(in_array($asset->status, ['ACTIVE', 'FULLY_DEPRECIATED'], true), 422, t('The asset is not active.'));
        $asset->load('category');
        $acc = $this->accumulated($asset);
        $nbv = round((float) $asset->acquisition_cost - $acc, 2);
        $gain = round($proceeds - $nbv, 2);
        $lines = [
            ['account_id' => $asset->category->accum_account_id, 'debit' => $acc],
            ['account_id' => $asset->category->asset_account_id, 'credit' => $asset->acquisition_cost],
        ];
        if ($proceeds > 0) {
            $lines[] = ['account_id' => $cashAccountId, 'debit' => $proceeds];
        }
        $other = (int) FinAccount::withoutGlobalScopes()->where('account_code', $gain >= 0 ? '811000' : '812000')->value('account_id');
        $lines[] = ['account_id' => $other, $gain >= 0 ? 'credit' : 'debit' => abs($gain), 'narration' => $gain >= 0 ? 'Laba pelepasan aset' : 'Rugi pelepasan aset'];

        $v = $this->journal->post('GEN', 'ASSET_DISPOSAL', $asset->asset_id, 'Pelepasan '.$asset->asset_code, $lines, $user, $date, ['journal_kind' => 'SYSTEM', 'data_origin' => $asset->data_origin]);
        $asset->update(['status' => 'DISPOSED', 'disposal_date' => $date, 'disposal_amount' => $proceeds, 'disposal_voucher_id' => $v->voucher_id, 'updated_by' => $user]);
    }

    /** Jadwal lengkap (realisasi + proyeksi) untuk layar detail. */
    public function schedule(FinAsset $asset): array
    {
        $out = [];
        $start = Carbon::parse($asset->depr_start_date)->startOfMonth();
        $posted = FinAssetDepr::withoutGlobalScopes()->where('asset_id', $asset->asset_id)->get()->keyBy(fn ($d) => $d->fiscal_year * 100 + $d->period_no);
        $acc = 0.0;
        foreach ($this->plan($asset) as $i => $planned) {
            $m = $start->copy()->addMonths($i);
            $row = $posted[$m->year * 100 + $m->month] ?? null;
            $amt = $row ? (float) $row->amount : $planned;
            $acc = round($acc + $amt, 2);
            $out[] = ['month' => $m->format('M Y'), 'amount' => $amt, 'accumulated' => $acc, 'nbv' => round((float) $asset->acquisition_cost - $acc, 2), 'status' => $row ? 'POSTED' : 'PLANNED'];
        }

        return $out;
    }
}
