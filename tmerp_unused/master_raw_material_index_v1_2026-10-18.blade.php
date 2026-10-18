<?php

/*
 * REMARK
 * Tujuan       : Komponen v1 untuk layar "Raw Material Master" (dibuka dari grid v2 lewat Open / New). Teks tampilan (label, kolom, tab, tombol, pesan) English sesuai keputusan Jo 2026-10-18; nilai tersimpan dan kunci data tidak diubah.
 * Sumber hulu  : Tabel dan service yang dibaca komponen ini (lihat query di file); tidak ada logika hulu baru.
 * Input        : Isian form dan filter layar.
 * Output       : Simpan ke tabel modul terkait lewat service yang sama seperti sebelumnya.
 * Dipakai oleh : Grid v2 modul terkait (v1Route).
 * Status       : Aktif; teks tampilan English (Jo 2026-10-18).
 */


use App\Livewire\Concerns\HasCustomizableGrid;
use App\Models\Master\MatRaw;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// Pilot fasilitas grid custom view/sort/group-by (keputusan user 2026-08-27) --
// lihat App\Livewire\Concerns\HasCustomizableGrid utk kontrak & griduistandard.md
// §10 utk pola UI (ikon ⚙ di toolbar, bukan menambah elemen toolbar lain --
// itu tetap dilarang §6). Dipilih jadi pilot krn mat_raw punya banyak kolom
// bermakna (59 total), representatif utk kasus "15 kolom tersedia, 5 tampil".
new #[Title('Raw Material Item Master — TELASIH MAKMUR ERP')] class extends Component
{
    use HasCustomizableGrid;
    use WithPagination;

    protected function gridKey(): string
    {
        return 'master.raw-material';
    }

    protected function gridColumnDefs(): array
    {
        return [
            'item_type' => ['label' => 'Type'],
            'stat_confirm' => ['label' => Translator::t('proc_col_status', 'Status')],
            'category1' => ['label' => 'Category'],
            'gram_weight' => ['label' => 'Grammage'],
            'uom_stock' => ['label' => Translator::t('proc_pv_col_uom', 'UoM')],
            'costing_method' => ['label' => 'Method'],
            'cur_stock' => ['label' => 'Stock'],
            'avg_unitprice' => ['label' => 'Average Price'],
            'last_unitprice' => ['label' => 'Last Price'],
            'min_stock' => ['label' => 'Min Stock'],
            'max_stock' => ['label' => 'Max Stock'],
            'reorder_point' => ['label' => Translator::t('mdata_rm_col_reorder_point', 'Reorder Point')],
            'brand' => ['label' => 'Brand'],
            'hs_code' => ['label' => Translator::t('mdata_rm_col_hs_code', 'HS Code')],
            'lead_time_days' => ['label' => 'Lead Time (days)'],
        ];
    }

    protected function gridDefaultColumns(): array
    {
        return ['item_type', 'stat_confirm', 'category1', 'cur_stock', 'avg_unitprice'];
    }

    protected function gridBaseQuery()
    {
        return MatRaw::query();
    }

    protected function gridIdentitySearchColumns(): array
    {
        return ['material_code' => 'Item Code', 'material_name' => 'Item Name'];
    }

    // grid_performance (globalrules.yaml): server-side pagination + WHERE.
    #[Computed]
    public function items()
    {
        return $this->gridSortedQuery()
            ->when(! $this->gridSortColumn, fn ($q) => $q->orderBy('material_name'))
            ->paginate($this->perPage);
    }

    // Hapus massal (keputusan user 2026-08-27, pilot Aksi-kiri): HANYA baris
    // draft/belum confirm boleh dicentang & dihapus -- guard di level query
    // juga (bukan cuma UI), jangan percaya $selectedRows mentah-mentah.
    public function bulkDelete(): void
    {
        MatRaw::whereIn('material_id', $this->selectedRows)->where('stat_confirm', false)->delete();

        $this->clearSelection();
        unset($this->items, $this->stats);
    }

    #[Computed]
    public function groupedItems()
    {
        if (! $this->gridGroupBy) {
            return null;
        }

        return $this->items->getCollection()->groupBy(fn ($row) => data_get($row, $this->gridGroupBy) ?: '—');
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => MatRaw::count(),
            'material' => MatRaw::where('item_type', 'MATERIAL')->count(),
            'non_material' => MatRaw::whereIn('item_type', ['SERVICE', 'FREE_SAMPLE'])->count(),
            'low_stock' => MatRaw::whereNotNull('reorder_point')->whereColumn('cur_stock', '<=', 'reorder_point')->count(),
            'draft' => MatRaw::where('stat_confirm', false)->count(),
        ];
    }

    public function toggleActive(int $materialId): void
    {
        $m = MatRaw::findOrFail($materialId);
        $m->activate = ! $m->activate;
        $m->update_by = Auth::user()->login;
        $m->save();

        unset($this->items, $this->stats);
    }

    public function columnValue($row, string $key): string
    {
        return match ($key) {
            'gram_weight' => $row->gram_weight ? number_format((float) $row->gram_weight, 0).' gsm' : '—',
            'cur_stock' => number_format((float) $row->cur_stock, 0, ',', '.'),
            'avg_unitprice', 'last_unitprice' => number_format((float) data_get($row, $key), 0, ',', '.'),
            'min_stock', 'max_stock', 'reorder_point' => data_get($row, $key) !== null ? number_format((float) data_get($row, $key), 0, ',', '.') : '—',
            'category1' => trim(($row->category1 ?? '').($row->category2 ? ' » '.$row->category2 : '')) ?: '—',
            default => (string) (data_get($row, $key) ?? '—'),
        };
    }

    // Rekonsiliasi component grid (2026-08-27): dulu buka detail lewat
    // ondblclick="window.location='...'" -- disamakan ke pola co/list
    // (wire:dblclick + redirect) supaya cuma 1 mekanisme di semua grid.
    // avatarInitials()/avatarGradient() dipindah ke App\Support\Grid\Avatar
    // (dipakai <x-grid.identity-cell>), tak perlu lagi di sini.
    public function openItem(int $id)
    {
        return $this->redirect(route('master.raw-material.edit', ['material' => $id]));
    }
};
?>

<div>
    <div class="max-w-[1500px] mx-auto my-5 px-4">

        <x-grid.stat-row :count="5">
            <x-grid.stat-box tone="total" label="{{ Translator::t('mdata_rm_stat_total', 'Total Master Item') }}" :value="number_format($this->stats['total'])" />
            <x-grid.stat-box tone="active" label="{{ 'Raw Material (Stock)' }}" :value="number_format($this->stats['material'])" subtitle="MATERIAL" />
            <x-grid.stat-box tone="extra" label="{{ 'Service / Sample' }}" :value="number_format($this->stats['non_material'])" subtitle="SERVICE / FREE_SAMPLE" />
            <x-grid.stat-box tone="critical" label="{{ 'Critical Stock' }}" :value="number_format($this->stats['low_stock'])" subtitle="≤ Reorder Point" />
            <x-grid.stat-box tone="warn" label="{{ 'Draft (Not Confirmed)' }}" :value="number_format($this->stats['draft'])" subtitle="{{ 'Not yet appearing in PR/PQ/PRT' }}" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            {{-- Pilot chrome ala PowerGrid (2026-08-30), roll-out ke-2 sesudah CO --
                 lihat griduistandard.md §12. --}}
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ 'Search code, name, HS code…' }}"
                        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    @include('components.grid.filters-panel')
                    @include('components.grid.toolbar-buttons')
                </x-slot:tools>
                <x-slot:newRecord>
                    <a href="{{ route('master.raw-material.create') }}"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] no-underline cursor-pointer">+ New Record</a>
                </x-slot:newRecord>
            </x-grid.toolbar>
            @include('components.grid.filters-row', ['columnDefs' => $this->gridSearchColumnDefs()])

            @if (count($selectedRows))
                <div class="flex items-center gap-2.5 bg-[#fdecec] border-b border-[#f5c6c6] px-3.5 py-2 text-[12px]">
                    <span class="font-semibold text-[#a3453f]">{{ count($selectedRows) }} {{ 'draft items selected' }}</span>
                    <button type="button" wire:click="bulkDelete" wire:confirm="{{ 'Permanently delete' }} {{ count($selectedRows) }} {{ 'selected draft items? This action cannot be undone.' }}"
                        class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-[#c0392b] text-white cursor-pointer">🗑 {{ 'Delete' }}</button>
                    <button type="button" wire:click="clearSelection" class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-white border border-[#f5c6c6] text-[#a3453f] cursor-pointer">{{ 'Cancel' }}</button>
                </div>
            @endif

            <div x-data="rowMultiSelect()">
            <x-grid.table-shell>
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1] w-[135px]"></th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ Translator::t('inv_line_col_item', 'Item') }}</th>
                            @foreach ($gridVisibleColumns as $key)
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] whitespace-nowrap">
                                    <button type="button" wire:click="toggleGridSort('{{ $key }}')"
                                        class="inline-flex items-center gap-1 cursor-pointer hover:text-navy {{ $gridSortColumn === $key ? 'text-accent' : '' }}">
                                        {{ $this->gridColumnDefs()[$key]['label'] }}
                                        <span class="text-[9px] {{ $gridSortColumn === $key ? 'text-accent' : 'opacity-40' }}">{{ match (true) {
                                            $gridSortColumn === $key && $gridSortDirection === 'asc' => '▲',
                                            $gridSortColumn === $key && $gridSortDirection === 'desc' => '▼',
                                            default => '▲',
                                        } }}</span>
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php $colspan = count($gridVisibleColumns) + 2; @endphp
                        @forelse ($this->items as $m)
                            @if ($gridGroupBy && $loop->first)
                                @php $renderedGroups = []; @endphp
                            @endif
                            @if ($gridGroupBy)
                                @php $groupVal = data_get($m, $gridGroupBy) ?: '—'; @endphp
                                @if (! in_array($groupVal, $renderedGroups, true))
                                    @php $renderedGroups[] = $groupVal; @endphp
                                    <x-grid.group-header-row
                                        :label="$this->gridColumnDefs()[$gridGroupBy]['label']"
                                        :value="$groupVal.' ('.$this->groupedItems[$groupVal]->count().' item)'"
                                        :colspan="$colspan" />
                                @endif
                            @endif
                            <tr class="border-b border-[#eef1f5] hover:bg-[#fbfcfe] cursor-pointer" wire:key="mat-{{ $m->material_id }}" wire:dblclick="openItem({{ $m->material_id }})" title="{{ 'Double-click to open detail' }}">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        @if (! $m->stat_confirm)
                                            <input type="checkbox" class="row-checkbox" data-id="{{ $m->material_id }}"
                                                @click.stop="handleCheckClick($event, {{ $m->material_id }})" @checked(in_array($m->material_id, $selectedRows, true))
                                                title="{{ 'Select for bulk delete (draft) — shift+click to select a range' }}" class="w-3.5 h-3.5 cursor-pointer">
                                        @else
                                            <span class="w-3.5 inline-block shrink-0"></span>
                                        @endif
                                        <x-grid.action-dropdown :actions="[
                                            ['icon' => '✎', 'label' => Translator::t('fin_coa_action_edit', 'Edit'), 'action' => 'openItem('.$m->material_id.')', 'tooltip' => 'Edit this item — or double-click the row'],
                                        ]" />
                                        <x-grid.toggle-switch :on="(bool) $m->activate" action="toggleActive({{ $m->material_id }})"
                                            :confirm="($m->activate ? 'Deactivate' : 'Activate').' item '.$m->material_name.'?'"
                                            :title="$m->activate ? 'Click to deactivate this item' : 'Click to activate this item'" />
                                    </div>
                                </td>
                                <x-grid.identity-cell :id="$m->material_id" :name="$m->material_name" :code="$m->material_code" />
                                @foreach ($gridVisibleColumns as $key)
                                    <td class="px-3 py-2 {{ in_array($key, ['cur_stock', 'avg_unitprice', 'last_unitprice', 'min_stock', 'max_stock', 'reorder_point', 'gram_weight', 'lead_time_days'], true) ? 'text-right' : '' }}">
                                        @if ($key === 'item_type')
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#eef1f5] text-[#4b5568]">{{ $m->item_type }}</span>
                                        @elseif ($key === 'stat_confirm')
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $m->stat_confirm ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#fef6e7] text-[#b45309]' }}">{{ $m->stat_confirm ? Translator::t('mdata_confirmed', 'CONFIRMED') : Translator::t('mdata_draft', 'DRAFT') }}</span>
                                        @elseif ($key === 'cur_stock')
                                            <span class="{{ $m->reorder_point && $m->cur_stock <= $m->reorder_point ? 'text-[#a3241f] font-bold' : '' }}">{{ $this->columnValue($m, $key) }}</span>
                                        @else
                                            {{ $this->columnValue($m, $key) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="$colspan" message="{{ 'No matching items.' }}" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
            </div>

            @include('components.grid.pagination-footer', ['paginator' => $this->items])
        </div>
    </div>

    @include('components.grid.column-modal', ['columnDefs' => $this->gridColumnDefs()])
    @include('components.grid.sort-modal', ['columnDefs' => $this->gridColumnDefs()])
</div>
