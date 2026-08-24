<?php

use App\Models\Pur\PqQuotation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// GRID_UI_STANDARD.md diikuti. PQ = "kondisional, boleh dilewati" (PROC_TIER_1
// §, tanpa approval berjenjang -- cuma konfirmasi sendiri oleh officer), jadi
// Aksi = ↗ Buka + ✓ Confirm (sama pola Quotation/CO), BUKAN pola Approval
// terpisah spt PR/PO/PRT. "+ New Record" tetap ada, PQ dibuat dari grid ini
// sendiri (picker supplier).
new #[Title('Purchase Quotation — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $pickerOpen = false;

    public string $supplierSearch = '';

    #[Computed]
    public function quotations()
    {
        return PqQuotation::when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('doc_no', 'like', "%{$term}%")
                        ->orWhereHas('lines', fn ($l) => $l->where('i_description', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('pq_id')
            ->paginate(20);
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = $this->quotations->pluck('i_supplier_id')->filter()->unique();

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function supplierResults()
    {
        if (mb_strlen(trim($this->supplierSearch)) < 2) {
            return collect();
        }

        return DB::connection('tm2mst')->table('sup_supplier')
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('supplier_name', 'like', "%{$this->supplierSearch}%")->orWhere('supplier_code', 'like', "%{$this->supplierSearch}%"))
            ->orderBy('supplier_name')->limit(15)->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return PqQuotation::count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return PqQuotation::where('status', 'DRAFT')->count();
    }

    #[Computed]
    public function statConfirmed(): int
    {
        return PqQuotation::where('status', 'CONFIRMED')->count();
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
        $pq = PqQuotation::create([
            'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => $supplierId,
            'i_quotation_valid_until' => now()->addDays(14)->toDateString(),
            'i_fx_rate' => 1,
            'status' => 'DRAFT',
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        return $this->redirect(route('pur.pq.entry', ['pq' => $pq->pq_id]));
    }

    public function openPq(int $id)
    {
        return $this->redirect(route('pur.pq.entry', ['pq' => $id]));
    }

    public function confirmPq(int $id): void
    {
        $pq = PqQuotation::with('lines')->findOrFail($id);
        if ($pq->status !== 'DRAFT' || $pq->lines->isEmpty()) {
            return;
        }

        $pq->status = 'CONFIRMED';
        $pq->updated_by = Auth::user()->login;
        $pq->save();

        unset($this->quotations);
    }

    public function statusBadgeClass(PqQuotation $pq): string
    {
        if ($pq->status === 'CONFIRMED' && $pq->i_quotation_valid_until?->isPast()) {
            return 'bg-[#fdecec] text-[#a3453f]';
        }

        return match ($pq->status) {
            'CONFIRMED' => 'bg-[#e9f9ef] text-[#16a34a]',
            'CANCELLED' => 'bg-[#fdecec] text-[#a3453f]',
            default => 'bg-[#fef6e7] text-[#b45309]',
        };
    }

    public function statusLabel(PqQuotation $pq): string
    {
        if ($pq->status === 'CONFIRMED' && $pq->i_quotation_valid_until?->isPast()) {
            return 'EXPIRED';
        }

        return $pq->status;
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
        <div class="grid three mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total PQ</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Draft</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statDraft }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Confirmed</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statConfirmed }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. PQ / item…"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Supplier / No. PQ</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Berlaku s/d</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Total (IDR)</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->quotations as $pq)
                            <tr wire:key="pq-{{ $pq->pq_id }}" wire:dblclick="openPq({{ $pq->pq_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($pq->pq_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($this->supplierNames[$pq->i_supplier_id] ?? ($pq->doc_no ?: 'PQ')) }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $this->supplierNames[$pq->i_supplier_id] ?? '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $pq->doc_no ?: '(draft)' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $pq->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $pq->i_quotation_valid_until?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $pq->c_total_idr, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($pq) }}">{{ $this->statusLabel($pq) }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openPq({{ $pq->pq_id }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                    @if ($pq->status === 'DRAFT')
                                        <button type="button" wire:click.stop="confirmPq({{ $pq->pq_id }})" wire:confirm="Konfirmasi PQ {{ $pq->doc_no }}? Harga akan terkunci." title="Confirm" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[#16a34a] hover:bg-[#e9f9ef] cursor-pointer">✓</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-8 text-muted text-sm">Belum ada Purchase Quotation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-3.5 py-2.5 border-t border-[#eef1f5]">
                {{ $this->quotations->links() }}
            </div>
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[480px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">PQ Baru — Pilih Supplier</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <input type="text" wire:model.live.debounce.300ms="supplierSearch" autofocus placeholder="Ketik min. 2 huruf nama/kode supplier..." class="w-full px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs mb-2">
                <div class="max-h-72 overflow-y-auto">
                    @forelse ($this->supplierResults as $s)
                        <button type="button" wire:click="startForSupplier({{ $s->id_supplier }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                            <div class="text-xs font-semibold text-navy">{{ $s->supplier_name }}</div>
                            <div class="text-[10.5px] text-muted">{{ $s->supplier_code }}</div>
                        </button>
                    @empty
                        <div class="text-[11px] text-muted text-center py-4">
                            {{ mb_strlen(trim($supplierSearch)) < 2 ? 'Ketik min. 2 huruf untuk cari.' : 'Tidak ada supplier cocok.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
