<?php

use App\Models\Master\UomConversion;
use App\Models\Master\UomUnit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Master Satuan (UoM) — TELASIH MAKMUR ERP')] class extends Component
{
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

    public bool $unit_is_active = true;

    // ---- modal konversi ----
    public bool $showConvModal = false;

    public string $convMode = 'new'; // new | edit

    public ?int $editingConversionId = null;

    public string $uom_src = '';

    public string $uom_dst = '';

    public string $conversion_value = '';

    public bool $conversion_is_active = true;

    #[Computed]
    public function units()
    {
        return UomUnit::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('uom_code', 'like', "%{$this->search}%")
                ->orWhere('uom_name', 'like', "%{$this->search}%")))
            ->orderBy('dimension')->orderBy('uom_code')
            ->get();
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
            ->when($this->hideInactiveConversions, fn ($q) => $q->where('is_active', true))
            ->orderBy('uom_src')->orderBy('uom_dst')
            ->get();
    }

    #[Computed]
    public function factorMap(): array
    {
        $units = UomUnit::query()->get(['uom_code', 'dimension', 'is_base']);
        $conversions = UomConversion::query()->where('is_active', true)->get(['uom_src', 'uom_dst', 'conversion_value']);

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
            $this->unit_is_active = (bool) $u->is_active;
        } else {
            $this->reset(['uom_code', 'dimension', 'uom_name', 'is_base']);
            $this->unit_is_active = true;
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
            'unit_is_active' => ['boolean'],
        ]);

        if ($data['dimension'] === 'SPEC' && $data['is_base']) {
            $this->addError('is_base', 'Dimensi SPEC tidak boleh punya satuan dasar.');

            return;
        }

        $payload = [
            'uom_code' => $data['uom_code'],
            'uom_name' => $data['uom_name'],
            'dimension' => $data['dimension'],
            'is_base' => $data['is_base'],
            'is_active' => $data['unit_is_active'],
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
            $this->conversion_is_active = (bool) $c->is_active;
        } else {
            $this->uom_src = $this->selectedCode ?? '';
            $this->uom_dst = '';
            $this->conversion_value = '';
            $this->conversion_is_active = true;
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
            'conversion_is_active' => ['boolean'],
        ]);

        $srcUnit = UomUnit::where('uom_code', $data['uom_src'])->first();
        $dstUnit = UomUnit::where('uom_code', $data['uom_dst'])->first();

        if (! $srcUnit || ! $dstUnit) {
            $this->addError('uom_dst', 'Satuan tidak ditemukan.');

            return;
        }

        if ($srcUnit->dimension !== $dstUnit->dimension) {
            $this->addError('uom_dst', 'Dimensi berbeda ('.$srcUnit->dimension.' vs '.$dstUnit->dimension.') — konversi tidak sah.');

            return;
        }

        $dupQuery = UomConversion::where('uom_src', $data['uom_src'])->where('uom_dst', $data['uom_dst']);
        if ($this->convMode === 'edit' && $this->editingConversionId) {
            $dupQuery->where('id_conversion', '!=', $this->editingConversionId);
        }
        if ($dupQuery->exists()) {
            $this->addError('uom_dst', 'Pasangan '.$data['uom_src'].' → '.$data['uom_dst'].' sudah ada. Edit baris tersebut.');

            return;
        }

        $payload = [
            'uom_src' => $data['uom_src'],
            'uom_dst' => $data['uom_dst'],
            'conversion_value' => $data['conversion_value'],
            'is_active' => $data['conversion_is_active'],
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
        $c->is_active = ! $c->is_active;
        $c->updated_by = Auth::user()->login;
        $c->save();

        unset($this->directConversions, $this->derivedConversions, $this->factorMap);
    }

    public function toggleUnitActive(string $code): void
    {
        $u = UomUnit::where('uom_code', $code)->firstOrFail();
        $u->is_active = ! $u->is_active;
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
        return UomUnit::where('is_active', true)->count();
    }

    #[Computed]
    public function statBase(): int
    {
        return UomUnit::where('is_base', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return UomUnit::where('is_active', false)->count();
    }

    // griduistandard.md #3 -- avatar inisial+warna utk kolom identitas.
    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    private const AVATAR_PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public function avatarGradient(int $id): string
    {
        return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="grid gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total Satuan</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#e9f9ef] border border-[#c8ecd6]">
                <p class="text-[10.5px] font-bold uppercase text-[#2f7a52]">Aktif</p>
                <p class="text-xl font-extrabold text-[#16a34a] mt-0.5">{{ $this->statActive }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Satuan Dasar</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ $this->statBase }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fdecec] border border-[#f5c6c6]">
                <p class="text-[10.5px] font-bold uppercase text-[#a3453f]">Nonaktif</p>
                <p class="text-xl font-extrabold text-[#c0392b] mt-0.5">{{ $this->statInactive }}</p>
            </div>
        </div>

        <div class="text-xs px-3 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#fff4e5] border border-[#ffd9a0] text-[#8a4b00]">
            🔒 Konversi hanya sah <b>dalam satu dimensi</b>. Satuan <b>SPEC</b> (GSM, WARNA, dsb.) tak dikonversi.
            Pilih satuan di kiri, klik <b>🔁 Konversi</b>, lalu <b>+ Konversi Baru</b> di panel kanan.
        </div>

        {{-- ============ GRID (LIST + PANEL KONVERSI) ============ --}}
        @if ($panel === 'grid')
        <div class="[display:grid] grid-cols-1 {{ $conversionPanelOpen ? 'lg:grid-cols-[1fr_420px]' : 'lg:grid-cols-1' }} gap-3.5 items-start">

            {{-- ============ KARTU KIRI: LIST SATUAN ============ --}}
            <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span class="text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama…"
                            class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <span class="flex-1"></span>
                    <button type="button" wire:click="openUnitModal('new')"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </div>

                <div class="max-h-[65vh] overflow-auto">
                    <table class="w-full border-collapse text-[12.5px]">
                        <thead>
                            <tr>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Satuan</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[120px]">Dimensi</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Dasar</th>
                                <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Status</th>
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->units as $u)
                                <tr wire:key="unit-{{ $u->id_uom }}" wire:click="selectUnit('{{ $u->uom_code }}')" wire:dblclick="openUnitModal('edit', '{{ $u->uom_code }}')"
                                    class="cursor-pointer hover:bg-[#f7faff] {{ $selectedCode === $u->uom_code ? 'bg-[#eef5ff] shadow-[inset_3px_0_0_var(--color-accent)]' : '' }}" title="Klik 2x untuk buka detail">
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($u->id_uom) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($u->uom_name ?: $u->uom_code) }}</div>
                                            <div>
                                                <div class="font-semibold text-[13px]">{{ $u->uom_name ?: '—' }}</div>
                                                <div class="text-[11px] text-muted font-mono">{{ $u->uom_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->dimensionBadgeClass($u->dimension) }}">{{ $u->dimension ?: '—' }}</span>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">{!! $u->is_base ? '<span class="text-sm text-[#e0a800]">⭐</span>' : '' !!}</td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5]">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block {{ $u->is_active ? 'bg-[#eaf7ee] text-[#1f8a4c]' : 'bg-[#eef0f3] text-[#8a8f98]' }}">{{ $u->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                    </td>
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        @if ($u->dimension !== 'SPEC')
                                            <button type="button" wire:click.stop="openConversionPanel('{{ $u->uom_code }}')" title="Konversi" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">🔁</button>
                                        @endif
                                        <button type="button" wire:click.stop="openUnitModal('edit', '{{ $u->uom_code }}')" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
                                        <button type="button" wire:click.stop="toggleUnitActive('{{ $u->uom_code }}')" wire:confirm="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} satuan {{ $u->uom_code }}?"
                                            title="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $u->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-8 text-muted text-sm">Tidak ada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex gap-3.5 flex-wrap text-[11px] text-[#667] px-3.5 py-2 border-t border-[#eef1f5] bg-[#fafbfd]">
                    <span><b class="text-navy">Cara pakai:</b> klik baris → tombol <b>Konversi</b> / <b>Edit</b> aktif. <span class="text-muted ml-1.5">{{ $this->units->count() }} satuan</span></span>
                    <span><b>⭐</b> = satuan dasar dimensi.</span>
                </div>
            </div>

            {{-- ============ KARTU KANAN: PANEL KONVERSI ============ --}}
            @if ($conversionPanelOpen)
                <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
                    <div class="px-3.5 py-3 bg-navy text-white text-[13px] font-semibold flex items-center gap-2 flex-wrap">
                        <span>Konversi {{ $selectedCode }} · {{ $this->selectedUnit?->uom_name }}</span>
                        <button type="button" wire:click="openConvModal('new')" class="ml-auto px-3 py-1.5 rounded-md text-[11px] font-semibold cursor-pointer bg-white/10 text-white border border-white/40 hover:bg-white/20">+ Konversi Baru</button>
                        <span class="cursor-pointer opacity-80 hover:opacity-100" wire:click="closeConversionPanel">✕</span>
                    </div>
                    <div class="px-3.5 py-2 border-b border-[#eef1f5]">
                        <label class="flex items-center gap-1.5 text-xs cursor-pointer">
                            <input type="checkbox" wire:model.live="hideInactiveConversions"> Sembunyikan konversi nonaktif
                        </label>
                    </div>

                    <div class="text-[11px] font-bold uppercase tracking-wide text-muted px-3.5 pt-2.5 pb-1">Konversi Langsung</div>
                    <div>
                        @forelse ($this->directConversions as $c)
                            <div wire:key="dc-{{ $c->id_conversion }}" class="flex items-center gap-2 px-3.5 py-2 border-b border-[#f0f3f7] text-[13px]">
                                <span class="font-mono font-bold text-navy min-w-[60px]">{{ $c->uom_src }}</span>
                                <span class="text-muted">→</span>
                                <span class="font-mono text-[#556] min-w-[44px]">{{ $c->uom_dst }}</span>
                                <span class="font-mono font-bold text-[#177a4b] text-right ml-1">1 {{ $c->uom_src }} = {{ $this->formatNumber($c->conversion_value) }} {{ $c->uom_dst }}</span>
                                @unless ($c->is_active)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#f0d6d6] text-[#a33]">NONAKTIF</span>
                                @endunless
                                <span class="ml-auto flex gap-0.5 shrink-0">
                                    <button type="button" wire:click="openConvModal('edit', {{ $c->id_conversion }})" title="Edit" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
                                    <button type="button" wire:click="toggleConversionActive({{ $c->id_conversion }})" wire:confirm="{{ $c->is_active ? 'Nonaktifkan' : 'Aktifkan' }} konversi {{ $c->uom_src }} → {{ $c->uom_dst }}?"
                                        title="{{ $c->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $c->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
                                </span>
                            </div>
                        @empty
                            <div class="text-center text-muted text-[12.5px] px-3.5 py-5 leading-relaxed">Belum ada pasangan konversi langsung untuk <b>{{ $selectedCode }}</b>. Klik <b>+ Konversi Baru</b> di atas.</div>
                        @endforelse
                    </div>

                    @if ($this->derivedConversions->isNotEmpty())
                        <div class="text-[11px] font-bold uppercase tracking-wide text-muted px-3.5 pt-2.5 pb-1">Turunan ke Semua Satuan se-Dimensi</div>
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
                    {{ $unitMode === 'edit' ? 'Edit Satuan' : 'Satuan Baru' }}
                    @if ($unitMode === 'edit')<span class="text-xs font-normal opacity-75 ml-2">{{ $editingUnitCode }}</span>@endif
                </div>
            </div>
            <form wire:submit="saveUnit" class="bg-white border border-t-0 border-[#d7dbe2] p-5 flex flex-col gap-3">
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Kode Satuan *</label>
                            <input type="text" wire:model="uom_code" maxlength="10" placeholder="mis. IK16" @disabled($unitMode === 'edit')
                                class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px] uppercase disabled:bg-[#f2f4f7] disabled:text-muted">
                            @error('uom_code') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Dimensi *</label>
                            <select wire:model="dimension" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($dimensions as $d)
                                    <option value="{{ $d }}">{{ $d }}</option>
                                @endforeach
                            </select>
                            @error('dimension') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Nama Satuan *</label>
                        <input type="text" wire:model="uom_name" placeholder="mis. Ikat isi 16 pcs" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        @error('uom_name') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="is_base"> Jadikan satuan <b>dasar</b> untuk dimensi ini (hanya 1 dasar per dimensi — dasar lama otomatis dilepas)
                    </label>
                    @error('is_base') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="unit_is_active"> Aktif
                    </label>
                    <div class="text-[11px] text-muted leading-relaxed">Dimensi <b>SPEC</b> tidak bisa punya satuan dasar / dikonversi. Setelah satuan tersimpan, atur konversinya lewat panel <b>🔁 Konversi</b>.</div>

                    <div class="flex gap-2 pt-3.5 -mx-5 -mb-5 px-4 py-3.5 bg-[#fafbfc] border-t border-[#d7dbe2] rounded-b-md">
                        <button type="submit" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                        <button type="button" wire:click="showGrid" class="px-4 py-2 rounded text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
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
                    <span>{{ $convMode === 'edit' ? 'Edit Konversi · '.$uom_src.' → '.$uom_dst : 'Konversi Baru · dari '.($uom_src ?: '—') }}</span>
                    <span class="ml-auto cursor-pointer opacity-85 hover:opacity-100" wire:click="closeConvModal">✕</span>
                </div>
                <form wire:submit="saveConv" class="p-4 flex flex-col gap-3">
                    <div class="flex gap-2.5">
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Dari (src) *</label>
                            <select wire:model="uom_src" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->convertibleUnits as $u)
                                    <option value="{{ $u->uom_code }}">{{ $u->uom_code }} — {{ $u->uom_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 flex flex-col gap-1">
                            <label class="text-xs font-semibold text-[#556]">Ke (dst) *</label>
                            <select wire:model="uom_dst" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                                <option value="">— pilih —</option>
                                @foreach ($this->convertibleUnits as $u)
                                    <option value="{{ $u->uom_code }}">{{ $u->uom_code }} — {{ $u->uom_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @error('uom_src') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    @error('uom_dst') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-[#556]">Nilai konversi * <span class="text-muted font-normal normal-case">(1 src = n × dst)</span></label>
                        <input type="number" step="any" min="0" wire:model="conversion_value" placeholder="mis. 20" class="px-2.5 py-2 border border-[#c7cfda] rounded-md text-[13px]">
                        @error('conversion_value') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-[13px] text-[#334]">
                        <input type="checkbox" wire:model="conversion_is_active"> Aktif
                    </label>

                    <div class="flex gap-2 justify-end pt-2 border-t border-[#eef1f5] mt-1">
                        <button type="button" wire:click="closeConvModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
