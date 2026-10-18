<?php

/*
 * REMARK
 * Tujuan       : Master of Accounts (COA) dari layar v2 acc-coa-form: simpan akun GL (kode, nama, tipe, saldo normal, parent, cost center, skema pajak, penanda rekonsiliasi / bisa dijurnal), aktif / nonaktif.
 * Sumber hulu  : Aturan dipindah apa adanya dari halaman v1 pages::finance.coa (diarsipkan di tmerp_unused/finance_coa_v1_2026-10-18.blade.php): kode unik maks 20, akun header tidak diedit lewat form, tidak boleh jadi parent diri sendiri.
 * Input        : Array isian form, akun (null = baru), user yang menyimpan.
 * Output       : tm2acc.fin_account berubah; ValidationException bila aturan dilanggar. account_id manual = MAX(account_id) + 1 (tabel bukan auto increment).
 * Dipakai oleh : routes/v2.php (acc.coa.save), layar acc-coa-form, tes ChartOfAccountServiceTest.
 * Status       : Aktif. Tarif pajak tidak disimpan di sini (hanya kode skema).
 */

namespace App\Services\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinCostCenter;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChartOfAccountService
{
    public const TYPES = [
        'ASSET' => 'Asset', 'LIABILITY' => 'Liability', 'EQUITY' => 'Equity', 'REVENUE' => 'Revenue',
        'COGS' => 'Cost of goods sold (COGS)', 'EXPENSE' => 'Operating expense', 'OTHER_INCOME' => 'Other income', 'OTHER_EXPENSE' => 'Other expense',
    ];

    public function save(?FinAccount $a, array $in, SecUser $user): FinAccount
    {
        if ($a && ! $a->is_postable) {
            throw ValidationException::withMessages(['account_code' => 'Header accounts cannot be edited here.']);
        }
        foreach (['is_recon', 'is_postable', 'activate'] as $flag) {
            $in[$flag] = ! empty($in[$flag]);
        }

        $d = Validator::make($in, [
            'parent_id' => ['nullable', 'integer', 'exists:tm2acc.fin_account,account_id'],
            'account_code' => ['required', 'string', 'max:20', Rule::unique('tm2acc.fin_account', 'account_code')->ignore($a?->account_id, 'account_id')],
            'account_name' => ['required', 'string', 'max:150'],
            'account_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'normal_balance' => ['required', 'in:DEBIT,CREDIT'],
            'default_cc_id' => ['nullable', 'integer', 'exists:tm2acc.fin_cost_center,cc_id'],
            'tax_scheme_code' => ['nullable', 'string', 'max:20'],
        ], ['account_code.unique' => 'This account code is already used.'])->validate();

        $parent = ($d['parent_id'] ?? null) ?: null;
        if ($a && $parent === $a->account_id) {
            throw ValidationException::withMessages(['parent_id' => 'An account cannot be its own parent.']);
        }

        $payload = [
            'parent_id' => $parent, 'account_code' => $d['account_code'], 'account_name' => $d['account_name'], 'account_type' => $d['account_type'],
            'normal_balance' => $d['normal_balance'], 'is_recon' => $in['is_recon'], 'is_postable' => $in['is_postable'],
            'default_cc_id' => ($d['default_cc_id'] ?? null) ?: null, 'tax_scheme_code' => ($d['tax_scheme_code'] ?? null) ?: null,
            'activate' => $in['activate'], 'updated_by' => $user->login,
        ];

        if ($a) {
            $a->update($payload);

            return $a;
        }

        return FinAccount::create($payload + ['account_id' => (int) FinAccount::query()->max('account_id') + 1, 'created_by' => $user->login]);
    }

    public function setActive(FinAccount $a, bool $active, SecUser $user): void
    {
        $a->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    /** @return array<int, string> account_id => "code · name" (semua akun, kecuali akun yang sedang dibuka) */
    public function parentOptions(?int $exceptId = null): array
    {
        return FinAccount::query()->when($exceptId, fn ($q) => $q->where('account_id', '!=', $exceptId))->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn ($r) => [$r->account_id => $r->account_code.' · '.$r->account_name])->all();
    }

    /** @return array<int, string> cc_id => "code · name" (cost center aktif) */
    public function costCenterOptions(): array
    {
        return FinCostCenter::query()->where('activate', true)->orderBy('cc_code')->get(['cc_id', 'cc_code', 'cc_name'])
            ->mapWithKeys(fn ($r) => [$r->cc_id => $r->cc_code.' · '.$r->cc_name])->all();
    }
}
