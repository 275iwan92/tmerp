{{--
    (2026-08-30) Export sekarang dropdown ringan (components.grid.export-dropdown)
    yang butuh $gridVisibleColumns dari scope Livewire -- file ini WAJIB
    dipakai via @include, BUKAN lagi <x-grid.toolbar-buttons /> (anonymous
    component scope-nya terisolasi, tak lihat properti Livewire induk).
    Sudah disesuaikan di co/list.blade.php & master/raw-material/index.blade.php.
--}}
<div class="flex items-center gap-1.5 shrink-0 pr-2 mr-0.5 border-r border-[#e2e7ee]">
    <button type="button" wire:click="openColumnModal" title="Pilih kolom tampil"
        class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-[11.5px] font-semibold border border-[#d7dbe2] text-[#4b5568] hover:bg-[#f4f5f8] cursor-pointer">☰ Kolom</button>
    <button type="button" wire:click="openSortModal" title="Urutkan data"
        class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-[11.5px] font-semibold border border-[#d7dbe2] text-[#4b5568] hover:bg-[#f4f5f8] cursor-pointer">↕ Sort</button>
    @include('components.grid.export-dropdown')
</div>
