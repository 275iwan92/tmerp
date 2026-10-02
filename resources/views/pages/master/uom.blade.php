<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\HasSpreadsheetGrid;
use App\Models\Master\UomConversion;
use App\Models\Master\UomUnit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Unit of Measure (UoM) Master — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    // Pilot #2 docs/spreadsheetgridstandard.md (2026-08-30) -- list Satuan
    // (kolom kiri) jadi spreadsheet-editable, panel Konversi (kolom kanan)
    // TETAP tidak berubah (relasional, bukan kandidat spreadsheet-edit).
    // Method modal lama (openUnitModal/saveUnit) SENGAJA tetap ada -- masih
    // dites langsung via Livewire::test() di MasterUomSmokeTest, cuma sudah
    // tidak dipanggil dari toolbar grid lagi ("+ Baris" spreadsheet gantikan
    // "+ New Record").
    use HasSpreadsheetGrid;

    public array $dimensions = ['PANJANG', 'LUAS', 'MASSA', 'VOLUME', 'JUMLAH', 'WAKTU', 'SPEC'];

    // ---- list & filter satuan ----
    public string $search = '';

    public ?string $selectedCode = null;

    // ---- panel konversi ----
    public bool $conversionPanelOpen = false;

    public bool $hideInactiveConversions = false;

    // ---- panel satuan (grid <-> form, GRID_UI_STANDARD/FORM_UI_STANDARD) ----
    public string $panel = 'grid'; // grid | form

    public string $unitMode = 'new'; // new | edit

    public ?string $editingUnitCode = null;

    public string $uom_code = '';

    public string $uom_name = '';

    public string $dimension = '';

    public bool $is_base = false;

    public bool $unit_activate = true;

    // ---- modal konversi ----
    public bool $showConvModal = false;

    public string $convMode = 'new'; // new | edit

    public ?int $editingConversionId = null;

    public string $uom_src = '';

    public string $uom_dst = '';

    public string $conversion_value = '';

    public bool $conversion_activate = true;

    #[Computed]
    public function units()
    {
        return UomUnit::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('uom_code', 'like', "%{$this->search}%")
                ->orWhere('uom_name', 'like', "%{$this->search}%")))
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderBy('dimension')->orderBy('uom_code')
            ->get();
    }

    // ============== SPREADSHEET GRID (docs/spreadsheetgridstandard.md) ==============
    protected function spreadsheetKey(): string
    {
        return 'master-uom-unit';
    }

    protected function spreadsheetColumns(): array
    {
        return [
            'uom_code' => ['label' => t('Code'), 'type' => 'text', 'required' => true, 'editableWhenExisting' => false],
            'uom_name' => ['label' => t('Unit Name'), 'type' => 'text', 'required' => true],
            'dimension' => ['label' => t('Dimension'), 'type' => 'select', 'options' => $this->dimensions, 'required' => true],
            'is_base' => ['label' => t('Base'), 'type' => 'boolean'],
            'activate' => ['label' => t('Active'), 'type' => 'boolean'],
            '_conversion' => ['label' => '', 'type' => 'action', 'icon' => '🔁', 'title' => t('Conversion'), 'method' => 'openConversionPanel', 'paramKey' => 'uom_code', 'hideWhenColumn' => 'dimension', 'hideWhenValue' => 'SPEC'],
        ];
    }

    protected function spreadsheetQuery()
    {
        return UomUnit::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('uom_code', 'like', "%{$this->search}%")
                ->orWhere('uom_name', 'like', "%{$this->search}%")))
            ->tap(fn ($q) => $this->applyStatFilter($q))
            ->orderBy('dimension')->orderBy('uom_code');
    }

    protected function spreadsheetRowId($row): int|string
    {
        return $row->uom_code;
    }

    protected function spreadsheetNewRowDefaults(): array
    {
        return ['uom_code' => '', 'uom_name' => '', 'dimension' => $this->dimensions[0], 'is_base' => false, 'activate' => true];
    }

    protected function spreadsheetValidateRow(array $data, int|string|null $existingId): array
    {
        $data['uom_code'] = strtoupper(trim((string) $data['uom_code']));

        $unique = 'unique:tm2mst.uom_unit,uom_code';
        if ($existingId) {
            $unique .= ','.$existingId.',uom_code';
        }

        $validator = Validator::make($data, [
            'uom_code' => ['required', 'string', 'max:50', $unique],
            'uom_name' => ['required', 'string', 'max:200'],
            'dimension' => ['required', 'string', 'in:'.implode(',', $this->dimensions)],
            'is_base' => ['boolean'],
            'activate' => ['boolean'],
        ]);

        $validator->after(function ($v) use ($data) {
            if (($data['dimension'] ?? null) === 'SPEC' && ! empty($data['is_base'])) {
                $v->errors()->add('is_base', t('Dimension SPEC cannot have a base unit.'));
            }
        });

        return $validator->validate();
    }

    protected function spreadsheetPersistRow(array $data, int|string|null $existingId): void
    {
        $payload = [
            'uom_code' => $data['uom_code'],
            'uom_name' => $data['uom_name'],
            'dimension' => $data['dimension'],
            'is_base' => (bool) ($data['is_base'] ?? false),
            'activate' => (bool) ($data['activate'] ?? false),
            'updated_by' => Auth::user()->login,
        ];

        if ($existingId) {
            UomUnit::where('uom_code', $existingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            UomUnit::create($payload);
        }

        if ($payload['is_base']) {
            UomUnit::where('dimension', $payload['dimension'])->where('uom_code', '!=', $payload['uom_code'])->update(['is_base' => false]);
        }

        unset($this->units, $this->statTotal, $this->statActive, $this->statInactive);
    }

    #[Computed]
    public function selectedUnit(): ?UomUnit
    {
        return $this->selectedCode ? UomUnit::where('uom_code', $this->selectedCode)->first() : null;
    }

    #[Computed]
    public function convertibleUnits()
    {
        return UomUnit::query()->where('dimension', '!=', 'SPEC')->orderBy('uom_code')->get();
    }

    #[Computed]
    public function directConversions()
    {
        if (! $this->selectedCode) {
            return collect();
        }

        return UomConversion::query()
            ->where(fn ($q) => $q->where('uom_src', $this->selectedCode)->orWhere('uom_dst', $this->selectedCode))
            ->when($this->hideInactiveConversions, fn ($q) => $q->where('activate', true))
            ->orderBy('uom_src')->orderBy('uom_dst')
            ->get();
    }

    #[Computed]
    public function factorMap(): array
    {
        $units = UomUnit::query()->get(['uom_code', 'dimension', 'is_base']);
        $conversions = UomConversion::query()->where('activate', true)->get(['uom_src', 'uom_dst', 'conversion_value']);

        $adjacency = [];
        foreach ($units as $u) {
            $adjacency[$u->uom_code] = [];
        }
        foreach ($conversions as $c) {
            if (! isset($adjacency[$c->uom_src], $adjacency[$c->uom_dst])) {
                continue;
            }
            // uom_src/uom_dst berarti "1 src = value dst" -> factor(base-unit per 1 unit)
            // dari src ke dst dibagi value, dari dst ke src dikali value.
            $value = (float) $c->conversion_value;
            $adjacency[$c->uom_src][] = [$c->uom_dst, $value > 0 ? 1 / $value : 0];
            $adjacency[$c->uom_dst][] = [$c->uom_src, $value];
        }

        $factor = [];
        foreach ($units as $u) {
            if (! $u->is_base) {
                continue;
            }
            $factor[$u->uom_code] = 1.0;
            $stack = [$u->uom_code];
            while ($stack) {
                $current = array_pop($stack);
                foreach ($adjacency[$current] as [$next, $value]) {
                    if (! isset($factor[$next])) {
                        $factor[$next] = $factor[$current] * $value;
                        $stack[] = $next;
                    }
                }
            }
        }

        return $factor;
    }

    #[Computed]
    public function derivedConversions()
    {
        if (! $this->selectedCode || ! $this->selectedUnit || $this->selectedUnit->dimension === 'SPEC') {
            return collect();
        }

        $factor = $this->factorMap;
        if (! isset($factor[$this->selectedCode])) {
            return collect();
        }

        $base = $factor[$this->selectedCode];

        return UomUnit::query()
            ->where('dimension', $this->selectedUnit->dimension)
            ->where('uom_code', '!=', $this->selectedCode)
            ->get()
            ->filter(fn ($u) => isset($factor[$u->uom_code]))
            ->map(fn ($u) => (object) [
                'uom_code' => $u->uom_code,
                'uom_name' => $u->uom_name,
                'value' => $base / $factor[$u->uom_code],
            ])
            ->sortBy('value')
            ->values();
    }

    public function formatNumber($value): string
    {
        $s = rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }

    public function dimensionBadgeClass(?string $dim): string
    {
        return match ($dim) {
            'PANJANG' => 'bg-[#e0ecff] text-[#1560a8]',
            'LUAS' => 'bg-[#e2e0ff] text-[#4b3fa8]',
            'MASSA' => 'bg-[#ffe0b2] text-[#8a4b00]',
            'VOLUME' => 'bg-[#d6f2e2] text-[#177a4b]',
            'JUMLAH' => 'bg-[#fde3ec] text-[#a01a54]',
            'WAKTU' => 'bg-[#dcefe9] text-[#0c6b57]',
            'SPEC' => 'bg-[#e8ebf0] text-[#556]',
            default => 'bg-gray-100 text-gray-500',
        };
    }

    // ============== SELEKSI & PANEL KONVERSI ==============
    public function selectUnit(string $code): void
    {
        $this->selectedCode = $code;
        $this->conversionPanelOpen = false;
    }

    public function openConversionPanel(?string $code = null): void
    {
        if ($code) {
            $this->selectedCode = $code;
        }
        if (! $this->selectedCode || ! $this->selectedUnit || $this->selectedUnit->dimension === 'SPEC') {
            return;
        }
        $this->conversionPanelOpen = true;
    }

    public function closeConversionPanel(): void
    {
        $this->conversionPanelOpen = false;
    }

    public function editSelectedUnit(): void
    {
        if ($this->selectedCode) {
            $this->openUnitModal('edit', $this->selectedCode);
        }
    }

    // ============== MODAL SATUAN ==============
    public function openUnitModal(string $mode, ?string $code = null): void
    {
        $this->resetErrorBag();
        $this->unitMode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingUnitCode = $code;

        if ($this->unitMode === 'edit' && $code) {
            $u = UomUnit::where('uom_code', $code)->firstOrFail();
            $this->uom_code = $u->uom_code;
            $this->dimension = (string) $u->dimension;
            $this->uom_name = (string) $u->uom_name;
            $this->is_base = (bool) $u->is_base;
            $this->unit_activate = (bool) $u->activate;
        } else {
            $this->reset(['uom_code', 'dimension', 'uom_name', 'is_base']);
            $this->unit_activate = true;
        }

        $this->panel = 'form';
    }

    public function showGrid(): void
    {
        $this->panel = 'grid';
    }

    public function saveUnit(): void
    {
        $this->uom_code = strtoupper(trim($this->uom_code));

        $unique = 'unique:tm2mst.uom_unit,uom_code';
        if ($this->unitMode === 'edit' && $this->editingUnitCode) {
            $unique .= ','.$this->editingUnitCode.',uom_code';
        }

        $data = $this->validate([
            'uom_code' => ['required', 'string', 'max:50', $unique],
            'dimension' => ['required', 'string', 'in:'.implode(',', $this->dimensions)],
            'uom_name' => ['required', 'string', 'max:200'],
            'is_base' => ['boolean'],
            'unit_activate' => ['boolean'],
        ]);

        if ($data['dimension'] === 'SPEC' && $data['is_base']) {
            $this->addError('is_base', t('Dimension SPEC cannot have a base unit.'));

            return;
        }

        $payload = [
            'uom_code' => $data['uom_code'],
            'uom_name' => $data['uom_name'],
            'dimension' => $data['dimension'],
            'is_base' => $data['is_base'],
            'activate' => $data['unit_activate'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->unitMode === 'edit' && $this->editingUnitCode) {
            UomUnit::where('uom_code', $this->editingUnitCode)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            UomUnit::create($payload);
        }

        if ($data['is_base']) {
            UomUnit::where('dimension', $data['dimension'])->where('uom_code', '!=', $data['uom_code'])->update(['is_base' => false]);
        }

        unset($this->units, $this->selectedUnit, $this->factorMap, $this->derivedConversions, $this->convertibleUnits);
        $this->selectedCode = $data['uom_code'];
        $this->panel = 'grid';
    }

    // ============== MODAL KONVERSI ==============
    public function openConvModal(string $mode, ?int $id = null): void
    {
        $this->resetErrorBag();
        $this->convMode = in_array($mode, ['new', 'edit'], true) ? $mode : 'new';
        $this->editingConversionId = $id;

        if ($this->convMode === 'edit' && $id) {
            $c = UomConversion::findOrFail($id);
            $this->uom_src = $c->uom_src;
            $this->uom_dst = $c->uom_dst;
            $this->conversion_value = (string) $c->conversion_value;
            $this->conversion_activate = (bool) $c->activate;
        } else {
            $this->uom_src = $this->selectedCode ?? '';
            $this->uom_dst = '';
            $this->conversion_value = '';
            $this->conversion_activate = true;
        }

        $this->showConvModal = true;
    }

    public function closeConvModal(): void
    {
        $this->showConvModal = false;
    }

    public function saveConv(): void
    {
        $data = $this->validate([
            'uom_src' => ['required', 'string', 'max:50'],
            'uom_dst' => ['required', 'string', 'max:50', 'different:uom_src'],
            'conversion_value' => ['required', 'numeric', 'gt:0'],
            'conversion_activate' => ['boolean'],
        ]);

        $srcUnit = UomUnit::where('uom_code', $data['uom_src'])->first();
        $dstUnit = UomUnit::where('uom_code', $data['uom_dst'])->first();

        if (! $srcUnit || ! $dstUnit) {
            $this->addError('uom_dst', t('Unit not found.'));

            return;
        }

        if ($srcUnit->dimension !== $dstUnit->dimension) {
            $this->addError('uom_dst', str_replace([':src', ':dst'], [$srcUnit->dimension, $dstUnit->dimension], t('Different dimensions (:src vs :dst) — conversion not valid.')));

            return;
        }

        $dupQuery = UomConversion::where('uom_src', $data['uom_src'])->where('uom_dst', $data['uom_dst']);
        if ($this->convMode === 'edit' && $this->editingConversionId) {
            $dupQuery->where('id_conversion', '!=', $this->editingConversionId);
        }
        if ($dupQuery->exists()) {
            $this->addError('uom_dst', str_replace([':src', ':dst'], [$data['uom_src'], $data['uom_dst']], t('Pair :src → :dst already exists. Edit that row.')));

            return;
        }

        $payload = [
            'uom_src' => $data['uom_src'],
            'uom_dst' => $data['uom_dst'],
            'conversion_value' => $data['conversion_value'],
            'activate' => $data['conversion_activate'],
            'updated_by' => Auth::user()->login,
        ];

        if ($this->convMode === 'edit' && $this->editingConversionId) {
            UomConversion::whereKey($this->editingConversionId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            UomConversion::create($payload);
        }

        unset($this->directConversions, $this->derivedConversions, $this->factorMap);
        $this->showConvModal = false;
    }

    public function toggleConversionActive(int $id): void
    {
        $c = UomConversion::findOrFail($id);
        $c->activate = ! $c->activate;
        $c->updated_by = Auth::user()->login;
        $c->save();

        unset($this->directConversions, $this->derivedConversions, $this->factorMap);
    }

    public function toggleUnitActive(string $code): void
    {
        $u = UomUnit::where('uom_code', $code)->firstOrFail();
        $u->activate = ! $u->activate;
        $u->updated_by = Auth::user()->login;
        $u->save();

        unset($this->units, $this->statTotal, $this->statActive, $this->statInactive);
    }

    #[Computed]
    public function statTotal(): int
    {
        return UomUnit::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return UomUnit::where('activate', true)->count();
    }

    #[Computed]
    public function statBase(): int
    {
        return UomUnit::where('is_base', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return UomUnit::where('activate', false)->count();
    }

    // Componentization grid+form (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).

    // Kotak statistik = filter data (griduistandard.md §2b). Dipasang di KEDUA
    // jalur query layar ini (daftar satuan kiri + grid spreadsheet) supaya
    // keduanya menunjukkan himpunan yang sama.
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total Units'), 'value' => $this->statTotal, 'tone' => 'total'],
            'active' => ['label' => t('Active'), 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'base' => ['label' => t('Base Units'), 'value' => $this->statBase, 'tone' => 'warn',
                'filter' => fn ($q) => $q->where('is_base', true)],
            'inactive' => ['label' => t('Inactive'), 'value' => $this->statInactive, 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('activate', false)],
        ];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">
            🔒 {!! t('Conversion is only valid <b>within one dimension</b>. <b>SPEC</b> units (GSM, COLOR, etc.) are not converted.') !!}
            {!! t('Select a unit on the left, click <b>🔁 Conversion</b>, then <b>+ New Conversion</b> in the right panel.') !!}
        </div>

        {{-- ============ GRID (LIST + PANEL KONVERSI) ============ --}}
        @if ($panel === 'grid')
        <div class="[display:grid] grid-cols-1 {{ $conversionPanelOpen ? 'lg:grid-cols-[1fr_420px]' : 'lg:grid-cols-1' }} gap-3.5 items-start">

            {{-- ============ KARTU KIRI: LIST SATUAN ============ --}}
            <div class="mb-2 flex items-center gap-1.5">
                <span class="text-sm">🔍</span>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search code / name…') }}"
                    class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
            </div>

            <x-grid.spreadsheet-table
                grid-key="{{ $this->spreadsheetKey() }}"
                :columns="$this->spreadsheetColumns()"
                :rows="$this->spreadsheetRows()"
                :new-row-defaults="$this->spreadsheetNewRowDefaults()"
                :errors="$this->spreadsheetErrors"
                :version="$this->spreadsheetVersion" />

            <div class="flex gap-3.5 flex-wrap text-[11px] text-[#667] px-1 py-2">
                <span><span class="text-muted">{{ $this->units->count() }} {{ t('units.') }}</span> {!! t('Click the <b>🔁</b> icon to open the Conversion panel.') !!}</span>
                <span>{!! t('<b>Base</b> = the base unit of a dimension (only 1 per dimension; the old base is automatically released when "Save All" runs).') !!}</span>
            </div>

            {{-- ============ KARTU KANAN: PANEL KONVERSI ============ --}}
            @if ($conversionPanelOpen)
                <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                    <div class="px-3.5 py-3 bg-navy text-white text-[13px] font-semibold flex items-center gap-2 flex-wrap">
                        <span>{{ t('Conversion') }} {{ $selectedCode }} · {{ $this->selectedUnit?->uom_name }}</span>
                        <button type="button" wire:click="openConvModal('new')" class="ml-auto px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-white/10 text-white border border-white/40 hover:bg-white/20">+ {{ t('New Conversion') }}</button>
                        <span class="cursor-pointer opacity-80 hover:opacity-100" wire:click="closeConversionPanel">✕</span>
                    </div>
                    <div class="px-3.5 py-2 border-b border-[#eef1f5]">
                        <x-form.checkbox label="{{ t('Hide inactive conversions') }}" wire:model.live="hideInactiveConversions" />
                    </div>

                    <div class="text-[11px] font-bold uppercase tracking-wide text-muted px-3.5 pt-2.5 pb-1">{{ t('Direct Conversion') }}</div>
                    <div>
                        @forelse ($this->directConversions as $c)
                            <div wire:key="dc-{{ $c->id_conversion }}" class="flex items-center gap-2 px-3.5 py-2 border-b border-[#f0f3f7] text-[13px]">
                                <span class="font-mono font-bold text-navy min-w-[60px]">{{ $c->uom_src }}</span>
                                <span class="text-muted">→</span>
                                <span class="font-mono text-[#556] min-w-[44px]">{{ $c->uom_dst }}</span>
                                <span class="font-mono font-bold text-[#177a4b] text-right ml-1">1 {{ $c->uom_src }} = {{ $this->formatNumber($c->conversion_value) }} {{ $c->uom_dst }}</span>
                                @unless ($c->activate)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#f0d6d6] text-[#a33]">{{ t('INACTIVE') }}</span>
                                @endunless
                                <span class="ml-auto flex gap-0.5 shrink-0">
                                    <button type="button" wire:click="openConvModal('edit', {{ $c->id_conversion }})" title="{{ t('Edit') }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
                                    <button type="button" wire:click="toggleConversionActive({{ $c->id_conversion }})" wire:confirm="{{ str_replace([':src', ':dst'], [$c->uom_src, $c->uom_dst], $c->activate ? t('Deactivate conversion :src → :dst?') : t('Activate conversion :src → :dst?')) }}"
                                        title="{{ $c->activate ? t('Deactivate') : t('Activate') }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $c->activate ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                </span>
                            </div>
                        @empty
                            <div class="text-center text-muted text-[12.5px] px-3.5 py-5 leading-relaxed">{{ t('No direct conversion pair yet for') }} <b>{{ $selectedCode }}</b>. {!! t('Click <b>+ New Conversion</b> above.') !!}</div>
                        @endforelse
                    </div>

                    @if ($this->derivedConversions->isNotEmpty())
                        <div class="text-[11px] font-bold uppercase tracking-wide text-muted px-3.5 pt-2.5 pb-1">{{ t('Derived to All Units in the Dimension') }}</div>
                        <div class="pb-1.5">
                            @foreach ($this->derivedConversions as $row)
                                <div wire:key="der-{{ $row->uom_code }}" class="flex items-center gap-2 px-3.5 py-2 border-b border-[#f0f3f7] text-[13px] last:border-b-0">
                                    <span class="font-mono font-bold text-navy min-w-[60px]">1 {{ $selectedCode }}</span>
                                    <span class="text-muted">=</span>
                                    <span class="font-mono font-bold text-[#177a4b]">{{ $this->formatNumber($row->value) }}</span>
                                    <span class="font-mono text-[#556]">{{ $row->uom_code }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
        @endif

        {{-- ============ FORM SATUAN (NEW/EDIT) ============ --}}
        @if ($panel === 'form')
            <div class="flex justify-between items-center bg-navy text-white px-4 py-3 rounded-t-md">
                <div class="text-sm font-bold">
                    {{ $unitMode === 'edit' ? t('Edit Unit') : t('New Unit') }}
                    @if ($unitMode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $editingUnitCode }}</span>@endif
                </div>
            </div>
            <form wire:submit="saveUnit" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.input :label="t('Unit Code *')" name="uom_code" wire:model="uom_code" maxlength="10" :placeholder="t('e.g. IK16')" :disabled="$unitMode === 'edit'" class="uppercase" />
                        </div>
                        <div class="flex-1">
                            <x-form.select :label="t('Dimension *')" name="dimension" wire:model="dimension" :options="array_combine($dimensions, $dimensions)" :selected="$dimension" />
                        </div>
                    </div>
                    <x-form.input :label="t('Unit Name *')" name="uom_name" wire:model="uom_name" :placeholder="t('e.g. Bundle of 16 pcs')" />
                    <x-form.checkbox wire:model="is_base">{!! t('Make this the <b>base</b> unit for this dimension (only 1 base per dimension — the old base is released automatically)') !!}</x-form.checkbox>
                    @error('is_base') <span class="text-[10.5px] text-red-600">{{ $message }}</span> @enderror
                    <x-form.checkbox :label="t('Active')" wire:model="unit_activate" />
                    <div class="text-[11px] text-muted leading-relaxed">{!! t('Dimension <b>SPEC</b> cannot have a base unit / be converted. Once the unit is saved, set its conversions via the <b>🔁 Conversion</b> panel.') !!}</div>

                    <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                    </div>
            </form>
        @endif
    </div>

    {{-- ══ MODAL: KONVERSI (sub-entity dalam panel konversi -- tetap modal,
         konsisten dgn pola supplier.blade.php Contact/Branch) ══ --}}
    @if ($showConvModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center" wire:click.self="closeConvModal">
            <div class="bg-white rounded-xl w-[420px] max-w-[92vw] shadow-2xl overflow-hidden">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2">
                    <span>{{ $convMode === 'edit' ? t('Edit Conversion').' · '.$uom_src.' → '.$uom_dst : t('New Conversion').' · '.t('from').' '.($uom_src ?: '—') }}</span>
                    <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeConvModal">✕</span>
                </div>
                <form wire:submit="saveConv" class="p-4 flex flex-col gap-3">
                    <div class="flex gap-2.5">
                        <div class="flex-1">
                            <x-form.select :label="t('From (src) *')" name="uom_src" wire:model="uom_src" :options="$this->convertibleUnits->mapWithKeys(fn ($u) => [$u->uom_code => $u->uom_code.' — '.$u->uom_name])" :selected="$uom_src" />
                        </div>
                        <div class="flex-1">
                            <x-form.select :label="t('To (dst) *')" name="uom_dst" wire:model="uom_dst" :options="$this->convertibleUnits->mapWithKeys(fn ($u) => [$u->uom_code => $u->uom_code.' — '.$u->uom_name])" :selected="$uom_dst" />
                        </div>
                    </div>

                    <x-form.input :label="t('Conversion value * (1 src = n × dst)')" name="conversion_value" type="number" step="any" min="0" wire:model="conversion_value" :placeholder="t('e.g. 20')" />
                    <x-form.checkbox :label="t('Active')" wire:model="conversion_activate" />

                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="closeConvModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Cancel') }}</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">{{ t('Save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
