{{--
    griduistandard.md §6 -- wrapper baris toolbar grid (search + opsional
    tombol Kolom/Sort/Export + opsional "+ New Record"). Slot default diisi
    @include('components.grid.search-box', ...) LANGSUNG di layar pemanggil
    (bukan di file component ini) -- konten slot dievaluasi di scope Livewire
    pemanggil, bukan scope terisolasi component, jadi search-box tetap bisa
    akses $showSearchDropdown dkk seperti biasa. Named slot `tools` (tombol
    Kolom/Sort/Export) & `newRecord` opsional.
--}}
{{-- $attributes->merge(...) supaya layar report yang butuh flex-wrap (2 baris
     filter: search + rentang tanggal) bisa tambah class tanpa fork component. --}}
@props([])
<div {{ $attributes->merge(['class' => 'flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto']) }}>
    {{ $slot }}
    <span class="flex-1"></span>
    {{ $tools ?? '' }}
    {{ $newRecord ?? '' }}
</div>
