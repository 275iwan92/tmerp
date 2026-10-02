<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\HasGridPagination;
use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrRequisition;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md diikuti (2026-08-22). PR belum punya supplier (baru
// dipilih di tahap PO), jadi kolom identitas pakai Departemen peminta -- sama
// peran dgn "nama customer" di grid Quotation/CO. "+ New Record" tetap ada
// karena PR memang dibuat dari grid ini sendiri (picker departemen).
new #[Title('Purchase Requisition — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    public bool $pickerOpen = false;

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->get();
    }

    // grid_performance (globalrules.yaml): server-side pagination + WHERE.
    #[Computed]
    public function requisitions()
    {
        return PrRequisition::with('department')
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('doc_no', 'like', "%{$term}%")
                        ->orWhereHas('lines', fn ($l) => $l->where('i_description', 'like', "%{$term}%"))
                        ->orWhereHas('department', fn ($d) => $d->where('department_name', 'like', "%{$term}%"));
                });
            })
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderByDesc('pr_id')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function statTotal(): int
    {
        return PrRequisition::count();
    }

    #[Computed]
    public function statSubmitted(): int
    {
        return PrRequisition::where('status', 'SUBMITTED')->count();
    }

    #[Computed]
    public function statProgress(): int
    {
        return PrRequisition::whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED', 'CLOSED'])->count();
    }

    #[Computed]
    public function statRejected(): int
    {
        return PrRequisition::whereIn('status', ['REJECTED', 'REVISION_NEEDED'])->count();
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    // i_department_id NOT NULL (PROC_TIER_3 §2) -- pilih dulu departemen
    // sebelum baris dibuat, konsisten dgn pola QuotationBuilder/CoOrderBuilder
    // (pilih customer/quotation dulu sebelum draft dibuat).
    public function createNew(int $departmentId)
    {
        $requisition = PrRequisition::create([
            'i_doc_date' => now()->toDateString(),
            'i_source' => 'MANUAL',
            'i_department_id' => $departmentId,
            'status' => 'DRAFT',
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        return $this->redirect(route('pur.pr.entry', ['pr' => $requisition->pr_id]));
    }

    public function openPr(int $id)
    {
        return $this->redirect(route('pur.pr.entry', ['pr' => $id]));
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).

    // Kotak statistik = filter data (griduistandard.md §2b). Klausa filternya
    // PERSIS sama dgn yang dipakai menghitung angkanya.
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total PRs'), 'value' => $this->statTotal, 'tone' => 'total'],
            'submitted' => ['label' => t('Awaiting Approval'), 'value' => $this->statSubmitted, 'tone' => 'warn',
                'filter' => fn ($q) => $q->where('status', 'SUBMITTED')],
            'progress' => ['label' => t('Approved / In Progress'), 'value' => $this->statProgress, 'tone' => 'active',
                'filter' => fn ($q) => $q->whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED', 'CLOSED'])],
            'rejected' => ['label' => t('Rejected / Revision'), 'value' => $this->statRejected, 'tone' => 'critical',
                'filter' => fn ($q) => $q->whereIn('status', ['REJECTED', 'REVISION_NEEDED'])],
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
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search PR no. / department / item…') }}"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Department / PR No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Date') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ t('Source') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Needed') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Est. Value') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->requisitions as $pr)
                            <tr wire:key="pr-{{ $pr->pr_id }}" wire:dblclick="openPr({{ $pr->pr_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$pr->pr_id" :name="$pr->department?->department_name ?? '—'" :code="$pr->doc_no ?: t('(draft)')" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $pr->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $pr->i_source === 'MRP' ? 'bg-[#eef3fb] text-[#2f6fed]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $pr->i_source === 'MRP' ? 'MRP AUTO' : 'MANUAL' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $pr->i_need_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $pr->c_est_total, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                        {{ match ($pr->status) {
                                            'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                            'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
                                            'APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED', 'CLOSED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                            'REJECTED', 'CANCELLED', 'REVISION_NEEDED' => 'bg-[#fbeaea] text-[#a3241f]',
                                            default => 'bg-[#eef1f5] text-[#4b5568]',
                                        } }}">{{ $pr->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openPr({{ $pr->pr_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="{{ t('No Purchase Requisitions yet.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->requisitions])
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[420px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">{{ t('New PR — Select Requesting Department') }}</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <div class="max-h-72 overflow-y-auto">
                    @foreach ($this->departments as $dept)
                        <button type="button" wire:click="createNew({{ $dept->department_id }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                            <div class="text-xs font-semibold text-navy">{{ $dept->department_name }}</div>
                            <div class="text-[10.5px] text-muted">{{ $dept->department_code }}</div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
