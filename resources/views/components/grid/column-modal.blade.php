@if ($showColumnModal)
    <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showColumnModal', false)">
        <div class="bg-white rounded-xl w-[600px] max-w-[95vw] shadow-2xl">
            <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                <span>{{ \App\Support\Localization\Translator::t('shared_colmodal_title', 'Pilih Kolom Tampil') }}</span>
                <button type="button" wire:click="$set('showColumnModal', false)" class="text-white/70 hover:text-white cursor-pointer">✕</button>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-[1fr_44px_1fr] gap-2 items-start">
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase mb-1.5">{{ \App\Support\Localization\Translator::t('shared_colmodal_available', 'Tersedia') }}</div>
                        <input type="text" wire:model.live.debounce.150ms="columnModalSearch" placeholder="{{ \App\Support\Localization\Translator::t('shared_colmodal_search_placeholder', 'Cari kolom…') }}" class="w-full mb-1.5 px-2 py-1.5 border border-[#d7dbe2] rounded text-[12px]">
                        <div class="border border-[#e2e7ee] rounded-md h-[230px] overflow-y-auto tm-scroll bg-[#fafbfd]">
                            @foreach ($columnDefs as $key => $def)
                                @continue(in_array($key, $columnModalSelected, true))
                                @continue($columnModalSearch !== '' && ! str_contains(mb_strtolower($def['label']), mb_strtolower($columnModalSearch)))
                                <div wire:click="addColumnToSelection('{{ $key }}')" wire:key="col-avail-{{ $key }}"
                                    class="px-2.5 py-1.5 text-[12px] cursor-pointer hover:bg-[#eef3fb] border-b border-[#f3f4f6]">{{ $def['label'] }}</div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5 pt-[27px] items-center">
                        <button type="button" wire:click="addAllColumns" title="{{ \App\Support\Localization\Translator::t('shared_colmodal_show_all_title', 'Tampilkan semua') }}" class="w-9 h-8 rounded-md border border-[#d7dbe2] hover:bg-[#f4f5f8] cursor-pointer text-[13px]">»</button>
                        <button type="button" wire:click="removeAllColumns" title="{{ \App\Support\Localization\Translator::t('shared_colmodal_clear_all_title', 'Kosongkan') }}" class="w-9 h-8 rounded-md border border-[#d7dbe2] hover:bg-[#f4f5f8] cursor-pointer text-[13px]">«</button>
                    </div>

                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase mb-1.5">{{ \App\Support\Localization\Translator::t('shared_colmodal_visible', 'Tampil') }} ({{ count($columnModalSelected) }})</div>
                        <input type="text" disabled placeholder="{{ \App\Support\Localization\Translator::t('shared_colmodal_click_remove', 'klik utk hapus →') }}" class="w-full mb-1.5 px-2 py-1.5 border border-[#e2e7ee] rounded text-[12px] bg-[#f8fafc] text-muted/70">
                        <div class="border border-[#e2e7ee] rounded-md h-[230px] overflow-y-auto tm-scroll bg-[#fafbfd]">
                            @foreach ($columnModalSelected as $key)
                                <div wire:click="removeColumnFromSelection('{{ $key }}')" wire:key="col-sel-{{ $key }}"
                                    class="group px-2.5 py-1.5 text-[12px] cursor-pointer hover:bg-[#fdecec] border-b border-[#f3f4f6] flex items-center justify-between">
                                    <span>{{ $columnDefs[$key]['label'] ?? $key }}</span>
                                    <span class="text-[#c0392b] opacity-0 group-hover:opacity-100">✕</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-[#eef1f5]">
                    <div class="text-[10px] font-bold text-muted uppercase mb-2">{{ \App\Support\Localization\Translator::t('shared_colmodal_groupby_label', 'Group By (dari kolom tampil)') }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" wire:click="setGridGroupBy(null)"
                            class="px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ ! $gridGroupBy ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ \App\Support\Localization\Translator::t('shared_colmodal_groupby_none', 'Tidak ada') }}</button>
                        @foreach ($columnModalSelected as $key)
                            <button type="button" wire:click="setGridGroupBy('{{ $key }}')"
                                class="px-2.5 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ $gridGroupBy === $key ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $columnDefs[$key]['label'] ?? $key }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-between items-center gap-2 pt-4 mt-3 border-t border-[#eef1f5]">
                    <button type="button" wire:click="resetGridPreference" class="px-3 py-1.5 rounded-md text-[11px] font-semibold text-[#a3453f] hover:bg-[#fdecec] cursor-pointer">↺ {{ \App\Support\Localization\Translator::t('shared_colmodal_reset', 'Reset ke default') }}</button>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('showColumnModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ \App\Support\Localization\Translator::t('shared_btn_cancel', 'Batal') }}</button>
                        <button type="button" wire:click="applyColumnSelection" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">{{ \App\Support\Localization\Translator::t('shared_btn_apply', 'Terapkan') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
