<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sdi\QuoQuotationItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md diikuti (2026-08-22, diperluas ke grid worklist/transaksi).
// Grid transaksi TETAP boleh punya "+ New Record" (langsung membuka form baru;
// customer dipilih di form, instruksi user 2026-09-26) -- beda
// dari Imposition/Costing worklist yang recordnya lahir di layar lain.
// Kolom Aksi non-master: bukan Edit+Toggle-aktif (§5), tapi ↗ Buka + ✓ Confirm
// (DRAFT->PUBLISHED langsung dari grid, sesuai instruksi user 2026-08-22).
new #[Title('Daftar Quotation — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
    use HasGridStatFilter;
    use WithPagination;

    public string $search = '';

    // grid_performance (globalrules.yaml): server-side pagination + WHERE, bukan load-semua-lalu-filter.
    #[Computed]
    public function quotations()
    {
        return QuoQuotation::with(['customer', 'items.inquiry:id_inquiry,sub_state'])
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('quotation_no', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('customer_name', 'like', "%{$term}%"));
                });
            })
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderByDesc('id_quotation')
            ->paginate($this->perPage);
    }

    public function newQuotation()
    {
        return $this->redirect(route('quotation.create'));
    }

    // Klik 2x / Buka selalu ke form (sama dgn New); yang sudah terbit tampil terkunci + tombol cetak.
    public function openQuotation(int $id)
    {
        return $this->redirect(route('quotation.entry', ['quotation' => $id]));
    }

    // Bukan master data -- tidak ada konsep aktif/nonaktif, jadi kolom Aksi
    // ganti "toggle" jadi CONFIRM (DRAFT -> PUBLISHED) langsung dari grid.
    public function confirmQuotation(int $id): void
    {
        $q = QuoQuotation::with('items')->findOrFail($id);
        if ($q->doc_status !== 'DRAFT' || $q->items->isEmpty()) {
            return;
        }

        $q->doc_status = 'PUBLISHED';
        $q->updated_by = Auth::user()->login;
        $q->save();

        unset($this->quotations);
    }

    #[Computed]
    public function grandTotals(): array
    {
        // Dihitung per baris di halaman ini saja (bukan full-scan semua quotation) --
        // konsisten dgn pagination server-side, hindari N+1 lintas ribuan baris.
        $ids = $this->quotations->pluck('id_quotation');

        return QuoQuotationItem::whereIn('quotation_id', $ids)
            ->selectRaw('quotation_id, SUM(subtotal) as subtotal_sum')
            ->groupBy('quotation_id')
            ->pluck('subtotal_sum', 'quotation_id')
            ->all();
    }

    #[Computed]
    public function statTotal(): int
    {
        return QuoQuotation::count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return QuoQuotation::where('doc_status', 'DRAFT')->count();
    }

    #[Computed]
    public function statPublished(): int
    {
        return QuoQuotation::where('doc_status', 'PUBLISHED')->count();
    }

    // Win/Lost dibaca dari iqp_inquiry.sub_state (sumber yang sama dgn layar
    // Win-Rate Sales). Quotation dihitung Win bila ada inquiry-nya CONVERTED.
    private function wonFilter($q)
    {
        return $q->whereHas('items.inquiry', fn ($i) => $i->where('sub_state', 'CONVERTED'));
    }

    private function lostFilter($q)
    {
        return $q->whereHas('items.inquiry', fn ($i) => $i->where('sub_state', 'LOST'))
            ->whereDoesntHave('items.inquiry', fn ($i) => $i->where('sub_state', '!=', 'LOST'));
    }

    #[Computed]
    public function statWon(): int
    {
        return $this->wonFilter(QuoQuotation::query())->count();
    }

    #[Computed]
    public function statLost(): int
    {
        return $this->lostFilter(QuoQuotation::query())->count();
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).

    // Kotak statistik = filter data (griduistandard.md §2b). "Total Quotation"
    // sama dengan tanpa filter -> sengaja tidak bisa diklik.
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total Quotations'), 'value' => $this->statTotal, 'tone' => 'total'],
            'draft' => ['label' => t('Draft'), 'value' => $this->statDraft, 'tone' => 'warn',
                'filter' => fn ($q) => $q->where('doc_status', 'DRAFT')],
            'published' => ['label' => t('Published'), 'value' => $this->statPublished, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('doc_status', 'PUBLISHED')],
            'won' => ['label' => t('Win'), 'value' => $this->statWon, 'tone' => 'extra',
                'filter' => fn ($q) => $this->wonFilter($q)],
            'lost' => ['label' => t('Lost'), 'value' => $this->statLost, 'tone' => 'critical',
                'filter' => fn ($q) => $this->lostFilter($q)],
            'winrate' => ['label' => t('Win-Rate'),
                'value' => ($this->statWon + $this->statLost) ? round($this->statWon / ($this->statWon + $this->statLost) * 100, 1).'%' : '—'],
        ];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="bg-white border border-field-border rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search quotation no. / customer…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-field-border rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="newQuotation"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1]">{{ t('Quotation') }}</th>
                            <th class="text-left px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1] w-[110px]">{{ t('Date') }}</th>
                            <th class="text-left px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1] w-[150px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1] w-[140px]">{{ t('Value (excl. VAT)') }}</th>
                            <th class="text-right px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1] w-[80px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->quotations as $q)
                            <tr wire:key="quo-{{ $q->id_quotation }}" wire:dblclick="openQuotation({{ $q->id_quotation }})" class="hover:bg-accent-soft cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$q->id_quotation" :name="$q->customer?->customer_name ?? '—'" :code="$q->quotation_no" />
                                <td class="px-3 py-2 border-b border-field-badge">{{ $q->issued_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-field-badge">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $q->doc_status === 'PUBLISHED' ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#fef6e7] text-[#b45309]' }}">{{ $q->doc_status }}</span>
                                    @if ($outcome = $q->outcome())
                                        <x-ui.badge :tone="$outcome === 'WON' ? 'success' : 'danger'" size="xs" pill>{{ $outcome === 'WON' ? t('WIN') : t('LOST') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-field-badge text-right font-semibold">{{ number_format($this->grandTotals[$q->id_quotation] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-field-badge text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openQuotation({{ $q->id_quotation }})" />
                                    @if ($q->doc_status === 'DRAFT')
                                        <x-grid.action-icon icon="fa-check" title="{{ t('Confirm / Publish') }}" action="confirmQuotation({{ $q->id_quotation }})"
                                            confirm="{{ t('Publish quotation') }} {{ $q->quotation_no }}? {{ t('The price will be locked.') }}" variant="success" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="{{ t('No quotations yet.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->quotations])
        </div>
    </div>
</div>
