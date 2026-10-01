<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// General Ledger (v1: pages::finance.ledger): setiap baris jurnal yang sudah POSTED (tidak dibatalkan), terbaru di atas.
// v1 memilih satu periode; di sini Period = kolom yang bisa disaring / dijadikan grup. Saldo per akun = Trial Balance.
class GeneralLedgerReport extends AccGrid
{
    public function slug(): string
    {
        return 'acc-rpt-ledger';
    }

    public function title(): string
    {
        return 'General Ledger';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function group(): string
    {
        return 'Accounting';
    }

    public function icon(): string
    {
        return 'fa-solid fa-book-open';
    }

    public function guide(): ?string
    {
        return 'Every posted journal line, newest first. Group by Account to read one account with its debit and credit subtotal, '
            .'or filter Period for one month. The balance of each account is in Trial Balance.';
    }

    protected function models(): array
    {
        return [FinVoucherLine::class, FinVoucher::class, FinAccount::class];
    }

    public function search(): string
    {
        return 'Search journal no, account, description...';
    }

    protected function query(): Builder
    {
        $line = (new FinVoucherLine)->getTable();
        $voucher = (new FinVoucher)->getTable();

        // Urut tanggal jurnal: satu database (tm2acc), jadi JOIN biasa.
        return FinVoucherLine::query()->with(['voucher', 'account'])->select("$line.*")
            ->join($voucher, "$voucher.voucher_id", '=', "$line.voucher_id")
            ->where("$voucher.status", 'POSTED')->where("$voucher.stat_cancel", 0)
            ->orderByDesc("$voucher.entry_date")->orderByDesc("$line.voucher_id")->orderBy("$line.line_id");
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'voucher_no', 'label' => 'Journal No.'],
            ['key' => 'account', 'label' => 'Account', 'type' => 'identity', 'code' => 'account_code', 'avatar' => false],
            ['key' => 'class', 'label' => 'Account Class', 'visible' => false],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'partner', 'label' => 'Partner', 'visible' => false],
            ['key' => 'reference', 'label' => 'Reference', 'visible' => false],
            ['key' => 'debit', 'label' => 'Debit', 'type' => 'money', 'sum' => true],
            ['key' => 'credit', 'label' => 'Credit', 'type' => 'money', 'sum' => true],
        ];
    }

    protected function row(Model $m): array
    {
        $v = $m->voucher;

        return [
            'date' => $v?->entry_date?->toDateString(), 'period' => $v?->entry_date?->format('Y-m'), 'voucher_no' => $v?->voucher_number,
            'account' => $m->account?->account_name, 'account_code' => $m->account?->account_code, 'class' => $this->accountClass($m->account?->account_type),
            'description' => $m->narration ?: $v?->description, 'partner' => $v?->partner_name, 'reference' => $v?->reference_no,
            'debit' => (float) $m->debit, 'credit' => (float) $m->credit,
        ];
    }

    public function filters(): array
    {
        return ['period' => 'Period', 'class' => 'Account Class', 'account' => 'Account'];
    }

    public function groups(): array
    {
        return ['account' => 'Account', 'period' => 'Period', 'voucher_no' => 'Journal No.', 'class' => 'Account Class'];
    }
}
