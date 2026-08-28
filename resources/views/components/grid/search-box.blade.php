{{-- Search fleksibel (referensi ScriptCase, keputusan user 2026-08-27):
     dropdown ringan (BUKAN modal spt Kolom/Sort/Export) nempel di search box --
     pilih kolom yg dicari + kondisi (Contains/Starts with/Equal/Not Contains).
     $columnDefs = $this->gridSearchColumnDefs() dari komponen pemanggil.

     Ditemukan bug 2026-08-27: toolbar induk pakai `overflow-x-auto` (griduistandard.md
     §6, jaga-jaga layar sempit) -- itu MEMOTONG `position:absolute` biasa
     (CSS: overflow-x selain visible otomatis bikin overflow-y jadi auto juga,
     bukan cuma horizontal). Fix: `x-teleport="body"` (Alpine core, tanpa
     plugin tambahan) pindahkan dropdown ke luar DOM toolbar sepenuhnya,
     posisi dihitung manual via getBoundingClientRect() + position:fixed
     (lepas dari SEMUA batasan overflow leluhur, bukan cuma toolbar ini). --}}
<div class="shrink-0" x-data="{
        top: 0, left: 0,
        reposition() {
            const r = this.$refs.searchAnchor.getBoundingClientRect();
            this.top = r.bottom + 4;
            this.left = r.left;
        }
    }">
    <div class="flex items-stretch" x-ref="searchAnchor">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ $placeholder ?? 'Cari...' }}"
            class="w-52 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded-l text-xs border-r-0">
        <button type="button" wire:click="toggleSearchDropdown" title="Pengaturan pencarian"
            class="shrink-0 px-2 border border-[#d7dbe2] rounded-r bg-[#f8fafc] hover:bg-[#f1f3f6] cursor-pointer text-[10px] text-muted">▾</button>
    </div>

    @if ($showSearchDropdown)
        <template x-teleport="body">
            <div x-init="reposition()" :style="`position:fixed; top:${top}px; left:${left}px;`"
                class="w-[280px] bg-white border border-[#d7dbe2] rounded-md shadow-lg z-[200] text-left"
                wire:click.outside="$set('showSearchDropdown', false)">
                <div class="p-3 border-b border-[#eef1f5]">
                    <div class="text-[10px] font-bold text-muted uppercase mb-1.5">Kolom Dicari</div>
                    <div class="flex flex-wrap gap-1.5 max-h-[140px] overflow-y-auto">
                        <button type="button" wire:click="$set('searchModalColumns', [])"
                            class="px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ empty($searchModalColumns) ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">Semua Field</button>
                        @foreach ($columnDefs as $key => $label)
                            <button type="button" wire:click="toggleSearchColumn('{{ $key }}')"
                                class="px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ in_array($key, $searchModalColumns, true) ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="p-3 border-b border-[#eef1f5]">
                    <div class="text-[10px] font-bold text-muted uppercase mb-1.5">Kondisi Pencarian</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['contains' => 'Mengandung', 'starts_with' => 'Diawali', 'equal' => 'Sama Dengan', 'not_contains' => 'Tidak Mengandung'] as $val => $lbl)
                            <button type="button" wire:click="$set('searchModalCondition', '{{ $val }}')"
                                class="px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ $searchModalCondition === $val ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $lbl }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="p-3 flex justify-end gap-2">
                    <button type="button" wire:click="resetSearchSettings" class="px-3 py-1.5 rounded-md text-[11px] font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Reset</button>
                    <button type="button" wire:click="applySearchSettings" class="px-3 py-1.5 rounded-md text-[11px] font-semibold bg-[#2c3e69] text-white cursor-pointer">Terapkan</button>
                </div>
            </div>
        </template>
    @endif
</div>
