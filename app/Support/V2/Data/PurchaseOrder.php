<?php

namespace App\Support\V2\Data;

use App\Models\Acc\FinPettyCash;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PqLine;
use App\Models\Pur\PrLineAlloc;
use App\Models\Sys\SysConstant;
use App\Services\Flow\FlowEngineService;
use App\Services\Pur\PoBuilderService;
use App\Support\V2\Data\Concerns\FlowDocument;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

// Purchase Order v2 = aturan & tulis data komponen v1 pages::pur.po.entry (updateHeader / updateLine harga+diskon /
// addAllocation dari PR approved / submit / approve / reject / hold / sendToSupplier). Baris PO lahir dari alokasi PR.
final class PurchaseOrder
{
    use FlowDocument;

    public const HEADER = ['i_doc_date', 'i_po_type', 'i_procure_type', 'i_payment_term', 'i_delivery_date', 'i_ship_to', 'i_notes'];

    // PO Kontan (user 2026-10-01): kolom tambahan, disimpan langsung (tidak lewat updateHeader v1).
    public const CASH_HEADER = ['i_cash_mode', 'i_petty_cash_id', 'i_shop_name'];

    public const CASH_MODES = ['ADVANCE' => 'Cash advance first, then settle with receipt', 'REIMBURSE' => 'Buy first, reimbursed from petty cash'];

    // Supplier default PO Kontan = master TOKO KONTAN (sudah ada di sup_supplier); nama toko riil diisi di i_shop_name.
    public const CASH_SUPPLIER = 'TOKO KONTAN';

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
        $pos = PoOrder::with('lines')->orderByDesc('po_id')->limit(500)->get();
        $sup = self::suppliers($pos->pluck('i_supplier_id')->unique()->all());

        return $pos->map(fn ($p) => ['id' => $p->po_id, 'no' => $p->doc_no ?: '(draft #'.$p->po_id.')', 'supplier' => $sup[$p->i_supplier_id] ?? '—',
            'date' => $p->i_doc_date?->toDateString(), 'delivery' => $p->i_delivery_date?->toDateString(), 'lines' => $p->lines->count(), 'items' => self::lineRows($p),
            'total' => (float) $p->c_grand_total, 'status' => $p->status])->all();
    }

    // Baris anak grid (Expand) PO / Petty Cash: nama item selalu tampil, tanpa harga. Butuh relasi lines.
    public static function lineRows(PoOrder $p): array
    {
        return $p->lines->map(fn ($l) => ['code' => $l->c_item_code, 'name' => $l->i_description, 'qty' => (float) $l->i_qty, 'unit' => $l->i_uom,
            'received' => (float) $l->c_qty_received, 'outstanding' => (float) $l->c_qty_outstanding, 'status' => $l->c_line_status])->all();
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

    public static function cashSupplierId(): ?int
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('supplier_name', self::CASH_SUPPLIER)->value('id_supplier');
    }

    public static function pettyCashOptions(): array
    {
        return FinPettyCash::where('activate', true)->orderBy('petty_code')->get()->mapWithKeys(fn ($p) => [$p->id_petty_cash => $p->petty_code.' · '.$p->petty_name])->all();
    }

    public static function maxCash(): float
    {
        return SysConstant::number('PO_CASH_MAX', 2000000);
    }

    // PO Kontan selalu draft baru (tidak digabung ke draft PO biasa supplier yang sama).
    public static function createCash(int $supplierId, string $mode, ?string $shop): PoOrder
    {
        $login = Auth::user()->login;

        return PoOrder::create(['i_doc_date' => now()->toDateString(), 'i_supplier_id' => $supplierId, 'i_po_type' => 'LOCAL', 'i_procure_type' => 'CASH',
            'i_cash_mode' => array_key_exists($mode, self::CASH_MODES) ? $mode : 'ADVANCE', 'i_petty_cash_id' => array_key_first(self::pettyCashOptions()),
            'i_shop_name' => $shop ?: null, 'i_payment_term' => 'Tunai', 'i_fx_rate' => 1, 'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
    }

    // Baris PO Kontan tanpa PR: item Master Bahan (stok) atau uraian bebas (non-stok, wajib akun biaya).
    public static function addMaterial(PoOrder $po, int $materialId): void
    {
        abort_unless($po->status === 'DRAFT', 422, t('The PO is already submitted.'));
        $m = MatRaw::findOrFail($materialId);
        $class = MatCategory::level(1)->where('category_name', $m->category1)->value('stock_class') ?? 'RAW';
        self::addLine($po, ['item_id' => $m->material_id, 'c_item_code' => $m->material_code, 'i_description' => $m->material_name, 'i_line_class' => $class,
            'i_uom' => $m->uom_purchase ?: ($m->uom_stock ?: 'PCS'), 'c_uom_stock' => $m->uom_stock, 'c_conv_to_stock' => $m->conversion_to_stock,
            'c_tol_pct' => $m->tol_purchase, 'is_stock' => $class !== 'SERVICE']);
    }

    public static function addFree(PoOrder $po, string $description, string $uom, ?int $accountId): void
    {
        abort_unless($po->status === 'DRAFT', 422, t('The PO is already submitted.'));
        self::addLine($po, ['i_description' => mb_substr($description, 0, 255), 'i_line_class' => 'INDIRECT', 'i_uom' => $uom ?: 'PCS', 'is_stock' => false,
            'i_coa_account_id' => $accountId]);
    }

    private static function addLine(PoOrder $po, array $data): void
    {
        $login = Auth::user()->login;
        $line = PoLine::create($data + ['po_id' => $po->po_id, 'line_no' => ((int) $po->lines()->max('line_no')) + 1, 'i_qty' => 1, 'i_price' => 0, 'i_disc_pct' => 0,
            'created_by' => $login, 'updated_by' => $login]);
        $line->recalcNet();
        $line->save();
        $po->refresh()->recalcTax();
        $po->save();
    }

    // Akun biaya untuk baris non-stok PO Kontan (akun beban yang bisa diposting).
    public static function expenseAccounts(): array
    {
        return DB::connection('tm2acc')->table('fin_account')->where('activate', 1)->where('is_postable', 1)->whereIn('account_type', ['EXPENSE', 'COGS'])
            ->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])->mapWithKeys(fn ($a) => [$a->account_id => $a->account_code.' · '.$a->account_name])->all();
    }

    public static function save(PoOrder $po, array $in): void
    {
        abort_unless($po->status === 'DRAFT', 422, t('The PO is already submitted and cannot be changed.'));
        if ($po->isCash()) {
            foreach (self::CASH_HEADER as $f) {
                if (array_key_exists($f, $in)) {
                    $po->{$f} = self::str($in[$f]) === '' ? null : self::str($in[$f]);
                }
            }
            $po->updated_by = Auth::user()->login;
            $po->save();
            foreach ((array) ($in['lines'] ?? []) as $id => $row) {
                $line = $po->lines()->find((int) $id);
                if ($line && array_key_exists('i_qty', (array) $row) && self::changed(self::num($row['i_qty']), $line->i_qty)) {
                    $line->i_qty = self::num($row['i_qty']);
                    $line->recalcNet();
                    $line->save();
                }
            }
        }
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

        return $allocs->mapWithKeys(fn ($a) => [$a->getKey() => ($a->line->requisition->doc_no ?? 'PR').' · '.$a->line->i_description.' · '.Locale::decimal(($a->i_qty ?? $a->line->i_qty), 2).' '.$a->line->i_uom
            .(isset($quoted[$a->pr_line_id]) ? ' · '.t('being quoted in a PQ') : '')])->all();
    }

    public static function addAllocation(PoOrder $po, int $allocId): void
    {
        self::component($po->po_id)->addAllocation($allocId, app(PoBuilderService::class));
    }

    public static function submit(PoOrder $po): string
    {
        if ($po->isCash()) {
            $po->refresh()->recalcTax();
            if ((float) $po->c_grand_total > self::maxCash()) {
                return tf('A Cash PO is at most Rp :max (Settings > Constants PO_CASH_MAX). This one is Rp :value — use a regular PO.',
                    ['max' => \Devlib\Locale::number(self::maxCash(), 0), 'value' => \Devlib\Locale::number((float) $po->c_grand_total, 0)]);
            }
            if (! $po->i_cash_mode || ! $po->i_petty_cash_id) {
                return t('A Cash PO needs a mode (Cash advance / Buy first) and a petty cash fund.');
            }
            if ($po->lines()->where('is_stock', false)->whereNull('i_coa_account_id')->exists()) {
                return t('Non-stock lines of a Cash PO need an expense account.');
            }
        }
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
        abort_unless($po->status === 'DRAFT', 422, t('Only a DRAFT PO can be deleted.'));
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
