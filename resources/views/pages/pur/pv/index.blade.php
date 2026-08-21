<?php

use App\Models\Pur\PvReceipt;
use App\Services\Pur\PvBuilderService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Penerimaan Barang (SPB) — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

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
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('pv_id')
            ->paginate(20);
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
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🚚 Penerimaan Barang (SPB)</b>
        <a href="{{ route('pur.po.list') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Daftar PO</a>
        <a href="{{ route('menu') }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">Menu</a>
    </header>

    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2] flex-wrap">
                <div class="flex items-center gap-2">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. SPB / PO / surat jalan..." class="w-64 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <select wire:model.live="statusFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        <option value="">Semua Status</option>
                        <option value="DRAFT">Draft</option>
                        <option value="QC_PENDING">Menunggu QC</option>
                        <option value="POSTED">Terposting</option>
                        <option value="CANCELLED">Dibatalkan</option>
                    </select>
                </div>
                <button type="button" wire:click="openPicker" class="text-[11px] font-bold text-white bg-accent rounded px-3 py-1.5 cursor-pointer">＋ Terima Barang</button>
            </div>

            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f4f5f8]">
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">No. SPB</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tanggal</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">PO Terkait</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Supplier</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Gudang</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Surat Jalan</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Status</th>
                        <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->receipts as $pv)
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe]" wire:key="pv-{{ $pv->pv_id }}">
                            <td class="px-3 py-2 font-mono font-bold text-accent">{{ $pv->doc_no }}</td>
                            <td class="px-3 py-2">{{ $pv->i_doc_date?->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-2">
                                @if (($pv->c_po_count ?? 0) > 1)
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-[#fff1f2] text-[#9f1239]">{{ $pv->c_po_count }} PO</span>
                                @endif
                                <span class="text-[10.5px] text-muted">{{ $pv->c_po_doc_list }}</span>
                            </td>
                            <td class="px-3 py-2">{{ $this->supplierNames[$pv->i_supplier_id] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $pv->i_warehouse_code }}</td>
                            <td class="px-3 py-2 font-mono text-[10.5px]">{{ $pv->i_supplier_do_no }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                    {{ match ($pv->status) {
                                        'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                        'QC_PENDING' => 'bg-[#fff5e0] text-[#8a5a00]',
                                        'POSTED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                        'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                                        default => 'bg-[#eef1f5] text-[#4b5568]',
                                    } }}">{{ $pv->status }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('pur.pv.entry', ['pv' => $pv->pv_id]) }}" class="text-accent font-semibold no-underline">Buka →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-8 text-muted">Belum ada Penerimaan Barang.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
                {{ $this->receipts->links() }}
            </div>
        </div>
    </div>

    {{-- PICKER 2 LANGKAH --}}
    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-6" wire:click.self="closePicker">
            <div class="bg-white rounded-lg w-full max-w-4xl max-h-[85vh] flex flex-col shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-[#eef1f5]">
                    <h3 class="text-sm font-bold text-navy">Terima Barang — Langkah {{ $pickerStep }}/2: {{ $pickerStep === 1 ? 'Pilih Supplier' : 'Pilih Baris PO Terbuka' }}</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    @if ($pickerStep === 1)
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#f4f5f8]">
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">Supplier</th>
                                    <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">PO Terbuka</th>
                                    <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">Baris</th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">PO Terlama</th>
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
                                    <tr><td colspan="4" class="text-center py-8 text-muted">Tidak ada supplier dengan PO terbuka.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <div class="px-4 py-2 border-b border-[#eef1f5] flex items-center gap-3">
                            <span class="text-xs font-semibold">{{ $this->supplierNames[$pickedSupplierId] ?? '—' }}</span>
                            <input type="text" wire:model="pickedWarehouse" placeholder="Kode gudang penerima (mis. GD-BB)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#f4f5f8]">
                                    <th class="px-3 py-2"></th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">No PO</th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">Item</th>
                                    <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">Sisa</th>
                                    <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">UoM</th>
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
                        <button type="button" wire:click="$set('pickerStep', 1)" class="text-xs font-semibold px-3 py-1.5 rounded border border-[#d7dbe2]">‹ Kembali</button>
                        <span class="text-[11px] text-muted">{{ count($checkedLineIds) }} baris dipilih</span>
                    @endif
                    <div class="ml-auto flex gap-2">
                        <button type="button" wire:click="closePicker" class="text-xs font-semibold px-3 py-1.5 rounded border border-[#d7dbe2]">Batal</button>
                        @if ($pickerStep === 2)
                            <button type="button" wire:click="createReceipt" @disabled(! $this->pickerValid) class="text-xs font-bold px-3 py-1.5 rounded bg-accent text-white disabled:opacity-40">Buat SPB</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
