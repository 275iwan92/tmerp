<?php

namespace App\Support\V2\Data;

use App\Models\Acc\FinPettyCash;
use App\Models\Acc\FinPettyCashTx;
use App\Models\Pur\PoOrder;
use App\Services\Acc\PettyCashService;
use Illuminate\Support\Facades\DB;

// Layar Kas Kecil v2 (user 2026-10-01): saldo, kasbon terbuka, PO Kontan yang menunggu kasbon / pertanggungjawaban /
// reimburse, riwayat transaksi, isi ulang, dan setting (nama, pemegang, plafon). Aturan & jurnal = PettyCashService.
final class PettyCash
{
    public const STEP_LABELS = ['ADVANCE' => 'Give Advance', 'SETTLE' => 'Settlement', 'REIMBURSE' => 'Reimburse'];

    public const STEP_TONES = ['Give Advance' => 'warn', 'Settlement' => 'info', 'Reimburse' => 'info'];

    public static function voucherNo(?int $id): string
    {
        return $id ? (string) DB::connection('tm2acc')->table('fin_voucher')->where('voucher_id', $id)->value('voucher_number') : '—';
    }

    public static function fund(): ?FinPettyCash
    {
        return FinPettyCash::where('activate', true)->orderBy('id_petty_cash')->first();
    }

    public static function summary(FinPettyCash $p): array
    {
        $balance = $p->balance();
        $settled = FinPettyCashTx::whereIn('tx_type', ['SETTLE', 'REIMBURSE'])->where('status', 'POSTED')->pluck('po_id');
        $open = (float) FinPettyCashTx::where('petty_cash_id', $p->id_petty_cash)->where('tx_type', 'ADVANCE')->where('status', 'POSTED')
            ->whereNotIn('po_id', $settled)->sum('advance_amount');

        return ['imprest' => $p->imprest_amount === null ? null : (float) $p->imprest_amount, 'balance' => $balance, 'open' => $open,
            'refill' => $p->imprest_amount === null ? null : max((float) $p->imprest_amount - $balance - $open, 0)];
    }

    public static function rows(FinPettyCash $p): array
    {
        $vouchers = DB::connection('tm2acc')->table('fin_voucher')->whereIn('voucher_id', FinPettyCashTx::where('petty_cash_id', $p->id_petty_cash)->pluck('voucher_id')->filter()->all())
            ->pluck('voucher_number', 'voucher_id');

        $txs = FinPettyCashTx::where('petty_cash_id', $p->id_petty_cash)->orderByDesc('id_petty_tx')->limit(500)->get();
        // Baris anak grid (Expand): item dari Cash PO transaksi itu (tm2acc -> tm2pur, query terpisah).
        $pos = PoOrder::with('lines')->whereIn('po_id', $txs->pluck('po_id')->filter()->unique())->get()->keyBy('po_id');

        return $txs->map(fn ($t) => ['id' => $t->id_petty_tx, 'items' => $pos->has($t->po_id) ? PurchaseOrder::lineRows($pos[$t->po_id]) : [],
            'no' => $t->tx_no, 'type' => t(FinPettyCashTx::TYPES[$t->tx_type] ?? $t->tx_type), 'date' => $t->tx_date?->toDateString(), 'po' => $t->po_no ?: '—',
            'who' => $t->receiver_name ?: '—', 'in' => $t->cash_effect > 0 ? (float) $t->cash_effect : null, 'out' => $t->cash_effect < 0 ? -(float) $t->cash_effect : null,
            'voucher' => $vouchers[$t->voucher_id] ?? '—', 'note' => $t->notes ?: '—', 'status' => $t->status === 'POSTED' ? 'Posted' : 'Cancelled'])->all();
    }

    // PO Kontan Approved yang masih menunggu uang keluar / nota.
    public static function waiting(): array
    {
        $sup = PurchaseOrder::suppliers();

        return PoOrder::with('lines')->where('i_procure_type', 'CASH')->where('status', 'APPROVED')->orderBy('po_id')->get()
            ->map(fn ($po) => ['po' => $po, 'step' => PettyCashService::nextStep($po)])->filter(fn ($x) => $x['step'])
            ->map(fn ($x) => ['id' => $x['po']->po_id, 'no' => $x['po']->doc_no, 'shop' => $x['po']->i_shop_name ?: ($sup[$x['po']->i_supplier_id] ?? '—'),
                'total' => (float) $x['po']->c_grand_total, 'step' => self::STEP_LABELS[$x['step']],
                'date' => $x['po']->i_doc_date?->toDateString(), 'items' => PurchaseOrder::lineRows($x['po']),
                'by' => $x['po']->created_by])->values()->all();
    }

    public static function bankAccounts(FinPettyCash $p): array
    {
        return DB::connection('tm2acc')->table('fin_account')->where('activate', 1)->where('is_postable', 1)->where('fs_line', 'CASH')
            ->where('account_id', '!=', $p->account_id)->orderBy('account_code')->get()
            ->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    public static function users(): array
    {
        return DB::connection('tm2sys')->table('sec_user')->where('activate', 1)->orderBy('name')->pluck('name', 'id_user')->all();
    }

    public static function custodianName(FinPettyCash $p): string
    {
        return $p->custodian_user_id ? (string) DB::connection('tm2sys')->table('sec_user')->where('id_user', $p->custodian_user_id)->value('name') : '—';
    }
}
