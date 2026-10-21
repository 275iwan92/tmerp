<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Sdi\IqpCosting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Worklist "Product Costing — Mode Instant" (keputusan user 2026-09-18): grid
// BERDIRI SENDIRI, terpisah dari inquiry.costing.list (mode Integrated) --
// lihat switch kecil di toolbar kedua grid. Baris di sini = iqp_costing yang
// TIDAK terhubung inquiry (mode=INSTANT, inquiry_id NULL) -- kalkulator HPP
// standalone. "+ New Record" bikin baris kosong lalu langsung buka form
// (beda dari grid Integrated yang gerbangnya selalu dari Inquiry yang sudah
// ada). griduistandard.md diikuti sama seperti costing-list.blade.php.
new #[Title('Product Costing (Instant) — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    #[Url]
    public string $search = '';

    public function newInstant()
    {
        $costing = IqpCosting::create([
            'mode' => IqpCosting::MODE_INSTANT,
            'calc_type' => 'NEW',
            'doc_status' => 'DRAFT',
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        return redirect()->route('inquiry.costing.instant', ['costing' => $costing->id_costing]);
    }

    public function open(int $id)
    {
        return redirect()->route('inquiry.costing.instant', ['costing' => $id]);
    }

    public function docStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            'CALCULATED' => 'bg-[#eaf2ff] text-[#1d4ed8]',
            'PENDING_APPROVAL' => 'bg-[#fef6e7] text-[#b45309]',
            'PUBLISHED' => 'bg-[#e9f9ef] text-[#16a34a]',
            'FAILED' => 'bg-[#fdecec] text-[#c0392b]',
            default => 'bg-[#eef0f3] text-[#8a8f98]', // DRAFT
        };
    }

    #[Computed]
    public function rows()
    {
        return IqpCosting::query()
            ->with('customer')
            ->where('mode', IqpCosting::MODE_INSTANT)
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('doc_no', 'like', "%{$this->search}%")
                        ->orWhere('product_name_draft', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('id_costing')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->rows->count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return $this->rows->where('doc_status', 'DRAFT')->count();
    }

    #[Computed]
    public function statCalculated(): int
    {
        return $this->rows->where('doc_status', 'CALCULATED')->count();
    }

    #[Computed]
    public function statReversed(): int
    {
        return $this->rows->whereNotNull('reversed_at')->count();
    }

    // Baris grid SETELAH filter kotak statistik (griduistandard.md §2b).
    #[Computed]
    public function visibleRows()
    {
        return $this->applyStatFilter($this->rows);
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => 'Total Instant', 'value' => $this->statTotal, 'tone' => 'total'],
            'draft' => ['label' => 'Draft', 'value' => $this->statDraft, 'tone' => 'warn',
                'filter' => fn ($c) => $c->where('doc_status', 'DRAFT')],
            'calculated' => ['label' => 'Sudah Dihitung', 'value' => $this->statCalculated, 'tone' => 'extra',
                'filter' => fn ($c) => $c->where('doc_status', 'CALCULATED')],
            'reversed' => ['label' => 'Sudah Direverse', 'value' => $this->statReversed, 'tone' => 'active', 'subtitle' => 'JADI MODE INTEGRATED',
                'filter' => fn ($c) => $c->whereNotNull('reversed_at')],
        ];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#eef3fb] border border-[#c3d8f5] text-[#1560a8]">
            🧮 Mode Instant — kalkulator HPP standalone, tanpa Inquiry Product. Sudah pasti angkanya cocok? Pakai <b>Reverse</b> di dalam layar untuk bikin Inquiry+ProDev otomatis dan pindah ke mode Integrated.
        </div>

        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. dokumen / nama produk…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    <div class="flex items-center gap-1 pr-2.5 mr-1 border-r border-[#e2e7ee] shrink-0">
                        <a href="{{ route('inquiry.costing.list') }}" wire:navigate title="Costing yang terhubung ke Inquiry Product"
                            class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold bg-white border border-[#d7dbe2]">🔗 Integrated</a>
                        <a href="{{ route('inquiry.costing.instant.list') }}" wire:navigate title="Kalkulator HPP standalone, tanpa Inquiry"
                            class="px-2.5 py-1.5 rounded-md text-[11px] font-semibold bg-[#2c3e69] text-white">🧮 Instant</a>
                    </div>
                </x-slot:tools>
                <x-slot:newRecord>
                    <button type="button" wire:click="newInstant"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Dokumen</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[170px]">Produk</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Status</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Reverse?</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->visibleRows as $row)
                            <tr wire:key="cost-instant-{{ $row->id_costing }}" wire:dblclick="open({{ $row->id_costing }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <x-grid.identity-cell :id="$row->id_costing" :name="$row->customer?->customer_name ?? '—'" :code="$row->doc_no ?: 'Draft #'.$row->id_costing" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $row->product_name_draft ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->docStatusBadgeClass($row->doc_status) }}">{{ $row->doc_status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($row->reversed_at)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#e9f9ef] text-[#16a34a]">✓ {{ $row->reversed_at->format('d/m/y') }}</span>
                                    @else
                                        <span class="text-[10px] text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="Buka" action="open({{ $row->id_costing }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="Belum ada kalkulasi Instant. Klik &quot;+ New Record&quot; untuk mulai." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
