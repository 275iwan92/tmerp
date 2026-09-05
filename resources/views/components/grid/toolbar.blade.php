{{--
    griduistandard.md §6 -- wrapper baris toolbar grid (search + opsional
    tombol Filters/Kolom/Sort/Export + opsional "+ New Record"). Slot default
    diisi input search polos LANGSUNG di layar pemanggil (bukan di file
    component ini) -- konten slot dievaluasi di scope Livewire pemanggil,
    bukan scope terisolasi component. Named slot `tools` (tombol Filters via
    components.grid.filters-panel §12 + Kolom/Sort/Export via
    components.grid.toolbar-buttons) & `newRecord` opsional. Panel filter
    (components.grid.filters-row) dirender TERPISAH sesudah komponen ini,
    bukan di dalam slot manapun -- lihat catatan di filters-row.blade.php.
--}}
{{-- $attributes->merge(...) supaya layar report yang butuh flex-wrap (2 baris
     filter: search + rentang tanggal) bisa tambah class tanpa fork component. --}}
@props([])
<div {{ $attributes->merge(['class' => 'flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto tm-scroll']) }}>
    {{ $slot }}
    <span class="flex-1"></span>
    {{ $tools ?? '' }}
    {{ $newRecord ?? '' }}
</div>
