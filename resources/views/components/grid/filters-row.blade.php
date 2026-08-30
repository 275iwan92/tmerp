{{--
    Pilot chrome ala PowerGrid (2026-08-30) -- baris panel penuh-lebar di
    BAWAH toolbar, muncul saat tombol "🔽 Filters" (components.grid.filters-panel)
    diklik. Isi & state SAMA PERSIS dgn components.grid.search-box (caret
    popover versi lama, TETAP dipakai apa adanya di grid lain) -- cuma tata-
    letaknya beda, jadi 0 logic baru di trait. HANYA co/list.blade.php.

    $columnDefs = $this->gridSearchColumnDefs() dari komponen pemanggil, dikirim
    lewat @include('components.grid.filters-row', ['columnDefs' => ...]) --
    BUKAN <x-...> supaya scope-nya nyambung ke Livewire pemanggil (sama pola
    dgn search-box.blade.php, lihat gotcha §10 griduistandard.md soal
    anonymous component scope terisolasi).
--}}
@if ($showSearchDropdown)
    <div class="flex flex-wrap items-start gap-5 px-3.5 py-3 bg-[#fafbfd] border-b border-[#eef1f5]">
        <div>
            <div class="text-[10px] font-bold text-muted uppercase mb-1.5">Kolom Dicari</div>
            <div class="flex flex-wrap gap-1.5 max-w-[520px]">
                <button type="button" wire:click="$set('searchModalColumns', [])"
                    class="px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ empty($searchModalColumns) ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">Semua Field</button>
                @foreach ($columnDefs as $key => $label)
                    <button type="button" wire:click="toggleSearchColumn('{{ $key }}')"
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ in_array($key, $searchModalColumns, true) ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div>
            <div class="text-[10px] font-bold text-muted uppercase mb-1.5">Kondisi</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach (['contains' => 'Mengandung', 'starts_with' => 'Diawali', 'equal' => 'Sama Dengan', 'not_contains' => 'Tidak Mengandung'] as $val => $lbl)
                    <button type="button" wire:click="$set('searchModalCondition', '{{ $val }}')"
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ $searchModalCondition === $val ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $lbl }}</button>
                @endforeach
            </div>
        </div>
        <div class="flex items-end gap-2 ml-auto self-end">
            <button type="button" wire:click="resetSearchSettings" class="px-3 py-1.5 rounded-md text-[11px] font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Reset</button>
            <button type="button" wire:click="applySearchSettings" class="px-3 py-1.5 rounded-md text-[11px] font-semibold bg-[#2c3e69] text-white cursor-pointer">Terapkan</button>
        </div>
    </div>
@endif
