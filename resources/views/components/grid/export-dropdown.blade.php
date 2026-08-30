{{--
    Pilot ala PowerGrid (2026-08-30, permintaan user): tombol "⬇ Export"
    jadi dropdown ringan (CSV/Excel/PDF), BUKAN modal pilih-kolom lagi.
    Aturan baru: export SELALU ikut kolom yang lagi TAMPIL di grid
    ($gridVisibleColumns) -- tidak ada lagi pemilihan kolom export terpisah.
    Tooltip WAJIB menjelaskan ini (permintaan user eksplisit).

    Method Livewire (exportCsv/exportExcel/exportPdf) ada di HasCustomizableGrid,
    masing2 langsung `exportGridXxx($this->gridVisibleColumns)` tanpa modal.
--}}
<div class="relative inline-block" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" @click.stop="open = !open"
        title="Export mengikuti kolom yang sedang TAMPIL di grid ({{ count($gridVisibleColumns) }} kolom). Pakai tombol ☰ Kolom dulu utk ubah tampilan sebelum export."
        class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-[11.5px] font-semibold border border-[#d7dbe2] text-[#4b5568] hover:bg-[#f4f5f8] cursor-pointer">⬇ Export</button>
    <div x-show="open" x-cloak x-transition.origin.top.right @click.stop
        class="absolute right-0 top-9 z-20 bg-white border border-[#d7dbe2] rounded-md shadow-lg min-w-[190px] overflow-hidden py-1">
        <div class="px-3 py-1.5 text-[10px] text-muted leading-relaxed border-b border-[#eef1f5] mb-1">
            Export {{ count($gridVisibleColumns) }} kolom yang sedang tampil.
        </div>
        <button type="button" wire:click.stop="exportExcel" @click="open = false"
            class="w-full text-left px-3 py-2 text-[12px] font-medium flex items-center gap-2 cursor-pointer hover:bg-[#f4f5f8]">📊 Export Excel</button>
        <button type="button" wire:click.stop="exportCsv" @click="open = false"
            class="w-full text-left px-3 py-2 text-[12px] font-medium flex items-center gap-2 cursor-pointer hover:bg-[#f4f5f8]">📄 Export CSV</button>
        <button type="button" wire:click.stop="exportPdf" @click="open = false"
            class="w-full text-left px-3 py-2 text-[12px] font-medium flex items-center gap-2 cursor-pointer hover:bg-[#f4f5f8]">📕 Export PDF</button>
    </div>
</div>
