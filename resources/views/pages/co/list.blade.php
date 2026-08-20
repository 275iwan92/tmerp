<?php

use App\Models\Sdi\CoOrder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Daftar Customer Order — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    // grid_performance (GLOBAL_RULES.yaml): server-side pagination + WHERE.
    #[Computed]
    public function orders()
    {
        return CoOrder::with('quotation.customer')
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
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📦 Daftar Customer Order</b>
        <a href="{{ route('quotation.list') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">→ Daftar Quotation</a>
        <a href="{{ route('inquiry.hub') }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Inquiry Hub</a>
    </header>

    <div class="max-w-[1180px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. CO / customer..." class="w-72 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                <span class="text-[10.5px] text-muted">CO dibuat dari Quotation yang sudah diterbitkan — buka Quotation, klik "Buat Customer Order".</span>
            </div>

            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f4f5f8]">
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">No. CO</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Ref. Quotation</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Customer</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tanggal</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Status</th>
                        <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Net Receivable</th>
                        <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->orders as $o)
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe]" wire:key="co-{{ $o->id_order }}">
                            <td class="px-3 py-2 font-mono font-bold text-accent">{{ $o->co_no }}</td>
                            <td class="px-3 py-2 font-mono text-muted">{{ $o->quotation?->quotation_no }}</td>
                            <td class="px-3 py-2">{{ $o->quotation?->customer?->customer_name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $o->co_date?->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $o->doc_status === 'CONFIRMED' ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $o->doc_status }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($this->grandTotals[$o->id_order] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('co.entry', ['order' => $o->id_order]) }}" class="text-accent font-semibold no-underline">Buka →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-8 text-muted">Belum ada Customer Order.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
                {{ $this->orders->links() }}
            </div>
        </div>
    </div>
</div>
