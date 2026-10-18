<?php

/*
 * REMARK
 * Tujuan       : Voucher Kas & Bank (BKM, BKK, BBM, BBK) dari layar v2 acc-cash-bank-form: simpan draft (header + baris), posting ke GL, batalkan draft; nomor voucher, opsi akun dan pajak.
 * Sumber hulu  : Aturan dipindah apa adanya dari halaman v1 pages::finance.cash-bank (diarsipkan di tmerp_unused/finance_cash_bank_v1_2026-10-18.blade.php); posting tetap di JournalPostingService::postDraft (tanpa persetujuan).
 * Input        : Isian form (header + pajak), baris (account_id, desc, debit, credit), user.
 * Output       : tm2acc.fin_voucher + fin_voucher_line berubah (DRAFT > POSTED, atau CANCELLED); ValidationException / abort 422 bila aturan dilanggar.
 * Dipakai oleh : routes/v2_finance.php (v2.acc.cashbank.*), layar acc-cash-bank-form, tes CashBankVoucherServiceTest.
 * Status       : Aktif. Nomor TYPE/YYYY/MM/NNN terbit saat Save pertama (bulan berjalan). Keseimbangan debit-kredit dan periode terbuka dicek saat Post, bukan saat Save.
 */

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Sys\SecUser;
use App\Support\Finance\TaxRate;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CashBankVoucherService
{
    public const TYPES = ['BKM' => 'Cash Receipt', 'BKK' => 'Cash Payment', 'BBM' => 'Bank Receipt', 'BBK' => 'Bank Payment'];

    private const JOURNAL = ['BKM' => 'CSH', 'BKK' => 'CSH', 'BBM' => 'BNK', 'BBK' => 'BNK'];

    public const PPH = ['NONE' => 'No withholding', 'PPH23_2' => 'PPh Article 23 (2% - services)', 'PPH4_2_10' => 'PPh Article 4(2) (10% - rent / buildings)', 'PPH21' => 'PPh Article 21 (non-employee)'];

    public function __construct(private JournalPostingService $posting) {}

    /** Uang masuk (BKM / BBM) = baris akun kas/bank di sisi debit; uang keluar = kredit. */
    public function isInflow(string $type): bool
    {
        return in_array($type, ['BKM', 'BBM'], true);
    }

    /** @return array<string, string> */
    public function typeOptions(): array
    {
        return array_combine(array_keys(self::TYPES), array_map(fn ($label, $code) => $label.' ('.$code.')', self::TYPES, array_keys(self::TYPES)));
    }

    /** @return array<string, string> kode => label, tarif PPN dari Tax Rate */
    public function ppnOptions(): array
    {
        $rate = Locale::number(TaxRate::ppn(), 0);

        return ['NONE' => 'Non-VAT', 'MASUKAN_11' => 'Input VAT '.$rate.'%', 'KELUARAN_11' => 'Output VAT '.$rate.'%'];
    }

    /**
     * Baris pertama voucher baru: akun kas/bank utama di sisi menurut jenis voucher (kemudahan v1).
     *
     * @return array<int, array<string, mixed>>
     */
    public function starterLines(string $type, int $mainAccountId): array
    {
        $in = $this->isInflow($type);

        return [['account_id' => $mainAccountId, 'desc' => self::TYPES[$type] ?? '', 'debit' => $in ? '' : '0', 'credit' => $in ? '0' : '']];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  baris form (account_id, desc, debit, credit)
     */
    public function save(?FinVoucher $v, array $in, array $lines, SecUser $user): FinVoucher
    {
        $d = Validator::make($in, [
            'voucher_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'entry_date' => ['required', 'date'],
            'main_account_id' => ['required', 'integer', 'exists:tm2acc.fin_account,account_id'],
            'partner_name' => ['nullable', 'string', 'max:150'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'ppn_type' => ['nullable', 'in:'.implode(',', array_keys($this->ppnOptions()))],
            'efaktur_no' => ['nullable', 'string', 'max:50'],
            'pph_type' => ['nullable', 'in:'.implode(',', array_keys(self::PPH))],
            'bupot_no' => ['nullable', 'string', 'max:50'],
        ], [], ['voucher_type' => 'Type', 'entry_date' => 'Entry date', 'main_account_id' => 'Cash / bank account', 'partner_name' => 'Paid to / received from'])->validate();

        $rows = $this->cleanLines($lines);
        if (! $rows) {
            throw ValidationException::withMessages(['lines' => 'At least 1 journal line with an account and a debit or credit amount.']);
        }
        Validator::make(['lines' => $rows], ['lines.*.account_id' => ['required', 'exists:tm2acc.fin_account,account_id'], 'lines.*.desc' => ['nullable', 'string', 'max:255']])->validate();

        $journalId = DB::connection('tm2acc')->table('fin_journal')->where('journal_code', self::JOURNAL[$d['voucher_type']])->value('journal_id');
        $data = [
            'voucher_type' => $d['voucher_type'], 'journal_id' => $journalId, 'main_account_id' => $d['main_account_id'],
            'entry_date' => $d['entry_date'], 'posting_date' => $d['entry_date'],
            'reference_no' => ($d['reference_no'] ?? null) ?: null, 'partner_name' => ($d['partner_name'] ?? null) ?: null,
            'ppn_type' => ($d['ppn_type'] ?? null) ?: 'NONE', 'efaktur_no' => ($d['efaktur_no'] ?? null) ?: null,
            'pph_type' => ($d['pph_type'] ?? null) ?: 'NONE', 'bupot_no' => ($d['bupot_no'] ?? null) ?: null,
            'description' => self::TYPES[$d['voucher_type']].' Voucher'.(($d['partner_name'] ?? null) ? ' — '.$d['partner_name'] : ''),
            'updated_by' => $user->login,
        ];

        return DB::connection('tm2acc')->transaction(function () use ($v, $data, $rows, $d, $user) {
            if ($v) {
                abort_if($v->status !== 'DRAFT', 422, 'The voucher is already posted or cancelled and cannot be changed.');
                $v->update($data);
                $v->lines()->delete();
            } else {
                $v = FinVoucher::create($data + ['voucher_number' => $this->nextNumber($d['voucher_type']), 'status' => 'DRAFT', 'created_by' => $user->login]);
            }
            foreach ($rows as $r) {
                FinVoucherLine::create(['voucher_id' => $v->voucher_id, 'account_id' => $r['account_id'], 'debit' => $r['debit'], 'credit' => $r['credit'], 'narration' => $r['desc']]);
            }

            return $v->fresh('lines');
        });
    }

    public function post(FinVoucher $v, SecUser $user): void
    {
        $this->posting->postDraft($v, $user->login);
    }

    public function cancel(FinVoucher $v, SecUser $user): void
    {
        abort_if($v->status !== 'DRAFT', 422, 'Only a DRAFT can be cancelled.');
        $v->update(['status' => 'CANCELLED', 'stat_cancel' => true, 'updated_by' => $user->login]);
    }

    /** Salinan voucher (biasanya yang dibatalkan) sebagai Draft baru bertanggal hari ini, tanpa nomor lama. */
    public function duplicate(FinVoucher $v, SecUser $user): FinVoucher
    {
        $v->loadMissing('lines');

        return $this->save(null, ['voucher_type' => $v->voucher_type, 'entry_date' => now()->toDateString(), 'main_account_id' => $v->main_account_id, 'partner_name' => $v->partner_name,
            'reference_no' => $v->reference_no, 'ppn_type' => $v->ppn_type, 'efaktur_no' => $v->efaktur_no, 'pph_type' => $v->pph_type, 'bupot_no' => $v->bupot_no],
            $v->lines->map(fn ($l) => ['account_id' => $l->account_id, 'desc' => $l->narration, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit])->all(), $user);
    }

    /** @return array{0: string, 1: ?string} [status panel DevLib, tahap] */
    public function formState(FinVoucher $v): array
    {
        return match ($v->status) {
            'POSTED' => ['confirmed', 'Posted'],
            'CANCELLED' => ['cancelled', null],
            default => ['draft', null],
        };
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(FinVoucher $v): array
    {
        $rows = array_filter([
            ['at' => $v->created_at?->toDateTimeString(), 'by' => $v->created_by, 'action' => 'Created', 'note' => ''],
            $v->posted_at ? ['at' => $v->posted_at->toDateTimeString(), 'by' => $v->posted_by, 'action' => 'Posted', 'note' => ''] : null,
            $v->stat_cancel ? ['at' => $v->updated_at?->toDateTimeString(), 'by' => $v->updated_by, 'action' => 'Cancelled', 'note' => ''] : null,
        ]);

        return array_reverse(array_values($rows));
    }

    /** @return array<int, string> account_id => "code · name": akun kas & setara kas (111x) yang bisa dijurnal */
    public function cashBankOptions(?int $include = null): array
    {
        return FinAccount::query()->where(fn ($q) => $q->where('account_code', 'like', '111%')->where('is_postable', true)->when($include, fn ($qq) => $qq->orWhere('account_id', $include)))
            ->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    /** @return array<int, string> account_id => "code · name": semua akun yang bisa dijurnal + akun yang sudah dipakai voucher ini */
    public function accountOptions(?FinVoucher $v = null): array
    {
        $used = $v ? $v->lines->pluck('account_id')->all() : [];

        return FinAccount::query()->where(fn ($q) => $q->where('is_postable', true)->orWhereIn('account_id', $used))->orderBy('account_code')
            ->get(['account_id', 'account_code', 'account_name'])->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    private function nextNumber(string $type): string
    {
        $prefix = $type.'/'.now()->format('Y').'/'.now()->format('m').'/';
        $last = FinVoucher::query()->where('voucher_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('voucher_number')->value('voucher_number');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, -3)) + 1 : 1), 3, '0', STR_PAD_LEFT);
    }

    /** @return array<int, array{account_id:int, desc:?string, debit:float, credit:float}> baris dengan akun dan nilai debit atau kredit */
    private function cleanLines(array $lines): array
    {
        $num = fn ($v) => (float) (Locale::input($v) ?? 0);
        $rows = [];
        foreach ($lines as $l) {
            $l = (array) $l;
            $row = ['account_id' => (int) ($l['account_id'] ?? 0), 'desc' => trim((string) ($l['desc'] ?? '')) ?: null, 'debit' => $num($l['debit'] ?? 0), 'credit' => $num($l['credit'] ?? 0)];
            if ($row['account_id'] && ($row['debit'] > 0 || $row['credit'] > 0)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
