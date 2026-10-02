<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\HasGridPagination;
use App\Models\Pur\PoOrder;
use App\Services\Pur\PoBuilderService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md diikuti (2026-08-22). "+ New Record" tetap ada karena
// PO memang dibuat dari grid ini sendiri (picker supplier).
new #[Title('Purchase Order — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    public bool $pickerOpen = false;

    public string $supplierSearch = '';

    // grid_performance (globalrules.yaml): server-side pagination + WHERE.
    #[Computed]
    public function orders()
    {
        return PoOrder::when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('doc_no', 'like', "%{$term}%")
                        ->orWhereHas('lines', fn ($l) => $l->where('i_description', 'like', "%{$term}%"));
                });
            })
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderByDesc('po_id')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = $this->orders->pluck('i_supplier_id')->filter()->unique();

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function supplierResults()
    {
        if (mb_strlen(trim($this->supplierSearch)) < 2) {
            return collect();
        }

        return DB::connection('tm2mst')->table('sup_supplier')
            ->where('activate', true)
            ->where(function ($q) {
                $q->where('supplier_name', 'like', "%{$this->supplierSearch}%")
                    ->orWhere('supplier_code', 'like', "%{$this->supplierSearch}%");
            })
            ->orderBy('supplier_name')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return PoOrder::count();
    }

    #[Computed]
    public function statSubmitted(): int
    {
        return PoOrder::where('status', 'SUBMITTED')->count();
    }

    #[Computed]
    public function statProgress(): int
    {
        return PoOrder::whereIn('status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED'])->count();
    }

    #[Computed]
    public function statRejected(): int
    {
        return PoOrder::whereIn('status', ['REJECTED', 'CANCELLED'])->count();
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
        $this->supplierSearch = '';
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    public function startForSupplier(int $supplierId)
    {
        $po = PoBuilderService::findOrCreateDraft($supplierId);

        return $this->redirect(route('pur.po.entry', ['po' => $po->po_id]));
    }

    public function openPo(int $id)
    {
        return $this->redirect(route('pur.po.entry', ['po' => $id]));
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).

    // Kotak statistik = filter data (griduistandard.md §2b).
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total POs'), 'value' => $this->statTotal, 'tone' => 'total'],
            'submitted' => ['label' => t('Awaiting Approval'), 'value' => $this->statSubmitted, 'tone' => 'warn',
                'filter' => fn ($q) => $q->where('status', 'SUBMITTED')],
            'progress' => ['label' => t('Approved / In Progress'), 'value' => $this->statProgress, 'tone' => 'active',
                'filter' => fn ($q) => $q->whereIn('status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED'])],
            'rejected' => ['label' => t('Rejected / Cancelled'), 'value' => $this->statRejected, 'tone' => 'critical',
                'filter' => fn ($q) => $q->whereIn('status', ['REJECTED', 'CANCELLED'])],
        ];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search PO no. / item…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="openPicker"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Supplier / PO No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Date') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Type') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ t('Total Bill') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->orders as $po)
                            <tr wire:key="po-{{ $po->po_id }}" wire:dblclick="openPo({{ $po->po_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$po->po_id" :name="$this->supplierNames[$po->i_supplier_id] ?? '—'" :code="$po->doc_no ?: t('(draft)')" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $po->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $po->i_po_type }} · {{ $po->i_procure_type }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $po->c_grand_total, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                        {{ match ($po->status) {
                                            'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                            'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
                                            'APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                            'REJECTED', 'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                                            default => 'bg-[#eef1f5] text-[#4b5568]',
                                        } }}">{{ $po->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openPo({{ $po->po_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="{{ t('No Purchase Orders yet.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->orders])
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[480px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">{{ t('New PO — Select Supplier') }}</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="supplierSearch" autofocus placeholder="{{ t('Type at least 2 letters of supplier name/code...') }}" class="mb-2" />
                <div class="max-h-72 overflow-y-auto">
                    @forelse ($this->supplierResults as $s)
                        <button type="button" wire:click="startForSupplier({{ $s->id_supplier }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                            <div class="text-xs font-semibold text-navy">{{ $s->supplier_name }}</div>
                            <div class="text-[10.5px] text-muted">{{ $s->supplier_code }}</div>
                        </button>
                    @empty
                        <div class="text-[11px] text-muted text-center py-4">
                            {{ mb_strlen(trim($supplierSearch)) < 2 ? t('Type at least 2 letters to search.') : t('No matching supplier.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
