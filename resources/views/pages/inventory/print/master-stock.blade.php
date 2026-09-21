<?php

use App\Models\Master\MatRaw;
use App\Models\Master\WhWarehouse;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// inventory_tier4.md §6.2 (OnhandGrid) + docs/mockups/mockup_print_master_stock.html.
// Sumber: tm2inv.v_inv_movement (view UNION RM/SFG/FG yang sudah menormalkan
// item_id/item_code/item_name -- lihat komentar sama di print/stock-card.blade.php)
// dalam rentang tanggal -- bukan v_rm_onhand/v_fg_onhand/v_sfg_onhand, itu
// snapshot SEKARANG saja, laporan ini butuh pecahan masuk/keluar/adj per
// periode. SFG/FG masih data uji (bukan produksi sungguhan) -- Stock In/Out
// belum digarap.
//
// {category} WAJIB di URL (bukan toggle dalam halaman) -- sidebarmenustandard.md
// §5 (2026-08-22): user minta 3 kelas jadi 3 MENU terpisah (RM/FG/SFG),
// baik utk Input maupun Laporan, karena recordnya memang masuk ke tabel
// fisik yang beda (rm_stockcard/fg_stockcard/sfg_stockcard). Satu file
// Livewire ini tetap dipakai bertiga (reuse kode), cuma dituju oleh 3 route
// param berbeda -- bukan 3 file terpisah.
//
// INTERAKTIF + COMPACT (2026-08-28, permintaan user): pencarian cepat +
// kolom bisa di-sort + grup bisa dilipat -- state SEMUA server-side lewat
// Livewire ($collapsedGroups), pola sama dgn expand-row grid master-detail
// (griduistandard.md §9), BUKAN Alpine local-state -- konsisten dgn konvensi
// codebase ini (single-file Livewire component, minim Alpine, semua state
// dokumen lewat PHP). Ini juga TIDAK mengubah keluarga layar ini dari
// "Laporan" ke "Nota" -- printnotastandard.md §1 tegas: dua keluarga itu
// TIDAK boleh dicampur (Laporan = header navy+Back, landscape, data
// internal; Nota = <x-print.document>, A4 potrait, dokumen ke pihak luar).
// Kontrol interaktif (search/sort/chevron) disembunyikan total saat cetak
// (`.no-print`); grup TETAP dirender penuh ke cetak apa pun state
// collapse-nya di layar (lihat cetak() -- collapsedGroups direset sesaat
// sebelum window.print(), bukan cuma disembunyikan via CSS, supaya hasil
// cetak selalu lengkap tanpa bergantung timing render ulang Livewire).
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

    public string $quickSearch = '';

    public string $sortBy = 'item_code';

    public string $sortDir = 'asc';

    /** @var array<int, string> nama grup yang sedang dilipat -- default semua terbuka. */
    public array $collapsedGroups = [];

    public function mount(string $category): void
    {
        $category = strtoupper($category);
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404);

        $this->category = $category;
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function toggleGroup(string $groupName): void
    {
        if (in_array($groupName, $this->collapsedGroups, true)) {
            $this->collapsedGroups = array_values(array_diff($this->collapsedGroups, [$groupName]));
        } else {
            $this->collapsedGroups[] = $groupName;
        }
    }

    // Cetak wajib lengkap apa pun state lipat di layar -- buka semua grup
    // dulu (round-trip Livewire biasa), baru event browser trigger window.print()
    // SETELAH re-render selesai (bukan CSS override tebak-tebakan timing).
    public function printReport(): void
    {
        $this->collapsedGroups = [];
        $this->dispatch('print-report');
    }

    // Klik header kolom: field baru -> asc; kolom sama -> asc->desc->reset
    // (kembali ke default item_code/asc), pola "3-arah" griduistandard.md §10.
    public function sortToggle(string $field): void
    {
        if ($this->sortBy !== $field) {
            $this->sortBy = $field;
            $this->sortDir = 'asc';

            return;
        }

        $this->sortDir = match ($this->sortDir) {
            'asc' => 'desc',
            default => 'asc',
        };

        if ($this->sortDir === 'asc' && $field !== 'item_code') {
            // baris ketiga klik pada kolom non-default -> reset ke default.
            $this->sortBy = 'item_code';
            $this->sortDir = 'asc';
        }
    }

    public function sortIcon(string $field): string
    {
        if ($this->sortBy !== $field) {
            return '↕';
        }

        return $this->sortDir === 'asc' ? '▲' : '▼';
    }

    #[Computed]
    public function warehouses()
    {
        return WhWarehouse::orderBy('warehouse_name')->get();
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
            default => MatRaw::whereIn('material_id', $ids)->get()->keyBy('material_id')->map(fn ($m) => [
                'code' => $m->material_code, 'name' => $m->material_name, 'group' => $m->category1 ?: 'Tanpa Kategori',
                'uom' => $m->uom_stock, 'min_stock' => $m->min_stock,
            ])->all(),
        };
    }

    // Kelompokkan baris v_inv_movement dalam rentang per item, lalu per item
    // per lokasi (saldo awal/akhir adalah snapshot per-lokasi -- dijumlah
    // lintas lokasi utk baris ringkas per item). quickSearch (kode/nama) &
    // sortBy/sortDir diterapkan ke $byItem SEBELUM regroup, supaya urutan di
    // dalam tiap grup ikut sort yang dipilih, bukan cuma urutan grup.
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
        $warehouseNames = WhWarehouse::whereIn('id_warehouse', $rows->pluck('warehouse_id')->unique())->pluck('warehouse_name', 'id_warehouse');

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

        if (trim($this->quickSearch) !== '') {
            $term = mb_strtolower(trim($this->quickSearch));
            $byItem = $byItem->filter(fn ($row) => str_contains(mb_strtolower($row->item_code), $term) || str_contains(mb_strtolower($row->item_name), $term))->values();
        }

        $sortField = in_array($this->sortBy, ['item_code', 'item_name', 'qty_bb', 'qty_in', 'qty_out', 'qty_adj', 'qty_eb'], true) ? $this->sortBy : 'item_code';
        $byItem = $this->sortDir === 'desc' ? $byItem->sortByDesc($sortField)->values() : $byItem->sortBy($sortField)->values();

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
        <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/15">{{ self::CATEGORY_LABELS[$category] }}</span>
        <x-back-button :fallback="route('menu')" title="Kembali ke Menu Utama" class="ml-auto" />
    </header>

    {{-- ============ FILTER (tidak ikut cetak) ============ --}}
    <div class="no-print max-w-[1100px] mx-auto my-4 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="shrink-0"><x-form.date-picker label="Dari Tanggal" wire:model.live="dateFrom" /></div>
                <div class="shrink-0"><x-form.date-picker label="Sampai Tanggal" wire:model.live="dateTo" /></div>
                <div class="flex flex-col gap-1 shrink-0">
                    <label class="text-[10px] text-muted uppercase font-semibold">Gudang</label>
                    <select wire:model.live="warehouseId" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        <option value="">Semua Gudang</option>
                        @foreach ($this->warehouses as $w)
                            <option value="{{ $w->id_warehouse }}">{{ $w->warehouse_code }} — {{ $w->warehouse_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1 shrink-0" style="min-width:220px">
                    <label class="text-[10px] text-muted uppercase font-semibold">Cari Item</label>
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm">🔍</span>
                        <div class="flex-1"><x-form.input wire:model.live.debounce.300ms="quickSearch" placeholder="Kode / nama item..." /></div>
                    </div>
                </div>
                <span class="flex-1"></span>
                <button type="button" wire:click="printReport" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer shrink-0">🖨️ Cetak</button>
            </div>
        </div>
    </div>

    <div class="sheet mx-auto my-4 bg-white border border-[#d7dbe2] rounded-lg shadow-sm" style="width:297mm;padding:10mm;font-size:9pt" x-on:print-report.window="window.print()">
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

        <div class="border border-[#c8ccd4] rounded-md p-2.5 bg-[#fbfcfe] mb-3 flex gap-8 flex-wrap">
            <div><div class="text-[7.5pt] text-muted uppercase">Tanggal Awal</div><div class="text-[10pt] font-bold text-navy">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">Tanggal Akhir</div><div class="text-[10pt] font-bold text-navy">{{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">Kelas Stok</div><div class="text-[10pt] font-bold text-navy">{{ self::CATEGORY_LABELS[$category] }}</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">Gudang</div><div class="text-[10pt] font-bold text-navy">{{ $warehouseId ? $this->warehouses->firstWhere('id_warehouse', $warehouseId)?->warehouse_name : 'Semua Gudang' }}</div></div>
            @if (trim($quickSearch) !== '')
                <div class="no-print"><div class="text-[7.5pt] text-muted uppercase">Filter Pencarian</div><div class="text-[10pt] font-bold text-accent">"{{ $quickSearch }}"</div></div>
            @endif
        </div>

        <div class="grid five mb-3">
            <div class="rounded-lg p-2.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb] text-center"><p class="text-[9.5px] font-bold uppercase text-[#3b5b99]">Total Item</p><p class="text-lg font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->report['itemCount'] }}</p></div>
            <div class="rounded-lg p-2.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3] text-center"><p class="text-[9.5px] font-bold uppercase text-[#8a6d00]">Saldo Awal</p><p class="text-lg font-extrabold text-[#b45309] mt-0.5">{{ number_format($this->report['totals']['bb'], 2) }}</p></div>
            <div class="rounded-lg p-2.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6] text-center"><p class="text-[9.5px] font-bold uppercase text-[#2f7a52]">Masuk</p><p class="text-lg font-extrabold text-[#16a34a] mt-0.5">+{{ number_format($this->report['totals']['in'], 2) }}</p></div>
            <div class="rounded-lg p-2.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6] text-center"><p class="text-[9.5px] font-bold uppercase text-[#a3453f]">Keluar</p><p class="text-lg font-extrabold text-[#c0392b] mt-0.5">−{{ number_format($this->report['totals']['out'], 2) }}</p></div>
            <div class="rounded-lg p-2.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb] text-center"><p class="text-[9.5px] font-bold uppercase text-[#3b5b99]">Saldo Akhir</p><p class="text-lg font-extrabold text-[#1d4ed8] mt-0.5">{{ number_format($this->report['totals']['eb'], 2) }}</p></div>
        </div>

        <div class="rounded-lg border border-[#c8ccd4] overflow-hidden">
            <table class="w-full text-[8.5pt] border-collapse">
                <thead>
                    <tr class="bg-navy text-white">
                        <th class="w-[26px] px-1 py-1.5 border border-navy no-print"></th>
                        <th class="text-center px-1.5 py-1.5 border border-navy">No.</th>
                        <th class="text-left px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('item_code')" title="Urutkan">Kode Item <span class="opacity-80">{{ $this->sortIcon('item_code') }}</span></th>
                        <th class="text-left px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('item_name')" title="Urutkan">Nama Item <span class="opacity-80">{{ $this->sortIcon('item_name') }}</span></th>
                        <th class="text-left px-1.5 py-1.5 border border-navy">Gudang</th>
                        <th class="text-center px-1.5 py-1.5 border border-navy">UOM</th>
                        <th class="text-right px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('qty_bb')" title="Urutkan">Saldo Awal <span class="opacity-80">{{ $this->sortIcon('qty_bb') }}</span></th>
                        <th class="text-right px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('qty_in')" title="Urutkan">Masuk <span class="opacity-80">{{ $this->sortIcon('qty_in') }}</span></th>
                        <th class="text-right px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('qty_out')" title="Urutkan">Keluar <span class="opacity-80">{{ $this->sortIcon('qty_out') }}</span></th>
                        <th class="text-right px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('qty_adj')" title="Urutkan">Penyesuaian <span class="opacity-80">{{ $this->sortIcon('qty_adj') }}</span></th>
                        <th class="text-right px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('qty_eb')" title="Urutkan">Saldo Akhir <span class="opacity-80">{{ $this->sortIcon('qty_eb') }}</span></th>
                        <th class="text-center px-1.5 py-1.5 border border-navy">Status</th>
                    </tr>
                </thead>
                @forelse ($this->report['groups'] as $groupName => $items)
                    @php($isCollapsed = in_array($groupName, $collapsedGroups, true))
                    <tbody wire:key="group-{{ Str::slug($groupName) }}">
                        <tr class="bg-[#eef1f6] cursor-pointer hover:bg-[#e5e9f1]" wire:click="toggleGroup('{{ $groupName }}')">
                            <td class="px-1 py-1.5 text-center no-print"><span class="text-navy font-bold">{{ $isCollapsed ? '▸' : '▾' }}</span></td>
                            <td colspan="11" class="px-1.5 py-1.5 font-bold text-navy uppercase text-[8pt]">{{ $groupName }} · {{ $items->count() }} item</td>
                        </tr>
                        @if (! $isCollapsed)
                            @foreach ($items as $i => $row)
                                <tr class="border-b border-[#eef1f5] {{ $i % 2 === 1 ? 'bg-[#fafbfc]' : 'bg-white' }} hover:bg-[#eef3fb]">
                                    <td class="no-print"></td>
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
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="12" class="text-center py-8 text-muted">Tidak ada pergerakan stok pada periode ini.</td></tr>
                    </tbody>
                @endforelse
                @if ($this->report['itemCount'] > 0)
                    <tfoot>
                        <tr class="bg-[#f4f6fa] font-bold">
                            <td class="px-1.5 py-1.5 border border-[#c8ccd4] no-print"></td>
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
    </div>

    <style>
        thead tr { position: sticky; top: 0; z-index: 5; }
        @media print {
            .no-print { display: none !important; }
            .sheet { box-shadow: none !important; margin: 0 !important; width: auto !important; border-radius: 0 !important; }
        }
    </style>
</div>
