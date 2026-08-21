<?php

use App\Models\Master\MRawMaterial;
use App\Models\Master\MWarehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// INV_TIER_4.md §6.1 + docs/mockups/mockup_print_stock_card.html. Sumber:
// tm2inv.rm_stockcard (via qty_bb/qty_in/qty_out/qty_adj/qty_eb yang sudah
// jadi snapshot per baris -- tidak dihitung ulang di sini, cukup dibaca).
// SFG/FG belum ada data riil (0 baris) -- layar ini baru RM dulu, kelas lain
// menyusul begitu Stock In/Out digarap dan datanya benar-benar ada.
new #[Title('Kartu Stok — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $materialId = null;

    public ?int $locationId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $materialSearch = '';

    public function mount(?int $material = null): void
    {
        $this->materialId = $material;
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    #[Computed]
    public function material(): ?MRawMaterial
    {
        return $this->materialId ? MRawMaterial::find($this->materialId) : null;
    }

    #[Computed]
    public function materialResults()
    {
        if (mb_strlen(trim($this->materialSearch)) < 2) {
            return collect();
        }

        return MRawMaterial::where('material_code', 'like', "%{$this->materialSearch}%")
            ->orWhere('material_name', 'like', "%{$this->materialSearch}%")
            ->limit(15)->get();
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
            ? DB::connection('tm2mst')->table('m_wh_loc')->where('location_id', $this->locationId)->value('location_name')
            : null;
    }

    // Baris kartu stok dalam rentang tanggal + saldo awal sebelum rentang
    // (dari baris rm_stockcard terakhir sebelum $dateFrom, bukan dihitung ulang).
    #[Computed]
    public function ledger(): array
    {
        if (! $this->materialId) {
            return ['rows' => collect(), 'openingBalance' => 0];
        }

        $query = DB::connection('tm2inv')->table('v_inv_movement')
            ->where('stock_class', 'RM')
            ->where('item_id', $this->materialId)
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->whereBetween('doc_date', ["{$this->dateFrom} 00:00:00", "{$this->dateTo} 23:59:59"])
            ->orderBy('doc_date')->orderBy('doc_id');

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $openingBalance = DB::connection('tm2inv')->table('rm_stockcard')
                ->where('material_id', $this->materialId)
                ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
                ->where('doc_date', '<', "{$this->dateFrom} 00:00:00")
                ->orderByDesc('doc_date')->orderByDesc('doc_id')
                ->value('qty_eb') ?? 0;
        } else {
            $openingBalance = (float) $rows->first()->qty_bb;
        }

        return ['rows' => $rows, 'openingBalance' => (float) $openingBalance];
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
        <a href="{{ route('menu') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Menu</a>
    </header>

    {{-- ============ FILTER (tidak ikut cetak) ============ --}}
    <div class="no-print max-w-[900px] mx-auto my-4 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1 relative" style="min-width:260px">
                <label class="text-[10px] text-muted uppercase font-semibold">Item (wajib)</label>
                <input type="text" wire:model.live.debounce.300ms="materialSearch" placeholder="🔍 Cari kode / nama item..." class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                @if ($this->materialResults->isNotEmpty())
                    <div class="absolute top-full left-0 right-0 mt-1 bg-white border border-[#d7dbe2] rounded shadow-lg z-10 max-h-56 overflow-y-auto">
                        @foreach ($this->materialResults as $m)
                            <button type="button" wire:click="pickMaterial({{ $m->material_id }})" class="w-full text-left px-2.5 py-1.5 text-xs hover:bg-[#eef3fb] border-b border-[#eef1f5] last:border-b-0">
                                <span class="font-mono font-bold text-accent">{{ $m->material_code }}</span> — {{ $m->material_name }}
                            </button>
                        @endforeach
                    </div>
                @endif
                @if ($this->material)
                    <div class="text-[11px] text-navy font-semibold mt-1">{{ $this->material->material_code }} — {{ $this->material->material_name }}</div>
                @endif
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[10px] text-muted uppercase font-semibold">Dari Tanggal</label>
                <input type="date" wire:model.live="dateFrom" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[10px] text-muted uppercase font-semibold">Sampai Tanggal</label>
                <input type="date" wire:model.live="dateTo" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
            </div>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer" @disabled(! $materialId)>🖨️ Cetak</button>
        </div>
    </div>

    @if (! $materialId)
        <div class="max-w-[900px] mx-auto px-4 no-print">
            <p class="text-center text-muted text-sm py-12">Pilih item dulu untuk menampilkan kartu stok.</p>
        </div>
    @else
        <div class="sheet mx-auto my-4 bg-white border border-[#d7dbe2]" style="width:210mm;min-height:150mm;padding:12mm;font-size:10.5px">
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

            <div class="border border-[#c8ccd4] rounded-sm p-3 bg-[#fbfcfe] mb-3">
                <div class="flex gap-6 flex-wrap">
                    <div><div class="text-[8pt] text-muted uppercase">Kode Item</div><div class="text-[10.5pt] font-bold text-navy">{{ $this->material->material_code }}</div></div>
                    <div class="flex-1 min-w-[200px]"><div class="text-[8pt] text-muted uppercase">Nama Item</div><div class="text-[10.5pt] font-bold text-navy">{{ $this->material->material_name }}</div></div>
                    <div><div class="text-[8pt] text-muted uppercase">UOM Stok</div><div class="text-[10.5pt] font-bold text-navy">{{ $this->material->uom_stock }}</div></div>
                </div>
                <div class="flex gap-6 flex-wrap mt-2">
                    <div><div class="text-[8pt] text-muted uppercase">Lokasi</div><div class="text-[10.5pt] font-bold text-navy">{{ $this->locationName ?? 'Semua Lokasi' }}</div></div>
                    <div><div class="text-[8pt] text-muted uppercase">Periode</div><div class="text-[10.5pt] font-bold text-navy">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div></div>
                </div>
            </div>

            <table class="w-full text-[9pt] border-collapse">
                <thead>
                    <tr class="bg-navy text-white">
                        <th class="text-left px-1.5 py-1 border border-navy">Tanggal</th>
                        <th class="text-left px-1.5 py-1 border border-navy">No. Dokumen</th>
                        <th class="text-center px-1.5 py-1 border border-navy">Tipe</th>
                        <th class="text-left px-1.5 py-1 border border-navy">Referensi</th>
                        <th class="text-left px-1.5 py-1 border border-navy">Lot</th>
                        <th class="text-right px-1.5 py-1 border border-navy">Masuk</th>
                        <th class="text-right px-1.5 py-1 border border-navy">Keluar</th>
                        <th class="text-right px-1.5 py-1 border border-navy">Penyesuaian</th>
                        <th class="text-right px-1.5 py-1 border border-navy">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-[#fff3d6] font-semibold">
                        <td class="px-1.5 py-1 border border-[#e9ecf1]" colspan="8">— Saldo Awal per {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} —</td>
                        <td class="px-1.5 py-1 border border-[#e9ecf1] text-right">{{ number_format($this->ledger['openingBalance'], 4) }}</td>
                    </tr>
                    @forelse ($this->ledger['rows'] as $r)
                        <tr class="border-b border-[#eef1f5]">
                            <td class="px-1.5 py-1 border border-[#eef1f5]">{{ \Carbon\Carbon::parse($r->doc_date)->format('d/m/Y') }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] font-semibold text-navy">{{ $r->doc_no ?: '—' }}</td>
                            <td class="px-1.5 py-1 border border-[#eef1f5] text-center">{{ $r->doc_cat ?: '—' }}</td>
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

            <div class="grid grid-cols-4 gap-3 mt-4">
                <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Saldo Awal</div><div class="text-[12pt] font-bold text-navy">{{ number_format($this->ledger['openingBalance'], 2) }}</div></div>
                <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Total Masuk</div><div class="text-[12pt] font-bold text-[#0f9d58]">+{{ number_format($this->totals['in'], 2) }}</div></div>
                <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Total Keluar</div><div class="text-[12pt] font-bold text-[#b71c1c]">−{{ number_format($this->totals['out'], 2) }}</div></div>
                <div class="border border-[#c8ccd4] rounded-sm p-2 text-center"><div class="text-[7.5pt] text-muted uppercase">Saldo Akhir</div><div class="text-[12pt] font-bold text-navy">{{ number_format($this->totals['closing'], 2) }}</div></div>
            </div>
        </div>
    @endif

    <style>
        @media print {
            .no-print { display: none !important; }
            .sheet { box-shadow: none !important; margin: 0 !important; width: auto !important; }
        }
    </style>
</div>
