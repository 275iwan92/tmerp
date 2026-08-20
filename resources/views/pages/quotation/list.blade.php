<?php

use App\Models\Master\CustCustomer;
use App\Models\Sdi\QuoQuotation;
use App\Services\Sales\QuotationBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Daftar Quotation — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $customerSearch = '';

    public bool $pickerOpen = false;

    // grid_performance (GLOBAL_RULES.yaml): server-side pagination + WHERE, bukan load-semua-lalu-filter.
    #[Computed]
    public function quotations()
    {
        return QuoQuotation::with('customer')
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('quotation_no', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('customer_name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('id_quotation')
            ->paginate(20);
    }

    #[Computed]
    public function customerResults()
    {
        if (mb_strlen(trim($this->customerSearch)) < 2) {
            return collect();
        }

        return CustCustomer::where('is_active', true)
            ->where(function ($q) {
                $q->where('customer_name', 'like', "%{$this->customerSearch}%")
                    ->orWhere('customer_code', 'like', "%{$this->customerSearch}%");
            })
            ->orderBy('customer_name')
            ->limit(15)
            ->get();
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
        $this->customerSearch = '';
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    public function startForCustomer(int $customerId)
    {
        $quotation = QuotationBuilder::findOrCreateDraft($customerId);

        return $this->redirect(route('quotation.entry', ['quotation' => $quotation->id_quotation]));
    }

    #[Computed]
    public function grandTotals(): array
    {
        // Dihitung per baris di halaman ini saja (bukan full-scan semua quotation) --
        // konsisten dgn pagination server-side, hindari N+1 lintas ribuan baris.
        $ids = $this->quotations->pluck('id_quotation');

        return \App\Models\Sdi\QuoQuotationItem::whereIn('quotation_id', $ids)
            ->selectRaw('quotation_id, SUM(subtotal) as subtotal_sum')
            ->groupBy('quotation_id')
            ->pluck('subtotal_sum', 'quotation_id')
            ->all();
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📄 Daftar Quotation</b>
        <a href="{{ route('inquiry.hub') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Inquiry Hub</a>
    </header>

    <div class="max-w-[1180px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. quotation / customer..." class="w-72 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                <button type="button" wire:click="openPicker" class="text-[11px] font-bold text-white bg-accent rounded px-3 py-1.5 cursor-pointer">＋ Quotation Baru</button>
            </div>

            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f4f5f8]">
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">No. Quotation</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Customer</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tanggal</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Status</th>
                        <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Nilai (excl PPN)</th>
                        <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->quotations as $q)
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe]" wire:key="quo-{{ $q->id_quotation }}">
                            <td class="px-3 py-2 font-mono font-bold text-accent">{{ $q->quotation_no }}</td>
                            <td class="px-3 py-2">{{ $q->customer?->customer_name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $q->issued_date?->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $q->doc_status === 'PUBLISHED' ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $q->doc_status }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($this->grandTotals[$q->id_quotation] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route($q->doc_status === 'DRAFT' ? 'quotation.entry' : 'quotation.print', ['quotation' => $q->id_quotation]) }}" class="text-accent font-semibold no-underline">Buka →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-8 text-muted">Belum ada quotation.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
                {{ $this->quotations->links() }}
            </div>
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[480px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">Pilih Customer</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <input type="text" wire:model.live.debounce.300ms="customerSearch" autofocus placeholder="Ketik min. 2 huruf nama/kode customer..." class="w-full px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs mb-2">
                <div class="max-h-72 overflow-y-auto">
                    @forelse ($this->customerResults as $c)
                        <button type="button" wire:click="startForCustomer({{ $c->id_customer }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                            <div class="text-xs font-semibold text-navy">{{ $c->customer_name }}</div>
                            <div class="text-[10.5px] text-muted">{{ $c->customer_code }}</div>
                        </button>
                    @empty
                        <div class="text-[11px] text-muted text-center py-4">
                            {{ mb_strlen(trim($customerSearch)) < 2 ? 'Ketik min. 2 huruf untuk cari.' : 'Tidak ada customer cocok.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
