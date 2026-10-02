<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Livewire\Concerns\OpensFromQuery;
use App\Models\Pur\PrtReturn;
use App\Models\Pur\PvReceipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// griduistandard.md diikuti. PRT butuh approval sungguhan (Manajer
// Operasional) -- Approve/Reject/Hold digabung ke form entri (pur/prt/entry),
// BUKAN di grid ini (pola sama PR/PO/Adjustment yang dirombak sesi ini).
// Aksi grid cuma ↗ Buka. "+ New Record" tetap ada (PRT dibuat dari grid ini
// sendiri via picker sumber PV/Stok).
new #[Title('Purchase Return — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
    use OpensFromQuery;
    use WithPagination;

    public string $search = '';

    public bool $pickerOpen = false;

    public ?string $pickerSource = null; // 'pv' | 'stock'

    public string $supplierSearch = '';

    #[Computed]
    public function returns()
    {
        return PrtReturn::when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(fn ($qq) => $qq->where('doc_no', 'like', "%{$term}%")->orWhere('i_reason', 'like', "%{$term}%"));
            })
            ->orderByDesc('prt_id')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = $this->returns->pluck('i_supplier_id')->filter()->unique();

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function statTotal(): int
    {
        return PrtReturn::count();
    }

    #[Computed]
    public function statPending(): int
    {
        return PrtReturn::where('status', 'SUBMITTED')->count();
    }

    #[Computed]
    public function statValue(): float
    {
        return (float) PrtReturn::whereIn('status', ['CONFIRMED', 'COMPLETED'])->withSum('lines', 'c_line_value')->get()->sum('lines_sum_c_line_value');
    }

    #[Computed]
    public function returnablePvs()
    {
        return PvReceipt::with('lines')
            ->where('status', 'POSTED')
            ->get()
            ->filter(fn ($pv) => $pv->lines->sum('c_qty_rejected') > 0)
            ->values();
    }

    #[Computed]
    public function returnablePvSupplierNames(): array
    {
        $ids = $this->returnablePvs->pluck('i_supplier_id')->filter()->unique();

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

    // Dibuka dari grid v2 (+ New Record -> ?new=1): langsung ke pemilih sumber.
    protected function newFromQuery(): void
    {
        $this->openPicker();
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
        $this->pickerSource = null;
        $this->supplierSearch = '';
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    public function pickSourceType(?string $source): void
    {
        $this->pickerSource = $source;
    }

    // QC_REJECT: supplier & po_id ikut dari PV yang dipilih (baris sungguhan
    // ditarik di layar entri, bukan di sini -- picker cuma menentukan sumber).
    public function startFromPv(int $pvId)
    {
        $pv = PvReceipt::with('lines')->findOrFail($pvId);
        $login = Auth::user()->login;

        $prt = PrtReturn::create([
            'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => $pv->i_supplier_id,
            'pv_id' => $pv->pv_id,
            'po_id' => $pv->lines->first()?->c_po_id,
            'i_source' => 'QC_REJECT',
            'i_disposition' => 'CREDIT_NOTE',
            'i_reason' => '',
            'status' => 'DRAFT',
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        return $this->redirect(route('pur.prt.entry', ['prt' => $prt->prt_id]));
    }

    public function startFromStock(int $supplierId)
    {
        $login = Auth::user()->login;

        $prt = PrtReturn::create([
            'i_doc_date' => now()->toDateString(),
            'i_supplier_id' => $supplierId,
            'i_source' => 'FROM_STOCK',
            'i_disposition' => 'CREDIT_NOTE',
            'i_reason' => '',
            'status' => 'DRAFT',
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        return $this->redirect(route('pur.prt.entry', ['prt' => $prt->prt_id]));
    }

    public function openReturn(int $id)
    {
        return $this->redirect(route('pur.prt.entry', ['prt' => $id]));
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
            'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
            'CONFIRMED', 'COMPLETED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
            'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
            default => 'bg-[#eef1f5] text-[#4b5568]',
        };
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="{{ t('Total Returns') }}" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" label="{{ t('Awaiting Approval') }}" :value="$this->statPending" />
            <x-grid.stat-box tone="active" label="{{ t('Return Value') }}" :value="number_format($this->statValue, 0, ',', '.')" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search return no. / reason…') }}"
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Supplier / Return No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ t('Source') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Reason') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Disposition') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">{{ t('Status') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->returns as $r)
                            <tr wire:key="prt-{{ $r->prt_id }}" wire:dblclick="openReturn({{ $r->prt_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="{{ t('Double-click to open') }}">
                                <x-grid.identity-cell :id="$r->prt_id" :name="$this->supplierNames[$r->i_supplier_id] ?? '—'" :code="$r->doc_no ?: t('(draft)')" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded {{ $r->i_source === 'QC_REJECT' ? 'bg-[#eef3fb] text-[#2f6fed]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $r->i_source === 'QC_REJECT' ? t('FROM RECEIPT') : t('FROM STOCK') }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $r->i_reason }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $r->i_disposition === 'CREDIT_NOTE' ? t('Credit Note') : t('Replace Goods') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($r->status) }}">{{ $r->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="fa-up-right-from-square" title="{{ t('Open') }}" action="openReturn({{ $r->prt_id }})" />
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" message="{{ t('No Purchase Returns yet.') }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->returns])
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg w-full max-w-lg max-h-[80vh] flex flex-col shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-[#eef1f5]">
                    <h3 class="text-sm font-bold text-navy">
                        @if (! $pickerSource) {{ t('New Return — Select Source') }}
                        @elseif ($pickerSource === 'pv') {{ t('Select Receipt Document (has QC-rejected goods)') }}
                        @else {{ t('Select Supplier') }}
                        @endif
                    </h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    @if (! $pickerSource)
                        <div class="grid two">
                            <button type="button" wire:click="pickSourceType('pv')" class="border-2 border-[#d7dbe2] hover:border-accent rounded-lg p-4 text-left cursor-pointer">
                                <div class="text-lg mb-1">📦</div>
                                <div class="text-xs font-bold text-navy">{{ t('From Receipt (QC Reject)') }}</div>
                                <div class="text-[10.5px] text-muted mt-1">{{ t('Goods failed QC upon receipt') }}</div>
                            </button>
                            <button type="button" wire:click="pickSourceType('stock')" class="border-2 border-[#d7dbe2] hover:border-accent rounded-lg p-4 text-left cursor-pointer">
                                <div class="text-lg mb-1">🏬</div>
                                <div class="text-xs font-bold text-navy">{{ t('From Stock') }}</div>
                                <div class="text-[10.5px] text-muted mt-1">{{ t('Goods already in warehouse, problem found later') }}</div>
                            </button>
                        </div>
                    @elseif ($pickerSource === 'pv')
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#f4f5f8]">
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ t('Receipt No.') }}</th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ t('Supplier') }}</th>
                                    <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ t('Reject Qty') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->returnablePvs as $pv)
                                    <tr class="border-b border-[#eef1f5] hover:bg-[#eef3fb] cursor-pointer" wire:click="startFromPv({{ $pv->pv_id }})">
                                        <td class="px-3 py-2 font-mono font-bold text-accent">{{ $pv->doc_no }}</td>
                                        <td class="px-3 py-2">{{ $this->returnablePvSupplierNames[$pv->i_supplier_id] ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format($pv->lines->sum('c_qty_rejected'), 2, ',', '.'), '0'), ',') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center py-8 text-muted">{{ t('No receipt with QC-rejected goods.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="supplierSearch" autofocus placeholder="{{ t('Type at least 2 letters of supplier name/code...') }}" class="mb-2" />
                        <div class="max-h-72 overflow-y-auto">
                            @forelse ($this->supplierResults as $s)
                                <button type="button" wire:click="startFromStock({{ $s->id_supplier }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                                    <div class="text-xs font-semibold text-navy">{{ $s->supplier_name }}</div>
                                    <div class="text-[10.5px] text-muted">{{ $s->supplier_code }}</div>
                                </button>
                            @empty
                                <div class="text-[11px] text-muted text-center py-4">
                                    {{ mb_strlen(trim($supplierSearch)) < 2 ? t('Type at least 2 letters to search.') : t('No matching supplier.') }}
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>

                @if ($pickerSource)
                    <div class="px-4 py-3 border-t border-[#eef1f5]">
                        <button type="button" wire:click="pickSourceType(null)" class="text-xs font-semibold px-3 py-1.5 rounded border border-[#d7dbe2]">‹ {{ t('Back') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
