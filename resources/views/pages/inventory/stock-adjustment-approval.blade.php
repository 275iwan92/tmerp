<?php

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Services\Flow\FlowEngineService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// inventory_tier4.md §4.3 -- route TERPISAH dari layar entri (inventory_rules.yaml
// layar_terpisah), sama pola dgn PR/PO Approval Center: murni ANTRIAN (grid),
// klik 2x buka dokumen ASLI (stock-adjustment.blade.php, {doc} deep-link) --
// Approve/Reject/riwayat ada di sana, BUKAN duplikat di sini. 1 antrian ini
// menggabung modul AD + OP (1 "Persetujuan" per kelas stok mencakup kedua jenis).
new #[Title('Adjustment & Stock Count Approval — TELASIH MAKMUR ERP')] class extends Component
{
    public const CATEGORY_LABELS = ['RM' => 'Raw & Supporting Materials', 'SFG' => 'Semi-Finished Goods', 'FG' => 'Finished Goods'];

    private const HEADER_MODEL = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class];

    public string $category;

    public string $search = '';

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
    public function pendingQueue()
    {
        $flow = app(FlowEngineService::class);

        return $flow->pendingForUser('AD', Auth::id())->concat($flow->pendingForUser('OP', Auth::id()));
    }

    #[Computed]
    public function documents(): array
    {
        $ids = $this->pendingQueue->pluck('doc_id')->unique();

        return $this->headerModel()::whereIn('doc_id', $ids)->get()->keyBy('doc_id')->all();
    }

    #[Computed]
    public function rows()
    {
        $docs = $this->documents;

        return $this->pendingQueue
            ->map(fn ($p) => (object) ['pending' => $p, 'doc' => $docs[$p->doc_id] ?? null])
            ->filter(fn ($r) => $r->doc !== null)
            ->when($this->search !== '', function ($rows) {
                $term = mb_strtolower($this->search);

                return $rows->filter(fn ($r) => str_contains(mb_strtolower($r->doc->doc_no ?? ''), $term)
                    || str_contains(mb_strtolower($r->doc->reason_code ?? ''), $term));
            })
            ->sortByDesc(fn ($r) => $r->pending->acted_at)
            ->values();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->pendingQueue->count();
    }

    #[Computed]
    public function statValue(): float
    {
        return (float) $this->pendingQueue->sum('amount_snapshot');
    }

    public function openDoc(int $id)
    {
        return $this->redirect(route('inv.stock-adj.index', ['category' => $this->category, 'doc' => $id]));
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="2">
            <x-grid.stat-box tone="total" :label="t('Total in My Queue')" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" :label="t('Total Absolute Value')" :value="number_format($this->statValue, 0, ',', '.')" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search document no. / reason…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-[#eef1f5] text-[#4b5568]">{{ t(self::CATEGORY_LABELS[$category]) }}</span>
                </x-slot:tools>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Document') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ t('Type') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Submitted') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">{{ t('Approval Position') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ t('Absolute Value') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="adjq-{{ $r->doc->doc_id }}" wire:dblclick="openDoc({{ $r->doc->doc_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$r->doc->doc_id" :name="$r->doc->doc_no" :code="$r->doc->reason_code ?: '—'" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->doc->doc_cat === 'OP' ? t('Opname') : t('Adjustment') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pending->acted_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $r->pending->pending_label ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $r->pending->amount_snapshot, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fff5e0] text-[#8a5a00]">{{ mb_strtoupper(t('Waiting')) }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openDoc({{ $r->doc->doc_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="{{ t('No documents awaiting your approval right now.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
