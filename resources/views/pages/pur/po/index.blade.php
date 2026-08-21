<?php

use App\Models\Pur\PoOrder;
use App\Services\Pur\PoBuilderService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Purchase Order — TELASIH MAKMUR ERP')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $pickerOpen = false;

    public string $supplierSearch = '';

    // grid_performance (GLOBAL_RULES.yaml): server-side pagination + WHERE.
    #[Computed]
    public function orders()
    {
        return PoOrder::when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('doc_no', 'like', "%{$term}%")
                        ->orWhereHas('lines', fn ($l) => $l->where('i_description', 'like', "%{$term}%"));
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('po_id')
            ->paginate(20);
    }

    #[Computed]
    public function supplierNames(): array
    {
        $ids = $this->orders->pluck('i_supplier_id')->filter()->unique();

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    #[Computed]
    public function supplierResults()
    {
        if (mb_strlen(trim($this->supplierSearch)) < 2) {
            return collect();
        }

        return DB::connection('tm2mst')->table('sup_supplier')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('supplier_name', 'like', "%{$this->supplierSearch}%")
                    ->orWhere('supplier_code', 'like', "%{$this->supplierSearch}%");
            })
            ->orderBy('supplier_name')
            ->limit(15)
            ->get();
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
        $po = PoBuilderService::findOrCreateDraft($supplierId);

        return $this->redirect(route('pur.po.entry', ['po' => $po->po_id]));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📦 Purchase Order</b>
        <a href="{{ route('pur.pv.list') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">🚚 Penerimaan Barang</a>
        <a href="{{ route('pur.po.approval') }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">✅ Approval Center</a>
        <a href="{{ route('pur.pr.list') }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Daftar PR</a>
        <a href="{{ route('menu') }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">Menu</a>
    </header>

    <div class="max-w-[1280px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2] flex-wrap">
                <div class="flex items-center gap-2">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. PO / item..." class="w-64 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <select wire:model.live="statusFilter" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        <option value="">Semua Status</option>
                        <option value="DRAFT">Draft</option>
                        <option value="SUBMITTED">Menunggu Approval</option>
                        <option value="APPROVED">Disetujui</option>
                        <option value="REJECTED">Ditolak</option>
                        <option value="SENT">Dikirim ke Supplier</option>
                        <option value="PARTIALLY_RECEIVED">Sebagian Diterima</option>
                        <option value="RECEIVED">Diterima</option>
                        <option value="CLOSED">Selesai</option>
                    </select>
                </div>
                <button type="button" wire:click="openPicker" class="text-[11px] font-bold text-white bg-accent rounded px-3 py-1.5 cursor-pointer">＋ PO Baru</button>
            </div>

            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f4f5f8]">
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">No. PO</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tanggal</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Supplier</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Tipe</th>
                        <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Total Tagihan</th>
                        <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold border-b border-[#d7dbe2]">Status</th>
                        <th class="px-3 py-2 border-b border-[#d7dbe2]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->orders as $po)
                        <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe]" wire:key="po-{{ $po->po_id }}">
                            <td class="px-3 py-2 font-mono font-bold text-accent">{{ $po->doc_no ?: '(draft)' }}</td>
                            <td class="px-3 py-2">{{ $po->i_doc_date?->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-2">{{ $this->supplierNames[$po->i_supplier_id] ?? '—' }}</td>
                            <td class="px-3 py-2 text-[10.5px]">{{ $po->i_po_type }} · {{ $po->i_procure_type }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $po->c_grand_total, 0, ',', '.') }}</td>
                            <td class="px-3 py-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full
                                    {{ match ($po->status) {
                                        'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                                        'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
                                        'APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                                        'REJECTED', 'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                                        default => 'bg-[#eef1f5] text-[#4b5568]',
                                    } }}">{{ $po->status }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('pur.po.entry', ['po' => $po->po_id]) }}" class="text-accent font-semibold no-underline">Buka →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-8 text-muted">Belum ada Purchase Order.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-[#eef1f5]">
                {{ $this->orders->links() }}
            </div>
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[480px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">PO Baru — Pilih Supplier</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <input type="text" wire:model.live.debounce.300ms="supplierSearch" autofocus placeholder="Ketik min. 2 huruf nama/kode supplier..." class="w-full px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs mb-2">
                <div class="max-h-72 overflow-y-auto">
                    @forelse ($this->supplierResults as $s)
                        <button type="button" wire:click="startForSupplier({{ $s->id_supplier }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                            <div class="text-xs font-semibold text-navy">{{ $s->supplier_name }}</div>
                            <div class="text-[10.5px] text-muted">{{ $s->supplier_code }}</div>
                        </button>
                    @empty
                        <div class="text-[11px] text-muted text-center py-4">
                            {{ mb_strlen(trim($supplierSearch)) < 2 ? 'Ketik min. 2 huruf untuk cari.' : 'Tidak ada supplier cocok.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
