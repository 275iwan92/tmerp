<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Worklist "Pro-Dev" (menu SALES_IMPO) -- GRID depan sebelum masuk layar
// kerja Imposition Plano Optimizer (production.prodev), sesuai
// griduistandard.md: grid dulu (ringkasan pekerjaan + status layout), baru
// klik 2x buka detail/engine.
//
// Grid ini sempat HILANG 2026-09-14 saat ProDev diganti ke layar mockup baru
// dan menu diarahkan langsung ke form -- user menegaskan 2026-09-16 bahwa
// grid TIDAK boleh dihilangkan, karena tanpa daftar tidak ada cara memilih
// part mana yang mau direvisi. Isinya sama dengan grid lama (yang kini
// diarsipkan jadi old_prodev_list), cuma tujuan double-click-nya sekarang
// layar ProDev baru.
//
// Murni ↗ Buka (tanpa ✓ Confirm/+ New Record, griduistandard.md#7) --
// confirm layout terjadi DI DALAM layar kerja lewat ENGINE, bukan flip
// status di grid; part baru lahir dari Inquiry "Kirim ke ProDev", bukan
// dari grid ini.
new #[Title('Pro-Dev — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    #[Url]
    public string $search = '';

    public function open(int $id): void
    {
        $this->redirect(route('production.prodev', ['part' => $id]));
    }

    #[Computed]
    public function parts()
    {
        return ProdPart::with(['product', 'layoutGroups'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('part_name', 'like', "%{$this->search}%")
                        ->orWhere('part_code', 'like', "%{$this->search}%")
                        ->orWhere('mastercard_code', 'like', "%{$this->search}%")
                        ->orWhereHas('product', fn ($q2) => $q2->where('product_name', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('id_part')
            ->limit(200)
            ->get();
    }

    // Mode #1 (dari Inquiry BARU) kalau ada minimal 1 iqp_part yang nunjuk balik
    // ke prod_part ini via prod_part_id (sama pola dgn originInquiryParts() di
    // prodev.blade.php) -- selain itu Mode #2 (manual/standalone).
    #[Computed]
    public function originPartIds()
    {
        return IqpPart::whereNotNull('prod_part_id')
            ->distinct()
            ->pluck('prod_part_id')
            ->flip();
    }

    public function isFromInquiry(ProdPart $part): bool
    {
        return $this->originPartIds->has($part->id_part);
    }

    public function layoutStatus(ProdPart $part): string
    {
        $latest = $part->layoutGroups->sortByDesc('id_layout_group')->first();

        if (! $latest) {
            return 'NOT STARTED';
        }

        return $latest->status === 'CONFIRMED' ? 'CONFIRMED' : 'DRAFT';
    }

    public function layoutStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'CONFIRMED' => 'bg-[#e9f9ef] text-[#16a34a]',
            'DRAFT' => 'bg-[#fef6e7] text-[#b45309]',
            default => 'bg-[#eef0f3] text-[#8a8f98]',
        };
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->parts->count();
    }

    #[Computed]
    public function statConfirmed(): int
    {
        return $this->parts->filter(fn ($p) => $this->layoutStatus($p) === 'CONFIRMED')->count();
    }

    #[Computed]
    public function statPending(): int
    {
        return $this->statTotal - $this->statConfirmed;
    }

    // Baris grid SETELAH filter kotak statistik. Sengaja computed terpisah dari
    // parts(): statTotal/statConfirmed tetap menghitung SELURUH part, bukan
    // hasil saringan (griduistandard.md §2b).
    #[Computed]
    public function visibleParts()
    {
        return $this->applyStatFilter($this->parts);
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total Parts'), 'value' => $this->statTotal, 'tone' => 'total'],
            'confirmed' => ['label' => t('Already Confirmed'), 'value' => $this->statConfirmed, 'tone' => 'active', 'subtitle' => t('LAYOUT CONFIRMED'),
                'filter' => fn ($c) => $c->filter(fn ($p) => $this->layoutStatus($p) === 'CONFIRMED')],
            'pending' => ['label' => t('Not Yet Confirmed'), 'value' => $this->statPending, 'tone' => 'warn',
                'filter' => fn ($c) => $c->filter(fn ($p) => $this->layoutStatus($p) !== 'CONFIRMED')],
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
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search part name/code or product…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Part') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[180px]">{{ t('Product') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Origin') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[120px]">{{ t('Layout Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->visibleParts as $p)
                            @php($status = $this->layoutStatus($p))
                            <tr wire:key="pdp-{{ $p->id_part }}" wire:dblclick="open({{ $p->id_part }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open Pro-Dev') }}">
                                <x-grid.identity-cell :id="$p->id_part" :name="$p->part_name" :code="$p->displayMastercardCode()" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $p->product?->product_name ?: '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($this->isFromInquiry($p))
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#eaf2ff] text-[#1d4ed8]">📥 {{ t('Inquiry') }}</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#f1ecfc] text-[#6d28d9]">✎ {{ t('Manual') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->layoutStatusBadgeClass($status) }}">{{ t($status) }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" :title="t('Open Pro-Dev')" action="open({{ $p->id_part }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" :message="t('No Parts yet. Send a part from Inquiry Product (NEW mode) or via the dummy seed.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>
</div>
