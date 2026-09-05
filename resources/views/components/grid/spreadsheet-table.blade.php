{{--
    docs/spreadsheetgridstandard.md -- tabel spreadsheet-editable (adopsi
    perilaku AutoFill DataTables.net, versi ringan buatan sendiri tanpa
    dependency baru, keputusan user 2026-08-30). SEMUA render baris & state
    edit terjadi di client (Alpine, resources/js/spreadsheet-grid.js) --
    server cuma dipanggil 1x lewat saveSpreadsheetBatch() saat "Simpan Semua".

    `wire:key` WAJIB ikut $version (naik tiap saveSpreadsheetBatch()) supaya
    Alpine full re-init sesudah simpan -- state dirty client otomatis bersih,
    data segar/errors dari server ke-load ulang. Kontrak PHP-nya ada di
    App\Livewire\Concerns\HasSpreadsheetGrid.

    Kolom bertipe 'action' (mis. ikon Konversi di UOM) BUKAN sel yang bisa
    diedit -- cuma tombol yang manggil 1 method Livewire dgn 1 parameter
    (col.paramKey, default 'id'). `hideWhenColumn`/`hideWhenValue` opsional
    utk sembunyikan tombolnya kondisional (mis. dimension === 'SPEC').
--}}
@props(['gridKey', 'columns', 'rows', 'newRowDefaults', 'errors' => [], 'version' => 0])
@php
    $jsColumns = collect($columns)->map(fn ($def, $key) => array_merge(['key' => $key], $def))->values()->all();
@endphp
<div wire:key="spreadsheet-{{ $gridKey }}-{{ $version }}"
    x-data="spreadsheetGrid(@js($rows), @js($jsColumns), @js($newRowDefaults), @js($errors))"
    class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">

    <div class="flex items-center justify-between gap-2 px-3.5 py-2 border-b border-[#eef1f5] bg-[#fafbfd] flex-wrap">
        <div class="text-[11px] text-muted leading-relaxed">
            🖊️ <b>Mode Spreadsheet</b> — klik sel untuk edit, drag titik biru di pojok sel aktif untuk salin nilai ke bawah.
            Perubahan baru tersimpan setelah <b>Simpan Semua</b>.
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="addRow()"
                class="px-3 py-1.5 rounded-md text-[11px] font-semibold bg-white border border-[#d7dbe2] cursor-pointer hover:bg-[#f1f3f6]">+ Baris</button>
            <button type="button" @click="saveAll()" :disabled="dirtyCount() === 0"
                :class="dirtyCount() === 0 ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'"
                class="px-3.5 py-1.5 rounded-md text-[11px] font-semibold bg-[#2c3e69] text-white border border-[#2c3e69]">
                💾 Simpan Semua<template x-if="dirtyCount() > 0"><span x-text="' (' + dirtyCount() + ')'"></span></template>
            </button>
        </div>
    </div>

    <div class="overflow-x-auto tm-scroll">
        <table class="w-full border-collapse text-[12.5px]">
            <thead>
                <tr>
                    <template x-for="col in columns" :key="col.key">
                        <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]" x-text="col.label"></th>
                    </template>
                    <th class="w-[36px] border-b border-[#eef1f5] bg-[#fafbfd]"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, r) in rows" :key="row.id">
                    <tr :class="isNewRow(row) ? 'bg-[#fefce8]' : ''">
                        <template x-for="col in columns" :key="col.key">
                            <td class="px-0 py-0 border-b border-[#eef1f5] align-top" @mouseover="overFill(r)">
                                <template x-if="col.type === 'action'">
                                    <div class="px-3 py-2 text-right" x-show="actionVisible(row, col)">
                                        <button type="button" @click.stop="runAction(row, col)" :title="col.title"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer"
                                            x-text="col.icon"></button>
                                    </div>
                                </template>

                                <div x-show="col.type !== 'action'" class="relative">
                                    <input x-show="col.type === 'text' || col.type === 'number'"
                                        :data-cell="r + ':' + col.key"
                                        :type="col.type === 'number' ? 'number' : 'text'"
                                        x-model="row[col.key]"
                                        :readonly="!isEditable(row, col)"
                                        @focus="activateCell(r, col.key)"
                                        @blur="blurCell()"
                                        @keydown.tab.prevent="moveActive(r, col.key, $event.shiftKey ? 'left' : 'right')"
                                        @keydown.enter.prevent="moveActive(r, col.key, 'down')"
                                        :class="isEditable(row, col) ? 'bg-transparent' : 'bg-[#f8fafc] text-muted cursor-not-allowed'"
                                        class="w-full px-3 py-2 border-0 text-[12.5px] focus:bg-[#eef5ff] focus:outline-none">

                                    <select x-show="col.type === 'select'"
                                        x-model="row[col.key]"
                                        :disabled="!isEditable(row, col)"
                                        @focus="activateCell(r, col.key)"
                                        @blur="blurCell()"
                                        class="w-full px-3 py-2 border-0 bg-transparent text-[12.5px] focus:bg-[#eef5ff] focus:outline-none disabled:bg-[#f8fafc] disabled:text-muted">
                                        <template x-for="opt in (col.options || [])" :key="opt">
                                            <option :value="opt" x-text="opt"></option>
                                        </template>
                                    </select>

                                    <div x-show="col.type === 'boolean'" class="px-3 py-2.5">
                                        <input type="checkbox" x-model="row[col.key]" :disabled="!isEditable(row, col)">
                                    </div>

                                    <div x-show="isActive(r, col.key) && isEditable(row, col)"
                                        @mousedown.stop.prevent="startFill(r, col.key)"
                                        class="absolute right-0 bottom-0 w-2 h-2 bg-accent border border-white cursor-crosshair z-10"></div>

                                    <div x-show="isFilling(r, col.key)" class="absolute inset-0 border-2 border-accent pointer-events-none z-[5]"></div>

                                    <div x-show="cellError(row, col.key)" x-text="cellError(row, col.key)"
                                        class="absolute left-1 -bottom-3.5 text-[10px] text-red-600 bg-white px-1 whitespace-nowrap z-20 rounded shadow-sm"></div>
                                </div>
                            </td>
                        </template>
                        <td class="px-1 text-center border-b border-[#eef1f5]">
                            <button type="button" x-show="isNewRow(row)" @click="removeRow(r)" title="Batalkan baris baru"
                                class="text-[#c0392b] cursor-pointer">✕</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="rows.length === 0">
                    <td :colspan="columns.length + 1" class="text-center py-8 text-muted text-sm">Belum ada data. Klik "+ Baris" untuk mulai.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
