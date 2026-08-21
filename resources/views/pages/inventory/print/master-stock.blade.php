<?php

use App\Models\Master\MRawMaterial;
use App\Models\Master\MWarehouse;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// INV_TIER_4.md §6.2 (OnhandGrid) + docs/mockups/mockup_print_master_stock.html.
// Sumber: tm2inv.v_inv_movement (view UNION RM/SFG/FG yang sudah menormalkan
// item_id/item_code/item_name -- lihat komentar sama di print/stock-card.blade.php)
// dalam rentang tanggal -- bukan v_rm_onhand/v_fg_onhand/v_sfg_onhand, itu
// snapshot SEKARANG saja, laporan ini butuh pecahan masuk/keluar/adj per
// periode. SFG/FG masih data uji (bukan produksi sungguhan) -- Stock In/Out
// belum digarap.
//
// {category} WAJIB di URL (bukan toggle dalam halaman) -- SIDEBAR_MENU_STANDARD.md
// §5 (2026-08-22): user minta 3 kelas jadi 3 MENU terpisah (RM/FG/SFG),
// baik utk Input maupun Laporan, karena recordnya memang masuk ke tabel
// fisik yang beda (rm_stockcard/fg_stockcard/sfg_stockcard). Satu file
// Livewire ini tetap dipakai bertiga (reuse kode), cuma dituju oleh 3 route
// param berbeda -- bukan 3 file terpisah.
new #[Title('Laporan Master Stok — TELASIH MAKMUR ERP')] class extends Component
{
    public const CATEGORY_LABELS = [
        'RM' => 'Bahan Baku & Bahan Pembantu',
        'SFG' => 'Barang Setengah Jadi',
        'FG' => 'Barang Jadi',
    ];

    public string $category = 'RM';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $warehouseId = null;

    public function mount(string $category): void
    {
        $category = strtoupper($category);
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404);

        $this->category = $category;
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    #[Computed]
    public function warehouses()
    {
        return MWarehouse::orderBy('warehouse_name')->get();
    }

    // Peta item_id -> [kode, nama, kategori-grup, uom, min_stock] generik per
    // kelas stok -- dipakai buat "menerjemahkan" baris v_inv_movement yang
    // sudah netral kelas, tapi grouping/min_stock tetap perlu master aslinya.
    protected function itemMeta(array $ids): array
    {
        return match ($this->category) {
            'SFG' => ProdPart::whereIn('id_part', $ids)->get()->keyBy('id_part')->map(fn ($p) => [
                'code' => $p->part_code, 'name' => $p->part_name, 'group' => 'Barang Setengah Jadi',
                'uom' => $p->uom_stock, 'min_stock' => $p->min_stock,
            ])->all(),
            'FG' => ProdProduct::with('category')->whereIn('id_product', $ids)->get()->keyBy('id_product')->map(fn ($p) => [
                'code' => $p->product_code, 'name' => $p->product_name, 'group' => $p->category?->category_name ?: 'Tanpa Kategori',
                'uom' => $p->uom_stock, 'min_stock' => $p->min_stock,
            ])->all(),
            default => MRawMaterial::whereIn('material_id', $ids)->get()->keyBy('material_id')->map(fn ($m) => [
                'code' => $m->material_code, 'name' => $m->material_name, 'group' => $m->category1 ?: 'Tanpa Kategori',
                'uom' => $m->uom_stock, 'min_stock' => $m->min_stock,
            ])->all(),
        };
    }

    // Kelompokkan baris v_inv_movement dalam rentang per item, lalu per item
    // per lokasi (saldo awal/akhir adalah snapshot per-lokasi -- dijumlah
    // lintas lokasi utk baris ringkas per item).
    #[Computed]
    public function report(): array
    {
        $rows = DB::connection('tm2inv')->table('v_inv_movement')
            ->where('stock_class', $this->category)
            ->when($this->warehouseId, fn ($q) => $q->where('warehouse_id', $this->warehouseId))
            ->whereBetween('doc_date', ["{$this->dateFrom} 00:00:00", "{$this->dateTo} 23:59:59"])
            ->orderBy('doc_date')->orderBy('doc_id')
            ->get();

        if ($rows->isEmpty()) {
            return ['groups' => collect(), 'totals' => ['bb' => 0, 'in' => 0, 'out' => 0, 'adj' => 0, 'eb' => 0], 'itemCount' => 0];
        }

        $itemIds = $rows->pluck('item_id')->unique()->all();
        $meta = $this->itemMeta($itemIds);
        $warehouseNames = MWarehouse::whereIn('id_warehouse', $rows->pluck('warehouse_id')->unique())->pluck('warehouse_name', 'id_warehouse');

        $byItem = $rows->groupBy('item_id')->map(function ($itemRows, $itemId) use ($meta, $warehouseNames) {
            $byLocation = $itemRows->groupBy('location_id');
            $bb = $byLocation->sum(fn ($r) => (float) $r->first()->qty_bb);
            $eb = $byLocation->sum(fn ($r) => (float) $r->last()->qty_eb);

            $m = $meta[$itemId] ?? null;

            return (object) [
                'item_id' => $itemId,
                'item_code' => $m['code'] ?? "#{$itemId}",
                'item_name' => $m['name'] ?? '(item tidak ditemukan di master)',
                'group' => $m['group'] ?? 'Tanpa Kategori',
                'uom' => $m['uom'] ?? null,
                'min_stock' => $m['min_stock'] ?? null,
                'warehouse_names' => $itemRows->pluck('warehouse_id')->unique()->map(fn ($id) => $warehouseNames->get($id))->filter()->implode(', '),
                'qty_bb' => $bb,
                'qty_in' => (float) $itemRows->sum('qty_in'),
                'qty_out' => (float) $itemRows->sum('qty_out'),
                'qty_adj' => (float) $itemRows->sum('qty_adj'),
                'qty_eb' => $eb,
            ];
        })->values();

        $groups = $byItem->groupBy('group')->sortKeys();

        return [
            'groups' => $groups,
            'totals' => [
                'bb' => $byItem->sum('qty_bb'),
                'in' => $byItem->sum('qty_in'),
                'out' => $byItem->sum('qty_out'),
                'adj' => $byItem->sum('qty_adj'),
                'eb' => $byItem->sum('qty_eb'),
            ],
            'itemCount' => $byItem->count(),
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
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4">
            <div class="flex gap-1.5 mb-3">
                @foreach (self::CATEGORY_LABELS as $code => $label)
                    <a href="{{ route('inv.print.master-stock', ['category' => $code]) }}"
                        class="px-3 py-1.5 rounded-md text-[11px] font-semibold no-underline border {{ $category === $code ? 'bg-[#2c3e69] text-white border-[#2c3e69]' : 'bg-white text-[#556] border-[#d7dbe2] hover:bg-[#f4f6fa]' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex flex-wrap items-end gap-3">
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
            <div><div class="text-[7.5pt] text-muted uppercase">Kelas Stok</div><div class="text-[10pt] font-bold text-navy">{{ self::CATEGORY_LABELS[$category] }}</div></div>
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
                @forelse ($this->report['groups'] as $groupName => $items)
                    <tr class="bg-[#eef1f6]"><td colspan="11" class="px-1.5 py-1 font-bold text-navy uppercase text-[8pt]">{{ $groupName }} · {{ $items->count() }} item</td></tr>
                    @foreach ($items as $i => $row)
                        <tr class="border-b border-[#eef1f5]">
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-center">{{ $i + 1 }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] font-mono font-bold text-accent">{{ $row->item_code }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5]">{{ $row->item_name }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5]">{{ $row->warehouse_names ?: '—' }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-center">{{ $row->uom }}</td>
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
