<?php

use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// inventory_tier4.md §6.1 + docs/mockups/mockup_print_stock_card.html. Sumber:
// tm2inv.v_inv_movement -- view UNION yang sudah menormalkan RM (rm_stockcard
// + mat_raw) / SFG (sfg_stockcard + prod_part) / FG (fg_stockcard +
// prod_product) jadi kolom seragam (item_id/item_code/item_name), jadi satu
// query generik cukup utk 3 kelas stok, tinggal ganti stock_class + sumber
// picker item. SFG/FG masih 0 baris nyata (baru diisi data uji) -- Stock
// In/Out sungguhan belum digarap.
//
// {category} WAJIB di URL (bukan toggle dalam halaman) -- sidebarmenustandard.md
// §5 (2026-08-22): user minta 3 kelas jadi 3 MENU terpisah (RM/FG/SFG),
// baik utk Input maupun Laporan, karena recordnya memang masuk ke tabel
// fisik yang beda (rm_stockcard/fg_stockcard/sfg_stockcard). Satu file
// Livewire ini tetap dipakai bertiga (reuse kode), cuma dituju oleh 3 route
// param berbeda -- bukan 3 file terpisah.
//
// COMPACT + MENARIK (2026-08-28, permintaan user): badge tipe transaksi
// berwarna (IN hijau/OUT merah/lainnya abu), header tabel sticky, zebra +
// hover baris -- TETAP keluarga "Laporan" (header navy+Back, bukan
// <x-print.document>), lihat catatan sama di master-stock.blade.php.
// Ledger TIDAK diberi kolom sortable -- urutan kronologis wajib (saldo
// berjalan bergantung urutan tanggal/doc_id), sort bebas akan merusak makna
// datanya, beda dari master-stock yang tiap baris independen per item.
new #[Title('Kartu Stok — TELASIH MAKMUR ERP')] class extends Component
{
    public const CATEGORY_LABELS = [
        'RM' => 'Bahan Baku & Bahan Pembantu',
        'SFG' => 'Barang Setengah Jadi',
        'FG' => 'Barang Jadi',
    ];

    public string $category = 'RM';

    public ?int $materialId = null;

    public ?int $locationId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $materialSearch = '';

    public function mount(string $category, ?int $material = null): void
    {
        $category = strtoupper($category);
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404);

        $this->category = $category;
        $this->materialId = $material;
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    // Item terpilih, dinormalisasi jadi bentuk seragam (code/name/uom) supaya
    // Blade tidak perlu tahu category-nya lagi utk sekadar menampilkan header.
    #[Computed]
    public function material(): ?array
    {
        if (! $this->materialId) {
            return null;
        }

        return match ($this->category) {
            'SFG' => optional(ProdPart::find($this->materialId), fn ($p) => ['code' => $p->part_code, 'name' => $p->part_name, 'uom' => $p->uom_stock]),
            'FG' => optional(ProdProduct::find($this->materialId), fn ($p) => ['code' => $p->product_code, 'name' => $p->product_name, 'uom' => $p->uom_stock]),
            default => optional(MatRaw::find($this->materialId), fn ($m) => ['code' => $m->material_code, 'name' => $m->material_name, 'uom' => $m->uom_stock]),
        };
    }

    #[Computed]
    public function materialResults()
    {
        if (mb_strlen(trim($this->materialSearch)) < 2) {
            return collect();
        }

        $term = $this->materialSearch;

        return match ($this->category) {
            'SFG' => ProdPart::where('part_code', 'like', "%{$term}%")->orWhere('part_name', 'like', "%{$term}%")
                ->limit(15)->get()->map(fn ($p) => ['id' => $p->id_part, 'code' => $p->part_code, 'name' => $p->part_name]),
            'FG' => ProdProduct::where('product_code', 'like', "%{$term}%")->orWhere('product_name', 'like', "%{$term}%")
                ->limit(15)->get()->map(fn ($p) => ['id' => $p->id_product, 'code' => $p->product_code, 'name' => $p->product_name]),
            default => MatRaw::where('material_code', 'like', "%{$term}%")->orWhere('material_name', 'like', "%{$term}%")
                ->limit(15)->get()->map(fn ($m) => ['id' => $m->material_id, 'code' => $m->material_code, 'name' => $m->material_name]),
        };
    }

    public function pickMaterial(int $materialId): void
    {
        $this->materialId = $materialId;
        $this->materialSearch = '';
        unset($this->rows, $this->material);
    }

    #[Computed]
    public function locationName(): ?string
    {
        return $this->locationId
            ? DB::connection('tm2mst')->table('wh_loc')->where('location_id', $this->locationId)->value('location_name')
            : null;
    }

    // Baris kartu stok dalam rentang tanggal + saldo awal sebelum rentang
    // (dari baris v_inv_movement terakhir sebelum $dateFrom, bukan dihitung ulang).
    #[Computed]
    public function ledger(): array
    {
        if (! $this->materialId) {
            return ['rows' => collect(), 'openingBalance' => 0];
        }

        $query = DB::connection('tm2inv')->table('v_inv_movement')
            ->where('stock_class', $this->category)
            ->where('item_id', $this->materialId)
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->whereBetween('doc_date', ["{$this->dateFrom} 00:00:00", "{$this->dateTo} 23:59:59"])
            ->orderBy('doc_date')->orderBy('doc_id');

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $openingBalance = DB::connection('tm2inv')->table('v_inv_movement')
                ->where('stock_class', $this->category)
                ->where('item_id', $this->materialId)
                ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
                ->where('doc_date', '<', "{$this->dateFrom} 00:00:00")
                ->orderByDesc('doc_date')->orderByDesc('doc_id')
                ->value('qty_eb') ?? 0;
        } else {
            $openingBalance = (float) $rows->first()->qty_bb;
        }

        return ['rows' => $rows, 'openingBalance' => (float) $openingBalance];
    }

    // Badge tipe dokumen -- warna per makna (masuk/keluar/penyesuaian/lainnya),
    // BUKAN palet baru per status (griduistandard.md §2: pilih dari 5 warna baku).
    public function docCatBadge(?string $docCat): array
    {
        return match ($docCat) {
            'STOCK' => ['bg' => '#e9f9ef', 'fg' => '#2f7a52'],
            'BB' => ['bg' => '#e9f9ef', 'fg' => '#2f7a52'],
            'RETURN' => ['bg' => '#eaf2ff', 'fg' => '#3b5b99'],
            'AD', 'OP', 'ADJUST', 'OPNAME' => ['bg' => '#fef6e7', 'fg' => '#8a6d00'],
            default => ['bg' => '#f1f3f6', 'fg' => '#5b6472'],
        };
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->ledger['rows'];

        return [
            'in' => (float) $rows->sum('qty_in'),
            'out' => (float) $rows->sum('qty_out'),
            'adj' => (float) $rows->sum('qty_adj'),
            'closing' => $rows->isNotEmpty() ? (float) $rows->last()->qty_eb : $this->ledger['openingBalance'],
        ];
    }
};
?>
<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50 no-print">
        <b class="text-[15px]">🧾 Kartu Stok</b>
        <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/15">{{ self::CATEGORY_LABELS[$category] }}</span>
        <a href="{{ route('menu') }}" title="Kembali ke Menu Utama"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
        </a>
    </header>

    {{-- ============ FILTER (tidak ikut cetak) ============ --}}
    <div class="no-print max-w-[900px] mx-auto my-4 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1 relative" style="min-width:260px">
                    <label class="text-[10px] text-muted uppercase font-semibold">Item (wajib)</label>
                    <input type="text" wire:model.live.debounce.300ms="materialSearch" placeholder="🔍 Cari kode / nama item..." class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    @if ($this->materialResults->isNotEmpty())
                        <div class="absolute top-full left-0 right-0 mt-1 bg-white border border-[#d7dbe2] rounded shadow-lg z-10 max-h-56 overflow-y-auto">
                            @foreach ($this->materialResults as $m)
                                <button type="button" wire:click="pickMaterial({{ $m['id'] }})" class="w-full text-left px-2.5 py-1.5 text-xs hover:bg-[#eef3fb] border-b border-[#eef1f5] last:border-b-0">
                                    <span class="font-mono font-bold text-accent">{{ $m['code'] }}</span> — {{ $m['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @if ($this->material)
                        <div class="text-[11px] text-navy font-semibold mt-1">{{ $this->material['code'] }} — {{ $this->material['name'] }}</div>
                    @endif
                </div>
                <x-form.date-picker label="Dari Tanggal" wire:model.live="dateFrom" />
                <x-form.date-picker label="Sampai Tanggal" wire:model.live="dateTo" />
                <button type="button" onclick="window.print()" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer" @disabled(! $materialId)>🖨️ Cetak</button>
            </div>
        </div>
    </div>

    @if (! $materialId)
        <div class="max-w-[900px] mx-auto px-4 no-print">
            <p class="text-center text-muted text-sm py-12">Pilih item dulu untuk menampilkan kartu stok.</p>
        </div>
    @else
        <div class="sheet mx-auto my-4 bg-white border border-[#d7dbe2] rounded-lg shadow-sm" style="width:210mm;min-height:150mm;padding:12mm;font-size:10.5px">
            <div class="flex justify-between border-b-2 border-[#1f2937] pb-3 mb-3">
                <div>
                    <div class="text-[14pt] font-bold text-navy">CV TELASIH MAKMUR GROUP</div>
                    <div class="text-[8.5pt] text-muted">Offset · Digital · Indostry Packaging</div>
                </div>
                <div class="text-right">
                    <div class="text-[15pt] font-bold text-navy uppercase">Kartu Stok</div>
                    <div class="text-[9pt] text-muted mt-1">Dicetak {{ now()->translatedFormat('d/m/Y H:i') }} oleh {{ Auth::user()->name }}</div>
                </div>
            </div>

            <div class="border border-[#c8ccd4] rounded-md p-3 bg-[#fbfcfe] mb-3">
                <div class="flex gap-6 flex-wrap">
                    <div><div class="text-[8pt] text-muted uppercase">Kelas Stok</div><div class="text-[10.5pt] font-bold text-navy">{{ self::CATEGORY_LABELS[$category] }}</div></div>
                    <div><div class="text-[8pt] text-muted uppercase">Kode Item</div><div class="text-[10.5pt] font-bold text-navy">{{ $this->material['code'] }}</div></div>
                    <div class="flex-1 min-w-[200px]"><div class="text-[8pt] text-muted uppercase">Nama Item</div><div class="text-[10.5pt] font-bold text-navy">{{ $this->material['name'] }}</div></div>
                    <div><div class="text-[8pt] text-muted uppercase">UOM Stok</div><div class="text-[10.5pt] font-bold text-navy">{{ $this->material['uom'] }}</div></div>
                </div>
                <div class="flex gap-6 flex-wrap mt-2">
                    <div><div class="text-[8pt] text-muted uppercase">Lokasi</div><div class="text-[10.5pt] font-bold text-navy">{{ $this->locationName ?? 'Semua Lokasi' }}</div></div>
                    <div><div class="text-[8pt] text-muted uppercase">Periode</div><div class="text-[10.5pt] font-bold text-navy">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div></div>
                </div>
            </div>

            <div class="rounded-lg border border-[#c8ccd4] overflow-hidden">
                <table class="w-full text-[9pt] border-collapse">
                    <thead>
                        <tr class="bg-navy text-white">
                            <th class="text-left px-1.5 py-1.5 border border-navy">Tanggal</th>
                            <th class="text-left px-1.5 py-1.5 border border-navy">No. Dokumen</th>
                            <th class="text-center px-1.5 py-1.5 border border-navy">Tipe</th>
                            <th class="text-left px-1.5 py-1.5 border border-navy">Referensi</th>
                            <th class="text-left px-1.5 py-1.5 border border-navy">Lot</th>
                            <th class="text-right px-1.5 py-1.5 border border-navy">Masuk</th>
                            <th class="text-right px-1.5 py-1.5 border border-navy">Keluar</th>
                            <th class="text-right px-1.5 py-1.5 border border-navy">Penyesuaian</th>
                            <th class="text-right px-1.5 py-1.5 border border-navy">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-[#fff3d6] font-semibold">
                            <td class="px-1.5 py-1 border border-[#e9ecf1]" colspan="8">— Saldo Awal per {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} —</td>
                            <td class="px-1.5 py-1 border border-[#e9ecf1] text-right">{{ number_format($this->ledger['openingBalance'], 4) }}</td>
                        </tr>
                        @forelse ($this->ledger['rows'] as $i => $r)
                            @php($badge = $this->docCatBadge($r->doc_cat))
                            <tr class="border-b border-[#eef1f5] {{ $i % 2 === 1 ? 'bg-[#fafbfc]' : 'bg-white' }} hover:bg-[#eef3fb]">
                                <td class="px-1.5 py-1 border border-[#eef1f5]">{{ \Carbon\Carbon::parse($r->doc_date)->format('d/m/Y') }}</td>
                                <td class="px-1.5 py-1 border border-[#eef1f5] font-semibold text-navy">{{ $r->doc_no ?: '—' }}</td>
                                <td class="px-1.5 py-1 border border-[#eef1f5] text-center">
                                    <span class="text-[7.5pt] font-bold px-1.5 py-0.5 rounded-full" style="background:{{ $badge['bg'] }};color:{{ $badge['fg'] }}">{{ $r->doc_cat ?: '—' }}</span>
                                </td>
                                <td class="px-1.5 py-1 border border-[#eef1f5]">{{ $r->ref_doc_no ?: '—' }}</td>
                                <td class="px-1.5 py-1 border border-[#eef1f5]">{{ $r->lot_no ?: '—' }}</td>
                                <td class="px-1.5 py-1 border border-[#eef1f5] text-right {{ (float) $r->qty_in > 0 ? 'text-[#0f9d58] font-semibold' : 'text-[#8b94a5]' }}">{{ (float) $r->qty_in > 0 ? number_format($r->qty_in, 4) : '—' }}</td>
                                <td class="px-1.5 py-1 border border-[#eef1f5] text-right {{ (float) $r->qty_out > 0 ? 'text-[#b71c1c] font-semibold' : 'text-[#8b94a5]' }}">{{ (float) $r->qty_out > 0 ? number_format($r->qty_out, 4) : '—' }}</td>
                                <td class="px-1.5 py-1 border border-[#eef1f5] text-right {{ (float) $r->qty_adj != 0 ? 'font-semibold' : 'text-[#8b94a5]' }}">{{ (float) $r->qty_adj != 0 ? number_format($r->qty_adj, 4) : '—' }}</td>
                                <td class="px-1.5 py-1 border border-[#eef1f5] text-right font-semibold">{{ number_format($r->qty_eb, 4) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-6 text-muted">Tidak ada pergerakan pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-[#f4f6fa] font-bold">
                            <td class="px-1.5 py-1.5 border border-[#c8ccd4]" colspan="5">Total pergerakan periode · {{ $this->ledger['rows']->count() }} transaksi</td>
                            <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->totals['in'], 4) }}</td>
                            <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->totals['out'], 4) }}</td>
                            <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->totals['adj'], 4) }}</td>
                            <td class="px-1.5 py-1.5 border border-[#c8ccd4] text-right">{{ number_format($this->totals['closing'], 4) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="grid mt-4">
                <div class="rounded-lg p-2.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb] text-center"><p class="text-[9.5px] font-bold uppercase text-[#3b5b99]">Saldo Awal</p><p class="text-lg font-extrabold text-[#1d4ed8] mt-0.5">{{ number_format($this->ledger['openingBalance'], 2) }}</p></div>
                <div class="rounded-lg p-2.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6] text-center"><p class="text-[9.5px] font-bold uppercase text-[#2f7a52]">Total Masuk</p><p class="text-lg font-extrabold text-[#16a34a] mt-0.5">+{{ number_format($this->totals['in'], 2) }}</p></div>
                <div class="rounded-lg p-2.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6] text-center"><p class="text-[9.5px] font-bold uppercase text-[#a3453f]">Total Keluar</p><p class="text-lg font-extrabold text-[#c0392b] mt-0.5">−{{ number_format($this->totals['out'], 2) }}</p></div>
                <div class="rounded-lg p-2.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb] text-center"><p class="text-[9.5px] font-bold uppercase text-[#3b5b99]">Saldo Akhir</p><p class="text-lg font-extrabold text-[#1d4ed8] mt-0.5">{{ number_format($this->totals['closing'], 2) }}</p></div>
            </div>
        </div>
    @endif

    <style>
        thead tr { position: sticky; top: 0; z-index: 5; }
        @media print {
            .no-print { display: none !important; }
            .sheet { box-shadow: none !important; margin: 0 !important; width: auto !important; border-radius: 0 !important; }
        }
    </style>
</div>
