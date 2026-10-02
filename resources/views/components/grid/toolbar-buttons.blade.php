{{--
    (2026-08-30) Export sekarang dropdown ringan (components.grid.export-dropdown)
    yang butuh $gridVisibleColumns dari scope Livewire -- file ini WAJIB
    dipakai via @include, BUKAN lagi <x-grid.toolbar-buttons /> (anonymous
    component scope-nya terisolasi, tak lihat properti Livewire induk).
    Sudah disesuaikan di co/list.blade.php & master/raw-material/index.blade.php.
--}}
<div class="flex items-center gap-1.5 shrink-0 pr-2 mr-0.5 border-r border-line">
    <x-ui.button variant="secondary" size="xs" class="shrink-0" wire:click="openColumnModal" title="{{ t('Choose visible columns') }}">☰ {{ t('Columns') }}</x-ui.button>
    <x-ui.button variant="secondary" size="xs" class="shrink-0" wire:click="openSortModal" title="{{ t('Sort data') }}">↕ Sort</x-ui.button>
    @include('components.grid.export-dropdown')
</div>
