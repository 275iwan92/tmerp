<?php

use App\Models\Sdi\CoOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// GRID_UI_STANDARD.md diikuti (2026-08-22, diperluas ke grid worklist/transaksi).
// Tidak ada "+ New Record" -- CO lahir dari Quotation yang sudah PUBLISHED
// (tombol "Buat Customer Order" di layar Quotation), bukan dibuat dari grid ini.
// Kolom Aksi non-master: bukan Edit+Toggle-aktif (§5), tapi ↗ Buka + ✓ Confirm
// (DRAFT->CONFIRMED langsung dari grid, sesuai instruksi user 2026-08-22).
new #[Title('Daftar Customer Order — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    // Master-detail expand-row (2026-08-24, instruksi user): klik chevron buka
    // preview item CO tanpa pindah halaman -- meniru ScriptCase report lama
    // (row-expand) + pola Odoo/SAP/Dynamics (list-detail inline). $expanded
    // simpan id_order yang lagi kebuka, bukan single value, krn user boleh
    // expand beberapa baris sekaligus.
    public array $expanded = [];

    public function toggleExpand(int $id): void
    {
        if (in_array($id, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$id]));

            return;
        }

        $this->expanded[] = $id;
    }

    // grid_performance (GLOBAL_RULES.yaml): server-side pagination + WHERE.
    #[Computed]
    public function orders()
    {
        return CoOrder::with(['quotation.customer', 'items' => fn ($q) => $q->orderBy('line_seq')])
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('co_no', 'like', "%{$term}%")
                        ->orWhereHas('quotation.customer', fn ($c) => $c->where('customer_name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('id_order')
            ->paginate(20);
    }

    #[Computed]
    public function grandTotals(): array
    {
        $ids = $this->orders->pluck('id_order');
        $orders = CoOrder::with('items', 'externalCosts')->whereIn('id_order', $ids)->get();

        return $orders->mapWithKeys(fn ($o) => [$o->id_order => $o->totals()['net_receivable']])->all();
    }

    #[Computed]
    public function statTotal(): int
    {
        return CoOrder::count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return CoOrder::where('doc_status', 'DRAFT')->count();
    }

    #[Computed]
    public function statConfirmed(): int
    {
        return CoOrder::where('doc_status', 'CONFIRMED')->count();
    }

    public function openOrder(int $id)
    {
        return $this->redirect(route('co.entry', ['order' => $id]));
    }

    // Bukan master data -- tidak ada konsep aktif/nonaktif, jadi kolom Aksi
    // ganti "toggle" jadi CONFIRM (DRAFT -> CONFIRMED) langsung dari grid.
    public function confirmOrder(int $id): void
    {
        $o = CoOrder::with('items')->findOrFail($id);
        if ($o->doc_status !== 'DRAFT' || $o->items->isEmpty()) {
            return;
        }

        $o->doc_status = 'CONFIRMED';
        $o->updated_by = Auth::user()->login;
        $o->save();

        unset($this->orders);
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
        <div class="grid grid-cols-3 gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Customer Order</p>
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

        <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fef6e7] border border-[#f5e0b3] text-[#8a4b00]">
            CO dibuat dari Quotation yang sudah <b>PUBLISHED</b> — buka Quotation, klik "Buat Customer Order". Tidak ada "+ New Record" di sini.
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. CO / customer…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1] w-[32px]"></th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Customer Order</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">Ref. Quotation</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Net Receivable</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->orders as $o)
                            @php($isOpen = in_array($o->id_order, $this->expanded, true))
                            <tr wire:key="co-{{ $o->id_order }}" wire:dblclick="openOrder({{ $o->id_order }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-2 py-2 border-b border-[#eef1f5] text-center">
                                    <button type="button" wire:click.stop="toggleExpand({{ $o->id_order }})" title="{{ $isOpen ? 'Tutup preview item' : 'Lihat item' }}"
                                        class="inline-flex items-center justify-center w-6 h-6 rounded text-muted hover:bg-[#eef3fb] hover:text-accent cursor-pointer">{{ $isOpen ? '▾' : '▸' }}</button>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($o->id_order) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($o->quotation?->customer?->customer_name ?: $o->co_no) }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $o->quotation?->customer?->customer_name ?? '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $o->co_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-mono text-muted">{{ $o->quotation?->quotation_no }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $o->co_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $o->doc_status === 'CONFIRMED' ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#fef6e7] text-[#b45309]' }}">{{ $o->doc_status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format($this->grandTotals[$o->id_order] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openOrder({{ $o->id_order }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                    @if ($o->doc_status === 'DRAFT')
                                        <button type="button" wire:click.stop="confirmOrder({{ $o->id_order }})" wire:confirm="Confirm CO {{ $o->co_no }}?" title="Confirm" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[#16a34a] hover:bg-[#e9f9ef] cursor-pointer">✓</button>
                                    @endif
                                </td>
                            </tr>
                            @if ($isOpen)
                                <tr wire:key="co-detail-{{ $o->id_order }}" class="bg-[#fafbfd]">
                                    <td colspan="7" class="px-3 py-2.5 border-b border-[#eef1f5]">
                                        <table class="w-full text-[11.5px] border-collapse ml-8" style="width:calc(100% - 2rem)">
                                            <thead>
                                                <tr class="text-[10px] text-muted uppercase font-bold">
                                                    <th class="text-left px-2 py-1">Produk / Jasa</th>
                                                    <th class="text-center px-2 py-1 w-14">Type</th>
                                                    <th class="text-right px-2 py-1 w-28">Qty</th>
                                                    <th class="text-right px-2 py-1 w-24">Harga</th>
                                                    <th class="text-right px-2 py-1 w-16">Disc%</th>
                                                    <th class="text-right px-2 py-1 w-28">Subtotal</th>
                                                    <th class="text-center px-2 py-1 w-24">Due Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($o->items as $item)
                                                    <tr class="border-t border-[#eef1f5]" wire:key="co-detail-item-{{ $item->id_item }}">
                                                        <td class="px-2 py-1.5">{{ $item->description }}</td>
                                                        <td class="px-2 py-1.5 text-center">
                                                            <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded {{ $item->line_kind === 'PRODUCT' ? 'bg-[#dbeafe] text-[#1e40af]' : 'bg-[#ede9fe] text-[#5b21b6]' }}">{{ $item->line_kind === 'PRODUCT' ? 'P' : 'S' }}</span>
                                                        </td>
                                                        <td class="px-2 py-1.5 text-right">{{ number_format($item->qty, 0, ',', '.') }} {{ $item->qty_unit }}</td>
                                                        <td class="px-2 py-1.5 text-right">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                                        <td class="px-2 py-1.5 text-right">{{ number_format($item->discount_pct, 1) }}%</td>
                                                        <td class="px-2 py-1.5 text-right font-semibold">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                                        <td class="px-2 py-1.5 text-center text-muted">{{ $item->due_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="7" class="text-center py-3 text-muted text-[11px]">Belum ada item.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="7" class="text-center py-8 text-muted text-sm">Belum ada Customer Order.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-3.5 py-2.5 border-t border-[#eef1f5]">
                {{ $this->orders->links() }}
            </div>
        </div>
    </div>
</div>
