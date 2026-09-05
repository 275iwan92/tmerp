@if ($showSortModal)
    <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showSortModal', false)">
        <div class="bg-white rounded-xl w-[360px] max-w-[95vw] shadow-2xl">
            <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                <span>Urutkan Data</span>
                <button type="button" wire:click="$set('showSortModal', false)" class="text-white/70 hover:text-white cursor-pointer">✕</button>
            </div>
            <div class="p-4">
                <div class="text-[10px] font-bold text-muted uppercase mb-2">Kolom (dari yang tampil)</div>
                <div class="border border-[#e2e7ee] rounded-md h-[180px] overflow-y-auto tm-scroll mb-3">
                    <div wire:click="$set('sortModalColumn', null)" class="px-2.5 py-1.5 text-[12px] cursor-pointer border-b border-[#f3f4f6] {{ ! $sortModalColumn ? 'bg-[#eaf2ff] font-semibold text-[#1d4ed8]' : 'hover:bg-[#f4f5f8]' }}">Tidak diurutkan</div>
                    @foreach ($gridVisibleColumns as $key)
                        @continue(($columnDefs[$key]['sortable'] ?? true) === false)
                        <div wire:click="$set('sortModalColumn', '{{ $key }}')" wire:key="sort-{{ $key }}"
                            class="px-2.5 py-1.5 text-[12px] cursor-pointer border-b border-[#f3f4f6] {{ $sortModalColumn === $key ? 'bg-[#eaf2ff] font-semibold text-[#1d4ed8]' : 'hover:bg-[#f4f5f8]' }}">{{ $columnDefs[$key]['label'] ?? $key }}</div>
                    @endforeach
                </div>

                @if ($sortModalColumn)
                    <div class="flex gap-2 mb-1">
                        <button type="button" wire:click="$set('sortModalDirection', 'asc')" class="flex-1 px-3 py-1.5 rounded-md text-[12px] font-semibold cursor-pointer {{ $sortModalDirection === 'asc' ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">▲ A → Z</button>
                        <button type="button" wire:click="$set('sortModalDirection', 'desc')" class="flex-1 px-3 py-1.5 rounded-md text-[12px] font-semibold cursor-pointer {{ $sortModalDirection === 'desc' ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">▼ Z → A</button>
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-3 mt-2 border-t border-[#eef1f5]">
                    <button type="button" wire:click="$set('showSortModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                    <button type="button" wire:click="applySortSelection" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">Terapkan</button>
                </div>
            </div>
        </div>
    </div>
@endif
