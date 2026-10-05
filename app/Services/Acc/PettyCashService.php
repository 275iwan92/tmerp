<?php

namespace App\Services\Acc;

use App\Models\Acc\FinPettyCash;
use App\Models\Acc\FinPettyCashTx;
use App\Models\Acc\FinVoucher;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Kas Kecil sistem imprest + PO Kontan (user 2026-10-01; pola modul Petty Cash Odoo / cash journal SAP).
//   KASBON (mode ADVANCE)   : Dr Uang Muka Pembelian (114300)  / Cr Kas Kecil            -- uang diambil pembeli
//   PERTANGGUNGJAWABAN      : Dr GR/IR (barang stok) | Dr akun biaya (non-stok) = nilai nota
//                             / Cr Uang Muka = kasbon; selisih: kembalian Dr Kas Kecil, kurang bayar Cr Kas Kecil
//   REIMBURSE (beli dulu)   : Dr GR/IR | biaya = nilai nota / Cr Kas Kecil
//   ISI ULANG               : Dr Kas Kecil / Cr Bank
// Harga baris PO Kontan diganti nilai nota saat pertanggungjawaban, lalu PO jadi SENT -> SPB mencatat barang masuk dgn
// harga riil (Dr Persediaan / Cr GR/IR), sehingga GR/IR barang stok kembali nol. Saldo kas = jumlah cash_effect POSTED.
final class PettyCashService
{
    public const ADVANCE_ACCOUNT = '114300';

    public static function account(string $code): int
    {
        return (int) (DB::connection('tm2acc')->table('fin_account')->where('account_code', $code)->value('account_id')
            ?? throw new InvalidArgumentException(tf('COA account :code does not exist yet.', ['code' => $code])));
    }

    public static function advanceOf(PoOrder $po): ?FinPettyCashTx
    {
        return FinPettyCashTx::where('po_id', $po->po_id)->where('tx_type', 'ADVANCE')->where('status', 'POSTED')->first();
    }

    public static function settlementOf(PoOrder $po): ?FinPettyCashTx
    {
        return FinPettyCashTx::where('po_id', $po->po_id)->whereIn('tx_type', ['SETTLE', 'REIMBURSE'])->where('status', 'POSTED')->first();
    }

    // Langkah berikut PO Kontan: ADVANCE | SETTLE | REIMBURSE | null (belum approved / sudah selesai).
    public static function nextStep(PoOrder $po): ?string
    {
        if (! $po->isCash() || $po->status !== 'APPROVED' || self::settlementOf($po)) {
            return null;
        }
        if ($po->i_cash_mode === 'REIMBURSE') {
            return 'REIMBURSE';
        }

        return self::advanceOf($po) ? 'SETTLE' : 'ADVANCE';
    }

    public static function advance(PoOrder $po, float $amount, string $receiver, string $login): FinPettyCashTx
    {
        if (self::nextStep($po) !== 'ADVANCE') {
            throw new InvalidArgumentException(t('This PO is not waiting for a cash advance (it must be a Cash PO in Cash Advance mode, Approved, not yet taken).'));
        }
        $petty = self::fund($po);
        if ($amount <= 0) {
            throw new InvalidArgumentException(t('The cash advance amount must be > 0.'));
        }
        if ($amount > $petty->balance()) {
            throw new InvalidArgumentException(tf('The petty cash balance is not enough (balance IDR :balance) — top up first.', ['balance' => number_format($petty->balance(), 0, ',', '.')]));
        }

        return self::record($petty, $po, 'ADVANCE', -$amount, ['advance_amount' => $amount, 'receiver_name' => $receiver], $login, "Kasbon PO Kontan {$po->doc_no}", [
            ['account_id' => self::account(self::ADVANCE_ACCOUNT), 'debit' => $amount, 'narration' => "Kasbon {$po->doc_no} ({$receiver})"],
            ['account_id' => $petty->account_id, 'credit' => $amount, 'narration' => "Kasbon {$po->doc_no} ({$receiver})"],
        ]);
    }

    /** @param array<int, float> $nota po_line_id => nilai nota baris (Rp) */
    public static function settle(PoOrder $po, array $nota, ?string $attachment, string $login, string $receiver = ''): FinPettyCashTx
    {
        $step = self::nextStep($po);
        if (! in_array($step, ['SETTLE', 'REIMBURSE'], true)) {
            throw new InvalidArgumentException(t('This PO is not waiting for a settlement / reimbursement.'));
        }
        $petty = self::fund($po);
        $po->load('lines');
        $debits = [];
        $actual = 0.0;
        foreach ($po->lines as $line) {
            $value = round((float) ($nota[$line->po_line_id] ?? 0), 2);
            if ($value <= 0) {
                throw new InvalidArgumentException(tf('Fill in the receipt amount of line :line (:item).', ['line' => $line->line_no, 'item' => $line->i_description]));
            }
            $accountId = self::debitAccount($line);
            $debits[$accountId] = ($debits[$accountId] ?? 0) + $value;
            $actual += $value;
        }

        $advance = $step === 'SETTLE' ? (float) self::advanceOf($po)->advance_amount : 0.0;
        $cashEffect = $step === 'SETTLE' ? $advance - $actual : -$actual;
        if ($cashEffect < 0 && -$cashEffect > $petty->balance()) {
            throw new InvalidArgumentException(tf('The petty cash balance is not enough to pay IDR :amount — top up first.', ['amount' => number_format(-$cashEffect, 0, ',', '.')]));
        }

        $lines = [];
        foreach ($debits as $accountId => $value) {
            $lines[] = ['account_id' => $accountId, 'debit' => $value, 'narration' => "Nota PO Kontan {$po->doc_no}"];
        }
        if ($advance > 0) {
            $lines[] = ['account_id' => self::account(self::ADVANCE_ACCOUNT), 'credit' => $advance, 'narration' => "Pertanggungjawaban kasbon {$po->doc_no}"];
        }
        if ($cashEffect != 0.0) {
            $lines[] = ['account_id' => $petty->account_id, $cashEffect > 0 ? 'debit' : 'credit' => abs($cashEffect),
                'narration' => $cashEffect > 0 ? "Kembalian kasbon {$po->doc_no}" : ($advance > 0 ? "Kurang bayar {$po->doc_no}" : "Reimburse {$po->doc_no}")];
        }

        return DB::connection('tm2pur')->transaction(function () use ($po, $nota, $petty, $step, $cashEffect, $advance, $actual, $attachment, $receiver, $login, $lines) {
            foreach ($po->lines as $line) {
                $line->i_price = round((float) $nota[$line->po_line_id] / max((float) $line->i_qty, 0.0001), 4);
                $line->i_disc_pct = 0;
                $line->recalcNet();
                $line->updated_by = $login;
                $line->save();
            }
            $po->refresh()->recalcTax();
            // Barang sudah dibeli -> siap dicatat di Penerimaan Barang (SPB), tanpa langkah "Kirim ke Supplier".
            $po->status = 'SENT';
            $po->sent_at = now();
            $po->updated_by = $login;
            $po->save();

            return self::record($petty, $po, $step, $cashEffect, ['advance_amount' => $advance, 'actual_amount' => $actual, 'attachment_path' => $attachment,
                'receiver_name' => $receiver ?: null], $login, ($step === 'SETTLE' ? 'Pertanggungjawaban' : 'Reimburse')." PO Kontan {$po->doc_no}", $lines);
        });
    }

    public static function topup(FinPettyCash $petty, float $amount, int $bankAccountId, string $date, string $login, string $notes = ''): FinPettyCashTx
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException(t('The top-up amount must be > 0.'));
        }

        return self::record($petty, null, 'TOPUP', $amount, ['counter_account_id' => $bankAccountId, 'notes' => $notes ?: null, 'tx_date' => $date], $login,
            "Isi ulang {$petty->petty_name}", [
                ['account_id' => $petty->account_id, 'debit' => $amount, 'narration' => "Isi ulang {$petty->petty_name}"],
                ['account_id' => $bankAccountId, 'credit' => $amount, 'narration' => "Isi ulang {$petty->petty_name}"],
            ]);
    }

    // Batalkan transaksi salah input (user 2026-10-01): jurnal dibalik, status CANCELLED, efek ke PO dikembalikan.
    // Penjaga: kasbon yang sudah dipertanggungjawabkan, nota yang barangnya sudah dicatat di SPB, atau pembatalan
    // yang membuat saldo kas negatif -> ditolak (batalkan langkah sesudahnya dulu).
    public static function cancel(FinPettyCashTx $tx, string $reason, string $login): void
    {
        if ($tx->status !== 'POSTED') {
            throw new InvalidArgumentException(t('This transaction is already cancelled.'));
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException(t('The cancellation reason is required, at least 10 characters.'));
        }
        $petty = $tx->pettyCash;
        if ((float) $tx->cash_effect > 0 && $petty->balance() - (float) $tx->cash_effect < -0.001) {
            throw new InvalidArgumentException(t('The petty cash balance is not enough to cancel this transaction (the money has been used).'));
        }
        $po = $tx->po_id ? PoOrder::find($tx->po_id) : null;
        if ($tx->tx_type === 'ADVANCE' && $po && self::settlementOf($po)) {
            throw new InvalidArgumentException(t('This cash advance has already been settled — cancel its settlement first.'));
        }
        if (in_array($tx->tx_type, ['SETTLE', 'REIMBURSE'], true) && $po) {
            $received = DB::connection('tm2pur')->table('pv_line')->join('pv_receipt', 'pv_receipt.pv_id', '=', 'pv_line.pv_id')
                ->where('pv_line.c_po_id', $po->po_id)->where('pv_receipt.status', 'POSTED')->value('pv_receipt.doc_no');
            if ($received) {
                throw new InvalidArgumentException(tf('The goods of this PO are already recorded in :no — cancel that SPB first.', ['no' => $received]));
            }
        }

        DB::connection('tm2acc')->transaction(function () use ($tx, $reason, $login, $po) {
            $voucher = $tx->voucher_id ? FinVoucher::withoutGlobalScopes()->find($tx->voucher_id) : null;
            $reversal = $voucher && ! $voucher->reversed_by_id
                ? app(JournalPostingService::class)->reverse($voucher, now()->toDateString(), $login, "Batal {$tx->tx_no}: ".trim($reason)) : null;
            $tx->update(['status' => 'CANCELLED', 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'cancelled_by' => $login, 'cancelled_at' => now(),
                'reversal_voucher_id' => $reversal?->voucher_id, 'updated_by' => $login]);
            // Nota dibatalkan -> PO Kontan kembali menunggu nota (APPROVED), belum boleh dicatat di SPB.
            if ($po && in_array($tx->tx_type, ['SETTLE', 'REIMBURSE'], true) && $po->status === 'SENT') {
                $po->update(['status' => 'APPROVED', 'sent_at' => null, 'updated_by' => $login]);
            }
        });
    }

    // Barang stok -> GR/IR (akun item, fallback kategori; sama dgn jurnal SPB). Non-stok -> akun biaya baris PO.
    private static function debitAccount(PoLine $line): int
    {
        if ($line->is_stock && $line->item_id) {
            $material = MatRaw::find($line->item_id);
            $prefix1 = $material ? MatCategory::level(1)->where('category_name', $material->category1)->first() : null;
            $grIr = app(JournalPostingService::class)->resolveAccount($material?->gr_ir_account_id, $prefix1?->default_gr_ir_account_id);
            if ($grIr) {
                return (int) $grIr;
            }
            throw new InvalidArgumentException(tf('The item on line :line has no GR/IR account yet (Material Master / Category).', ['line' => $line->line_no]));
        }
        if ($line->i_coa_account_id) {
            return (int) $line->i_coa_account_id;
        }
        throw new InvalidArgumentException(tf('Line :line (:item) is non-stock: choose its expense account on the PO.', ['line' => $line->line_no, 'item' => $line->i_description]));
    }

    private static function fund(PoOrder $po): FinPettyCash
    {
        return FinPettyCash::find($po->i_petty_cash_id) ?? throw new InvalidArgumentException(t('The Cash PO has not chosen a petty cash fund yet.'));
    }

    private static function record(FinPettyCash $petty, ?PoOrder $po, string $type, float $cashEffect, array $extra, string $login, string $description, array $journal): FinPettyCashTx
    {
        $date = $extra['tx_date'] ?? now()->toDateString();

        return DB::connection('tm2acc')->transaction(function () use ($petty, $po, $type, $cashEffect, $extra, $login, $description, $journal, $date) {
            $tx = FinPettyCashTx::create($extra + ['petty_cash_id' => $petty->id_petty_cash, 'tx_no' => FinPettyCashTx::nextNo(), 'tx_date' => $date, 'tx_type' => $type,
                'po_id' => $po?->po_id, 'po_no' => $po?->doc_no, 'cash_effect' => round($cashEffect, 2), 'status' => 'POSTED', 'created_by' => $login, 'updated_by' => $login]);
            $voucher = app(JournalPostingService::class)->post('PTY', 'PETTY', $tx->id_petty_tx, "{$description} ({$tx->tx_no})", $journal, $login, $date);
            $tx->update(['voucher_id' => $voucher->voucher_id]);

            return $tx;
        });
    }
}
