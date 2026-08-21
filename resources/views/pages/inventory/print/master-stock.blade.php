<?php

use App\Models\Master\MRawMaterial;
use App\Models\Master\MWarehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// INV_TIER_4.md §6.2 (OnhandGrid) + docs/mockups/mockup_print_master_stock.html.
// Sumber: tm2inv.rm_stockcard dalam rentang tanggal (bukan tm2inv.v_rm_onhand --
// itu snapshot SEKARANG saja, laporan ini butuh pecahan masuk/keluar/adj per
// periode). Hanya item RM yang punya pergerakan di rentang yang tampil --
// SFG/FG belum ada data riil.
new #[Title('Laporan Master Stok — TELASIH MAKMUR ERP')] class extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $warehouseId = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    #[Computed]
    public function warehouses()
    {
        return MWarehouse::orderBy('warehouse_name')->get();
    }

    // Kelompokkan baris rm_stockcard dalam rentang per material, lalu per
    // material per lokasi (saldo awal/akhir adalah snapshot per-lokasi --
    // dijumlah lintas lokasi utk baris ringkas per item).
    #[Computed]
    public function report(): array
    {
        $rows = DB::connection('tm2inv')->table('rm_stockcard')
            ->when($this->warehouseId, fn ($q) => $q->where('warehouse_id', $this->warehouseId))
            ->whereBetween('doc_date', ["{$this->dateFrom} 00:00:00", "{$this->dateTo} 23:59:59"])
            ->orderBy('doc_date')->orderBy('doc_id')
            ->get();

        if ($rows->isEmpty()) {
            return ['groups' => collect(), 'totals' => ['bb' => 0, 'in' => 0, 'out' => 0, 'adj' => 0, 'eb' => 0], 'itemCount' => 0];
        }

        $materialIds = $rows->pluck('material_id')->unique();
        $materials = MRawMaterial::whereIn('material_id', $materialIds)->get()->keyBy('material_id');
        $warehouseNames = MWarehouse::whereIn('id_warehouse', $rows->pluck('warehouse_id')->unique())->pluck('warehouse_name', 'id_warehouse');

        $byMaterial = $rows->groupBy('material_id')->map(function ($materialRows, $materialId) use ($materials, $warehouseNames) {
            $byLocation = $materialRows->groupBy('location_id');
            $bb = $byLocation->sum(fn ($r) => (float) $r->first()->qty_bb);
            $eb = $byLocation->sum(fn ($r) => (float) $r->last()->qty_eb);

            $m = $materials->get($materialId);

            return (object) [
                'material_id' => $materialId,
                'material_code' => $m?->material_code ?? "#{$materialId}",
                'material_name' => $m?->material_name ?? '(item tidak ditemukan di master)',
                'category1' => $m?->category1 ?: 'Tanpa Kategori',
                'uom_stock' => $m?->uom_stock,
                'min_stock' => $m?->min_stock,
                'warehouse_names' => $materialRows->pluck('warehouse_id')->unique()->map(fn ($id) => $warehouseNames->get($id))->filter()->implode(', '),
                'qty_bb' => $bb,
                'qty_in' => (float) $materialRows->sum('qty_in'),
                'qty_out' => (float) $materialRows->sum('qty_out'),
                'qty_adj' => (float) $materialRows->sum('qty_adj'),
                'qty_eb' => $eb,
            ];
        })->values();

        $groups = $byMaterial->groupBy('category1')->sortKeys();

        return [
            'groups' => $groups,
            'totals' => [
                'bb' => $byMaterial->sum('qty_bb'),
                'in' => $byMaterial->sum('qty_in'),
                'out' => $byMaterial->sum('qty_out'),
                'adj' => $byMaterial->sum('qty_adj'),
                'eb' => $byMaterial->sum('qty_eb'),
            ],
            'itemCount' => $byMaterial->count(),
        ];
    }
};
?>
<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50 no-print">
        <b class="text-[15px]">📋 Laporan Master Stok</b>
        <a href="{{ route('menu') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Menu</a>
    </header>

    {{-- ============ FILTER (tidak ikut cetak) ============ --}}
    <div class="no-print max-w-[1000px] mx-auto my-4 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-[10px] text-muted uppercase font-semibold">Dari Tanggal</label>
                <input type="date" wire:model.live="dateFrom" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[10px] text-muted uppercase font-semibold">Sampai Tanggal</label>
                <input type="date" wire:model.live="dateTo" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[10px] text-muted uppercase font-semibold">Gudang</label>
                <select wire:model.live="warehouseId" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    <option value="">Semua Gudang</option>
                    @foreach ($this->warehouses as $w)
                        <option value="{{ $w->id_warehouse }}">{{ $w->warehouse_code }} — {{ $w->warehouse_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">🖨️ Cetak</button>
        </div>
    </div>

    <div class="sheet mx-auto my-4 bg-white border border-[#d7dbe2]" style="width:297mm;padding:10mm;font-size:9pt">
        <div class="flex justify-between border-b-2 border-[#1f2937] pb-3 mb-3">
            <div>
                <div class="text-[13pt] font-bold text-navy">CV TELASIH MAKMUR GROUP</div>
                <div class="text-[8.5pt] text-muted">Offset · Digital · Indostry Packaging</div>
            </div>
            <div class="text-right">
                <div class="text-[14pt] font-bold text-navy uppercase">Laporan Master Stok</div>
                <div class="text-[8.5pt] text-muted mt-1">Dicetak {{ now()->translatedFormat('d/m/Y H:i') }} oleh {{ Auth::user()->name }}</div>
            </div>
        </div>

        <div class="border border-[#c8ccd4] rounded-sm p-2.5 bg-[#fbfcfe] mb-3 flex gap-8 flex-wrap">
            <div><div class="text-[7.5pt] text-muted uppercase">Tanggal Awal</div><div class="text-[10pt] font-bold text-navy">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">Tanggal Akhir</div><div class="text-[10pt] font-bold text-navy">{{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">Kelas Stok</div><div class="text-[10pt] font-bold text-navy">Bahan Baku (RM)</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">Gudang</div><div class="text-[10pt] font-bold text-navy">{{ $warehouseId ? $this->warehouses->firstWhere('id_warehouse', $warehouseId)?->warehouse_name : 'Semua Gudang' }}</div></div>
        </div>

        <div class="grid grid-cols-5 gap-2.5 mb-3">
            <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Total Item</div><div class="text-[12pt] font-bold text-navy">{{ $this->report['itemCount'] }}</div></div>
            <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Total Saldo Awal</div><div class="text-[12pt] font-bold text-navy">{{ number_format($this->report['totals']['bb'], 2) }}</div></div>
            <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Total Masuk</div><div class="text-[12pt] font-bold text-[#0f9d58]">+{{ number_format($this->report['totals']['in'], 2) }}</div></div>
            <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Total Keluar</div><div class="text-[12pt] font-bold text-[#b71c1c]">−{{ number_format($this->report['totals']['out'], 2) }}</div></div>
            <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Total Saldo Akhir</div><div class="text-[12pt] font-bold text-navy">{{ number_format($this->report['totals']['eb'], 2) }}</div></div>
        </div>

        <table class="w-full text-[8.5pt] border-collapse">
            <thead>
                <tr class="bg-navy text-white">
                    <th class="text-center px-1.5 py-1 border border-navy">No.</th>
                    <th class="text-left px-1.5 py-1 border border-navy">Kode Item</th>
                    <th class="text-left px-1.5 py-1 border border-navy">Nama Item</th>
                    <th class="text-left px-1.5 py-1 border border-navy">Gudang</th>
                    <th class="text-center px-1.5 py-1 border border-navy">UOM</th>
                    <th class="text-right px-1.5 py-1 border border-navy">Saldo Awal</th>
                    <th class="text-right px-1.5 py-1 border border-navy">Masuk</th>
                    <th class="text-right px-1.5 py-1 border border-navy">Keluar</th>
                    <th class="text-right px-1.5 py-1 border border-navy">Penyesuaian</th>
                    <th class="text-right px-1.5 py-1 border border-navy">Saldo Akhir</th>
                    <th class="text-center px-1.5 py-1 border border-navy">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->report['groups'] as $category => $items)
                    <tr class="bg-[#eef1f6]"><td colspan="11" class="px-1.5 py-1 font-bold text-navy uppercase text-[8pt]">{{ $category }} · {{ $items->count() }} item</td></tr>
                    @foreach ($items as $i => $row)
                        <tr class="border-b border-[#eef1f5]">
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-center">{{ $i + 1 }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] font-mono font-bold text-accent">{{ $row->material_code }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5]">{{ $row->material_name }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5]">{{ $row->warehouse_names ?: '—' }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-center">{{ $row->uom_stock }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-right">{{ number_format($row->qty_bb, 2) }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-right text-[#0f9d58]">{{ number_format($row->qty_in, 2) }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-right text-[#b71c1c]">{{ number_format($row->qty_out, 2) }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-right {{ $row->qty_adj != 0 ? '' : 'text-[#8b94a5]' }}">{{ number_format($row->qty_adj, 2) }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-right font-bold">{{ number_format($row->qty_eb, 2) }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-center">
                                @if ($row->min_stock !== null && $row->qty_eb < (float) $row->min_stock)
                                    <span class="text-[7.5pt] font-bold px-1.5 py-0.5 rounded-full bg-[#fbeaea] text-[#a3241f]">RENDAH</span>
                                @else
                                    <span class="text-[7.5pt] font-bold px-1.5 py-0.5 rounded-full bg-[#eaf7ee] text-[#1f8a4c]">OK</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="11" class="text-center py-8 text-muted">Tidak ada pergerakan stok pada periode ini.</td></tr>
                @endforelse
            </tbody>
            @if ($this->report['itemCount'] > 0)
                <tfoot>
                    <tr class="bg-[#f4f6fa] font-bold">
                        <td class="px-1.5 py-1.5 border border-[#c8ccd4]" colspan="5">Total Keseluruhan · {{ $this->report['itemCount'] }} item</td>
                        <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->report['totals']['bb'], 2) }}</td>
                        <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->report['totals']['in'], 2) }}</td>
                        <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->report['totals']['out'], 2) }}</td>
                        <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->report['totals']['adj'], 2) }}</td>
                        <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->report['totals']['eb'], 2) }}</td>
                        <td class="px-1.5 py-1.5 border border-[#c8ccd4]"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            .sheet { box-shadow: none !important; margin: 0 !important; width: auto !important; }
        }
    </style>
</div>
