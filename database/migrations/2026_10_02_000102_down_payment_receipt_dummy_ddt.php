<?php

use App\Models\Acc\FinAccount;
use App\Models\Acc\FinInvoice;
use App\Models\Acc\FinJournal;
use App\Models\Acc\FinVoucher;
use App\Models\Acc\FinVoucherLine;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\CoOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Contoh data Tanda Terima Uang Muka untuk UAT -- HANYA di database dummy ddt* (ddt belum punya tagihan uang muka sama
// sekali, jadi cetakannya tidak bisa dicoba). Di tm2* migration ini tidak menulis apa pun. Satu tagihan uang muka 30% atas
// Customer Order Confirmed terbaru yang ber-PPN, lalu pembayarannya -- jurnal dan nomor persis seperti layar Down Payment
// v1 (terbitkan tagihan: piutang / uang muka / PPN keluaran; catat pembayaran: bank / piutang).
return new class extends Migration
{
    private const TAG = '[UAT down payment receipt]';

    private function isDummy(): bool
    {
        return str_starts_with(DB::connection('tm2acc')->getDatabaseName(), 'ddt');
    }

    public function up(): void
    {
        if (! $this->isDummy() || FinInvoice::query()->where('invoice_type', 'CUSTOMER_DP')->where('description', 'like', '%'.self::TAG.'%')->exists()) {
            return;
        }
        $co = CoOrder::query()->with(['items', 'externalCosts'])->where('doc_status', 'CONFIRMED')->where('is_ppn', 1)->orderByDesc('id_order')->first();
        $customer = $co ? CustCustomer::withoutGlobalScopes()->find($co->customer_id) : null;
        $account = fn (string $code) => (int) FinAccount::where('account_code', $code)->value('account_id');
        $journal = fn (string $code) => (int) FinJournal::where('journal_code', $code)->value('journal_id');
        [$ar, $dp, $ppn, $bank] = [$account('112100'), $account('213200'), $account('212100'), $account('111200')];
        if (! $co || ! $customer || ! $ar || ! $dp || ! $ppn || ! $bank) {
            return;
        }
        $t = $co->totals();
        $subtotal = round($t['dpp'] * 0.30, 2);
        $tax = round($subtotal * $t['ppn_pct'] / 100, 2);
        $total = $subtotal + $tax;
        if ($subtotal <= 0) {
            return;
        }
        $number = function (string $prefix, string $model, string $column) {
            return $prefix.str_pad((string) ($model::where($column, 'like', $prefix.'%')->count() + 1), 4, '0', STR_PAD_LEFT);
        };
        $voucher = function (string $journalCode, string $source, int $sourceId, string $text) use ($journal, $number, $co) {
            $v = new FinVoucher(['voucher_number' => $number('VCH-'.$journalCode.'-'.now()->format('Ym').'-', FinVoucher::class, 'voucher_number'), 'journal_id' => $journal($journalCode),
                'entry_date' => now()->toDateString(), 'posting_date' => now()->toDateString(), 'doc_type' => 'NEW', 'source_type' => $source, 'source_id' => $sourceId,
                'reference_no' => $co->co_no, 'description' => $text, 'status' => 'POSTED', 'posted_by' => 'uat', 'posted_at' => now(), 'created_by' => 'uat', 'updated_by' => 'uat']);
            $v->forceFill(['data_origin' => 'DUMMY'])->save();

            return $v;
        };
        $line = fn (FinVoucher $v, int $accountId, float $debit, float $credit, string $text, bool $partner = true) => FinVoucherLine::create(['voucher_id' => $v->voucher_id,
            'data_origin' => 'DUMMY', 'account_id' => $accountId, 'debit' => $debit, 'credit' => $credit, 'narration' => $text] + ($partner ? ['partner_type' => 'CUSTOMER', 'partner_id' => $customer->id_customer] : []));

        DB::connection('tm2acc')->transaction(function () use ($co, $customer, $subtotal, $tax, $total, $ar, $dp, $ppn, $bank, $number, $voucher, $line) {
            $no = $number('INV-DP-'.now()->format('Y').'-'.now()->format('m').'-', FinInvoice::class, 'invoice_number');
            $inv = new FinInvoice(['invoice_number' => $no, 'invoice_type' => 'CUSTOMER_DP', 'partner_type' => 'CUSTOMER', 'partner_id' => $customer->id_customer,
                'source_type' => 'MANUAL', 'co_id' => $co->id_order, 'description' => 'Down payment 30% '.$co->co_no.' '.self::TAG, 'doc_status' => 'POSTED',
                'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(7)->toDateString(), 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total_amount' => $total,
                'paid_amount' => 0, 'payment_status' => 'UNPAID', 'created_by' => 'uat', 'updated_by' => 'uat']);
            $inv->forceFill(['data_origin' => 'DUMMY'])->save();

            $issue = $voucher('SAL', 'CUSTOMER_DP', $inv->invoice_id, "DP Invoice {$no} — {$customer->customer_name}. ".self::TAG);
            $line($issue, $ar, $total, 0, "Piutang DP {$no}");
            $line($issue, $dp, 0, $subtotal, "Uang Muka {$no}");
            $line($issue, $ppn, 0, $tax, "PPN Keluaran DP {$no}", false);

            $pay = $voucher('BNK', 'CUSTOMER_DP_PAYMENT', $inv->invoice_id, "Pembayaran DP {$no} — {$customer->customer_name}. ".self::TAG);
            $line($pay, $bank, $total, 0, "Terima pembayaran DP {$no}", false);
            $line($pay, $ar, 0, $total, "Tutup piutang DP {$no}");

            $inv->update(['voucher_id' => $issue->voucher_id, 'paid_amount' => $total, 'payment_status' => 'PAID']);
        });
    }

    public function down(): void
    {
        if (! $this->isDummy()) {
            return;
        }
        $ids = FinInvoice::query()->where('invoice_type', 'CUSTOMER_DP')->where('description', 'like', '%'.self::TAG.'%')->pluck('invoice_id');
        DB::connection('tm2acc')->transaction(function () use ($ids) {
            $vouchers = FinVoucher::query()->whereIn('source_type', ['CUSTOMER_DP', 'CUSTOMER_DP_PAYMENT'])->whereIn('source_id', $ids)->pluck('voucher_id');
            FinInvoice::query()->whereKey($ids)->update(['voucher_id' => null]);
            FinVoucherLine::query()->whereIn('voucher_id', $vouchers)->delete();
            FinVoucher::query()->whereKey($vouchers)->delete();
            FinInvoice::query()->whereKey($ids)->delete();
        });
    }
};
