<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Pur\PqQuotation;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md diikuti. PQ = "kondisional, boleh dilewati" (PROC_TIER_1
// §, tanpa approval berjenjang -- cuma konfirmasi sendiri oleh officer), jadi
// Aksi = ↗ Buka + ✓ Confirm (sama pola Quotation/CO), BUKAN pola Approval
// terpisah spt PR/PO/PRT. "+ New Record" tetap ada, PQ dibuat dari grid ini
// sendiri (picker supplier).
new #[Title('Purchase Quotation — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
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
            ->paginate($this->perPage);
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
            ->where('activate', true)
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

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="{{ Translator::t('proc_pq_stat_total', 'Total PQ') }}" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" label="{{ Translator::t('sales_quo_stat_draft', 'Draft') }}" :value="$this->statDraft" />
            <x-grid.stat-box tone="active" label="{{ Translator::t('sales_co_stat_confirmed', 'Confirmed') }}" :value="$this->statConfirmed" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ Translator::t('proc_pq_search_placeholder', 'Cari no. PQ / item…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('proc_pq_col_supplier_no', 'Supplier / No. PQ') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('proc_col_date', 'Tanggal') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('proc_pq_col_valid_until', 'Berlaku s/d') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ Translator::t('proc_pq_col_total_idr', 'Total (IDR)') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ Translator::t('proc_col_status', 'Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">{{ Translator::t('proc_col_action', 'Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->quotations as $pq)
                            <tr wire:key="pq-{{ $pq->pq_id }}" wire:dblclick="openPq({{ $pq->pq_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ Translator::t('shared_grid_dblclick_hint', 'Klik 2x untuk buka') }}">
                                <x-grid.identity-cell :id="$pq->pq_id" :name="$this->supplierNames[$pq->i_supplier_id] ?? '—'" :code="$pq->doc_no ?: Translator::t('proc_draft_placeholder', '(draft)')" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $pq->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $pq->i_quotation_valid_until?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $pq->c_total_idr, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($pq) }}">{{ $this->statusLabel($pq) }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ Translator::t('shared_grid_action_open', 'Buka') }}" action="openPq({{ $pq->pq_id }})" />
                                    @if ($pq->status === 'DRAFT')
                                        <x-grid.action-icon icon="fa-check" title="{{ Translator::t('sales_form_btn_confirm', 'Confirm') }}" action="confirmPq({{ $pq->pq_id }})"
                                            confirm="{{ Translator::t('proc_pq_confirm_prefix', 'Konfirmasi PQ') }} {{ $pq->doc_no }}? {{ Translator::t('sales_quo_confirm_publish_suffix', 'Harga akan terkunci.') }}" variant="success" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="{{ Translator::t('proc_pq_empty', 'Belum ada Purchase Quotation.') }}" />
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
                    <h3 class="text-sm font-bold text-navy">{{ Translator::t('proc_pq_picker_title', 'PQ Baru — Pilih Supplier') }}</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="supplierSearch" autofocus placeholder="{{ Translator::t('proc_supplier_picker_search_placeholder', 'Ketik min. 2 huruf nama/kode supplier...') }}" class="mb-2" />
                <div class="max-h-72 overflow-y-auto">
                    @forelse ($this->supplierResults as $s)
                        <button type="button" wire:click="startForSupplier({{ $s->id_supplier }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                            <div class="text-xs font-semibold text-navy">{{ $s->supplier_name }}</div>
                            <div class="text-[10.5px] text-muted">{{ $s->supplier_code }}</div>
                        </button>
                    @empty
                        <div class="text-[11px] text-muted text-center py-4">
                            {{ mb_strlen(trim($supplierSearch)) < 2 ? Translator::t('proc_supplier_picker_min_chars', 'Ketik min. 2 huruf untuk cari.') : Translator::t('proc_supplier_picker_no_match', 'Tidak ada supplier cocok.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
