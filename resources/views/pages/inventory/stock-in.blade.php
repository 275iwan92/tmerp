<?php

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Models\Master\MRawMaterial;
use App\Models\Master\MWarehouse;
use App\Models\Master\MWhLocation;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// INV_TIER_4.md #1.1 (route inv.stock-in.index -> StockInPage) + mockup_stock_in.html.
// {category} WAJIB dari URL (RM/FG/SFG), pola sama seperti print/stock-card
// & print/master-stock -- lihat SIDEBAR_MENU_STANDARD.md #5. Sumber Dokumen
// v1 cuma MANUAL (PV/WO auto-post belum diintegrasikan -- itu domain Purchase
// Receive, lihat SIDEBAR_MENU_STANDARD.md #6/#6a, dikerjakan terpisah nanti).
// GRID_UI_STANDARD.md diikuti utk grid: non-master -> Buka + Confirm
// kondisional (bukan toggle aktif), Confirm di sini = posting sungguhan
// lewat StockPostingService (bukan sekadar ubah status).
new #[Title('Stock In — TELASIH MAKMUR ERP')] class extends Component
{
    public const CATEGORY_LABELS = [
        'RM' => 'Bahan Baku & Bahan Pembantu',
        'SFG' => 'Barang Setengah Jadi',
        'FG' => 'Barang Jadi',
    ];

    private const HEADER_MODEL = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class];

    private const DOC_PREFIX = ['RM' => 'IN', 'FG' => 'IN', 'SFG' => 'IN'];

    public string $category;

    public string $panel = 'grid'; // grid | form

    public string $search = '';

    public ?int $editingId = null;

    public bool $readOnly = false;

    // ---- header form ----
    public string $doc_no = '';

    public string $doc_date = '';

    public string $doc_cat = 'STOCK'; // STOCK | BB

    public ?int $warehouse_id = null;

    public string $notes = '';

    public string $flow_status = 'DRAFT';

    // ---- baris detail ----
    public array $lines = [];

    public string $itemSearch = '';

    public function mount(string $category): void
    {
        $category = strtoupper($category);
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404);
        $this->category = $category;
    }

    private function headerModel(): string
    {
        return self::HEADER_MODEL[$this->category];
    }

    #[Computed]
    public function warehouses()
    {
        return MWarehouse::where('is_active', true)->orderBy('warehouse_name')->get();
    }

    #[Computed]
    public function selectedWarehouseLocations()
    {
        if (! $this->warehouse_id) {
            return collect();
        }

        return MWhLocation::where('warehouse_id', $this->warehouse_id)
            ->where('is_active', true)->where('is_usable', true)
            ->orderBy('location_code')->get();
    }

    #[Computed]
    public function itemResults()
    {
        if (mb_strlen(trim($this->itemSearch)) < 2) {
            return collect();
        }
        $term = $this->itemSearch;

        return match ($this->category) {
            'SFG' => ProdPart::where('is_active', true)->where(fn ($q) => $q->where('part_code', 'like', "%{$term}%")->orWhere('part_name', 'like', "%{$term}%"))
                ->limit(15)->get()->map(fn ($p) => ['id' => $p->id_part, 'code' => $p->part_code, 'name' => $p->part_name, 'uom' => $p->uom_stock]),
            'FG' => ProdProduct::where('is_active', true)->where(fn ($q) => $q->where('product_code', 'like', "%{$term}%")->orWhere('product_name', 'like', "%{$term}%"))
                ->limit(15)->get()->map(fn ($p) => ['id' => $p->id_product, 'code' => $p->product_code, 'name' => $p->product_name, 'uom' => $p->uom_stock]),
            default => MRawMaterial::where('is_active', true)->where(fn ($q) => $q->where('material_code', 'like', "%{$term}%")->orWhere('material_name', 'like', "%{$term}%"))
                ->limit(15)->get()->map(fn ($m) => ['id' => $m->material_id, 'code' => $m->material_code, 'name' => $m->material_name, 'uom' => $m->uom_stock]),
        };
    }

    public function addLine(int $itemId, string $code, string $name, ?string $uom): void
    {
        $this->lines[] = [
            'item_id' => $itemId, 'code' => $code, 'name' => $name,
            'lot_no' => '', 'location_id' => null, 'qty' => '', 'uom_code' => $uom ?: '', 'unit_price' => '0',
        ];
        $this->itemSearch = '';
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
            ->whereIn('doc_cat', ['STOCK', 'BB'])
            ->where('doc_type', 1)
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('doc_no', 'like', "%{$this->search}%")
                ->orWhere('notes', 'like', "%{$this->search}%")))
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
        $this->reset(['editingId', 'doc_no', 'warehouse_id', 'notes', 'lines', 'itemSearch']);
        $this->doc_date = now()->toDateString();
        $this->doc_cat = 'STOCK';
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
        $this->doc_cat = $doc->doc_cat ?: 'STOCK';
        $this->warehouse_id = $doc->warehouse_id;
        $this->notes = (string) $doc->notes;
        $this->flow_status = (string) $doc->flow_status;
        $this->readOnly = (bool) $doc->stat_confirm || (bool) $doc->stat_cancel;

        $itemKey = match ($this->category) { 'SFG' => 'parts_id', 'FG' => 'product_id', default => 'material_id' };
        $this->lines = $doc->lines->map(fn ($l) => [
            'item_id' => $l->{$itemKey}, 'code' => '', 'name' => $this->itemLabel($l->{$itemKey}),
            'lot_no' => (string) $l->lot_no, 'location_id' => $l->location_id,
            'qty' => (string) $l->qty, 'uom_code' => (string) $l->uom_code, 'unit_price' => (string) $l->unit_price,
        ])->all();

        $this->panel = 'form';
    }

    private function itemLabel(int $itemId): string
    {
        return match ($this->category) {
            'SFG' => ProdPart::find($itemId)?->part_name ?? "#{$itemId}",
            'FG' => ProdProduct::find($itemId)?->product_name ?? "#{$itemId}",
            default => MRawMaterial::find($itemId)?->material_name ?? "#{$itemId}",
        };
    }

    public function backToGrid(): void
    {
        $this->panel = 'grid';
    }

    public function saveDraft(StockDocumentService $svc): void
    {
        $this->validate([
            'doc_date' => ['required', 'date'],
            'doc_cat' => ['required', 'in:STOCK,BB'],
            'warehouse_id' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.location_id' => ['required', 'integer'],
        ], [], [
            'warehouse_id' => 'Gudang', 'doc_date' => 'Tanggal Dokumen', 'doc_cat' => 'Kategori Dokumen',
        ]);

        $itemKey = match ($this->category) { 'SFG' => 'parts_id', 'FG' => 'product_id', default => 'material_id' };
        $docNo = $this->editingId ? $this->doc_no : $svc->nextDocNo($this->category, self::DOC_PREFIX[$this->category]);

        $lines = array_map(fn ($l) => [
            $itemKey => $l['item_id'],
            'qty' => $l['qty'],
            'uom_code' => $l['uom_code'],
            'lot_no' => $l['lot_no'] ?: null,
            'location_id' => $l['location_id'],
            'unit_price' => $l['unit_price'] ?: 0,
        ], $this->lines);

        $this->editingId = $svc->saveDraft($this->category, $this->editingId, [
            'doc_no' => $docNo,
            'doc_date' => $this->doc_date,
            'doc_type' => 1,
            'doc_cat' => $this->doc_cat,
            'ref_doc_type' => 'MANUAL',
            'warehouse_id' => $this->warehouse_id,
            'notes' => $this->notes ?: null,
        ], $lines, Auth::user()->login);

        $this->doc_no = $docNo;
        unset($this->documents, $this->statTotal, $this->statDraft, $this->statPosted);
        session()->flash('flash', "Draft {$docNo} tersimpan.");
        $this->panel = 'grid';
    }

    public function postNow(StockPostingService $svc, ?int $docId = null): void
    {
        $id = $docId ?? $this->editingId;
        if (! $id) {
            return;
        }

        $result = $svc->postDocument($this->category, $id, Auth::user()->login);

        unset($this->documents, $this->statTotal, $this->statDraft, $this->statPosted);

        if (! $result['posted']) {
            session()->flash('error', $result['error']);

            return;
        }

        session()->flash('flash', 'Dokumen berhasil di-posting ke Kartu Stok.');
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
        unset($this->documents, $this->statTotal, $this->statDraft, $this->statPosted);
        session()->flash('flash', 'Dokumen dibatalkan.');
        $this->panel = 'grid';
    }

    public function subStateBadgeClass(string $status): string
    {
        return match ($status) {
            'DRAFT' => 'bg-[#eef0f3] text-[#8a8f98]',
            'SUBMITTED' => 'bg-[#eaf2ff] text-[#1d4ed8]',
            'POSTED' => 'bg-[#e9f9ef] text-[#16a34a]',
            'CANCELLED' => 'bg-[#fdecec] text-[#c0392b]',
            default => 'bg-[#eef0f3] text-[#8a8f98]',
        };
    }

    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    private const AVATAR_PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public function avatarGradient(int $id): string
    {
        return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        @if (session('flash'))
            <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 bg-[#e9f9ef] border border-[#c8ecd6] text-[#1f8a4c]">✅ {{ session('flash') }}</div>
        @endif
        @if (session('error'))
            <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f]">⚠️ {{ session('error') }}</div>
        @endif

        @if ($panel === 'grid')
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-3.5">
                <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                    <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Dokumen</p>
                    <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
                </div>
                <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                    <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Draft</p>
                    <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statDraft }}</p>
                </div>
                <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                    <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Sudah Posting</p>
                    <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statPosted }}</p>
                </div>
            </div>

            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. dokumen / catatan…"
                            class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <span class="flex-1"></span>
                    <button type="button" wire:click="openNew"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </div>

                <div class="max-h-[65vh] overflow-auto">
                    <table class="w-full border-collapse text-[12.5px]">
                        <thead>
                            <tr>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Dokumen</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[90px]">Kategori</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Status</th>
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->documents as $d)
                                <tr wire:key="doc-{{ $d->doc_id }}" wire:dblclick="openEdit({{ $d->doc_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($d->doc_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($d->doc_no ?: 'DOC') }}</div>
                                            <div>
                                                <div class="font-semibold text-[13px] font-mono">{{ $d->doc_no ?: '(draft)' }}</div>
                                                <div class="text-[11px] text-muted">{{ $d->notes ?: '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->doc_date?->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->doc_cat }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($d->flow_status) }}">{{ $d->flow_status }}</span>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <button type="button" wire:click.stop="openEdit({{ $d->doc_id }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                        @if ($d->flow_status === 'DRAFT')
                                            <button type="button" wire:click.stop="postNow(null, {{ $d->doc_id }})" wire:confirm="Posting {{ $d->doc_no }} ke Kartu Stok? Setelah posting tidak bisa diedit lagi." title="Posting" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[#16a34a] hover:bg-[#e9f9ef] cursor-pointer">✓</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-8 text-muted text-sm">Belum ada dokumen Stock In.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- ============ FORM ============ --}}
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                    <span>{{ $editingId ? 'Edit Stock In · '.$doc_no : 'Stock In Baru' }}</span>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/15">{{ self::CATEGORY_LABELS[$category] }}</span>
                    @if ($editingId)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($flow_status) }}">{{ $flow_status }}</span>
                    @endif
                    <button type="button" wire:click="backToGrid" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md cursor-pointer hover:brightness-110">← Daftar</button>
                </div>

                <div class="p-4 flex flex-col gap-3">
                    @if ($readOnly)
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">🔒 Dokumen sudah {{ $flow_status === 'POSTED' ? 'diposting' : 'dibatalkan' }} — lihat-saja, tidak bisa diedit. Koreksi lewat Stock Adjustment.</div>
                    @endif

                    <div class="text-xs font-bold text-navy uppercase tracking-wide">Header</div>
                    <div class="flex gap-2.5 flex-wrap">
                        <div class="flex-1 min-w-[160px] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">No. Dokumen</label>
                            <input type="text" value="{{ $doc_no ?: '(otomatis saat simpan)' }}" disabled class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] font-mono bg-[#f4f6fa] text-muted">
                        </div>
                        <div class="flex-1 min-w-[140px] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Tanggal Dokumen *</label>
                            <input type="date" wire:model="doc_date" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                            @error('doc_date') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 min-w-[140px] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kategori Dokumen *</label>
                            <select wire:model="doc_cat" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="STOCK">STOCK (masuk biasa)</option>
                                <option value="BB">BB (saldo awal)</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[180px] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Gudang *</label>
                            <select wire:model="warehouse_id" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->warehouses as $w)
                                    <option value="{{ $w->id_warehouse }}">{{ $w->warehouse_code }} — {{ $w->warehouse_name }}</option>
                                @endforeach
                            </select>
                            @error('warehouse_id') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 min-w-[160px] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Diterima oleh</label>
                            <input type="text" value="{{ Auth::user()->name }}" disabled class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] bg-[#f4f6fa] text-muted">
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Catatan</label>
                        <textarea wire:model="notes" @disabled($readOnly) rows="2" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]"></textarea>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Baris Item</div>
                    @if (! $readOnly)
                        <div class="relative" style="max-width:380px">
                            <input type="text" wire:model.live.debounce.300ms="itemSearch" placeholder="🔍 Cari kode / nama item utk ditambah…" class="w-full px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            @if ($this->itemResults->isNotEmpty())
                                <div class="absolute top-full left-0 right-0 mt-1 bg-white border border-[#d7dbe2] rounded shadow-lg z-10 max-h-56 overflow-y-auto">
                                    @foreach ($this->itemResults as $it)
                                        <button type="button" wire:click="addLine({{ $it['id'] }}, '{{ $it['code'] }}', '{{ addslashes($it['name']) }}', '{{ $it['uom'] }}')" class="w-full text-left px-2.5 py-1.5 text-xs hover:bg-[#eef3fb] border-b border-[#eef1f5] last:border-b-0">
                                            <span class="font-mono font-bold text-accent">{{ $it['code'] }}</span> — {{ $it['name'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                    @error('lines') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror

                    <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                        <table class="w-full text-[12.5px] border-collapse">
                            <thead>
                                <tr class="bg-[#fafbfd]">
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5]">Item</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[110px]">Lot/Batch</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[160px]">Lokasi *</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[100px]">Qty *</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[70px]">UOM</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[110px]">Harga Satuan</th>
                                    @if (! $readOnly)
                                        <th class="w-[40px] border-b border-[#eef1f5]"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lines as $i => $line)
                                    <tr wire:key="line-{{ $i }}" class="border-b border-[#eef1f5]">
                                        <td class="px-2 py-1.5">{{ $line['name'] }}</td>
                                        <td class="px-2 py-1.5"><input type="text" wire:model="lines.{{ $i }}.lot_no" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs"></td>
                                        <td class="px-2 py-1.5">
                                            <select wire:model="lines.{{ $i }}.location_id" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs">
                                                <option value="">— pilih —</option>
                                                @foreach ($this->selectedWarehouseLocations as $loc)
                                                    <option value="{{ $loc->location_id }}">{{ $loc->location_code }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5"><input type="text" wire:model="lines.{{ $i }}.qty" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right"></td>
                                        <td class="px-2 py-1.5 text-muted">{{ $line['uom_code'] }}</td>
                                        <td class="px-2 py-1.5"><input type="text" wire:model="lines.{{ $i }}.unit_price" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right"></td>
                                        @if (! $readOnly)
                                            <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeLine({{ $i }})" class="text-[#c0392b] cursor-pointer" title="Hapus baris">✕</button></td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center py-6 text-muted text-xs">Belum ada baris — cari item di atas utk ditambahkan.</td></tr>
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
                                <button type="button" wire:click="postNow" wire:confirm="Posting {{ $doc_no }} ke Kartu Stok? Setelah posting tidak bisa diedit lagi." class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">✓ Konfirmasi &amp; Posting</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
