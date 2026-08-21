<?php

use App\Models\Master\CustCustomer;
use App\Models\Sdi\QuoQuotation;
use App\Services\Sales\QuotationBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// GRID_UI_STANDARD.md diikuti (2026-08-22, diperluas ke grid worklist/transaksi).
// Grid transaksi TETAP boleh punya "+ New Record" (di sini "+ Quotation Baru"
// via picker customer) karena Quotation memang dibuat dari grid ini -- beda
// dari Imposition/Costing worklist yang recordnya lahir di layar lain.
// Kolom Aksi non-master: bukan Edit+Toggle-aktif (§5), tapi ↗ Buka + ✓ Confirm
// (DRAFT->PUBLISHED langsung dari grid, sesuai instruksi user 2026-08-22).
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

    public function openQuotation(int $id)
    {
        $status = QuoQuotation::whereKey($id)->value('doc_status');

        return $this->redirect(route($status === 'DRAFT' ? 'quotation.entry' : 'quotation.print', ['quotation' => $id]));
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

        return \App\Models\Sdi\QuoQuotationItem::whereIn('quotation_id', $ids)
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
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Quotation</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Draft</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statDraft }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Published</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statPublished }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. quotation / customer…"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Quotation</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Nilai (excl PPN)</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->quotations as $q)
                            <tr wire:key="quo-{{ $q->id_quotation }}" wire:dblclick="openQuotation({{ $q->id_quotation }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($q->id_quotation) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($q->customer?->customer_name ?: $q->quotation_no) }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px]">{{ $q->customer?->customer_name ?? '—' }}</div>
                                            <div class="text-[11px] text-muted font-mono">{{ $q->quotation_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $q->issued_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $q->doc_status === 'PUBLISHED' ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#fef6e7] text-[#b45309]' }}">{{ $q->doc_status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format($this->grandTotals[$q->id_quotation] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openQuotation({{ $q->id_quotation }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                    @if ($q->doc_status === 'DRAFT')
                                        <button type="button" wire:click.stop="confirmQuotation({{ $q->id_quotation }})" wire:confirm="Publish quotation {{ $q->quotation_no }}? Harga akan terkunci." title="Confirm / Publish" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[#16a34a] hover:bg-[#e9f9ef] cursor-pointer">✓</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-8 text-muted text-sm">Belum ada quotation.</td></tr>
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
