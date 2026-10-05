<?php

namespace App\Support\V2\Data;

use App\Models\Acc\FinPettyCash;
use App\Models\Acc\FinPettyCashTx;
use App\Models\Pur\PoOrder;
use App\Services\Acc\PettyCashService;
use App\Support\V2\Print\PrintDocs;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    // Isi objek form khusus x-mp::acc.petty-cash: kepala kas (saldo), lalu SATU mode menurut alamat -- tx (satu transaksi),
    // po (PO Kontan: kasbon / pertanggungjawaban / reimburse), topup, setup, atau list (daftar; gridnya milik layar).
    // Semua teks sudah diterjemahkan dan angka sudah diformat di sini; objeknya hanya menampilkan.
    public static function desk(FinPettyCash $fund, string $home): array
    {
        $rp = fn ($v) => $v === null ? '—' : 'Rp '.Locale::number((float) $v, 0);
        $sum = self::summary($fund);
        $po = request('po') ? PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->find((int) request('po')) : null;
        $tx = request('tx') ? FinPettyCashTx::where('petty_cash_id', $fund->id_petty_cash)->find((int) request('tx')) : null;
        $mode = $tx ? 'tx' : ($po ? 'po' : (request('topup') ? 'topup' : (request('setup') ? 'setup' : 'list')));
        $desk = [
            'home' => $home, 'mode' => $mode,
            'title' => $fund->petty_name.' · '.$fund->petty_code,
            'description' => tf('Custodian: :name · account 111400 Petty Cash · cash PO limit :limit', ['name' => self::custodianName($fund), 'limit' => $rp(PurchaseOrder::maxCash()).' (Constants PO_CASH_MAX)']),
            'stats' => [
                ['Imprest Limit', $rp($sum['imprest']), null, $sum['imprest'] === null ? t('not set — open Setting') : null],
                ['Cash on Hand', $rp($sum['balance']), 'success', null],
                ['Open Cash Advances', $rp($sum['open']), 'warn', null],
                ['To Top Up', $rp($sum['refill']), 'info', null],
            ],
            'showActions' => ! request()->hasAny(['po', 'topup', 'setup', 'tx']),
            'tx' => null, 'po' => null, 'topup' => null, 'setup' => null,
        ];

        if ($tx) {
            $posted = $tx->status === 'POSTED';
            $desk['tx'] = [
                'title' => t(FinPettyCashTx::TYPES[$tx->tx_type] ?? $tx->tx_type).' · '.$tx->tx_no,
                'status' => $posted ? 'Status: Posted' : 'Status: Cancelled',
                'facts' => [
                    ['Date', Locale::d($tx->tx_date), ['tm2acc.fin_petty_cash_tx.tx_date']],
                    ['Cash PO', $tx->po_no ?: '—', ['tm2acc.fin_petty_cash_tx.po_no']],
                    ['Receiver', $tx->receiver_name ?: '—', ['tm2acc.fin_petty_cash_tx.receiver_name']],
                    ['Cash Effect', ($tx->cash_effect >= 0 ? '+ ' : '− ').$rp(abs((float) $tx->cash_effect)), ['tm2acc.fin_petty_cash_tx.cash_effect']],
                    ['Advance', $rp($tx->advance_amount), ['tm2acc.fin_petty_cash_tx.advance_amount']],
                    ['Receipt Amount', $rp($tx->actual_amount), ['tm2acc.fin_petty_cash_tx.actual_amount']],
                    ['Journal', self::voucherNo($tx->voucher_id).($tx->reversal_voucher_id ? ' · '.t('reversed by').' '.self::voucherNo($tx->reversal_voucher_id) : ''), ['tm2acc.fin_voucher']],
                    ['Notes', $tx->notes ?: '—', ['tm2acc.fin_petty_cash_tx.notes']],
                ],
                'receiptUrl' => $tx->attachment_path ? Storage::disk('public')->url($tx->attachment_path) : null,
                'printUrl' => PrintDocs::url('petty-cash', $tx->id_petty_tx),
                'cancelled' => $posted ? null : $tx->cancelled_by.' · '.Locale::dt($tx->cancelled_at).' — '.$tx->cancel_reason,
                'cancel' => $posted ? ['action' => route('v2.petty.cancel', $tx->id_petty_tx), 'reason' => old('cancel_reason'),
                    'confirm' => t('Cancel this petty cash transaction and reverse its journal?')] : null,
            ];
        } elseif ($po) {
            $step = PettyCashService::nextStep($po);
            $shop = $po->i_shop_name ?: (PurchaseOrder::suppliers([$po->i_supplier_id])[$po->i_supplier_id] ?? '—');
            $advance = PettyCashService::advanceOf($po);
            $settle = in_array($step, ['SETTLE', 'REIMBURSE'], true);
            $desk['po'] = [
                'title' => t('Cash PO').' '.$po->doc_no.' · '.$shop,
                'description' => tf('Mode: :mode · PO status :status · total :total', ['mode' => t(PurchaseOrder::CASH_MODES[$po->i_cash_mode] ?? '—'), 'status' => $po->status, 'total' => $rp($po->c_grand_total)]),
                'lines' => $po->lines->map(fn ($l) => ['item' => $l->i_description, 'qty' => (float) $l->i_qty, 'uom' => $l->i_uom, 'est' => (float) $l->c_line_net])->all(),
                'back' => route('v2.screen', 'purchase-order').'?id='.$po->po_id,
                'advance' => $step === 'ADVANCE' ? ['action' => route('v2.petty.advance', $po->po_id), 'amount' => old('amount', (float) $po->c_grand_total),
                    'receiver' => old('receiver_name'), 'confirm' => t('Give this cash advance from petty cash?')] : null,
                'settle' => $settle ? [
                    'action' => route('v2.petty.settle', $po->po_id), 'reimburse' => $step === 'REIMBURSE',
                    'description' => $step === 'SETTLE'
                        ? tf('Advance :advance to :name. Enter the receipt amount per line; change or shortfall is calculated automatically.', ['advance' => $advance->tx_no.' '.$rp($advance->advance_amount), 'name' => $advance->receiver_name])
                        : t('Enter the receipt amount per line; the buyer is paid back from petty cash.'),
                    'lines' => $po->lines->map(fn ($l) => ['name' => 'nota['.$l->po_line_id.']',
                        'label' => tf('Receipt: :item', ['item' => $l->i_description.' ('.Locale::decimal($l->i_qty, 2).' '.$l->i_uom.')']),
                        'value' => old('nota.'.$l->po_line_id, (float) $l->c_line_net)])->all(),
                    'receiver' => old('receiver_name'), 'confirm' => t('Post this receipt to petty cash?'),
                ] : null,
            ];
        } elseif ($mode === 'topup') {
            $desk['topup'] = ['action' => route('v2.petty.topup'), 'amount' => old('amount', $sum['refill']), 'accounts' => self::bankAccounts($fund),
                'date' => old('tx_date', now()->toDateString()), 'notes' => old('notes')];
        } elseif ($mode === 'setup') {
            $desk['setup'] = ['action' => route('v2.petty.setup'), 'name' => old('petty_name', $fund->petty_name), 'users' => self::users(),
                'custodian' => $fund->custodian_user_id, 'imprest' => $fund->imprest_amount === null ? null : (float) $fund->imprest_amount];
        }

        return $desk;
    }
}
