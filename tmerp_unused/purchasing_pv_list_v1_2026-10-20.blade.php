<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Pur\PvReceipt;
use App\Services\Pur\PvBuilderService;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md diikuti (2026-08-22). "+ New Record" tetap ada karena
// SPB memang dibuat dari grid ini sendiri (picker supplier -> baris PO).
new #[Title('Penerimaan Barang (SPB) — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    // ---- picker 2 langkah ----
    public bool $pickerOpen = false;

    public int $pickerStep = 1;

    public ?int $pickedSupplierId = null;

    public string $pickedWarehouse = '';

    public array $checkedLineIds = [];

    #[Computed]
    public function receipts()
    {
        return PvReceipt::when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('doc_no', 'like', "%{$term}%")
                        ->orWhere('c_po_doc_list', 'like', "%{$term}%")
                        ->orWhere('i_supplier_do_no', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('pv_id')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function statTotal(): int
    {
        return PvReceipt::count();
    }

    #[Computed]
    public function statQcPending(): int
    {
        return PvReceipt::where('status', 'QC_PENDING')->count();
    }

    #[Computed]
    public function statPosted(): int
    {
        return PvReceipt::where('status', 'POSTED')->count();
    }

    #[Computed]
    public function statCancelled(): int
    {
        return PvReceipt::where('status', 'CANCELLED')->count();
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = $this->receipts->pluck('i_supplier_id')->filter()->unique()->merge($this->supplierWorklist->pluck('i_supplier_id'));

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function supplierWorklist()
    {
        return PvBuilderService::supplierWorklist();
    }

    #[Computed]
    public function openLines()
    {
        return $this->pickedSupplierId ? PvBuilderService::openLinesForSupplier($this->pickedSupplierId) : collect();
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
        $this->pickerStep = 1;
        $this->pickedSupplierId = null;
        $this->pickedWarehouse = '';
        $this->checkedLineIds = [];
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    public function pickSupplier(int $supplierId): void
    {
        $this->pickedSupplierId = $supplierId;
        $this->checkedLineIds = $this->openLines->pluck('po_line_id')->all();
        $this->pickerStep = 2;
    }

    public function toggleLine(int $lineId): void
    {
        if (in_array($lineId, $this->checkedLineIds, true)) {
            $this->checkedLineIds = array_values(array_diff($this->checkedLineIds, [$lineId]));
        } else {
            $this->checkedLineIds[] = $lineId;
        }
    }

    #[Computed]
    public function pickerValid(): bool
    {
        return ! empty($this->checkedLineIds) && trim($this->pickedWarehouse) !== '';
    }

    public function createReceipt()
    {
        if (! $this->pickerValid || ! $this->pickedSupplierId) {
            return;
        }

        $pv = PvBuilderService::createFromLines($this->pickedSupplierId, trim($this->pickedWarehouse), $this->checkedLineIds);

        return $this->redirect(route('pur.pv.entry', ['pv' => $pv->pv_id]));
    }

    public function openReceipt(int $id)
    {
        return $this->redirect(route('pur.pv.entry', ['pv' => $id]));
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="total" label="{{ Translator::t('proc_pv_stat_total', 'Total SPB') }}" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" label="{{ Translator::t('proc_pv_stat_qc_pending', 'Menunggu QC') }}" :value="$this->statQcPending" />
            <x-grid.stat-box tone="active" label="{{ Translator::t('proc_pv_stat_posted', 'Terposting') }}" :value="$this->statPosted" />
            <x-grid.stat-box tone="critical" label="{{ Translator::t('proc_pv_stat_cancelled', 'Dibatalkan') }}" :value="$this->statCancelled" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ Translator::t('proc_pv_search_placeholder', 'Cari no. SPB / PO / surat jalan…') }}"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('proc_pv_col_supplier_no', 'Supplier / No. SPB') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('proc_col_date', 'Tanggal') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('proc_pv_col_related_po', 'PO Terkait') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ Translator::t('proc_pv_col_warehouse', 'Gudang') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[120px]">{{ Translator::t('proc_pv_col_delivery_note', 'Surat Jalan') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ Translator::t('proc_col_status', 'Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ Translator::t('proc_col_action', 'Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->receipts as $pv)
                            <tr wire:key="pv-{{ $pv->pv_id }}" wire:dblclick="openReceipt({{ $pv->pv_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ Translator::t('shared_grid_dblclick_hint', 'Klik 2x untuk buka') }}">
                                <x-grid.identity-cell :id="$pv->pv_id" :name="$this->supplierNames[$pv->i_supplier_id] ?? '—'" :code="$pv->label()" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $pv->i_doc_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if (($pv->c_po_count ?? 0) > 1)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-[#fff1f2] text-[#9f1239]">{{ $pv->c_po_count }} PO</span>
                                    @endif
                                    <span class="text-[10.5px] text-muted">{{ $pv->c_po_doc_list }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $pv->i_warehouse_code }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-mono text-[10.5px]">{{ $pv->i_supplier_do_no }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                        {{ match ($pv->status) {
                                            'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                            'QC_PENDING' => 'bg-[#fff5e0] text-[#8a5a00]',
                                            'POSTED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                            'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                                            default => 'bg-[#eef1f5] text-[#4b5568]',
                                        } }}">{{ $pv->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ Translator::t('shared_grid_action_open', 'Buka') }}" action="openReceipt({{ $pv->pv_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="7" message="{{ Translator::t('proc_pv_empty', 'Belum ada Penerimaan Barang.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->receipts])
        </div>
    </div>

    {{-- PICKER 2 LANGKAH --}}
    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-6" wire:click.self="closePicker">
            <div class="bg-white rounded-lg w-full max-w-4xl max-h-[85vh] flex flex-col shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-[#eef1f5]">
                    <h3 class="text-sm font-bold text-navy">{{ Translator::t('proc_pv_picker_title', 'Terima Barang — Langkah') }} {{ $pickerStep }}/2: {{ $pickerStep === 1 ? Translator::t('proc_pv_picker_step1', 'Pilih Supplier') : Translator::t('proc_pv_picker_step2', 'Pilih Baris PO Terbuka') }}</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    @if ($pickerStep === 1)
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#f4f5f8]">
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ Translator::t('proc_supplier_label', 'Supplier') }}</th>
                                    <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ Translator::t('proc_pv_col_open_po', 'PO Terbuka') }}</th>
                                    <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ Translator::t('proc_pv_col_lines', 'Baris') }}</th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ Translator::t('proc_pv_col_oldest_po', 'PO Terlama') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->supplierWorklist as $s)
                                    <tr class="border-b border-[#eef1f5] hover:bg-[#eef3fb] cursor-pointer" wire:click="pickSupplier({{ $s->i_supplier_id }})">
                                        <td class="px-3 py-2 font-semibold">{{ $this->supplierNames[$s->i_supplier_id] ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right">{{ $s->open_po_count }}</td>
                                        <td class="px-3 py-2 text-right">{{ $s->open_line_count }}</td>
                                        <td class="px-3 py-2 font-mono">{{ \Illuminate\Support\Carbon::parse($s->oldest_po_date)->translatedFormat('d M Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-8 text-muted">{{ Translator::t('proc_pv_no_open_po_supplier', 'Tidak ada supplier dengan PO terbuka.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <div class="px-4 py-2 border-b border-[#eef1f5] flex items-center gap-3">
                            <span class="text-xs font-semibold">{{ $this->supplierNames[$pickedSupplierId] ?? '—' }}</span>
                            <x-form.input wire:model="pickedWarehouse" placeholder="{{ Translator::t('proc_pv_warehouse_placeholder', 'Kode gudang penerima (mis. GD-BB)') }}" class="!py-1.5" />
                        </div>
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#f4f5f8]">
                                    <th class="px-3 py-2"></th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ Translator::t('proc_pv_col_po_no', 'No PO') }}</th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ Translator::t('proc_pv_col_item', 'Item') }}</th>
                                    <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ Translator::t('proc_pv_col_remaining', 'Sisa') }}</th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ Translator::t('proc_pv_col_uom', 'UoM') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->openLines as $line)
                                    <tr class="border-b border-[#eef1f5] cursor-pointer" wire:click="toggleLine({{ $line->po_line_id }})">
                                        <td class="px-3 py-2"><input type="checkbox" @checked(in_array($line->po_line_id, $checkedLineIds)) onclick="event.preventDefault()"></td>
                                        <td class="px-3 py-2 font-mono text-accent">{{ $line->order->doc_no }} <span class="text-muted">#{{ $line->line_no }}</span></td>
                                        <td class="px-3 py-2">{{ $line->i_description }}</td>
                                        <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $line->i_qty - (float) $line->c_qty_received, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2">{{ $line->i_uom }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="px-4 py-3 border-t border-[#eef1f5] flex items-center gap-2">
                    @if ($pickerStep === 2)
                        <button type="button" wire:click="$set('pickerStep', 1)" class="text-xs font-semibold px-3 py-1.5 rounded border border-[#d7dbe2]">‹ {{ Translator::t('proc_pv_btn_back_step', 'Kembali') }}</button>
                        <span class="text-[11px] text-muted">{{ count($checkedLineIds) }} {{ Translator::t('proc_pv_lines_selected_suffix', 'baris dipilih') }}</span>
                    @endif
                    <div class="ml-auto flex gap-2">
                        <button type="button" wire:click="closePicker" class="text-xs font-semibold px-3 py-1.5 rounded border border-[#d7dbe2]">{{ Translator::t('shared_btn_cancel', 'Batal') }}</button>
                        @if ($pickerStep === 2)
                            <button type="button" wire:click="createReceipt" @disabled(! $this->pickerValid) class="text-xs font-bold px-3 py-1.5 rounded bg-accent text-white disabled:opacity-40">{{ Translator::t('proc_pv_btn_create', 'Buat SPB') }}</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
