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

// Laporan Slow Moving Item (2026-08-28, permintaan user) -- item yang MASIH
// ADA stoknya (qty_onhand > 0) tapi pergerakan terakhirnya sudah lama, per
// ambang 3/6/9/12 bulan. Sumber: v_rm_onhand / v_sfg_onhand / v_fg_onhand
// (SUDAH punya last_movement + avg_unitprice/value_onhand per item+lokasi,
// tak perlu hitung ulang dari stockcard mentah -- lihat komentar sama di
// print/stock-card.blade.php soal view mana punya kolom apa). Item TANPA
// last_movement (baris legacy dari data lama, doc_date NULL) dianggap
// "belum pernah bergerak sejak tercatat" -- selalu masuk laporan apa pun
// ambangnya, bukan dikecualikan (kebalikannya justru paling perlu perhatian).
//
// Sama seperti master-stock/stock-card: keluarga "Laporan" (header navy+Back,
// landscape), BUKAN "Nota" -- printnotastandard.md §1. {category} wajib di
// URL, konvensi sidebarmenustandard.md §5 (3 menu/laporan per kelas stok).
new #[Title('Laporan Slow Moving Item — TELASIH MAKMUR ERP')] class extends Component
{
    public const CATEGORY_LABELS = [
        'RM' => 'Raw & Supporting Materials',
        'SFG' => 'Semi-Finished Goods',
        'FG' => 'Finished Goods',
    ];

    public const VIEW_MAP = [
        'RM' => 'v_rm_onhand',
        'SFG' => 'v_sfg_onhand',
        'FG' => 'v_fg_onhand',
    ];

    public const ITEM_KEY_MAP = [
        'RM' => 'material_id',
        'SFG' => 'parts_id',
        'FG' => 'product_id',
    ];

    public const THRESHOLDS = [3, 6, 9, 12];

    public string $category = 'RM';

    public int $thresholdMonths = 3;

    public ?int $warehouseId = null;

    public string $quickSearch = '';

    public string $sortBy = 'idle_days';

    public string $sortDir = 'desc';

    /** @var array<int, string> */
    public array $collapsedGroups = [];

    public function mount(string $category): void
    {
        $category = strtoupper($category);
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404);

        $this->category = $category;
    }

    public function setThreshold(int $months): void
    {
        if (in_array($months, self::THRESHOLDS, true)) {
            $this->thresholdMonths = $months;
        }
    }

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

        if ($this->sortDir === 'asc' && $field !== 'idle_days') {
            $this->sortBy = 'idle_days';
            $this->sortDir = 'desc';
        }
    }

    public function sortIcon(string $field): string
    {
        if ($this->sortBy !== $field) {
            return '↕';
        }

        return $this->sortDir === 'asc' ? '▲' : '▼';
    }

    public function toggleGroup(string $groupName): void
    {
        if (in_array($groupName, $this->collapsedGroups, true)) {
            $this->collapsedGroups = array_values(array_diff($this->collapsedGroups, [$groupName]));
        } else {
            $this->collapsedGroups[] = $groupName;
        }
    }

    public function printReport(): void
    {
        $this->collapsedGroups = [];
        $this->dispatch('print-report');
    }

    #[Computed]
    public function warehouses()
    {
        return WhWarehouse::orderBy('warehouse_name')->get();
    }

    protected function itemMeta(array $ids): array
    {
        return match ($this->category) {
            'SFG' => ProdPart::whereIn('id_part', $ids)->get()->keyBy('id_part')->map(fn ($p) => [
                'code' => $p->part_code, 'name' => $p->part_name, 'group' => t('Semi-Finished Goods'),
            ])->all(),
            'FG' => ProdProduct::with('category')->whereIn('id_product', $ids)->get()->keyBy('id_product')->map(fn ($p) => [
                'code' => $p->product_code, 'name' => $p->product_name, 'group' => $p->category?->category_name ?: t('No category'),
            ])->all(),
            default => MatRaw::whereIn('material_id', $ids)->get()->keyBy('material_id')->map(fn ($m) => [
                'code' => $m->material_code, 'name' => $m->material_name, 'group' => $m->category1 ?: t('No category'),
            ])->all(),
        };
    }

    // Agregat per item (jumlah semua lokasi is_usable=1, ambil last_movement
    // PALING BARU antar lokasi -- kalau salah satu lokasi masih aktif
    // bergerak, item itu bukan slow-moving meski lokasi lain diam). Baris
    // qty_onhand<=0 dibuang di level SQL -- item kosong bukan "slow moving",
    // itu "habis" (beda makna, tidak relevan di laporan ini).
    #[Computed]
    public function report(): array
    {
        $view = self::VIEW_MAP[$this->category];
        $itemKey = self::ITEM_KEY_MAP[$this->category];
        $cutoff = now()->subMonths($this->thresholdMonths)->toDateString();

        $rows = DB::connection('tm2inv')->table($view)
            ->where('is_usable', true)
            ->when($this->warehouseId, fn ($q) => $q->where('warehouse_id', $this->warehouseId))
            ->havingRaw('qty_onhand > 0')
            ->selectRaw("{$itemKey} as item_id, SUM(qty_onhand) as qty_onhand, SUM(value_onhand) as value_onhand, MAX(last_movement) as last_movement, GROUP_CONCAT(DISTINCT warehouse_name ORDER BY warehouse_name SEPARATOR ', ') as warehouse_names, MAX(uom_code) as uom_code")
            ->groupBy($itemKey)
            ->get()
            ->filter(fn ($r) => $r->last_movement === null || $r->last_movement < $cutoff)
            ->values();

        if ($rows->isEmpty()) {
            return ['groups' => collect(), 'totals' => ['qty' => 0, 'value' => 0], 'itemCount' => 0];
        }

        $itemIds = $rows->pluck('item_id')->unique()->all();
        $meta = $this->itemMeta($itemIds);

        $byItem = $rows->map(function ($r) use ($meta) {
            $m = $meta[$r->item_id] ?? null;
            $idleDays = $r->last_movement ? now()->diffInDays(\Carbon\Carbon::parse($r->last_movement)) : null;

            return (object) [
                'item_id' => $r->item_id,
                'item_code' => $m['code'] ?? "#{$r->item_id}",
                'item_name' => $m['name'] ?? t('(item not found in the master)'),
                'group' => $m['group'] ?? t('No category'),
                'uom' => $r->uom_code,
                'warehouse_names' => $r->warehouse_names,
                'qty_onhand' => (float) $r->qty_onhand,
                'value_onhand' => (float) $r->value_onhand,
                'last_movement' => $r->last_movement,
                'idle_days' => $idleDays,
            ];
        });

        if (trim($this->quickSearch) !== '') {
            $term = mb_strtolower(trim($this->quickSearch));
            $byItem = $byItem->filter(fn ($row) => str_contains(mb_strtolower($row->item_code), $term) || str_contains(mb_strtolower($row->item_name), $term))->values();
        }

        $sortField = in_array($this->sortBy, ['item_code', 'item_name', 'qty_onhand', 'value_onhand', 'idle_days'], true) ? $this->sortBy : 'idle_days';
        // idle_days NULL (belum pernah bergerak) selalu paling atas -- lebih genting dari yang punya tanggal.
        $byItem = $byItem->sortBy(function ($row) use ($sortField) {
            $v = $row->{$sortField};

            return $v === null ? -INF : $v;
        }, SORT_REGULAR, $this->sortDir === 'desc')->values();

        $groups = $byItem->groupBy('group')->sortKeys();

        return [
            'groups' => $groups,
            'totals' => [
                'qty' => $byItem->sum('qty_onhand'),
                'value' => $byItem->sum('value_onhand'),
            ],
            'itemCount' => $byItem->count(),
        ];
    }
};
?>
<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50 no-print">
        <b class="text-[15px]">🐢 {{ t('Slow Moving Item Report') }}</b>
        <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-white/15">{{ t(self::CATEGORY_LABELS[$category]) }}</span>
        <x-back-button :fallback="route('menu')" :title="t('Back to Main Menu')" class="ml-auto" />
    </header>

    {{-- ============ FILTER (tidak ikut cetak) ============ --}}
    <div class="no-print max-w-[1100px] mx-auto my-4 px-4">
        <div class="bg-white border border-[#d7dbe2] rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1 shrink-0">
                    <label class="text-[10px] text-muted uppercase font-semibold">{{ t('Idle Threshold') }}</label>
                    <div class="flex items-center gap-1">
                        @foreach (self::THRESHOLDS as $m)
                            <button type="button" wire:click="setThreshold({{ $m }})"
                                class="px-3 py-1.5 rounded-full text-xs font-semibold border cursor-pointer {{ $thresholdMonths === $m ? 'bg-navy text-white border-navy' : 'bg-white text-navy border-[#d7dbe2] hover:bg-[#eef1f6]' }}">
                                {{ str_replace(':n', $m, t(':n Months')) }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="flex flex-col gap-1 shrink-0">
                    <label class="text-[10px] text-muted uppercase font-semibold">{{ t('Warehouse') }}</label>
                    <select wire:model.live="warehouseId" class="px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        <option value="">{{ t('All Warehouses') }}</option>
                        @foreach ($this->warehouses as $w)
                            <option value="{{ $w->id_warehouse }}">{{ $w->warehouse_code }} — {{ $w->warehouse_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1 shrink-0" style="min-width:220px">
                    <label class="text-[10px] text-muted uppercase font-semibold">{{ t('Search Item') }}</label>
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm">🔍</span>
                        <div class="flex-1"><x-form.input :case-toggle="false" wire:model.live.debounce.300ms="quickSearch" placeholder="{{ t('Item code / name...') }}" /></div>
                    </div>
                </div>
                <span class="flex-1"></span>
                <button type="button" wire:click="printReport" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer shrink-0">🖨️ {{ t('Print') }}</button>
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
                <div class="text-[14pt] font-bold text-navy uppercase">{{ t('Slow Moving Item Report') }}</div>
                <div class="text-[8.5pt] text-muted mt-1">{{ str_replace([':date', ':user'], [now()->translatedFormat('d/m/Y H:i'), Auth::user()->name], t('Printed :date by :user')) }}</div>
            </div>
        </div>

        <div class="border border-[#c8ccd4] rounded-md p-2.5 bg-[#fbfcfe] mb-3 flex gap-8 flex-wrap">
            <div><div class="text-[7.5pt] text-muted uppercase">{{ t('Stock Class') }}</div><div class="text-[10pt] font-bold text-navy">{{ t(self::CATEGORY_LABELS[$category]) }}</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">{{ t('Threshold') }}</div><div class="text-[10pt] font-bold text-navy">{{ str_replace(':n', $thresholdMonths, t('Not moved > :n months')) }}</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">{{ t('As of Date') }}</div><div class="text-[10pt] font-bold text-navy">{{ now()->translatedFormat('d F Y') }}</div></div>
            <div><div class="text-[7.5pt] text-muted uppercase">{{ t('Warehouse') }}</div><div class="text-[10pt] font-bold text-navy">{{ $warehouseId ? $this->warehouses->firstWhere('id_warehouse', $warehouseId)?->warehouse_name : t('All Warehouses') }}</div></div>
            @if (trim($quickSearch) !== '')
                <div class="no-print"><div class="text-[7.5pt] text-muted uppercase">{{ t('Search Filter') }}</div><div class="text-[10pt] font-bold text-accent">"{{ $quickSearch }}"</div></div>
            @endif
        </div>

        <div class="grid three mb-3">
            <div class="rounded-lg p-2.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6] text-center"><p class="text-[9.5px] font-bold uppercase text-[#a3453f]">{{ t('Slow Moving Items') }}</p><p class="text-lg font-extrabold text-[#c0392b] mt-0.5">{{ $this->report['itemCount'] }}</p></div>
            <div class="rounded-lg p-2.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3] text-center"><p class="text-[9.5px] font-bold uppercase text-[#8a6d00]">{{ t('Total Idle Qty') }}</p><p class="text-lg font-extrabold text-[#b45309] mt-0.5">{{ number_format($this->report['totals']['qty'], 2) }}</p></div>
            <div class="rounded-lg p-2.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6] text-center"><p class="text-[9.5px] font-bold uppercase text-[#a3453f]">{{ t('Idle Value') }}</p><p class="text-lg font-extrabold text-[#c0392b] mt-0.5">Rp {{ number_format($this->report['totals']['value'], 0, ',', '.') }}</p></div>
        </div>

        <div class="rounded-lg border border-[#c8ccd4] overflow-hidden">
            <table class="w-full text-[8.5pt] border-collapse">
                <thead>
                    <tr class="bg-navy text-white">
                        <th class="w-[26px] px-1 py-1.5 border border-navy no-print"></th>
                        <th class="text-center px-1.5 py-1.5 border border-navy">{{ t('No.') }}</th>
                        <th class="text-left px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('item_code')" title="{{ t('Sort') }}">{{ t('Item Code') }} <span class="opacity-80">{{ $this->sortIcon('item_code') }}</span></th>
                        <th class="text-left px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('item_name')" title="{{ t('Sort') }}">{{ t('Item Name') }} <span class="opacity-80">{{ $this->sortIcon('item_name') }}</span></th>
                        <th class="text-left px-1.5 py-1.5 border border-navy">{{ t('Warehouse') }}</th>
                        <th class="text-center px-1.5 py-1.5 border border-navy">{{ t('UOM') }}</th>
                        <th class="text-right px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('qty_onhand')" title="{{ t('Sort') }}">{{ t('On Hand') }} <span class="opacity-80">{{ $this->sortIcon('qty_onhand') }}</span></th>
                        <th class="text-right px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('value_onhand')" title="{{ t('Sort') }}">{{ t('Value') }} <span class="opacity-80">{{ $this->sortIcon('value_onhand') }}</span></th>
                        <th class="text-center px-1.5 py-1.5 border border-navy">{{ t('Last Stock Movement') }}</th>
                        <th class="text-center px-1.5 py-1.5 border border-navy cursor-pointer select-none" wire:click="sortToggle('idle_days')" title="{{ t('Sort') }}">{{ t('Idle Since') }} <span class="opacity-80">{{ $this->sortIcon('idle_days') }}</span></th>
                    </tr>
                </thead>
                @forelse ($this->report['groups'] as $groupName => $items)
                    @php($isCollapsed = in_array($groupName, $collapsedGroups, true))
                    <tbody wire:key="group-{{ Str::slug($groupName) }}">
                        <tr class="bg-[#eef1f6] cursor-pointer hover:bg-[#e5e9f1]" wire:click="toggleGroup('{{ $groupName }}')">
                            <td class="px-1 py-1.5 text-center no-print"><span class="text-navy font-bold">{{ $isCollapsed ? '▸' : '▾' }}</span></td>
                            <td colspan="9" class="px-1.5 py-1.5 font-bold text-navy uppercase text-[8pt]">{{ $groupName }} · {{ str_replace(':n', $items->count(), t(':n item(s)')) }}</td>
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
                                    <td class="px-1.5 py-1 border border-[#eef1f5] text-right font-semibold">{{ number_format($row->qty_onhand, 2) }}</td>
                                    <td class="px-1.5 py-1 border border-[#eef1f5] text-right">{{ $row->value_onhand > 0 ? number_format($row->value_onhand, 0, ',', '.') : '—' }}</td>
                                    <td class="px-1.5 py-1 border border-[#eef1f5] text-center">{{ $row->last_movement ? \Carbon\Carbon::parse($row->last_movement)->format('d/m/Y') : '—' }}</td>
                                    <td class="px-1.5 py-1 border border-[#eef1f5] text-center">
                                        @if ($row->idle_days === null)
                                            <span class="text-[7.5pt] font-bold px-1.5 py-0.5 rounded-full bg-[#fdecec] text-[#a3241f]">{{ mb_strtoupper(t('Never moved')) }}</span>
                                        @else
                                            <span class="text-[7.5pt] font-bold px-1.5 py-0.5 rounded-full {{ $row->idle_days > 365 ? 'bg-[#fdecec] text-[#a3241f]' : 'bg-[#fef6e7] text-[#8a6d00]' }}">{{ str_replace(':n', number_format($row->idle_days), t(':n DAYS')) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="10" class="text-center py-8 text-muted">{{ str_replace(':n', $thresholdMonths, t('No slow moving items at this :n month threshold — all items have stock moving within a reasonable period.')) }} 🎉</td></tr>
                    </tbody>
                @endforelse
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
