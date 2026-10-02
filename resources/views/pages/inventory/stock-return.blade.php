<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\SfgStockcard;
use App\Models\Inventory\SfgStockD;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhLoc;
use App\Models\Master\ProdPart;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\OpensFromQuery;
use Livewire\Component;

// inventory_tier4.md §5 + mockup_stock_return.html. RM + SFG (2026-08-22, diperluas
// sama hari dgn Stock Out SFG -- kalau SFG bisa keluar ke produksi, wajar bisa
// diretur juga: barang setengah jadi cacat/nggak kepakai kembali ke gudang,
// pola akuntansi & fisiknya SAMA dgn RM cuma beda kelas item (ProdPart/
// parts_id, bukan MatRaw/material_id). FG TETAP tidak relevan di sini --
// FG tak pernah bisa Stock Out (lihat stock-out.blade.php), jadi tak ada
// sumber utk diretur balik. Simplifikasi vs spec penuh: tanpa tombol "Pecah
// Baris" (split GOOD/SCRAP dari 1 baris) -- kalau perlu keduanya dari 1 baris
// keluar, buat 2 dokumen retur terpisah utk v1. Tanpa tab "Rekonsiliasi WO"
// (butuh data Production/tm2prd yg blm ada).
// Alur "DRAFT -> POSTED" TANPA approval (return_wajib_ref_out: sumbernya --
// Stock Out -- sudah tervalidasi POSTED, menahan retur cuma menahan lantai produksi).
new #[Title('Return from Production — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter, OpensFromQuery;

    private const DOC_PREFIX = 'RTN';

    public const CATEGORY_LABELS = ['RM' => 'Bahan Baku', 'SFG' => 'Barang Setengah Jadi'];

    private const HEADER_MODEL = ['RM' => RmStock::class, 'SFG' => SfgStock::class];

    private const DETAIL_MODEL = ['RM' => RmStockD::class, 'SFG' => SfgStockD::class];

    private const CARD_MODEL = ['RM' => RmStockcard::class, 'SFG' => SfgStockcard::class];

    public string $category;

    public string $panel = 'grid';

    public string $search = '';

    public ?int $editingId = null;

    public bool $readOnly = false;

    public string $doc_no = '';

    public string $doc_date = '';

    public ?int $sourceDocId = null;

    public string $sourceDocNo = '';

    public ?int $goodWarehouseId = null;

    public string $process_code = '';

    public string $notes = '';

    public string $flow_status = 'DRAFT';

    public array $lines = [];

    public bool $pickerOpen = false;

    public function mount(string $category): void
    {
        $category = strtoupper($category);
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404, 'Return from Production cuma berlaku utk kelas Bahan Baku (RM) atau Barang Setengah Jadi (SFG).');
        $this->category = $category;
    }

    private function headerModel(): string
    {
        return self::HEADER_MODEL[$this->category];
    }

    private function itemKey(): string
    {
        return $this->category === 'SFG' ? 'parts_id' : 'material_id';
    }

    private function itemLabel(int $itemId): string
    {
        return $this->category === 'SFG'
            ? (ProdPart::find($itemId)?->part_name ?? "#{$itemId}")
            : (\App\Models\Master\MatRaw::find($itemId)?->material_name ?? "#{$itemId}");
    }

    #[Computed]
    public function warehouses()
    {
        return WhWarehouse::where('activate', true)->orderBy('warehouse_name')->get();
    }

    #[Computed]
    public function goodLocations()
    {
        if (! $this->goodWarehouseId) {
            return collect();
        }

        return WhLoc::where('warehouse_id', $this->goodWarehouseId)
            ->where('activate', true)->where('is_usable', true)
            ->orderBy('location_code')->get();
    }

    #[Computed]
    public function scrapLocations()
    {
        return WhLoc::where('activate', true)->where('is_usable', false)->orderBy('location_code')->get();
    }

    // return_wajib_ref_out: cuma Stock Out POSTED, belum dibatalkan, dan masih
    // punya sisa yang boleh diretur (qty - qty_returned > 0 di minimal 1 baris).
    #[Computed]
    public function returnableSourceDocs()
    {
        return $this->headerModel()::with('lines')
            ->where('doc_cat', 'STOCK')->where('doc_type', -1)
            ->where('flow_status', 'POSTED')->where('stat_cancel', false)
            ->orderByDesc('doc_id')
            ->get()
            ->filter(fn ($d) => $d->lines->sum(fn ($l) => (float) $l->qty - (float) ($l->qty_returned ?? 0)) > 0.0001)
            ->values();
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    public function pickSource(int $docId): void
    {
        $doc = $this->headerModel()::with('lines')->findOrFail($docId);
        $this->sourceDocId = $doc->getKey();
        $this->sourceDocNo = (string) $doc->doc_no;
        $this->process_code = (string) $doc->process_code;

        $itemKey = $this->itemKey();
        $this->lines = $doc->lines
            ->map(function ($l) use ($itemKey) {
                $returnable = (float) $l->qty - (float) ($l->qty_returned ?? 0);

                return [
                    'source_detail_id' => $l->doc_detail_id,
                    'item_id' => $l->{$itemKey},
                    'name' => $this->itemLabel($l->{$itemKey}),
                    'qty_issued' => (float) $l->qty,
                    'qty_returned_before' => (float) ($l->qty_returned ?? 0),
                    'qty_returnable' => $returnable,
                    'lot_no' => (string) $l->lot_no,
                    'uom_code' => (string) $l->uom_code,
                    'qty' => '',
                    'return_condition' => 'GOOD',
                    'location_id' => null,
                    'note_detail' => '',
                ];
            })
            ->filter(fn ($l) => $l['qty_returnable'] > 0.0001)
            ->values()->all();

        $this->pickerOpen = false;
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    #[Computed]
    public function documents()
    {
        return $this->headerModel()::query()
            ->where('doc_cat', 'RETURN')->where('doc_type', 1)
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('doc_no', 'like', "%{$this->search}%")
                ->orWhere('ref_doc_no', 'like', "%{$this->search}%")))
            ->orderByDesc('doc_id')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->documents->count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return $this->documents->where('flow_status', 'DRAFT')->count();
    }

    #[Computed]
    public function statPosted(): int
    {
        return $this->documents->where('flow_status', 'POSTED')->count();
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'doc_no', 'sourceDocId', 'sourceDocNo', 'goodWarehouseId', 'process_code', 'notes', 'lines']);
        $this->doc_date = now()->toDateString();
        $this->flow_status = 'DRAFT';
        $this->readOnly = false;
        $this->panel = 'form';
    }

    public function openEdit(int $docId): void
    {
        $doc = $this->headerModel()::with('lines')->findOrFail($docId);
        $this->editingId = $doc->getKey();
        $this->doc_no = (string) $doc->doc_no;
        $this->doc_date = $doc->doc_date?->format('Y-m-d') ?? now()->toDateString();
        $this->sourceDocId = $doc->ref_doc_id;
        $this->sourceDocNo = (string) $doc->ref_doc_no;
        $this->goodWarehouseId = $doc->warehouse_id;
        $this->process_code = (string) $doc->process_code;
        $this->notes = (string) $doc->notes;
        $this->flow_status = (string) $doc->flow_status;
        $this->readOnly = (bool) $doc->stat_confirm || (bool) $doc->stat_cancel;

        $itemKey = $this->itemKey();
        $detailModel = self::DETAIL_MODEL[$this->category];
        $this->lines = $doc->lines->map(function ($l) use ($itemKey, $detailModel) {
            $sourceLine = $l->source_detail_id ? $detailModel::find($l->source_detail_id) : null;

            return [
                'source_detail_id' => $l->source_detail_id,
                'item_id' => $l->{$itemKey},
                'name' => $this->itemLabel($l->{$itemKey}),
                'qty_issued' => (float) ($sourceLine?->qty ?? 0),
                'qty_returned_before' => (float) ($sourceLine?->qty_returned ?? 0) - (float) $l->qty,
                'qty_returnable' => (float) ($sourceLine?->qty ?? 0) - ((float) ($sourceLine?->qty_returned ?? 0) - (float) $l->qty),
                'lot_no' => (string) $l->lot_no,
                'uom_code' => (string) $l->uom_code,
                'qty' => (string) $l->qty,
                'return_condition' => $l->return_condition ?: 'GOOD',
                'location_id' => $l->location_id,
                'note_detail' => '',
            ];
        })->all();

        $this->panel = 'form';
    }

    public function backToGrid(): void
    {
        $this->panel = 'grid';
    }

    public function saveDraft(StockDocumentService $svc): void
    {
        $this->validate([
            'doc_date' => ['required', 'date'],
            'sourceDocId' => ['required', 'integer'],
            'goodWarehouseId' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.location_id' => ['required', 'integer'],
        ], [], [
            'sourceDocId' => 'Ref. Stock Out', 'goodWarehouseId' => 'Gudang Tujuan (bahan layak)', 'doc_date' => 'Tanggal Dokumen',
        ]);

        foreach ($this->lines as $l) {
            if ((float) $l['qty'] > $l['qty_returnable'] + 0.0001) {
                $this->addError('lines', "Qty retur {$l['name']} melebihi sisa yang boleh diretur ({$l['qty_returnable']}).");

                return;
            }
            if ($l['return_condition'] === 'SCRAP' && trim($l['note_detail']) === '') {
                $this->addError('lines', "Keterangan wajib diisi utk baris afkir/SCRAP ({$l['name']}).");

                return;
            }
        }

        $docNo = $this->editingId ? $this->doc_no : $svc->nextDocNo($this->category, self::DOC_PREFIX);

        $itemKey = $this->itemKey();
        $cardModel = self::CARD_MODEL[$this->category];
        $refName = app(StockPostingService::class)->referenceName($this->category);
        $lines = array_map(function ($l) use ($itemKey, $cardModel, $refName) {
            $sourceCardPrice = $cardModel::where('reference_name', $refName)
                ->where('reference_detail_id', $l['source_detail_id'])
                ->value('avg_unitprice');

            return [
                $itemKey => $l['item_id'],
                'qty' => $l['qty'],
                'uom_code' => $l['uom_code'],
                'lot_no' => $l['lot_no'] ?: null,
                'location_id' => $l['location_id'],
                'action_type' => 'RETURN',
                'return_condition' => $l['return_condition'],
                'source_detail_id' => $l['source_detail_id'],
                'unit_price' => $sourceCardPrice ?? 0,
            ];
        }, $this->lines);

        $this->editingId = $svc->saveDraft($this->category, $this->editingId, [
            'doc_no' => $docNo,
            'doc_date' => $this->doc_date,
            'doc_type' => 1,
            'doc_cat' => 'RETURN',
            'ref_doc_type' => 'OUT',
            'ref_doc_id' => $this->sourceDocId,
            'ref_doc_no' => $this->sourceDocNo,
            'warehouse_id' => $this->goodWarehouseId,
            'process_code' => $this->process_code ?: null,
            'notes' => $this->notes ?: null,
        ], $lines, Auth::user()->login);

        $this->doc_no = $docNo;
        unset($this->documents, $this->visibleDocuments, $this->statTotal, $this->statDraft, $this->statPosted);
        session()->flash('flash', "Draft {$docNo} tersimpan.");
        $this->panel = 'grid';
    }

    public function postNow(StockPostingService $svc, ?int $docId = null): void
    {
        $id = $docId ?? $this->editingId;
        if (! $id) {
            return;
        }

        $doc = $this->headerModel()::with('lines')->findOrFail($id);
        $result = $svc->postDocument($this->category, $id, Auth::user()->login);

        unset($this->documents, $this->visibleDocuments, $this->statTotal, $this->statDraft, $this->statPosted);

        if (! $result['posted']) {
            session()->flash('error', $result['error']);

            return;
        }

        // Akumulasi qty_returned di baris Stock Out sumber (bukan tanggung
        // jawab StockPostingService generik -- ini logika khusus Return).
        // COALESCE: qty_returned default NULL di baris lama, pola sama dgn
        // fix cur_stock NULL-safe sesi ini.
        $detailModel = self::DETAIL_MODEL[$this->category];
        foreach ($doc->lines as $line) {
            if (! $line->source_detail_id) {
                continue;
            }
            $detailModel::where('doc_detail_id', $line->source_detail_id)
                ->update(['qty_returned' => DB::raw('COALESCE(qty_returned, 0) + ('.(float) $line->qty.')')]);
        }

        session()->flash('flash', 'Retur berhasil diposting & Kartu Stok ter-update.'.(isset($result['stock_warning']) ? ' '.$result['stock_warning'] : ''));
        if ($this->panel === 'form') {
            $this->openEdit($id);
        }
    }

    public function cancelDoc(StockDocumentService $svc): void
    {
        if (! $this->editingId) {
            return;
        }
        $svc->cancel($this->category, $this->editingId, Auth::user()->login);
        unset($this->documents, $this->visibleDocuments, $this->statTotal, $this->statDraft, $this->statPosted);
        session()->flash('flash', 'Dokumen dibatalkan.');
        $this->panel = 'grid';
    }

    public function subStateBadgeClass(string $status): string
    {
        return match ($status) {
            'DRAFT' => 'bg-[#eef0f3] text-[#8a8f98]',
            'POSTED' => 'bg-[#e9f9ef] text-[#16a34a]',
            'CANCELLED' => 'bg-[#fdecec] text-[#c0392b]',
            default => 'bg-[#eef0f3] text-[#8a8f98]',
        };
    }

    // Avatar helper dipindah ke App\Support\Grid\Avatar (static, dipakai
    // langsung di markup) -- lihat catatan sama di stock-in/stock-out.

    // Baris grid SETELAH filter kotak statistik (griduistandard.md §2b).
    #[Computed]
    public function visibleDocuments()
    {
        return $this->applyStatFilter($this->documents);
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => 'Total Dokumen', 'value' => $this->statTotal, 'tone' => 'total'],
            'draft' => ['label' => 'Draft', 'value' => $this->statDraft, 'tone' => 'warn',
                'filter' => fn ($c) => $c->where('flow_status', 'DRAFT')],
            'posted' => ['label' => 'Sudah Diterima', 'value' => $this->statPosted, 'tone' => 'active',
                'filter' => fn ($c) => $c->where('flow_status', 'POSTED')],
        ];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        <x-alert.banner type="success" :message="session('flash')" />
        <x-alert.banner type="danger" :message="session('error')" />

        @if ($panel === 'grid')
            <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <x-grid.toolbar>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. dokumen / no. Stock Out…"
                            class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <x-slot:newRecord>
                        <button type="button" wire:click="openNew"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                    </x-slot:newRecord>
                </x-grid.toolbar>

                <x-grid.table-shell>
                    <table class="w-full border-collapse text-[12.5px]">
                        <thead>
                            <tr>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Dokumen</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Ref. Stock Out</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Status</th>
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->visibleDocuments as $d)
                                <tr wire:key="doc-{{ $d->doc_id }}" wire:dblclick="openEdit({{ $d->doc_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($d->doc_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ \App\Support\Grid\Avatar::initials($d->doc_no ?: 'DOC') }}</div>
                                            <div>
                                                <div class="font-semibold text-[13px] font-mono">{{ $d->doc_no ?: '(draft)' }}</div>
                                                <div class="text-[11px] text-muted">{{ $d->notes ?: '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->doc_date?->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] font-mono text-[10.5px]">{{ $d->ref_doc_no }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($d->flow_status) }}">{{ $d->flow_status }}</span>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <x-grid.action-icon icon="fa-up-right-from-square" title="Buka" action="openEdit({{ $d->doc_id }})" />
                                        @if ($d->flow_status === 'DRAFT')
                                            <x-grid.action-icon icon="fa-check" title="Konfirmasi & Posting" action="postNow(null, {{ $d->doc_id }})" variant="success" confirm="Konfirmasi &amp; Posting {{ $d->doc_no }}? Barang akan masuk Kartu Stok." />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-grid.empty-row :colspan="5" message="Belum ada dokumen Return from Production." />
                            @endforelse
                        </tbody>
                    </table>
                </x-grid.table-shell>
            </div>
        @else
            {{-- ============ FORM ============ --}}
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                    <span>{{ $editingId ? 'Edit Return · '.$doc_no : 'Return from Production Baru' }}</span>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/15">{{ self::CATEGORY_LABELS[$category] }}</span>
                    @if ($editingId)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($flow_status) }}">{{ $flow_status }}</span>
                    @endif
                    <button type="button" wire:click="backToGrid" data-dirty-guard title="Kembali ke Daftar"
                        class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full cursor-pointer hover:brightness-110">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
                    </button>
                </div>

                <div class="p-4 flex flex-col gap-3">
                    @if ($readOnly)
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">🔒 Dokumen sudah {{ $flow_status === 'POSTED' ? 'diterima/diposting' : 'dibatalkan' }} — lihat-saja, tidak bisa diedit.</div>
                    @endif

                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Header</div>
                    <div class="flex gap-2.5 flex-wrap items-end">
                        <div class="flex-1 min-w-[160px]">
                            <x-form.input label="No. Dokumen" value="{{ $doc_no ?: '(otomatis saat simpan)' }}" disabled class="!font-mono" />
                        </div>
                        <div class="flex-1 min-w-[140px]">
                            <x-form.date-picker label="Tanggal Dokumen *" wire:model="doc_date" :disabled="$readOnly" name="doc_date" />
                        </div>
                        <div class="flex-1 min-w-[220px] flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Ref. Stock Out *</label>
                            @if (! $readOnly && ! $editingId)
                                <button type="button" wire:click="openPicker" class="px-2.5 py-2 border border-dashed border-accent rounded-md text-[13px] text-accent font-semibold text-left">{{ $sourceDocNo ?: '🔍 Pilih dokumen Stock Out…' }}</button>
                            @else
                                <x-form.input value="{{ $sourceDocNo }}" disabled class="font-mono" />
                            @endif
                            @error('sourceDocId') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <x-form.select label="Gudang Tujuan (bahan layak) *" wire:model="goodWarehouseId" :disabled="$readOnly" name="goodWarehouseId"
                                :options="$this->warehouses->mapWithKeys(fn ($w) => [$w->id_warehouse => $w->warehouse_code.' — '.$w->warehouse_name])->all()" />
                        </div>
                        <div class="flex-1 min-w-[160px]">
                            <x-form.input label="Proses Asal" wire:model="process_code" :disabled="$readOnly" />
                        </div>
                    </div>
                    <x-form.textarea label="Catatan" wire:model="notes" :disabled="$readOnly" rows="2" />

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Baris Item (Retur)</div>
                    @error('lines') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror

                    <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                        <table class="w-full text-[12.5px] border-collapse">
                            <thead>
                                <tr class="bg-[#fafbfd]">
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5]">Item</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[90px]">Dikeluarkan</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[90px]">Sisa Boleh</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[100px]">Qty Retur *</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[100px]">Kondisi *</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[180px]">Lokasi Tujuan *</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5]">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lines as $i => $line)
                                    <tr wire:key="line-{{ $i }}" class="border-b border-[#eef1f5] {{ (float) ($line['qty'] ?: 0) > $line['qty_returnable'] + 0.0001 ? 'bg-[#fdecec]' : '' }}">
                                        <td class="px-2 py-1.5">{{ $line['name'] }}</td>
                                        <td class="px-2 py-1.5 text-right font-mono">{{ rtrim(rtrim(number_format($line['qty_issued'], 2, ',', '.'), '0'), ',') }} {{ $line['uom_code'] }}</td>
                                        <td class="px-2 py-1.5 text-right font-mono font-semibold">{{ rtrim(rtrim(number_format($line['qty_returnable'], 2, ',', '.'), '0'), ',') }}</td>
                                        <td class="px-2 py-1.5"><x-form.input wire:model="lines.{{ $i }}.qty" :disabled="$readOnly" class="!py-1 text-right" /></td>
                                        <td class="px-2 py-1.5">
                                            <x-form.select wire:model="lines.{{ $i }}.return_condition" :disabled="$readOnly" class="!py-1" :placeholder="false"
                                                :options="['GOOD' => 'GOOD (Layak)', 'SCRAP' => 'SCRAP (Afkir)']" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <x-form.select wire:model="lines.{{ $i }}.location_id" :disabled="$readOnly" class="!py-1"
                                                :selected="$line['location_id']"
                                                :options="(($line['return_condition'] === 'SCRAP') ? $this->scrapLocations : $this->goodLocations)->pluck('location_code', 'location_id')->all()" />
                                        </td>
                                        <td class="px-2 py-1.5"><x-form.input wire:model="lines.{{ $i }}.note_detail" :disabled="$readOnly" placeholder="{{ $line['return_condition'] === 'SCRAP' ? 'wajib utk afkir' : 'opsional' }}" class="!py-1" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center py-6 text-muted text-xs">Pilih Ref. Stock Out di atas utk menarik baris yang bisa diretur.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (! $readOnly)
                        <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                            @if ($editingId)
                                <button type="button" wire:click="cancelDoc" wire:confirm="Batalkan dokumen ini?" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#fdecec] text-[#c0392b] cursor-pointer">Batalkan</button>
                            @endif
                            <button type="button" wire:click="saveDraft" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">💾 Simpan Draft</button>
                            @if ($editingId)
                                <button type="button" wire:click="postNow" wire:confirm="Konfirmasi &amp; Posting {{ $doc_no }}? Barang akan masuk Kartu Stok, tidak bisa diedit lagi." class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">✓ Konfirmasi &amp; Posting</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg w-full max-w-2xl max-h-[80vh] flex flex-col shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-[#eef1f5]">
                    <h3 class="text-sm font-bold text-navy">Pilih Dokumen Stock Out</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-[#f4f5f8]">
                                <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">No. Dokumen</th>
                                <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">Tanggal</th>
                                <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">Sisa Bisa Diretur</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->returnableSourceDocs as $d)
                                <tr class="border-b border-[#eef1f5] hover:bg-[#eef3fb] cursor-pointer" wire:click="pickSource({{ $d->doc_id }})">
                                    <td class="px-3 py-2 font-mono font-bold text-accent">{{ $d->doc_no }}</td>
                                    <td class="px-3 py-2">{{ $d->doc_date?->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($d->lines->sum(fn ($l) => (float) $l->qty - (float) ($l->qty_returned ?? 0)), 2, ',', '.'), '0'), ',') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-8 text-muted">Tidak ada Stock Out yang masih bisa diretur.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
