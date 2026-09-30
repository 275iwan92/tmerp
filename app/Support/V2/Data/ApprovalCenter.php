<?php

namespace App\Support\V2\Data;

use App\Models\Master\FlowLog;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrRequisition;
use App\Models\Sdi\CoOrder;
use App\Services\Flow\FlowEngineService;
use App\Support\V2\Data\Concerns\FlowDocument;
use Illuminate\Support\Facades\Auth;

// Approval Center v2 (DevLib Form Approval, disetujui user 2026-09-30): antrian dokumen yang menunggu keputusan user login
// (flow engine: jabatan tetap + PLT), detail hanya-baca, keputusan Approve / Reject / Hold lewat aturan layar dokumennya
// (PurchaseRequisition / PurchaseOrder / CustomerOrder::decide), log = tm2mst.flow_log.
final class ApprovalCenter
{
    use FlowDocument;

    public const MODULES = [
        'PR' => ['title' => 'Purchase Requisition', 'screen' => 'purchase-requisition', 'menu' => 'PROC'],
        'PO' => ['title' => 'Purchase Order', 'screen' => 'purchase-order', 'menu' => 'PROC'],
        'CO' => ['title' => 'Customer Order', 'screen' => 'customer-order', 'menu' => 'SALES'],
    ];

    public static function lineColumns(string $module): array
    {
        return match ($module) {
            'CO' => ['description' => 'Item', 'qty' => 'Qty', 'unit' => 'Satuan', 'price:money' => 'Harga/Unit', 'disc' => 'Disc %', 'subtotal:money' => 'Subtotal', 'source' => 'Sumber Harga'],
            'PO' => ['description' => 'Item', 'qty' => 'Qty', 'unit' => 'Satuan', 'price:money' => 'Harga/Unit', 'disc' => 'Disc %', 'subtotal:money' => 'Subtotal'],
            default => ['description' => 'Item', 'qty' => 'Qty', 'unit' => 'Satuan', 'price:money' => 'Harga Est.', 'subtotal:money' => 'Subtotal', 'need' => 'Tgl Butuh'],
        };
    }

    // Dokumen yang menunggu keputusan user login, format x-dl::approval.
    public static function docs(string $module): array
    {
        $pending = app(FlowEngineService::class)->pendingForUser($module, Auth::id());

        return $pending->map(fn ($p) => self::doc($module, (int) $p->doc_id, (float) $p->amount_snapshot))->filter()->values()->all();
    }

    // Hanya dokumen yang statusnya masih SUBMITTED: log flow yang tertinggal pada dokumen yang sudah lanjut (data lama)
    // tidak dianggap antrian.
    private static function doc(string $module, int $id, float $amount): ?array
    {
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        $date = fn ($d) => $d?->format('d-M-Y') ?? '—';
        $base = match ($module) {
            'PR' => (function () use ($id, $num, $rp, $date) {
                $pr = PrRequisition::with(['lines' => fn ($q) => $q->orderBy('line_no'), 'department'])->where('status', 'SUBMITTED')->find($id);

                return $pr ? ['no' => $pr->doc_no ?: 'PR #'.$id, 'party' => $pr->department?->department_name, 'date' => $pr->i_doc_date?->toDateString(), 'amount' => (float) $pr->c_est_total,
                    'summary' => ['Departemen' => $pr->department?->department_name ?? '—', 'Tgl Butuh' => $date($pr->i_need_date), 'Est. Total' => $rp($pr->c_est_total), 'Jumlah Item' => $pr->lines->count()],
                    'lines' => $pr->lines->map(fn ($l) => ['description' => $l->i_description, 'qty' => $num($l->i_qty), 'unit' => $l->i_uom, 'price' => (float) $l->i_est_price,
                        'subtotal' => (float) $l->i_qty * (float) $l->i_est_price, 'need' => $date($l->i_need_date)])->all(), 'note' => $pr->i_notes] : null;
            })(),
            'PO' => (function () use ($id, $num, $rp, $date) {
                $po = PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->where('status', 'SUBMITTED')->find($id);
                $sup = $po ? (PurchaseOrder::suppliers([$po->i_supplier_id])[$po->i_supplier_id] ?? '—') : null;

                return $po ? ['no' => $po->doc_no ?: 'PO #'.$id, 'party' => $sup, 'date' => $po->i_doc_date?->toDateString(), 'amount' => (float) $po->c_grand_total,
                    'summary' => ['Supplier' => $sup, 'Grand Total' => $rp($po->c_grand_total), 'Termin' => $po->i_payment_term ?: '—', 'Tgl Kirim' => $date($po->i_delivery_date), 'Jumlah Item' => $po->lines->count()],
                    'lines' => $po->lines->map(fn ($l) => ['description' => $l->i_description, 'qty' => $num($l->i_qty), 'unit' => $l->i_uom, 'price' => (float) $l->i_price,
                        'disc' => $num($l->i_disc_pct), 'subtotal' => (float) $l->c_line_net])->all(), 'note' => $po->i_notes] : null;
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
                    'summary' => ['Customer' => $o->customer?->customer_name ?? '—', 'Jalur' => $o->isInstant() ? 'Direct (INSTANT) — harga input manual' : 'Dari Quotation (INTEGRATED)',
                        'Net Receivable' => $rp($t['net_receivable']), 'Termin' => $o->payment_term ?: '—', 'Jumlah Item' => $o->items->count()]
                        + ($noPrice ? ['⚠ Tanpa harga' => $noPrice.' baris'] : []),
                    'lines' => $o->items->map(fn ($l) => ['description' => $l->description, 'qty' => $num($l->qty), 'unit' => $l->qty_unit, 'price' => (float) $l->unit_price,
                        'disc' => $num($l->discount_pct), 'subtotal' => (float) $l->subtotal, 'source' => (float) $l->unit_price <= 0 ? '⚠ Belum ada harga' : ($l->quotation_item_id !== null ? 'Quotation (terkunci)' : 'Input manual')])->all(),
                    'note' => $o->notes, 'direct' => $o->isInstant()];
            })(),
        };
        if (! $base) {
            return null;
        }
        $state = self::approvalState($module, $id, $amount);
        $history = FlowLog::where('module_code', $module)->where('doc_id', $id)->orderBy('flow_log_id')->get()
            ->map(fn ($l) => ['action' => $l->action, 'by' => $l->created_by, 'at' => $l->acted_at?->format('d-M-Y H:i'), 'note' => $l->notes, 'plt' => (bool) $l->is_acting])->all();
        $steps = array_merge([['role' => 'Pembuat', 'by' => $state['submitted_by'], 'at' => $state['submitted_at'], 'status' => 'done', 'submitter' => true]], $state['steps']);

        return $base + ['id' => $id, 'title' => self::MODULES[$module]['title'], 'submitted_by' => $state['submitted_by'] ?? '—', 'steps' => $steps,
            'history' => $history, 'held' => $state['held'] ? ['by' => collect($history)->last()['by'] ?? '', 'at' => collect($history)->last()['at'] ?? '', 'note' => collect($history)->last()['note'] ?? ''] : false,
            'url' => route('v2.screen', self::MODULES[$module]['screen']).'?id='.$id];
    }

    // Keputusan lewat aturan layar dokumennya. Balasan = pesan error (kosong = sukses).
    public static function decide(string $module, int $id, string $action, string $note): string
    {
        abort_unless(in_array($action, ['approve', 'reject', 'hold'], true), 422);
        if ($action !== 'approve' && trim($note) === '') {
            return 'Alasan wajib diisi.';
        }

        return match ($module) {
            'PR' => PurchaseRequisition::decide(PrRequisition::findOrFail($id), $action, $note),
            'PO' => PurchaseOrder::decide(PoOrder::findOrFail($id), $action, $note),
            'CO' => CustomerOrder::decide(CoOrder::findOrFail($id), $action, $note),
        };
    }

    // Log Keputusan user login di modul ini (flow_log): siapa, kapan, aksi, level, alasan.
    public static function myLog(string $module, int $limit = 20): array
    {
        $login = Auth::user()->login;
        $logs = FlowLog::where('module_code', $module)->where('actual_user_id', Auth::id())->whereIn('action', ['APPROVED', 'REJECTED', 'HOLD'])
            ->orderByDesc('flow_log_id')->limit($limit)->get();

        return $logs->map(function ($l) use ($module, $login) {
            $state = self::approvalState($module, (int) $l->doc_id, (float) $l->amount_snapshot);
            $level = collect(self::approvalPath($module, (float) $l->amount_snapshot))->search(fn ($s) => $s->flow_step_id === $l->flow_step_id);
            $no = match ($module) {
                'PR' => PrRequisition::whereKey($l->doc_id)->value('doc_no'),
                'PO' => PoOrder::whereKey($l->doc_id)->value('doc_no'),
                'CO' => CoOrder::whereKey($l->doc_id)->value('co_no'),
            };

            return ['id' => $l->doc_id, 'no' => $no ?: '#'.$l->doc_id, 'status' => $l->action, 'by' => $l->created_by ?? $login, 'at' => $l->acted_at?->format('d-M-Y H:i'),
                'level' => $level === false ? 0 : $level + 1, 'levels' => $state['levels'], 'note' => $l->notes];
        })->all();
    }
}
