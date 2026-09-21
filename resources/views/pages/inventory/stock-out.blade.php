<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Models\Master\MatRaw;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhLoc;
use App\Models\Master\OrgDepartment;
use App\Models\Master\ProdPart;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// inventory_tier4.md §3 + mockup_stock_out.html. v1 DIKUNCI MANUAL (inventory_rules.yaml
// out_wajib_wo mensyaratkan tarik kebutuhan dari tm2prd.prd_wo_material -- modul
// Production/WO belum dibangun sama sekali di codebase ini, jadi baris ISSUE
// diisi manual, tanpa cabang SUBSTITUTE/PURCHASE yg butuh data WO.
// RM + SFG (2026-08-22, permintaan user): SFG keluar gudang utk lanjut ke
// proses produksi berikutnya (mis. barang setengah jadi yg sempat nunggu di
// gudang krn keterlambatan material/prioritas produk lain) -- pola akuntansi
// & fisiknya SAMA dgn RM, cuma beda kelas item (ProdPart, bukan MatRaw).
// FG TETAP ditolak di level route -- barang jadi keluar gudang cuma lewat
// Delivery Order ke customer (modul Dispatch, belum dibangun), BUKAN Stock
// Out generik: user sendiri mengkonfirmasi alur FG-nya adalah "masuk gudang
// dulu (Stock In FG, sudah ada) -> sebagian diambil truk utk kirim (itu DO,
// bukan Stock Out)" -- lihat memory project_delivery_order_scope kalau nanti
// dibangun.
// Alur "DRAFT -> POSTED" TANPA approval (inventory_rules.yaml alasan_out_rtp_tanpa_approval).
new #[Title('Stock Out — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    private const DOC_PREFIX = 'OUT';

    public const CATEGORY_LABELS = ['RM' => 'Bahan Baku', 'SFG' => 'Barang Setengah Jadi'];

    private const HEADER_MODEL = ['RM' => RmStock::class, 'SFG' => SfgStock::class];

    public string $category;

    public string $panel = 'grid';

    public string $search = '';

    public ?int $editingId = null;

    public bool $readOnly = false;

    public string $doc_no = '';

    public string $doc_date = '';

    public ?int $warehouse_id = null;

    public ?int $issued_to_dept = null;

    public string $process_code = '';

    public string $notes = '';

    public string $flow_status = 'DRAFT';

    public array $lines = [];

    public string $itemSearch = '';

    public function mount(string $category): void
    {
        $category = strtoupper($category);
        // fg_out_bukan_milik_modul: FG keluar cuma lewat Delivery Order (Dispatch,
        // belum dibangun) -- lihat catatan panjang di atas kelas ini.
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404, 'Stock Out cuma berlaku utk kelas Bahan Baku (RM) atau Barang Setengah Jadi (SFG).');
        $this->category = $category;
    }

    private function headerModel(): string
    {
        return self::HEADER_MODEL[$this->category];
    }

    #[Computed]
    public function warehouses()
    {
        return WhWarehouse::where('activate', true)->orderBy('warehouse_name')->get();
    }

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->get();
    }

    #[Computed]
    public function selectedWarehouseLocations()
    {
        if (! $this->warehouse_id) {
            return collect();
        }

        return WhLoc::where('warehouse_id', $this->warehouse_id)
            ->where('activate', true)->where('is_usable', true)
            ->orderBy('location_code')->get();
    }

    #[Computed]
    public function itemResults()
    {
        if (mb_strlen(trim($this->itemSearch)) < 2) {
            return collect();
        }
        $term = $this->itemSearch;

        if ($this->category === 'SFG') {
            return ProdPart::where('activate', true)
                ->where(fn ($q) => $q->where('part_code', 'like', "%{$term}%")->orWhere('part_name', 'like', "%{$term}%"))
                ->limit(15)->get()->map(fn ($p) => ['id' => $p->id_part, 'code' => $p->part_code, 'name' => $p->part_name, 'uom' => $p->uom_stock]);
        }

        return MatRaw::where('activate', true)
            ->where(fn ($q) => $q->where('material_code', 'like', "%{$term}%")->orWhere('material_name', 'like', "%{$term}%"))
            ->limit(15)->get()->map(fn ($m) => ['id' => $m->material_id, 'code' => $m->material_code, 'name' => $m->material_name, 'uom' => $m->uom_stock]);
    }

    public function addLine(int $itemId, string $code, string $name, ?string $uom): void
    {
        $this->lines[] = [
            'item_id' => $itemId, 'code' => $code, 'name' => $name,
            'lot_no' => '', 'location_id' => null, 'qty' => '', 'uom_code' => $uom ?: '',
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
            ->where('doc_cat', 'STOCK')->where('doc_type', -1)
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
        $this->reset(['editingId', 'doc_no', 'warehouse_id', 'issued_to_dept', 'process_code', 'notes', 'lines', 'itemSearch']);
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
        $this->warehouse_id = $doc->warehouse_id;
        $this->issued_to_dept = $doc->issued_to_dept;
        $this->process_code = (string) $doc->process_code;
        $this->notes = (string) $doc->notes;
        $this->flow_status = (string) $doc->flow_status;
        $this->readOnly = (bool) $doc->stat_confirm || (bool) $doc->stat_cancel;

        $itemKey = $this->category === 'SFG' ? 'parts_id' : 'material_id';
        $this->lines = $doc->lines->map(fn ($l) => [
            'item_id' => $l->{$itemKey}, 'code' => '', 'name' => $this->itemLabel($l->{$itemKey}),
            'lot_no' => (string) $l->lot_no, 'location_id' => $l->location_id,
            'qty' => (string) $l->qty, 'uom_code' => (string) $l->uom_code,
        ])->all();

        $this->panel = 'form';
    }

    private function itemLabel(int $itemId): string
    {
        return $this->category === 'SFG'
            ? (ProdPart::find($itemId)?->part_name ?? "#{$itemId}")
            : (MatRaw::find($itemId)?->material_name ?? "#{$itemId}");
    }

    public function backToGrid(): void
    {
        $this->panel = 'grid';
    }

    public function saveDraft(StockDocumentService $svc): void
    {
        $this->validate([
            'doc_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer'],
            'issued_to_dept' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.location_id' => ['required', 'integer'],
        ], [], [
            'warehouse_id' => 'Gudang', 'doc_date' => 'Tanggal Dokumen', 'issued_to_dept' => 'Diserahkan kepada (departemen)',
        ]);

        $docNo = $this->editingId ? $this->doc_no : $svc->nextDocNo($this->category, self::DOC_PREFIX);

        $itemKey = $this->category === 'SFG' ? 'parts_id' : 'material_id';
        $lines = array_map(fn ($l) => [
            $itemKey => $l['item_id'],
            'qty' => $l['qty'],
            'uom_code' => $l['uom_code'],
            'lot_no' => $l['lot_no'] ?: null,
            'location_id' => $l['location_id'],
            'action_type' => 'ISSUE',
        ], $this->lines);

        $this->editingId = $svc->saveDraft($this->category, $this->editingId, [
            'doc_no' => $docNo,
            'doc_date' => $this->doc_date,
            'doc_type' => -1,
            'doc_cat' => 'STOCK',
            'ref_doc_type' => 'MANUAL',
            'warehouse_id' => $this->warehouse_id,
            'issued_to_dept' => $this->issued_to_dept,
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

        $result = $svc->postDocument($this->category, $id, Auth::user()->login);

        unset($this->documents, $this->visibleDocuments, $this->statTotal, $this->statDraft, $this->statPosted);

        if (! $result['posted']) {
            session()->flash('error', $result['error']);

            return;
        }

        session()->flash('flash', 'Barang berhasil dikeluarkan & Kartu Stok ter-update.');
        if ($result['journal_warning'] ?? null) {
            session()->flash('journal_warning', $result['journal_warning']);
        }
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

    // Componentization grid+form (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai langsung sbg static call).

    // Baris grid SETELAH filter kotak statistik -- terpisah supaya angka kotak
    // tetap menghitung seluruh dokumen (griduistandard.md §2b).
    #[Computed]
    public function visibleDocuments()
    {
        return $this->applyStatFilter($this->documents);
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => Translator::t('inv_stat_total_doc', 'Total Dokumen'), 'value' => $this->statTotal, 'tone' => 'total'],
            'draft' => ['label' => Translator::t('sales_quo_stat_draft', 'Draft'), 'value' => $this->statDraft, 'tone' => 'warn',
                'filter' => fn ($c) => $c->where('flow_status', 'DRAFT')],
            'posted' => ['label' => Translator::t('inv_stockout_stat_issued', 'Sudah Diserahkan'), 'value' => $this->statPosted, 'tone' => 'active',
                'filter' => fn ($c) => $c->where('flow_status', 'POSTED')],
        ];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        <x-alert.banner type="success" :message="session('flash')" />
        <x-alert.banner type="danger" :message="session('error')" />
        @if (session('journal_warning'))
            <x-alert.banner type="info" :message="session('journal_warning').' '.Translator::t('inv_journal_warning_hint_prefix', 'Lengkapi akun HPP/Persediaan di').' '.($category === 'SFG' ? Translator::t('inv_journal_warning_hint_sfg', 'Master Card (part)') : Translator::t('inv_journal_warning_hint_rm', 'Master Bahan Baku atau kategori terkait')).'.'" />
        @endif

        @if ($panel === 'grid')
            <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <x-grid.toolbar>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ Translator::t('inv_search_doc_placeholder', 'Cari no. dokumen / catatan…') }}"
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
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('inv_col_document', 'Dokumen') }}</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('proc_col_date', 'Tanggal') }}</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ Translator::t('inv_col_issued_to', 'Diserahkan Kepada') }}</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('proc_col_status', 'Status') }}</th>
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ Translator::t('proc_col_action', 'Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->visibleDocuments as $d)
                                <tr wire:key="doc-{{ $d->doc_id }}" wire:dblclick="openEdit({{ $d->doc_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ Translator::t('shared_grid_dblclick_hint', 'Klik 2x untuk buka') }}">
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient($d->doc_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ \App\Support\Grid\Avatar::initials($d->doc_no ?: 'DOC') }}</div>
                                            <div>
                                                <div class="font-semibold text-[13px] font-mono">{{ $d->doc_no ?: Translator::t('proc_draft_placeholder', '(draft)') }}</div>
                                                <div class="text-[11px] text-muted">{{ $d->notes ?: '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->doc_date?->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->issuedToDepartment?->department_name ?? '—' }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($d->flow_status) }}">{{ $d->flow_status }}</span>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <x-grid.action-icon icon="fa-up-right-from-square" title="{{ Translator::t('shared_grid_action_open', 'Buka') }}" action="openEdit({{ $d->doc_id }})" />
                                        @if ($d->flow_status === 'DRAFT')
                                            <x-grid.action-icon icon="fa-check" title="{{ Translator::t('inv_btn_confirm_posting', 'Konfirmasi & Posting') }}" action="postNow(null, {{ $d->doc_id }})"
                                                confirm="{{ Translator::t('inv_btn_confirm_posting', 'Konfirmasi & Posting') }} {{ $d->doc_no }}? {{ Translator::t('inv_stockout_confirm_posting_suffix', 'Barang akan keluar dari Kartu Stok.') }}" variant="success" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-grid.empty-row :colspan="5" message="{{ Translator::t('inv_stockout_empty', 'Belum ada dokumen Stock Out.') }}" />
                            @endforelse
                        </tbody>
                    </table>
                </x-grid.table-shell>
            </div>
        @else
            {{-- ============ FORM ============ --}}
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                    <span>{{ $editingId ? Translator::t('inv_stockout_edit_title', 'Edit Stock Out ·').' '.$doc_no : Translator::t('inv_stockout_new_title', 'Stock Out Baru') }}</span>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/15">{{ Translator::t('inv_stockout_category_'.strtolower($category), self::CATEGORY_LABELS[$category]) }}</span>
                    @if ($editingId)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($flow_status) }}">{{ $flow_status }}</span>
                    @endif
                    <button type="button" wire:click="backToGrid" data-dirty-guard title="{{ Translator::t('inv_back_to_list_title', 'Kembali ke Daftar') }}"
                        class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full cursor-pointer hover:brightness-110">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
                    </button>
                </div>

                <div class="p-4 flex flex-col gap-3">
                    @if ($readOnly)
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">🔒 {{ Translator::t('inv_readonly_prefix', 'Dokumen sudah') }} {{ $flow_status === 'POSTED' ? Translator::t('inv_stockout_readonly_posted', 'diserahkan/diposting') : Translator::t('inv_readonly_cancelled', 'dibatalkan') }} {{ Translator::t('inv_readonly_suffix', '— lihat-saja, tidak bisa diedit. Koreksi lewat Stock Adjustment.') }}</div>
                    @endif

                    <div class="text-xs font-bold text-navy uppercase tracking-wide">{{ Translator::t('inv_section_header', 'Header') }}</div>
                    <div class="flex gap-2.5 flex-wrap">
                        <div class="flex-1 min-w-[160px]">
                            <x-form.input label="{{ Translator::t('inv_field_doc_no', 'No. Dokumen') }}" value="{{ $doc_no ?: Translator::t('inv_field_doc_no_auto', '(otomatis saat simpan)') }}" disabled class="font-mono" />
                        </div>
                        <div class="flex-1 min-w-[140px]">
                            <x-form.date-picker label="{{ Translator::t('inv_field_doc_date', 'Tanggal Dokumen *') }}" name="doc_date" wire:model="doc_date" :disabled="$readOnly" />
                        </div>
                        <div class="flex-1 min-w-[180px]">
                            <x-form.select label="{{ Translator::t('inv_field_warehouse_source', 'Gudang Sumber *') }}" name="warehouse_id" wire:model="warehouse_id" :disabled="$readOnly"
                                :options="$this->warehouses->mapWithKeys(fn ($w) => [$w->id_warehouse => $w->warehouse_code.' — '.$w->warehouse_name])" :selected="$warehouse_id" />
                        </div>
                        <div class="flex-1 min-w-[180px]">
                            <x-form.select label="{{ Translator::t('inv_field_issued_to_dept', 'Diserahkan Kepada (Dept.) *') }}" name="issued_to_dept" wire:model="issued_to_dept" :disabled="$readOnly"
                                :options="$this->departments->pluck('department_name', 'department_id')" :selected="$issued_to_dept" />
                        </div>
                        <div class="flex-1 min-w-[160px]">
                            <x-form.input label="{{ Translator::t('inv_field_process_target', 'Proses Tujuan') }}" wire:model="process_code" :disabled="$readOnly" placeholder="{{ Translator::t('inv_field_process_target_placeholder', 'mis. Cetak Offset, Laminasi') }}" />
                        </div>
                        <div class="flex-1 min-w-[160px]">
                            <x-form.input label="{{ Translator::t('inv_stockout_field_issued_by', 'Diserahkan oleh') }}" value="{{ Auth::user()->name }}" disabled />
                        </div>
                    </div>
                    <x-form.textarea label="{{ Translator::t('inv_field_notes', 'Catatan') }}" wire:model="notes" :disabled="$readOnly" rows="2" />

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">{{ Translator::t('inv_stockout_section_lines', 'Baris Item (Keluar)') }}</div>
                    @if (! $readOnly)
                        <div class="relative" style="max-width:380px">
                            <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="itemSearch" placeholder="🔍 {{ Translator::t('inv_stockout_item_search_prefix', 'Cari kode / nama') }} {{ $category === 'SFG' ? Translator::t('inv_item_kind_part', 'part') : Translator::t('inv_item_kind_raw_material', 'bahan baku') }} {{ Translator::t('inv_stockout_item_search_suffix', 'utk ditambah…') }}" />
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
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5]">{{ Translator::t('inv_line_col_item', 'Item') }}</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[110px]">{{ Translator::t('inv_line_col_lot', 'Lot/Batch') }}</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[180px]">{{ Translator::t('inv_line_col_location_source', 'Lokasi Sumber *') }}</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[100px]">{{ Translator::t('inv_line_col_qty_out', 'Qty Keluar *') }}</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[70px]">{{ Translator::t('proc_pv_col_uom', 'UoM') }}</th>
                                    @if (! $readOnly)
                                        <th class="w-[40px] border-b border-[#eef1f5]"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lines as $i => $line)
                                    <tr wire:key="line-{{ $i }}" class="border-b border-[#eef1f5]">
                                        <td class="px-2 py-1.5">{{ $line['name'] }}</td>
                                        <td class="px-2 py-1.5"><x-form.input wire:model="lines.{{ $i }}.lot_no" :disabled="$readOnly" class="!py-1" /></td>
                                        <td class="px-2 py-1.5">
                                            <x-form.select wire:model="lines.{{ $i }}.location_id" :disabled="$readOnly" class="!py-1"
                                                :options="$this->selectedWarehouseLocations->pluck('location_code', 'location_id')" :selected="$line['location_id'] ?? null" />
                                        </td>
                                        <td class="px-2 py-1.5"><x-form.input wire:model="lines.{{ $i }}.qty" :disabled="$readOnly" class="!py-1 text-right" /></td>
                                        <td class="px-2 py-1.5 text-muted">{{ $line['uom_code'] }}</td>
                                        @if (! $readOnly)
                                            <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeLine({{ $i }})" class="text-[#c0392b] cursor-pointer" title="{{ Translator::t('inv_line_remove_title', 'Hapus baris') }}">✕</button></td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center py-6 text-muted text-xs">{{ Translator::t('inv_stockout_no_lines_prefix', 'Belum ada baris — cari') }} {{ $category === 'SFG' ? Translator::t('inv_item_kind_part', 'part') : Translator::t('inv_item_kind_raw_material', 'bahan baku') }} {{ Translator::t('inv_stockout_no_lines_suffix', 'di atas utk ditambahkan.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (! $readOnly)
                        <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                            @if ($editingId)
                                <button type="button" wire:click="cancelDoc" wire:confirm="{{ Translator::t('inv_confirm_cancel_doc', 'Batalkan dokumen ini?') }}" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#fdecec] text-[#c0392b] cursor-pointer">{{ Translator::t('sales_form_btn_cancel_doc', 'Batalkan') }}</button>
                            @endif
                            <button type="button" wire:click="saveDraft" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">💾 {{ Translator::t('inv_btn_save_draft', 'Simpan Draft') }}</button>
                            @if ($editingId)
                                <button type="button" wire:click="postNow" wire:confirm="{{ Translator::t('inv_btn_confirm_posting', 'Konfirmasi & Posting') }} {{ $doc_no }}? {{ Translator::t('inv_stockout_confirm_posting_final_suffix', 'Barang akan keluar dari Kartu Stok, tidak bisa diedit lagi.') }}" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">✓ {{ Translator::t('inv_btn_confirm_posting', 'Konfirmasi & Posting') }}</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
