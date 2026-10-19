<?php
/* REMARK Tujuan: data layar Approval Center v2 (antrian dokumen menunggu keputusan user: PR, PO, CO, Adjustment / Opname, PRT, TF) dan keputusan Approve / Reject / Hold lewat aturan layar dokumennya; Adjustment dan Opname lewat StockAdjustmentService (tanpa komponen v1). Sumber hulu: flow engine (flow_config, flow_log), dokumen modul terkait. Input: layar approval-*, modul dan id dokumen, aksi, catatan. Output: baris x-dl::approval, pesan error keputusan. Dipakai oleh: layar approval-pr / po / co / stock / prt / tf, Board (WaitingDecision). Status: Aktif. */
namespace App\Support\V2\Data;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Models\Master\FlowLog;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrRequisition;
use App\Models\Pur\PrtReturn;
use App\Models\Sdi\CoOrder;
use App\Services\Flow\FlowEngineService;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Pur\PurchaseReturnService;
use App\Services\Inventory\StockTransferService;
use App\Support\V2\Data\Concerns\FlowDocument;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;

// Approval Center v2 (DevLib Form Approval, disetujui user 2026-09-30): antrian dokumen yang menunggu keputusan user login
// (flow engine: jabatan tetap + PLT), detail hanya-baca, keputusan Approve / Reject / Hold lewat aturan layar dokumennya
// (PurchaseRequisition / PurchaseOrder / CustomerOrder::decide, StockAdjustmentService::decide untuk Adjustment dan Opname), log = tm2mst.flow_log.
final class ApprovalCenter
{
    use FlowDocument;

    // screen = layar dokumen v2 (tautan Buka); null = belum ada layar v2 -> tautan ke layar v1.
    public const MODULES = [
        'PR' => ['title' => 'Purchase Requisition', 'screen' => 'purchase-requisition', 'menu' => 'PROC'],
        'PO' => ['title' => 'Purchase Order', 'screen' => 'purchase-order', 'menu' => 'PROC'],
        'POC' => ['title' => 'Cash PO', 'screen' => 'purchase-order', 'menu' => 'PROC'],
        'CO' => ['title' => 'Customer Order', 'screen' => 'customer-order', 'menu' => 'SALES'],
        'AD' => ['title' => 'Stock Adjustment', 'screen' => null, 'menu' => 'INV'], // tautan Buka = inv-stock-adj-form?class=&id= (disusun di doc())
        'OP' => ['title' => 'Stock Opname', 'screen' => null, 'menu' => 'INV'],
        'PRT' => ['title' => 'Purchase Return', 'screen' => null, 'menu' => 'PROC'],
        'TF' => ['title' => 'Stock Transfer', 'screen' => 'stock-transfer', 'menu' => 'INV'],
    ];

    // Layar Approval Center v2 (slug approval-<key>) => modul yang tampil bersama + hak modul menu.
    public const SCREENS = [
        'pr' => ['PR'], 'po' => ['PO', 'POC'], 'co' => ['CO'], 'stock' => ['AD', 'OP'], 'prt' => ['PRT'], 'tf' => ['TF'],
    ];

    private const STOCK_MODELS = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class];

    public static function lineColumns(string $screen): array
    {
        return array_map(fn (string $label) => t($label), match (self::SCREENS[$screen][0]) {
            'AD' => ['description' => 'Item', 'unit' => 'Unit', 'system' => 'System Qty', 'physical' => 'Counted Qty', 'diff' => 'Difference', 'price:money' => 'Price/Unit', 'subtotal:money' => 'Difference Value'],
            'PRT' => ['description' => 'Item', 'qty' => 'Return Qty', 'unit' => 'Unit', 'price:money' => 'Price/Unit', 'subtotal:money' => 'Value'],
            'TF' => ['description' => 'Item', 'qty' => 'Qty', 'unit' => 'Unit', 'from' => 'From Bin', 'to' => 'To Bin', 'check' => 'Check'],
            'CO' => ['description' => 'Item', 'qty' => 'Qty', 'unit' => 'Unit', 'price:money' => 'Price/Unit', 'disc' => 'Disc %', 'subtotal:money' => 'Subtotal', 'source' => 'Price Source'],
            'PO' => ['description' => 'Item', 'qty' => 'Qty', 'unit' => 'Unit', 'price:money' => 'Price/Unit', 'disc' => 'Disc %', 'subtotal:money' => 'Subtotal'],
            default => ['description' => 'Item', 'qty' => 'Qty', 'unit' => 'Unit', 'price:money' => 'Est. Price', 'subtotal:money' => 'Subtotal', 'need' => 'Need Date'],
        });
    }

    // Dokumen yang menunggu keputusan user login, format x-dl::approval.
    // id dokumen di layar = "MODUL:id" (AD & OP tampil bersama, id-nya bisa sama).
    public static function docs(string $screen): array
    {
        $flow = app(FlowEngineService::class);

        return collect(self::SCREENS[$screen])->flatMap(fn ($m) => $flow->pendingForUser($m, Auth::id())
            ->map(fn ($p) => self::doc($m, (int) $p->doc_id, (float) $p->amount_snapshot)))->filter()->values()->all();
    }

    // Dokumen stok AD/OP tersebar di 3 tabel kategori (rm/fg/sfg_stock); flow_log hanya menyimpan doc_id.
    private static function stockDoc(string $module, int $id): ?array
    {
        foreach (self::STOCK_MODELS as $cat => $model) {
            $doc = $model::where('doc_cat', $module)->where('flow_status', 'SUBMITTED')->find($id);
            if ($doc) {
                return [$cat, $doc];
            }
        }

        return null;
    }

    // Hanya dokumen yang statusnya masih SUBMITTED: log flow yang tertinggal pada dokumen yang sudah lanjut (data lama)
    // tidak dianggap antrian.
    private static function doc(string $module, int $id, float $amount): ?array
    {
        $num = fn ($v) => Locale::decimal((float) $v, 2);
        $rp = fn ($v) => 'IDR '.Locale::number((float) $v, 0);
        $date = fn ($d) => Locale::d($d) ?? '—';
        $base = match ($module) {
            'PR' => (function () use ($id, $num, $rp, $date) {
                $pr = PrRequisition::with(['lines' => fn ($q) => $q->orderBy('line_no'), 'department'])->where('status', 'SUBMITTED')->find($id);

                return $pr ? ['no' => $pr->doc_no ?: 'PR #'.$id, 'party' => $pr->department?->department_name, 'date' => $pr->i_doc_date?->toDateString(), 'amount' => (float) $pr->c_est_total,
                    'summary' => [t('Department') => $pr->department?->department_name ?? '—', t('Need Date') => $date($pr->i_need_date), t('Est. Total') => $rp($pr->c_est_total), t('Items') => $pr->lines->count()],
                    'lines' => $pr->lines->map(fn ($l) => ['description' => $l->i_description, 'qty' => $num($l->i_qty), 'unit' => $l->i_uom, 'price' => (float) $l->i_est_price,
                        'subtotal' => (float) $l->i_qty * (float) $l->i_est_price, 'need' => $date($l->i_need_date)])->all(), 'note' => $pr->i_notes] : null;
            })(),
            'PO', 'POC' => (function () use ($id, $num, $rp, $date) {
                $po = PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->where('status', 'SUBMITTED')->find($id);
                $sup = $po ? (PurchaseOrder::suppliers([$po->i_supplier_id])[$po->i_supplier_id] ?? '—') : null;

                return $po ? ['no' => $po->doc_no ?: 'PO #'.$id, 'party' => $sup, 'date' => $po->i_doc_date?->toDateString(), 'amount' => (float) $po->c_grand_total,
                    'summary' => [t('Supplier') => $sup, t('Grand Total') => $rp($po->c_grand_total), t('Payment Term') => $po->i_payment_term ?: '—', t('Ship Date') => $date($po->i_delivery_date), t('Items') => $po->lines->count()],
                    'lines' => $po->lines->map(fn ($l) => ['description' => $l->i_description, 'qty' => $num($l->i_qty), 'unit' => $l->i_uom, 'price' => (float) $l->i_price,
                        'disc' => $num($l->i_disc_pct), 'subtotal' => (float) $l->c_line_net])->all(), 'note' => $po->i_notes] : null;
            })(),
            'AD', 'OP' => (function () use ($module, $id, $num, $rp, $date) {
                [$cat, $doc] = self::stockDoc($module, $id) ?? [null, null];
                if (! $doc) {
                    return null;
                }
                $lines = collect(app(StockAdjustmentService::class)->rows($cat, $doc->load('lines')))->map(function ($l) use ($num) {
                    $diff = (float) ($l['physical'] ?: 0) - (float) $l['qty_system'];

                    return ['description' => $l['item_id_label'], 'unit' => $l['uom_code'], 'system' => $num($l['qty_system']), 'physical' => $num($l['physical'] ?: 0),
                        'diff' => ($diff > 0 ? '+' : '').$num($diff), 'price' => (float) $l['unit_price'], 'subtotal' => $diff * (float) $l['unit_price']];
                });
                $value = $lines->sum(fn ($l) => abs($l['subtotal']));

                return ['no' => $doc->doc_no ?: $module.' #'.$id, 'party' => $cat.' · '.($module === 'OP' ? 'Opname' : 'Adjustment'), 'date' => $doc->doc_date?->toDateString(),
                    'amount' => $value,
                    'summary' => [t('Category') => $cat, t('Reason') => $doc->reason_code ?: '—', t('Difference Value (absolute)') => $rp($value), t('Date') => $date($doc->doc_date),
                        t('Items') => $lines->count()] + ($module === 'OP' ? [t('Count Officer') => $doc->opn_officer ?: '—', t('Witness') => $doc->opn_witness ?: '—'] : []),
                    'lines' => $lines->all(), 'note' => $doc->notes, 'url' => route('v2.screen', 'inv-stock-adj-form').'?class='.$cat.'&id='.$id];
            })(),
            'PRT' => (function () use ($id, $num, $rp) {
                $r = PrtReturn::with('lines')->where('status', 'SUBMITTED')->find($id);
                $sup = $r ? (PurchaseOrder::suppliers([$r->i_supplier_id])[$r->i_supplier_id] ?? '—') : null;

                return $r ? ['no' => $r->doc_no ?: 'PRT #'.$id, 'party' => $sup, 'date' => $r->i_doc_date?->toDateString(), 'amount' => (float) $r->c_credit_value,
                    'summary' => [t('Supplier') => $sup, t('Return Value') => $rp($r->c_credit_value), t('Disposition') => $r->i_disposition ?: '—', t('Reason') => $r->i_reason ?: '—', t('Items') => $r->lines->count()],
                    'lines' => $r->lines->map(fn ($l) => ['description' => $l->i_description, 'qty' => $num($l->i_qty), 'unit' => $l->i_uom, 'price' => (float) $l->c_unit_price,
                        'subtotal' => (float) $l->c_line_value])->all(), 'note' => $r->i_reason, 'url' => route('v2.screen', 'pur-return-form').'?id='.$id] : null;
            })(),
            'TF' => (function () use ($id, $num, $rp) {
                $t = StockTransfer::find($id);
                if (! $t || $t->status() !== 'SUBMITTED') {
                    return null;
                }
                $checks = StockTransfer::checks($t);
                $route = ($t->fromWarehouse?->warehouse_code ?? '?').' → '.($t->toWarehouse?->warehouse_code ?? '?');

                return ['no' => $t->label(), 'party' => $route, 'date' => $t->transfer_date?->toDateString(), 'amount' => $amount = (new StockTransferService)->value($t),
                    'summary' => [t('Route') => $route, t('Reason') => t(InvTransfer::REASONS[$t->reason] ?? '—'), t('Stock value') => $rp($amount), t('Carried By') => $t->carried_by ?: '—',
                        t('Lines') => $t->lines->count()],
                    'lines' => array_map(fn (array $c) => ['description' => $c['code'].' · '.$c['item'], 'qty' => $num($c['qty']), 'unit' => $c['unit'], 'from' => $c['from'], 'to' => $c['to'] ?? '—',
                        'check' => t($c['flag'])], $checks),
                    'note' => $t->notes];
            })(),
            'CO' => (function () use ($id, $num, $rp) {
                $o = CoOrder::with(['items', 'customer'])->where('doc_status', 'SUBMITTED')->find($id);
                if (! $o) {
                    return null;
                }
                $t = $o->totals();
                $noPrice = $o->items->filter(fn ($l) => (float) $l->unit_price <= 0)->count();

                // Tugas F&A SPV = memastikan harga tidak salah input; CO direct (INSTANT) semua harga diketik manual dan boleh dibuat
                // sebelum harga ada (pengecualian yang sering terjadi, tidak diblokir) -> baris tanpa harga ditandai.
                return ['no' => $o->co_no, 'party' => $o->customer?->customer_name, 'date' => $o->co_date?->toDateString(), 'amount' => (float) $t['net_receivable'],
                    'summary' => [t('Customer') => $o->customer?->customer_name ?? '—', t('Entry Mode') => $o->isInstant() ? t('Direct (INSTANT) — prices entered by hand') : t('From Quotation (INTEGRATED)'),
                        t('Net Receivable') => $rp($t['net_receivable']), t('Payment Term') => $o->payment_term ?: '—', t('Items') => $o->items->count()]
                        + ($noPrice ? [t('⚠ No price') => tf(':n lines', ['n' => $noPrice])] : []),
                    'lines' => $o->items->map(fn ($l) => ['description' => $l->description, 'qty' => $num($l->qty), 'unit' => $l->qty_unit, 'price' => (float) $l->unit_price,
                        'disc' => $num($l->discount_pct), 'subtotal' => (float) $l->subtotal, 'source' => (float) $l->unit_price <= 0 ? t('⚠ No price yet') : ($l->quotation_item_id !== null ? t('Quotation (locked)') : t('Manual input'))])->all(),
                    'note' => $o->notes, 'direct' => $o->isInstant()];
            })(),
        };
        if (! $base) {
            return null;
        }
        $state = self::approvalState($module, $id, $amount);
        $history = FlowLog::where('module_code', $module)->where('doc_id', $id)->orderBy('flow_log_id')->get()
            ->map(fn ($l) => ['action' => $l->action, 'by' => $l->created_by, 'at' => Locale::dt($l->acted_at), 'note' => $l->notes, 'plt' => (bool) $l->is_acting])->all();
        $steps = array_merge([['role' => t('Creator'), 'by' => $state['submitted_by'], 'at' => $state['submitted_at'], 'status' => 'done', 'submitter' => true]], $state['steps']);

        $screen = self::MODULES[$module]['screen'];

        return $base + ['id' => $module.':'.$id, 'title' => t(self::MODULES[$module]['title']), 'submitted_by' => $state['submitted_by'] ?? '—', 'steps' => $steps,
            'history' => $history, 'held' => $state['held'] ? ['by' => collect($history)->last()['by'] ?? '', 'at' => collect($history)->last()['at'] ?? '', 'note' => collect($history)->last()['note'] ?? ''] : false,
            'url' => $base['url'] ?? ($screen ? route('v2.screen', $screen).'?id='.$id : ($base['v1'] ?? null))];
    }

    // Keputusan lewat aturan layar dokumennya. Balasan = pesan error (kosong = sukses).
    public static function decide(string $module, int $id, string $action, string $note): string
    {
        abort_unless(in_array($action, ['approve', 'reject', 'hold'], true), 422);
        if ($action !== 'approve' && trim($note) === '') {
            return t('A reason is required.');
        }

        return match ($module) {
            'PR' => PurchaseRequisition::decide(PrRequisition::findOrFail($id), $action, $note),
            'PO', 'POC' => PurchaseOrder::decide(PoOrder::findOrFail($id), $action, $note),
            'CO' => CustomerOrder::decide(CoOrder::findOrFail($id), $action, $note),
            'AD', 'OP' => self::decideStock($module, $id, $action, $note),
            'PRT' => self::decideReturn($id, $action, $note),
            'TF' => StockTransfer::decide(StockTransfer::find($id) ?? abort(404), $action, $note),
        };
    }

    // Aturan StockAdjustmentService::decide: approve terakhir = posting ke Kartu Stok; Reject butuh alasan >= 10 karakter dan mengembalikan ke pembuat; Hold lewat engine.
    private static function decideStock(string $module, int $id, string $action, string $note): string
    {
        [$cat] = self::stockDoc($module, $id) ?? [null];
        if (! $cat) {
            return t('This document is not waiting for your approval.');
        }

        return t(app(StockAdjustmentService::class)->decide($cat, $id, $action, $note, Auth::user()));
    }

    private static function decideReturn(int $id, string $action, string $note): string
    {
        return t(app(PurchaseReturnService::class)->decide($id, $action, $note, Auth::user()));
    }

    // Log Keputusan user login di layar ini (flow_log): siapa, kapan, aksi, level, alasan.
    public static function myLog(string $screen, int $limit = 20): array
    {
        $login = Auth::user()->login;
        $logs = FlowLog::whereIn('module_code', self::SCREENS[$screen])->where('actual_user_id', Auth::id())->whereIn('action', ['APPROVED', 'REJECTED', 'HOLD'])
            ->orderByDesc('flow_log_id')->limit($limit)->get();

        return $logs->map(function ($l) use ($login) {
            $m = $l->module_code;
            $state = self::approvalState($m, (int) $l->doc_id, (float) $l->amount_snapshot);
            $level = collect(self::approvalPath($m, (float) $l->amount_snapshot))->search(fn ($s) => $s->flow_step_id === $l->flow_step_id);
            $no = match ($m) {
                'PR' => PrRequisition::whereKey($l->doc_id)->value('doc_no'),
                'PO', 'POC' => PoOrder::whereKey($l->doc_id)->value('doc_no'),
                'CO' => CoOrder::whereKey($l->doc_id)->value('co_no'),
                'PRT' => PrtReturn::whereKey($l->doc_id)->value('doc_no'),
                'TF' => InvTransfer::whereKey($l->doc_id)->value('transfer_no'),
                default => collect(self::STOCK_MODELS)->map(fn ($model) => $model::where('doc_cat', $m)->whereKey($l->doc_id)->value('doc_no'))->filter()->first(),
            };

            return ['id' => $m.':'.$l->doc_id, 'no' => $no ?: $m.' #'.$l->doc_id, 'status' => $l->action, 'by' => $l->created_by ?? $login, 'at' => Locale::dt($l->acted_at),
                'level' => $level === false ? 0 : $level + 1, 'levels' => $state['levels'], 'note' => $l->notes];
        })->all();
    }
}
