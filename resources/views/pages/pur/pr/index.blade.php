<?php

use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrRequisition;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Purchase Requisition — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

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
                        ->orWhereHas('lines', fn ($l) => $l->where('i_description', 'like', "%{$term}%"));
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('pr_id')
            ->paginate(20);
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
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🧾 Purchase Requisition</b>
        <span class="text-xs text-white/75">Permintaan pembelian: hasil MRP (auto) maupun manual (ad-hoc)</span>
        <a href="{{ route('menu') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Menu</a>
    </header>

    <div class="max-w-[1280px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2] flex-wrap">
                <div class="flex items-center gap-2">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. PR / item..." class="w-64 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <select wire:model.live="statusFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        <option value="">Semua Status</option>
                        <option value="DRAFT">Draft</option>
                        <option value="SUBMITTED">Menunggu Approval</option>
                        <option value="APPROVED">Disetujui</option>
                        <option value="REJECTED">Ditolak</option>
                        <option value="REVISION_NEEDED">Perlu Revisi</option>
                        <option value="PARTIALLY_ORDERED">Sebagian jadi PO</option>
                        <option value="FULLY_ORDERED">Sudah jadi PO</option>
                        <option value="CLOSED">Selesai</option>
                    </select>
                </div>
                <button type="button" wire:click="openPicker" class="text-[11px] font-bold text-white bg-accent rounded px-3 py-1.5 cursor-pointer">＋ PR Baru (Manual)</button>
            </div>

            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f4f5f8]">
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">No. PR</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tanggal</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Sumber</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Departemen</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Dibutuhkan</th>
                        <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Est. Nilai</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Status</th>
                        <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->requisitions as $pr)
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe]" wire:key="pr-{{ $pr->pr_id }}">
                            <td class="px-3 py-2 font-mono font-bold text-accent">{{ $pr->doc_no ?: '(draft)' }}</td>
                            <td class="px-3 py-2">{{ $pr->i_doc_date?->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded {{ $pr->i_source === 'MRP' ? 'bg-[#eef3fb] text-[#2f6fed]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $pr->i_source === 'MRP' ? 'MRP AUTO' : 'MANUAL' }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $pr->department?->department_name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $pr->i_need_date?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $pr->c_est_total, 0, ',', '.') }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                    {{ match ($pr->status) {
                                        'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                        'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
                                        'APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED', 'CLOSED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                        'REJECTED', 'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                                        default => 'bg-[#eef1f5] text-[#4b5568]',
                                    } }}">{{ $pr->status }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('pur.pr.entry', ['pr' => $pr->pr_id]) }}" class="text-accent font-semibold no-underline">Buka →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-8 text-muted">Belum ada Purchase Requisition.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
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
