<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinCompanyBank;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Company Bank Accounts (tm2acc.fin_company_bank): rekening perusahaan yang dicetak di Customer Invoice dan menerima uang
// (uang muka, pelunasan). Tax Identity menentukan dokumen mana yang boleh memakainya; satu bawaan per identitas.
class CompanyBankLookup extends DbLookup
{
    public function slug(): string
    {
        return 'fin-company-bank';
    }

    public function title(): string
    {
        return 'Company Bank Accounts';
    }

    public function icon(): string
    {
        return 'fa-solid fa-building-columns';
    }

    public function module(): string
    {
        return 'FINACC';
    }

    public function moduleLabel(): string
    {
        return 'Finance & Accounting';
    }

    public function guide(): ?string
    {
        // Paling banyak 255 huruf (batas kamus terjemahan).
        return 'Company bank accounts. Tax Identity = which documents may use the account (PPN, Non PPN, Both). Default = proposed on a new invoice of that identity. '
            .'Ledger Account = its code in Master of Accounts; without it the account cannot receive money.';
    }

    protected function query(): Builder
    {
        return FinCompanyBank::query()->orderByDesc('activate')->orderBy('tax_identity')->orderByDesc('is_default')->orderBy('bank_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'bank_name', 'label' => 'Bank', 'required' => true, 'width' => '160px'],
            ['key' => 'account_no', 'label' => 'Account No.', 'required' => true, 'width' => '180px'],
            ['key' => 'account_holder', 'label' => 'Account Holder', 'required' => true, 'width' => '280px'],
            ['key' => 'tax_identity', 'label' => 'Tax Identity', 'type' => 'select', 'options' => FinCompanyBank::IDENTITIES, 'required' => true, 'width' => '140px'],
            ['key' => 'is_default', 'label' => 'Default', 'type' => 'check', 'width' => '90px'],
            ['key' => 'gl_account_code', 'label' => 'Ledger Account', 'width' => '150px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function rules(): array
    {
        return ['bank_name' => ['string', 'max:60'], 'account_no' => ['string', 'max:40'], 'account_holder' => ['string', 'max:120'],
            'tax_identity' => ['in:'.implode(',', array_keys(FinCompanyBank::IDENTITIES))], 'gl_account_code' => ['nullable', 'string', 'max:20']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $code = trim((string) ($in['gl_account_code'] ?? ''));
        if ($code !== '' && ! FinAccount::withoutGlobalScopes()->where('account_code', $code)->where('is_postable', 1)->exists()) {
            return "Ledger Account {$code} is not a postable account in Master of Accounts.";
        }

        return null;
    }

    // Satu bawaan per identitas pajak: menandai satu rekening sebagai bawaan mencabut bawaan rekening lain yang seidentitas.
    protected function saved(Model $row): void
    {
        if ($row->is_default) {
            FinCompanyBank::query()->where('tax_identity', $row->tax_identity)->whereKeyNot($row->getKey())->update(['is_default' => false]);
        }
    }
}
