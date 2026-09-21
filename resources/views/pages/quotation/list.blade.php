<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\QuoQuotation;
use App\Services\Sales\QuotationBuilder;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md diikuti (2026-08-22, diperluas ke grid worklist/transaksi).
// Grid transaksi TETAP boleh punya "+ New Record" (di sini "+ Quotation Baru"
// via picker customer) karena Quotation memang dibuat dari grid ini -- beda
// dari Imposition/Costing worklist yang recordnya lahir di layar lain.
// Kolom Aksi non-master: bukan Edit+Toggle-aktif (§5), tapi ↗ Buka + ✓ Confirm
// (DRAFT->PUBLISHED langsung dari grid, sesuai instruksi user 2026-08-22).
new #[Title('Daftar Quotation — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    public string $customerSearch = '';

    public bool $pickerOpen = false;

    // grid_performance (globalrules.yaml): server-side pagination + WHERE, bukan load-semua-lalu-filter.
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
            ->paginate($this->perPage);
    }

    #[Computed]
    public function customerResults()
    {
        if (mb_strlen(trim($this->customerSearch)) < 2) {
            return collect();
        }

        return CustCustomer::where('activate', true)
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

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="{{ Translator::t('sales_quo_stat_total', 'Total Quotation') }}" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" label="{{ Translator::t('sales_quo_stat_draft', 'Draft') }}" :value="$this->statDraft" />
            <x-grid.stat-box tone="active" label="{{ Translator::t('sales_quo_stat_published', 'Published') }}" :value="$this->statPublished" />
        </x-grid.stat-row>

        <div class="bg-white border border-field-border rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ Translator::t('sales_quo_search_placeholder', 'Cari no. quotation / customer…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-field-border rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="openPicker"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1]">{{ Translator::t('sales_quo_col_quotation', 'Quotation') }}</th>
                            <th class="text-left px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1] w-[110px]">{{ Translator::t('sales_quo_col_date', 'Tanggal') }}</th>
                            <th class="text-left px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1] w-[100px]">{{ Translator::t('sales_quo_col_status', 'Status') }}</th>
                            <th class="text-right px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1] w-[140px]">{{ Translator::t('sales_quo_col_value', 'Nilai (excl PPN)') }}</th>
                            <th class="text-right px-3 py-2 bg-field-soft text-muted text-[11px] uppercase tracking-wide border-b border-field-badge sticky top-0 z-[1] w-[80px]">{{ Translator::t('sales_quo_col_action', 'Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->quotations as $q)
                            <tr wire:key="quo-{{ $q->id_quotation }}" wire:dblclick="openQuotation({{ $q->id_quotation }})" class="hover:bg-accent-soft cursor-pointer" title="{{ Translator::t('shared_grid_dblclick_hint', 'Klik 2x untuk buka') }}">
                                <x-grid.identity-cell :id="$q->id_quotation" :name="$q->customer?->customer_name ?? '—'" :code="$q->quotation_no" />
                                <td class="px-3 py-2 border-b border-field-badge">{{ $q->issued_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-field-badge">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $q->doc_status === 'PUBLISHED' ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#fef6e7] text-[#b45309]' }}">{{ $q->doc_status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-field-badge text-right font-semibold">{{ number_format($this->grandTotals[$q->id_quotation] ?? 0, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-field-badge text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="↗" title="{{ Translator::t('shared_grid_action_open', 'Buka') }}" action="openQuotation({{ $q->id_quotation }})" />
                                    @if ($q->doc_status === 'DRAFT')
                                        <x-grid.action-icon icon="✓" title="{{ Translator::t('sales_quo_action_confirm_publish', 'Confirm / Publish') }}" action="confirmQuotation({{ $q->id_quotation }})"
                                            confirm="{{ Translator::t('sales_quo_confirm_publish_prefix', 'Publish quotation') }} {{ $q->quotation_no }}? {{ Translator::t('sales_quo_confirm_publish_suffix', 'Harga akan terkunci.') }}" variant="success" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="{{ Translator::t('sales_quo_empty', 'Belum ada quotation.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->quotations])
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[480px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">{{ Translator::t('sales_quo_picker_title', 'Pilih Customer') }}</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="customerSearch" autofocus placeholder="{{ Translator::t('sales_quo_picker_search_placeholder', 'Ketik min. 2 huruf nama/kode customer...') }}" class="mb-2" />
                <div class="max-h-72 overflow-y-auto">
                    @forelse ($this->customerResults as $c)
                        <button type="button" wire:click="startForCustomer({{ $c->id_customer }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-accent-soft cursor-pointer border-b border-field-badge last:border-b-0">
                            <div class="text-xs font-semibold text-navy">{{ $c->customer_name }}</div>
                            <div class="text-[10.5px] text-muted">{{ $c->customer_code }}</div>
                        </button>
                    @empty
                        <div class="text-[11px] text-muted text-center py-4">
                            {{ mb_strlen(trim($customerSearch)) < 2 ? Translator::t('sales_quo_picker_min_chars', 'Ketik min. 2 huruf untuk cari.') : Translator::t('sales_quo_picker_no_match', 'Tidak ada customer cocok.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
