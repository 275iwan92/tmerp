<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Laporan Gudang & Inventori -- salah satu "macam laporan" tambahan
// (keputusan user 2026-09-13, lihat catatan lengkap di report/accounting.blade.php).
// Data nyata dari view tm2inv.v_rm_onhand/v_fg_onhand/v_sfg_onhand (sudah
// dipakai layar Kartu/Master Stok yang ada, lihat project_inventory_3_stock_classes) --
// bukan query baru yang mengarang logic stok sendiri.
new #[Title('Laporan Gudang — TELASIH MAKMUR ERP')] class extends Component
{
    #[Computed]
    public function stockValue(): array
    {
        $db = DB::connection('tm2inv');

        return [
            'rm' => (float) $db->table('v_rm_onhand')->sum('value_onhand'),
            'fg' => (float) $db->table('v_fg_onhand')->sum('value_onhand'),
            'sfg' => (float) $db->table('v_sfg_onhand')->sum('value_onhand'),
        ];
    }

    #[Computed]
    public function skuCounts(): array
    {
        $db = DB::connection('tm2inv');

        return [
            'rm' => $db->table('v_rm_onhand')->where('qty_onhand', '>', 0)->distinct('material_id')->count('material_id'),
            'fg' => $db->table('v_fg_onhand')->where('qty_onhand', '>', 0)->distinct('product_id')->count('product_id'),
        ];
    }

    #[Computed]
    public function staleStock(): int
    {
        // Belum bergerak >90 hari -- indikator dead stock, dipakai apa
        // adanya (last_movement sudah ada di view, bukan kolom baru).
        return DB::connection('tm2inv')->table('v_rm_onhand')
            ->where('qty_onhand', '>', 0)
            ->where('last_movement', '<', now()->subDays(90))
            ->count();
    }

    #[Computed]
    public function topRawMaterials(): array
    {
        return DB::connection('tm2inv')->table('v_rm_onhand as v')
            ->join('tm2mst.mat_raw as m', 'm.material_id', '=', 'v.material_id')
            ->where('v.qty_onhand', '>', 0)
            ->selectRaw('m.material_name, v.uom_code, SUM(v.qty_onhand) as qty, SUM(v.value_onhand) as value')
            ->groupBy('m.material_name', 'v.uom_code')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['name' => $r->material_name, 'qty' => (float) $r->qty, 'uom' => $r->uom_code, 'value' => (float) $r->value])
            ->all();
    }

    #[Computed]
    public function warehouseSplit(): array
    {
        return DB::connection('tm2inv')->table('v_rm_onhand')
            ->where('qty_onhand', '>', 0)
            ->selectRaw('warehouse_name, SUM(value_onhand) as value')
            ->groupBy('warehouse_name')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => ['name' => $r->warehouse_name ?: '(tanpa gudang)', 'value' => (float) $r->value])
            ->all();
    }
};
?>

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $miliar = fn ($v) => 'Rp ' . number_format((float) $v / 1_000_000_000, 2, ',', '.') . ' M';
    $totalValue = array_sum($this->stockValue);
    $palette = ['var(--sh-accent)', 'var(--sh-ok)', 'var(--sh-warn)', 'var(--sh-violet)', 'var(--sh-danger)', 'var(--sh-amber)'];
@endphp

<div class="p-4 sm:p-6" style="background:var(--content-bg)">
    <div class="mb-5">
        <h1 class="text-[22px] font-extrabold -tracking-wide" style="color:var(--sh-ink-strong)">Laporan Gudang &amp; Inventori</h1>
        <p class="text-[13px] mt-0.5" style="color:var(--sh-muted)">Nilai stok bahan baku, WIP, dan barang jadi — CV. Telasih Makmur.</p>
    </div>

    <div class="[display:grid] grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <x-report.kpi label="Nilai Stok Bahan Baku" icon="fa-solid fa-boxes-stacked" tone="blue" :value="$miliar($this->stockValue['rm'])" :note="number_format($this->skuCounts['rm']).' SKU aktif'" />
        <x-report.kpi label="Nilai Stok WIP (SFG)" icon="fa-solid fa-layer-group" tone="violet" :value="$miliar($this->stockValue['sfg'])" note="Semi Finished Goods" />
        <x-report.kpi label="Nilai Stok Barang Jadi" icon="fa-solid fa-box-open" tone="green" :value="$miliar($this->stockValue['fg'])" :note="number_format($this->skuCounts['fg']).' SKU aktif'" />
        <x-report.kpi label="Bahan Tidak Bergerak" icon="fa-solid fa-triangle-exclamation" tone="danger" :value="number_format($this->staleStock)" note="&gt;90 hari tanpa mutasi" />
    </div>

    <div class="[display:grid] grid-cols-1 lg:grid-cols-[1fr_1.2fr] gap-3.5 mb-4">
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Komposisi Nilai Stok</h2>
            <x-report.svg-donut :center-value="$miliar($totalValue)" center-label="Total nilai stok"
                :segments="[
                    ['label' => 'Bahan Baku', 'value' => $this->stockValue['rm'], 'color' => $palette[0]],
                    ['label' => 'WIP', 'value' => $this->stockValue['sfg'], 'color' => $palette[1]],
                    ['label' => 'Barang Jadi', 'value' => $this->stockValue['fg'], 'color' => $palette[2]],
                ]" />
        </div>
        <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
            <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Nilai Stok Bahan Baku per Gudang</h2>
            @forelse ($this->warehouseSplit as $w)
                <x-report.progress-row :label="$w['name']" :pct="$this->stockValue['rm'] > 0 ? round($w['value'] / $this->stockValue['rm'] * 100, 1) : 0" :value="$miliar($w['value'])" color="var(--sh-accent)" />
            @empty
                <p class="text-[12px]" style="color:var(--sh-muted)">Belum ada stok bahan baku.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border p-4" style="background:var(--sh-surface);border-color:var(--sh-border)">
        <h2 class="font-bold text-[14px] mb-3" style="color:var(--sh-ink-strong)">Bahan Baku Bernilai Tertinggi</h2>
        <table class="w-full text-[12px]">
            <thead>
                <tr class="text-left border-b" style="border-color:var(--sh-border)">
                    <th class="pb-2 font-bold uppercase text-[10px]" style="color:var(--sh-muted)">Material</th>
                    <th class="pb-2 font-bold uppercase text-[10px] text-right" style="color:var(--sh-muted)">Qty On Hand</th>
                    <th class="pb-2 font-bold uppercase text-[10px] text-right" style="color:var(--sh-muted)">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->topRawMaterials as $m)
                    <tr class="border-b" style="border-color:var(--sh-border)">
                        <td class="py-2 font-semibold" style="color:var(--sh-ink-strong)">{{ $m['name'] }}</td>
                        <td class="py-2 text-right tabular-nums" style="color:var(--sh-muted)">{{ number_format($m['qty'], 2) }} {{ $m['uom'] }}</td>
                        <td class="py-2 text-right tabular-nums font-semibold" style="color:var(--sh-ink-strong)">{{ $rp($m['value']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-3 text-center" style="color:var(--sh-muted)">Belum ada data stok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
