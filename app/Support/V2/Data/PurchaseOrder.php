<?php

namespace App\Support\V2\Data;

use App\Models\Pur\PoOrder;
use App\Models\Pur\PqLine;
use App\Models\Pur\PrLineAlloc;
use App\Services\Flow\FlowEngineService;
use App\Services\Pur\PoBuilderService;
use App\Support\V2\Data\Concerns\FlowDocument;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

// Purchase Order v2 = aturan & tulis data komponen v1 pages::pur.po.entry (updateHeader / updateLine harga+diskon /
// addAllocation dari PR approved / submit / approve / reject / hold / sendToSupplier). Baris PO lahir dari alokasi PR.
final class PurchaseOrder
{
    use FlowDocument;

    public const HEADER = ['i_doc_date', 'i_po_type', 'i_procure_type', 'i_payment_term', 'i_delivery_date', 'i_ship_to', 'i_notes'];

    public const LINE = ['i_price', 'i_disc_pct'];

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'APPROVED' => 'success', 'SENT' => 'info', 'PARTIALLY_RECEIVED' => 'info',
        'RECEIVED' => 'success', 'CLOSED' => 'neutral', 'REJECTED' => 'danger', 'CANCELLED' => 'danger'];

    public static function component(int $id): Component
    {
        $c = app('livewire')->new('pages::pur.po.entry');
        $c->poId = $id;

        return $c;
    }

    public static function rows(): array
    {
        $pos = PoOrder::withCount('lines')->orderByDesc('po_id')->limit(500)->get();
        $sup = self::suppliers($pos->pluck('i_supplier_id')->unique()->all());

        return $pos->map(fn ($p) => ['id' => $p->po_id, 'no' => $p->doc_no ?: '(draft #'.$p->po_id.')', 'supplier' => $sup[$p->i_supplier_id] ?? '—',
            'date' => $p->i_doc_date?->toDateString(), 'delivery' => $p->i_delivery_date?->toDateString(), 'lines' => $p->lines_count,
            'total' => (float) $p->c_grand_total, 'status' => $p->status])->all();
    }

    public static function suppliers(?array $ids = null): array
    {
        return DB::connection('tm2mst')->table('sup_supplier')->when($ids !== null, fn ($q) => $q->whereIn('id_supplier', $ids), fn ($q) => $q->where('activate', 1))
            ->orderBy('supplier_name')->pluck('supplier_name', 'id_supplier')->all();
    }

    // + New Record = draft PO per supplier (dipakai ulang bila supplier itu masih punya draft) -- sama dgn v1.
    public static function create(int $supplierId): PoOrder
    {
        return PoBuilderService::findOrCreateDraft($supplierId);
    }

    public static function save(PoOrder $po, array $in): void
    {
        abort_unless($po->status === 'DRAFT', 422, 'PO sudah diajukan, tidak bisa diubah.');
        $c = self::component($po->po_id);
        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $in) && self::changed(self::str($in[$f]), $po->{$f})) {
                $c->updateHeader($f, self::str($in[$f]));
            }
        }
        $lines = $po->lines()->get()->keyBy('po_line_id');
        foreach ((array) ($in['lines'] ?? []) as $id => $row) {
            $line = $lines->get((int) $id);
            foreach ($line ? self::LINE : [] as $f) {
                if (array_key_exists($f, (array) $row) && self::changed(self::num($row[$f]), $line->{$f})) {
                    $c->updateLine((int) $id, $f, self::num($row[$f]));
                }
            }
        }
    }

    // Alokasi PR APPROVED yang belum jadi PO, supplier cocok / belum ditetapkan (availableAllocations v1).
    public static function allocationOptions(PoOrder $po): array
    {
        $allocs = PrLineAlloc::with(['line.requisition'])->where('c_is_converted', false)
            ->where(fn ($q) => $q->whereNull('i_supplier_id')->orWhere('i_supplier_id', $po->i_supplier_id))
            ->whereHas('line.requisition', fn ($q) => $q->where('status', 'APPROVED'))->get()->filter(fn ($a) => $a->line !== null);
        // Baris PR yang sedang ditawar di PQ Confirmed ditandai, supaya tidak di-PO lewat dua jalur tanpa sadar.
        $quoted = PqLine::whereIn('pr_line_id', $allocs->pluck('pr_line_id'))->whereHas('quotation', fn ($q) => $q->where('status', 'CONFIRMED'))
            ->pluck('pr_line_id')->flip();

        return $allocs->mapWithKeys(fn ($a) => [$a->getKey() => ($a->line->requisition->doc_no ?? 'PR').' · '.$a->line->i_description.' · '.\Devlib\Locale::decimal(($a->i_qty ?? $a->line->i_qty), 2).' '.$a->line->i_uom
            .(isset($quoted[$a->pr_line_id]) ? ' · sedang di-PQ' : '')])->all();
    }

    public static function addAllocation(PoOrder $po, int $allocId): void
    {
        self::component($po->po_id)->addAllocation($allocId, app(PoBuilderService::class));
    }

    public static function submit(PoOrder $po): string
    {
        $c = self::component($po->po_id);
        $c->submit(app(FlowEngineService::class));

        return $c->blockedMessage;
    }

    public static function decide(PoOrder $po, string $action, string $note): string
    {
        $c = self::component($po->po_id);
        $c->approvalNote = $note;
        match ($action) {
            'approve' => $c->approveDoc(app(FlowEngineService::class)),
            'reject' => $c->rejectDoc(app(FlowEngineService::class)),
            'hold' => $c->holdDoc(app(FlowEngineService::class)),
        };

        return $c->approvalError;
    }

    public static function send(PoOrder $po): void
    {
        self::component($po->po_id)->sendToSupplier();
    }

    public static function delete(PoOrder $po): void
    {
        abort_unless($po->status === 'DRAFT', 422, 'Hanya PO DRAFT yang bisa dihapus.');
        $c = self::component($po->po_id);
        try {
            $c->delete();
        } catch (\Throwable) {
            // delete() v1 diakhiri redirect Livewire; hapusnya sendiri sudah terjadi.
        }
    }

    // Draft > Confirmed > Approval > Approved (APPROVED / SENT / sebagian diterima) > Closed (RECEIVED / CLOSED).
    public static function formStatus(PoOrder $po, bool $held): string
    {
        return match ($po->status) {
            'DRAFT' => 'draft', 'SUBMITTED' => $held ? 'hold' : 'approval', 'APPROVED', 'SENT', 'PARTIALLY_RECEIVED' => 'approved',
            'RECEIVED', 'CLOSED' => 'closed', 'REJECTED' => 'rejected', 'CANCELLED' => 'cancelled', default => 'draft',
        };
    }
}
