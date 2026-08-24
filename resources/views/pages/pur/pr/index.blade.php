<?php

use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrRequisition;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// GRID_UI_STANDARD.md diikuti (2026-08-22). PR belum punya supplier (baru
// dipilih di tahap PO), jadi kolom identitas pakai Departemen peminta -- sama
// peran dgn "nama customer" di grid Quotation/CO. "+ New Record" tetap ada
// karena PR memang dibuat dari grid ini sendiri (picker departemen).
new #[Title('Purchase Requisition — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $pickerOpen = false;

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('is_active', true)->orderBy('department_name')->get();
    }

    // grid_performance (GLOBAL_RULES.yaml): server-side pagination + WHERE.
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
            ->orderByDesc('pr_id')
            ->paginate(20);
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

    // GRID_UI_STANDARD.md #3 -- avatar inisial+warna utk kolom identitas.
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
        <div class="grid gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total PR</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Menunggu Approval</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statSubmitted }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Disetujui / Diproses</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statProgress }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
                <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">Ditolak / Revisi</p>
                <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ $this->statRejected }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. PR / departemen / item…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="flex-1"></span>
                <button type="button" wire:click="openPicker"
                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Departemen / No. PR</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Sumber</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Dibutuhkan</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">Est. Nilai</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->requisitions as $pr)
                            <tr wire:key="pr-{{ $pr->pr_id }}" wire:dblclick="openPr({{ $pr->pr_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($pr->pr_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($pr->department?->department_name ?: $pr->doc_no ?: 'PR') }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $pr->department?->department_name ?? '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $pr->doc_no ?: '(draft)' }}</div>
                                        </div>
                                    </div>
                                </td>
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
                                    <button type="button" wire:click.stop="openPr({{ $pr->pr_id }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-8 text-muted text-sm">Belum ada Purchase Requisition.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-3.5 py-2.5 border-t border-[#eef1f5]">
                {{ $this->requisitions->links() }}
            </div>
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[420px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">PR Baru — Pilih Departemen Peminta</h3>
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
