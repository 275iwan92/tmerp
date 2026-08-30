{{--
    Pilot chrome ala PowerGrid (2026-08-30, permintaan user) -- tombol
    "Filters" terpisah di toolbar, HANYA dipakai co/list.blade.php sejauh
    ini. JANGAN roll out ke grid lain tanpa ditanya (sama status-nya dgn
    pilot Aksi-kiri §10 griduistandard.md). Ditaruh di dalam <x-grid.toolbar>
    (x-slot:tools, sebelum grup Kolom/Sort/Export) -- cuma tombolnya, panel
    isinya dirender terpisah lewat components.grid.filters-row (lihat file
    itu) krn toolbar induk `flex` TANPA wrap (griduistandard.md §6), jadi
    panel tak bisa "turun baris" di dalam toolbar yang sama.
--}}
<button type="button" wire:click="toggleSearchDropdown" title="Filter pencarian"
    class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-[11.5px] font-semibold border border-[#d7dbe2] text-[#4b5568] hover:bg-[#f4f5f8] cursor-pointer {{ $showSearchDropdown ? 'bg-[#eef2ff] border-accent text-accent' : '' }}">
    🔽 Filters
    @if (! empty($searchColumns))
        <span class="inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-accent text-white text-[9.5px] font-bold">{{ count($searchColumns) }}</span>
    @endif
</button>
