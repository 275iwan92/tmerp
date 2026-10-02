<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

// Rekening perusahaan: dicetak di invoice ("Pelunasan harap ditransfer ke") dan menjadi rekening penerima uang.
// gl_account_code = akun bank di buku besar (fin_account.account_code); kosong = belum bisa menerima uang.
class FinCompanyBank extends Model
{
    public const IDENTITIES = ['PPN' => 'PPN', 'NON_PPN' => 'Non PPN', 'BOTH' => 'Both'];

    protected $connection = 'tm2acc';

    protected $table = 'fin_company_bank';

    protected $primaryKey = 'id_company_bank';

    protected $fillable = ['bank_name', 'account_no', 'account_holder', 'gl_account_code', 'tax_identity', 'is_default', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['is_default' => 'boolean', 'activate' => 'boolean'];

    public function label(): string
    {
        return $this->bank_name.' '.$this->account_no.' · '.$this->account_holder;
    }

    // Tulisan di cetakan invoice: "BCA 331.030.0313 a.n. Puspita Dewi Widodo".
    public function printLabel(): string
    {
        return $this->bank_name.' '.$this->account_no.' a.n. '.$this->account_holder;
    }

    public function usableFor(bool $ppn): bool
    {
        return $this->tax_identity === 'BOTH' || $this->tax_identity === ($ppn ? 'PPN' : 'NON_PPN');
    }

    public function glAccountId(): ?int
    {
        return $this->gl_account_code ? (FinAccount::withoutGlobalScopes()->where('account_code', $this->gl_account_code)->value('account_id') ?: null) : null;
    }
}
