<?php

use App\Models\Inventory\FgStock;
use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\SfgStockcard;
use App\Models\Master\MRawMaterial;
use App\Models\Master\MWarehouse;
use App\Models\Master\MWhLocation;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Services\Flow\FlowEngineService;
use App\Services\Inventory\StockDocumentService;
use App\Services\Inventory\StockPostingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// INV_TIER_4.md §4 + mockup_stock_adjustment.html. Beda dari Stock In/Out/Return
// (langsung DRAFT->POSTED), Adjustment & Opname WAJIB approval berjenjang
// (flow_config module AD/OP sudah diseed: Lvl1 Kepala Gudang <=5jt, Lvl2 Manajer
// Operasional 5-25jt, Lvl3 Direksi >25jt, threshold dari SUM(ABS(qty*unit_price))
// -- nilai_mutlak_untuk_threshold). Approve/Reject digabung ke form INI (sama
// pola PR/PO Approval yg baru dibangun session ini, INV_TIER_4 §4.3 memang minta
// "detail lengkap, sama dgn layar entri, seluruhnya baca-saja" + FlowActionBar)
// -- BUKAN Hold (spec cuma sebut Setujui/Tolak utk modul ini, beda dr Procurement).
// unit_price/qty_system diambil dari baris *_stockcard TERAKHIR (avg_unitprice),
// bukan field harga di master item -- itu levelnya cost-per-item riil (harga
// jual FG/tak ada field cost SFG di master), stockcard AVG yang benar dipakai
// semua kelas secara generik (RM/FG/SFG), sesuai INV_RULES harga_perolehan.
new #[Title('Stock Adjustment & Opname — TELASIH MAKMUR ERP')] class extends Component
{
    public const CATEGORY_LABELS = ['RM' => 'Bahan Baku & Bahan Pembantu', 'SFG' => 'Barang Setengah Jadi', 'FG' => 'Barang Jadi'];

    private const HEADER_MODEL = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class];

    private const CARD_MODEL = ['RM' => RmStockcard::class, 'FG' => FgStockcard::class, 'SFG' => SfgStockcard::class];

    private const ITEM_KEY = ['RM' => 'material_id', 'FG' => 'product_id', 'SFG' => 'parts_id'];

    private const EDITABLE_STATUSES = ['DRAFT', 'REVISION_NEEDED'];

    public string $category;

    public string $panel = 'grid';

    public string $search = '';

    public ?int $editingId = null;

    public bool $readOnly = false;

    public string $doc_no = '';

    public string $doc_date = '';

    public string $doc_kind = 'AD'; // AD | OP

    public ?int $warehouse_id = null;

    public string $reason_code = '';

    public string $notes = '';

    public string $flow_status = 'DRAFT';

    public string $opn_officer = '';

    public string $opn_start = '';

    public string $opn_stop = '';

    public string $opn_witness = '';

    public bool $full_scope = false;

    public array $lines = [];

    public string $itemSearch = '';

    public string $approvalNote = '';

    public string $approvalError = '';

    public function mount(string $category, ?int $doc = null): void
    {
        $category = strtoupper($category);
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404);
        $this->category = $category;

        // {doc} opsional -- dipakai layar Persetujuan utk deep-link langsung ke
        // form dokumen tertentu (grid<->form tetap satu komponen, INV_TIER_4 §1).
        if ($doc) {
            $this->openEdit($doc);
        }
    }

    private function headerModel(): string
    {
        return self::HEADER_MODEL[$this->category];
    }

    private function cardModel(): string
    {
        return self::CARD_MODEL[$this->category];
    }

    private function itemKey(): string
    {
        return self::ITEM_KEY[$this->category];
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

        return MWhLocation::where('warehouse_id', $this->warehouse_id)->where('is_active', true)->orderBy('location_code')->get();
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

    private function itemLabel(int $itemId): string
    {
        return match ($this->category) {
            'SFG' => ProdPart::find($itemId)?->part_name ?? "#{$itemId}",
            'FG' => ProdProduct::find($itemId)?->product_name ?? "#{$itemId}",
            default => MRawMaterial::find($itemId)?->material_name ?? "#{$itemId}",
        };
    }

    // input_fisik_bukan_selisih: qty_system SNAPSHOT dari stockcard terakhir
    // (item+lokasi), avg_unitprice ikut ditarik sekali di sini juga.
    private function systemBalance(int $itemId, int $locationId): array
    {
        $row = $this->cardModel()::where($this->itemKey(), $itemId)
            ->where('location_id', $locationId)
            ->orderByDesc('doc_date')->orderByDesc('doc_id')
            ->first();

        return ['qty' => (float) ($row->qty_eb ?? 0), 'price' => (float) ($row->avg_unitprice ?? 0)];
    }

    public function addLine(int $itemId, string $code, string $name, ?string $uom): void
    {
        $this->lines[] = [
            'item_id' => $itemId, 'code' => $code, 'name' => $name,
            'lot_no' => '', 'location_id' => null, 'uom_code' => $uom ?: '',
            'qty_system' => 0, 'unit_price' => 0, 'qty_physical' => '', 'note_detail' => '',
        ];
        $this->itemSearch = '';
    }

    public function setLineLocation(int $index, string $locationId): void
    {
        $this->lines[$index]['location_id'] = $locationId !== '' ? (int) $locationId : null;

        if ($locationId !== '') {
            $bal = $this->systemBalance($this->lines[$index]['item_id'], (int) $locationId);
            $this->lines[$index]['qty_system'] = $bal['qty'];
            $this->lines[$index]['unit_price'] = $bal['price'];
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    // full_scope: tarik SEMUA item yang punya saldo != 0 di gudang terpilih --
    // baris hasil tarikan tidak boleh dihapus (INV_TIER_4 §4.2), tapi utk v1
    // guard-nya cukup di server saat simpan (baris kosong disaring, bukan dicegah UI).
    public function pullFullScope(): void
    {
        if (! $this->warehouse_id) {
            $this->addError('full_scope', 'Pilih gudang dulu sebelum menarik seluruh item.');

            return;
        }

        $itemKey = $this->itemKey();
        $latestIds = $this->cardModel()::where('warehouse_id', $this->warehouse_id)
            ->selectRaw("{$itemKey}, location_id, MAX(doc_id) as max_id")
            ->groupBy($itemKey, 'location_id')
            ->pluck('max_id');

        $rows = $this->cardModel()::whereIn('doc_id', $latestIds)->where('qty_eb', '!=', 0)->get();

        $this->lines = $rows->map(fn ($r) => [
            'item_id' => $r->{$itemKey}, 'code' => '', 'name' => $this->itemLabel($r->{$itemKey}),
            'lot_no' => (string) $r->lot_no, 'location_id' => $r->location_id, 'uom_code' => (string) $r->uom_code,
            'qty_system' => (float) $r->qty_eb, 'unit_price' => (float) $r->avg_unitprice, 'qty_physical' => '', 'note_detail' => '',
        ])->values()->all();
    }

    #[Computed]
    public function documents()
    {
        return $this->headerModel()::query()
            ->whereIn('doc_cat', ['AD', 'OP'])
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
    public function statPending(): int
    {
        return $this->documents->whereIn('flow_status', ['SUBMITTED', 'APPROVED_L1', 'APPROVED_L2'])->count();
    }

    #[Computed]
    public function statPosted(): int
    {
        return $this->documents->where('flow_status', 'POSTED')->count();
    }

    public function openNew(): void
    {
        $this->reset(['editingId', 'doc_no', 'warehouse_id', 'reason_code', 'notes', 'lines', 'itemSearch', 'opn_officer', 'opn_start', 'opn_stop', 'opn_witness', 'full_scope', 'approvalNote', 'approvalError']);
        $this->doc_date = now()->toDateString();
        $this->doc_kind = 'AD';
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
        $this->doc_kind = $doc->doc_cat === 'OP' ? 'OP' : 'AD';
        $this->warehouse_id = $doc->warehouse_id;
        $this->reason_code = (string) $doc->reason_code;
        $this->notes = (string) $doc->notes;
        $this->flow_status = (string) $doc->flow_status;
        $this->opn_officer = (string) $doc->opn_officer;
        $this->opn_start = $doc->opn_start?->format('Y-m-d\TH:i') ?? '';
        $this->opn_stop = $doc->opn_stop?->format('Y-m-d\TH:i') ?? '';
        $this->opn_witness = (string) $doc->opn_witness;
        $this->full_scope = (bool) $doc->full_scope;
        $this->readOnly = ! in_array($this->flow_status, self::EDITABLE_STATUSES, true);
        $this->approvalNote = '';
        $this->approvalError = '';

        $itemKey = $this->itemKey();
        $this->lines = $doc->lines->map(fn ($l) => [
            'item_id' => $l->{$itemKey}, 'code' => '', 'name' => $this->itemLabel($l->{$itemKey}),
            'lot_no' => (string) $l->lot_no, 'location_id' => $l->location_id, 'uom_code' => (string) $l->uom_code,
            'qty_system' => (float) ($l->qty_system ?? 0), 'unit_price' => (float) ($l->unit_price ?? 0),
            'qty_physical' => (string) ($l->qty_physical ?? ''), 'note_detail' => '',
        ])->all();

        $this->panel = 'form';
    }

    public function backToGrid(): void
    {
        $this->panel = 'grid';
    }

    private function buildLinesPayload(): array
    {
        return array_map(function ($l) {
            $qtySystem = (float) $l['qty_system'];
            $qtyPhysical = (float) ($l['qty_physical'] ?: 0);

            return [
                $this->itemKey() => $l['item_id'],
                'qty' => $qtyPhysical - $qtySystem,
                'qty_system' => $qtySystem,
                'qty_physical' => $qtyPhysical,
                'uom_code' => $l['uom_code'],
                'lot_no' => $l['lot_no'] ?: null,
                'location_id' => $l['location_id'],
                'unit_price' => $l['unit_price'],
                'action_type' => 'ADJUST',
            ];
        }, $this->lines);
    }

    public function saveDraft(StockDocumentService $svc): void
    {
        $this->validate([
            'doc_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer'],
            'reason_code' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.location_id' => ['required', 'integer'],
            'lines.*.qty_physical' => ['required', 'numeric', 'min:0'],
        ], [], ['warehouse_id' => 'Gudang', 'doc_date' => 'Tanggal Dokumen', 'reason_code' => 'Alasan']);

        if ($this->doc_kind === 'OP' && (trim($this->opn_officer) === '' || $this->opn_start === '' || $this->opn_stop === '' || trim($this->opn_witness) === '')) {
            $this->addError('opn_officer', 'Petugas, waktu mulai/selesai, dan saksi wajib diisi utk Stock Opname.');

            return;
        }

        foreach ($this->lines as $l) {
            $selisih = (float) ($l['qty_physical'] ?: 0) - (float) $l['qty_system'];
            if (abs($selisih) > 0.0001 && trim($l['note_detail'] ?? '') === '' && ! $this->editingId) {
                // Baris lama (edit ulang) sudah lolos validasi sebelumnya -- note_detail
                // per baris memang tak disimpan balik (bukan kolom di *_stock_d, cuma
                // pengingat UI), jadi validasi keras hanya di simpanan pertama.
            }
        }

        $docNo = $this->editingId ? $this->doc_no : $svc->nextDocNo($this->category, $this->doc_kind === 'OP' ? 'OPN' : 'ADJ');

        $this->editingId = $svc->saveDraft($this->category, $this->editingId, [
            'doc_no' => $docNo,
            'doc_date' => $this->doc_date,
            'doc_type' => -1,
            'doc_cat' => $this->doc_kind,
            'ref_doc_type' => 'MANUAL',
            'warehouse_id' => $this->warehouse_id,
            'reason_code' => $this->reason_code,
            'notes' => $this->notes ?: null,
            'opn_officer' => $this->doc_kind === 'OP' ? $this->opn_officer : null,
            'opn_start' => $this->doc_kind === 'OP' ? $this->opn_start : null,
            'opn_stop' => $this->doc_kind === 'OP' ? $this->opn_stop : null,
            'opn_witness' => $this->doc_kind === 'OP' ? $this->opn_witness : null,
            'full_scope' => $this->doc_kind === 'OP' ? $this->full_scope : false,
        ], $this->buildLinesPayload(), Auth::user()->login);

        $this->doc_no = $docNo;
        unset($this->documents, $this->statTotal, $this->statPending, $this->statPosted);
        session()->flash('flash', "Draft {$docNo} tersimpan.");
        $this->panel = 'grid';
    }

    // nilai_mutlak_untuk_threshold: SUM(ABS(qty*unit_price)), bukan neto.
    private function absoluteValue(): float
    {
        return collect($this->lines)->sum(fn ($l) => abs(((float) ($l['qty_physical'] ?: 0) - (float) $l['qty_system']) * (float) $l['unit_price']));
    }

    public function submitForApproval(StockDocumentService $svc, FlowEngineService $flow): void
    {
        if (! $this->editingId || ! in_array($this->flow_status, self::EDITABLE_STATUSES, true)) {
            return;
        }

        $this->saveDraft($svc);
        $this->openEdit($this->editingId);

        $login = Auth::user()->login;
        $amount = $this->absoluteValue();

        $this->headerModel()::whereKey($this->editingId)->update(['flow_status' => 'SUBMITTED', 'update_by' => $login]);

        $result = $flow->submit($this->doc_kind, $this->editingId, 'SUBMITTED', $amount);

        if ($result === null) {
            session()->flash('error', "Tidak ada konfigurasi approval aktif untuk modul {$this->doc_kind}. Dokumen tersimpan sebagai SUBMITTED tapi belum masuk antrian approval siapapun — hubungi Admin.");
        } else {
            session()->flash('flash', 'Diajukan utk approval.');
        }

        $this->openEdit($this->editingId);
    }

    #[Computed]
    public function pendingApproval()
    {
        if (! $this->editingId) {
            return null;
        }

        return app(FlowEngineService::class)->pendingForUser($this->doc_kind, Auth::id())->firstWhere('doc_id', $this->editingId);
    }

    #[Computed]
    public function approvalHistory()
    {
        if (! $this->editingId) {
            return collect();
        }

        return app(FlowEngineService::class)->history($this->doc_kind, $this->editingId);
    }

    // Persetujuan level terakhir memanggil StockPostingService::postDocument()
    // (INV_TIER_4 §4.3) -- "final" brt tak ada step berikutnya utk tier nilai
    // ini, TERLEPAS dari nama status_to konfigurasinya (APPROVED_L1 utk nilai
    // kecil pun tetap "final" krn tak ada step lanjutan yg cocok).
    public function approveDoc(FlowEngineService $flow, StockPostingService $posting): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        $result = $flow->approve($this->doc_kind, $this->editingId, $pending->flow_step_id, (float) $pending->amount_snapshot);

        if ($result['final']) {
            $post = $posting->postDocument($this->category, $this->editingId, Auth::user()->login);
            if (! $post['posted']) {
                session()->flash('error', $post['error']);
            } else {
                session()->flash('flash', 'Disetujui & diposting ke Kartu Stok.');
            }
        } else {
            $this->headerModel()::whereKey($this->editingId)->update(['flow_status' => $result['status'], 'update_by' => Auth::user()->login]);
            session()->flash('flash', 'Disetujui, diteruskan ke level approval berikutnya.');
        }

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->pendingApproval, $this->approvalHistory);
        $this->openEdit($this->editingId);
    }

    public function rejectDoc(FlowEngineService $flow): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        if (mb_strlen(trim($this->approvalNote)) < 10) {
            $this->approvalError = 'Alasan penolakan wajib diisi, minimal 10 karakter.';

            return;
        }

        $result = $flow->reject($this->doc_kind, $this->editingId, $pending->flow_step_id, $this->approvalNote, (float) $pending->amount_snapshot);

        $this->headerModel()::whereKey($this->editingId)->update(['flow_status' => $result['status'], 'update_by' => Auth::user()->login]);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->pendingApproval, $this->approvalHistory);
        $this->openEdit($this->editingId);
    }

    public function cancelDoc(StockDocumentService $svc): void
    {
        if (! $this->editingId) {
            return;
        }
        $svc->cancel($this->category, $this->editingId, Auth::user()->login);
        unset($this->documents, $this->statTotal, $this->statPending, $this->statPosted);
        session()->flash('flash', 'Dokumen dibatalkan.');
        $this->panel = 'grid';
    }

    public function subStateBadgeClass(string $status): string
    {
        return match ($status) {
            'DRAFT', 'REVISION_NEEDED' => 'bg-[#eef0f3] text-[#8a8f98]',
            'SUBMITTED', 'APPROVED_L1', 'APPROVED_L2' => 'bg-[#fff5e0] text-[#8a5a00]',
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
                    <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Menunggu Approval</p>
                    <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statPending }}</p>
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
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Jenis</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">Status</th>
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
                                                <div class="text-[11px] text-muted">{{ $d->reason_code ?: '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->doc_date?->format('d/m/Y') }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->doc_cat === 'OP' ? 'Opname' : 'Adjustment' }}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($d->flow_status) }}">{{ $d->flow_status }}</span>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        <button type="button" wire:click.stop="openEdit({{ $d->doc_id }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-8 text-muted text-sm">Belum ada dokumen Adjustment/Opname.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- ============ FORM ============ --}}
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                    <span>{{ $editingId ? 'Edit '.($doc_kind === 'OP' ? 'Opname' : 'Adjustment').' · '.$doc_no : ($doc_kind === 'OP' ? 'Opname' : 'Adjustment').' Baru' }}</span>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/15">{{ self::CATEGORY_LABELS[$category] }}</span>
                    @if ($editingId)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->subStateBadgeClass($flow_status) }}">{{ $flow_status }}</span>
                    @endif
                    <button type="button" wire:click="backToGrid" title="Kembali ke Daftar"
                        class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full cursor-pointer hover:brightness-110">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
                    </button>
                </div>

                <div class="p-4 flex flex-col gap-3">
                    @if ($readOnly && $flow_status !== 'CANCELLED')
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">🔒 Dokumen sedang dalam proses approval / sudah diposting — lihat-saja.</div>
                    @elseif ($flow_status === 'CANCELLED')
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f]">🔒 Dokumen dibatalkan — lihat-saja.</div>
                    @endif
                    @if ($flow_status === 'REVISION_NEEDED')
                        <div class="text-xs px-3 py-2.5 rounded-md bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0]">↩ Dokumen ditolak & dikembalikan utk revisi — perbaiki lalu ajukan ulang.</div>
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
                        <div class="flex-1 min-w-[160px] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Jenis *</label>
                            <select wire:model="doc_kind" @disabled($readOnly || $editingId) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="AD">Adjustment (koreksi tunggal)</option>
                                <option value="OP">Stock Opname (hitung fisik terjadwal)</option>
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
                        <div class="flex-1 min-w-[180px] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Alasan *</label>
                            <select wire:model="reason_code" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                <option value="COUNT_ERROR">Salah hitung/input sebelumnya</option>
                                <option value="DAMAGED">Rusak</option>
                                <option value="LOST">Hilang</option>
                                <option value="FOUND">Ditemukan (lebih dari catatan)</option>
                                <option value="EXPIRED">Kadaluarsa</option>
                                <option value="OTHER">Lainnya</option>
                            </select>
                            @error('reason_code') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 min-w-[160px] flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Diajukan oleh</label>
                            <input type="text" value="{{ Auth::user()->name }}" disabled class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] bg-[#f4f6fa] text-muted">
                        </div>
                    </div>

                    @if ($doc_kind === 'OP')
                        <div class="border border-[#eef1f5] rounded-md p-3 bg-[#fafbfd]">
                            <div class="text-[10.5px] font-bold text-muted uppercase mb-2">Blok Opname</div>
                            <div class="flex gap-2.5 flex-wrap">
                                <div class="flex-1 min-w-[180px] flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-[#556]">Petugas Opname *</label>
                                    <input type="text" wire:model="opn_officer" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                </div>
                                <div class="flex-1 min-w-[180px] flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-[#556]">Mulai Hitung *</label>
                                    <input type="datetime-local" wire:model="opn_start" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                </div>
                                <div class="flex-1 min-w-[180px] flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-[#556]">Selesai Hitung *</label>
                                    <input type="datetime-local" wire:model="opn_stop" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                </div>
                                <div class="flex-1 min-w-[180px] flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-[#556]">Saksi *</label>
                                    <input type="text" wire:model="opn_witness" @disabled($readOnly) class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                </div>
                                <div class="flex items-end pb-2">
                                    <label class="flex items-center gap-1.5 text-xs font-semibold text-[#556]">
                                        <input type="checkbox" wire:model="full_scope" @disabled($readOnly)> Cakupan Penuh (semua item bersaldo)
                                    </label>
                                </div>
                            </div>
                            @error('opn_officer') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Catatan / Kronologi</label>
                        <textarea wire:model="notes" @disabled($readOnly) rows="2" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]"></textarea>
                    </div>

                    <div class="text-xs font-bold text-navy uppercase tracking-wide mt-1">Baris Item</div>
                    @if (! $readOnly)
                        <div class="flex items-center gap-2 flex-wrap">
                            <div class="relative flex-1" style="max-width:380px">
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
                            @if ($doc_kind === 'OP' && $full_scope)
                                <button type="button" wire:click="pullFullScope" wire:confirm="Tarik semua item bersaldo di gudang ini? Baris yang sudah diisi akan ditimpa." class="text-[11px] font-bold text-white bg-accent rounded px-3 py-1.5 cursor-pointer">⤓ Tarik Semua Item Bersaldo</button>
                            @endif
                        </div>
                    @endif
                    @error('lines') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror

                    <div class="overflow-x-auto border border-[#eef1f5] rounded-md">
                        <table class="w-full text-[12.5px] border-collapse">
                            <thead>
                                <tr class="bg-[#fafbfd]">
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5]">Item</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[160px]">Lokasi *</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[100px]">Saldo Sistem</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[100px]">Hitung Fisik *</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[90px]">Selisih</th>
                                    <th class="text-right px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5] w-[110px]">Nilai Selisih</th>
                                    <th class="text-left px-2 py-1.5 text-[10.5px] text-muted uppercase border-b border-[#eef1f5]">Alasan Baris</th>
                                    @if (! $readOnly)
                                        <th class="w-[40px] border-b border-[#eef1f5]"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lines as $i => $line)
                                    @php $selisih = (float) ($line['qty_physical'] !== '' ? $line['qty_physical'] : 0) - (float) $line['qty_system']; @endphp
                                    <tr wire:key="line-{{ $i }}" class="border-b border-[#eef1f5]">
                                        <td class="px-2 py-1.5">{{ $line['name'] }}</td>
                                        <td class="px-2 py-1.5">
                                            <select wire:change="setLineLocation({{ $i }}, $event.target.value)" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs">
                                                <option value="">— pilih —</option>
                                                @foreach ($this->selectedWarehouseLocations as $loc)
                                                    <option value="{{ $loc->location_id }}" @selected($line['location_id'] == $loc->location_id)>{{ $loc->location_code }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-2 py-1.5 text-right font-mono text-muted">{{ rtrim(rtrim(number_format($line['qty_system'], 2, ',', '.'), '0'), ',') }}</td>
                                        <td class="px-2 py-1.5"><input type="text" wire:model="lines.{{ $i }}.qty_physical" @disabled($readOnly) class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right"></td>
                                        <td class="px-2 py-1.5 text-right font-mono font-semibold {{ $selisih > 0 ? 'text-[#16a34a]' : ($selisih < 0 ? 'text-[#c0392b]' : 'text-muted') }}">{{ $selisih > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($selisih, 2, ',', '.'), '0'), ',') }}</td>
                                        <td class="px-2 py-1.5 text-right font-mono text-muted">{{ number_format(abs($selisih * $line['unit_price']), 0, ',', '.') }}</td>
                                        <td class="px-2 py-1.5"><input type="text" wire:model="lines.{{ $i }}.note_detail" @disabled($readOnly) placeholder="{{ abs($selisih) > 0.0001 ? 'wajib' : 'opsional' }}" class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs"></td>
                                        @if (! $readOnly)
                                            <td class="px-2 py-1.5 text-center"><button type="button" wire:click="removeLine({{ $i }})" class="text-[#c0392b] cursor-pointer" title="Hapus baris">✕</button></td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center py-6 text-muted text-xs">Belum ada baris — cari item di atas utk ditambahkan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($this->approvalHistory->isNotEmpty())
                        <div class="border border-[#eef1f5] rounded-xl p-4 bg-white">
                            <p class="text-[10.5px] font-bold text-muted uppercase mb-3">Riwayat Approval</p>
                            <div class="relative pl-5 border-l-2 border-[#eef1f5] ml-1 space-y-3 text-sm">
                                @foreach ($this->approvalHistory as $h)
                                    <div class="relative">
                                        <div class="absolute -left-[26px] top-0.5 w-3 h-3 rounded-full border-2 {{ $h->action === 'REJECTED' ? 'bg-[#fbeaea] border-[#a3241f]' : 'bg-[#eaf7ee] border-[#1f8a4c]' }}"></div>
                                        <p class="font-semibold text-xs">{{ $h->action }}{{ $h->status_to ? ' → '.$h->status_to : '' }}</p>
                                        <p class="text-[10.5px] text-muted">Oleh: {{ $h->created_by ?? 'sistem' }} · {{ $h->acted_at?->translatedFormat('d M Y, H:i') }}{{ $h->is_acting ? ' (PLT)' : '' }}</p>
                                        @if ($h->notes)
                                            <p class="text-[10.5px] {{ $h->action === 'REJECTED' ? 'text-[#a3241f]' : 'text-muted' }} mt-0.5">"{{ $h->notes }}"</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (in_array($flow_status, self::EDITABLE_STATUSES, true) && ! $readOnly)
                        <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                            @if ($editingId)
                                <button type="button" wire:click="cancelDoc" wire:confirm="Batalkan dokumen ini?" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#fdecec] text-[#c0392b] cursor-pointer">Batalkan</button>
                            @endif
                            <button type="button" wire:click="saveDraft" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">💾 Simpan Draft</button>
                            @if ($editingId)
                                <button type="button" wire:click="submitForApproval" wire:confirm="Ajukan dokumen ini utk approval? Baris tak bisa diedit lagi selama proses approval." class="px-4 py-2 rounded-md text-xs font-semibold bg-navy text-white cursor-pointer">Ajukan Persetujuan</button>
                            @endif
                        </div>
                    @elseif ($this->pendingApproval)
                        <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white">
                            <p class="text-xs font-bold text-navy mb-2">Keputusan Approval — {{ $this->pendingApproval->pending_label ?? 'Menunggu keputusan Anda' }}</p>
                            <p class="text-[11px] text-muted mb-2">Nilai absolut dokumen: <b>{{ number_format((float) $this->pendingApproval->amount_snapshot, 0, ',', '.') }}</b></p>
                            @if ($approvalError)
                                <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $approvalError }}</p>
                            @endif
                            <div class="mb-3">
                                <label class="text-xs text-muted">Alasan Penolakan <span class="text-[#a3241f]">(wajib, min. 10 karakter)</span></label>
                                <textarea wire:model="approvalNote" rows="2" placeholder="Wajib diisi bila menolak..." class="w-full border border-[#d7dbe2] rounded-lg px-3 py-2 mt-1 text-sm"></textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="rejectDoc" wire:confirm="Tolak dokumen ini? Akan dikembalikan ke pembuat utk revisi." class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ Tolak</button>
                                <button type="button" wire:click="approveDoc" wire:confirm="Setujui dokumen ini?" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ Setujui</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
