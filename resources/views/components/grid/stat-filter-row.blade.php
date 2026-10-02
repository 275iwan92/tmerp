{{--
    griduistandard.md §2b -- baris kotak statistik yang SEKALIGUS filter data
    (ketentuan user 2026-09-21). Dipasangkan dgn trait
    App\Livewire\Concerns\HasGridStatFilter: layar cukup mendeklarasikan
    statFilterDefs(), markup-nya 1 baris ini utk semua grid.

    :boxes = hasil $this->statBoxes() (label/value/tone/subtitle/filterable/
    selected). :active = $statFilter yang sedang jalan.

    Kotak tanpa 'filterable' (angka agregat: rupiah, rata-rata) tetap tampil
    tapi tidak bisa diklik -- lihat catatan di trait.
--}}
@props(['boxes' => [], 'active' => ''])
@php
    $activeBox = $active !== '' ? ($boxes[$active] ?? null) : null;
@endphp
@if (! empty($boxes))
    <x-grid.stat-row :count="count($boxes)" :class="$activeBox ? 'mb-2' : ''">
        @foreach ($boxes as $key => $box)
            <x-grid.stat-box
                :tone="$box['tone'] ?? 'total'"
                :label="$box['label']"
                :value="$box['value']"
                :subtitle="$box['subtitle'] ?? false"
                :action="($box['filterable'] ?? false) ? 'toggleStatFilter('.\Illuminate\Support\Js::from($key).')' : false"
                :selected="$box['selected'] ?? false" />
        @endforeach
    </x-grid.stat-row>

    @if ($activeBox)
        <div class="flex items-center gap-2 mb-3.5 text-[11px]">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-accent-soft border border-field-border text-navy font-semibold">
                <i class="fa-solid fa-filter text-[10px]"></i>
                {{ t('Filter active') }}:
                <b>{{ $activeBox['label'] }}</b>
            </span>
            <button type="button" wire:click="clearStatFilter"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-field-border bg-white text-muted hover:text-navy cursor-pointer">
                <i class="fa-solid fa-xmark text-[10px]"></i>
                {{ t('Clear filter') }}
            </button>
        </div>
    @endif
@endif
