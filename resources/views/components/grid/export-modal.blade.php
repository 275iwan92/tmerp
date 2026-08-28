@if ($showExportModal)
    <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="$set('showExportModal', false)">
        <div class="bg-white rounded-xl w-[600px] max-w-[95vw] shadow-2xl">
            <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                <span>Export Data</span>
                <button type="button" wire:click="$set('showExportModal', false)" class="text-white/70 hover:text-white cursor-pointer">✕</button>
            </div>
            <div class="p-4">
                <div class="text-[10px] font-bold text-muted uppercase mb-2">Format</div>
                <div class="flex gap-2 mb-4">
                    <button type="button" wire:click="$set('exportFormat', 'excel')" class="flex-1 px-3 py-2 rounded-md text-[12px] font-semibold cursor-pointer flex items-center justify-center gap-1.5 {{ $exportFormat === 'excel' ? 'bg-[#16a34a] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">📊 Excel</button>
                    <button type="button" wire:click="$set('exportFormat', 'csv')" class="flex-1 px-3 py-2 rounded-md text-[12px] font-semibold cursor-pointer flex items-center justify-center gap-1.5 {{ $exportFormat === 'csv' ? 'bg-[#0ea5e9] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">📄 CSV</button>
                    <button type="button" wire:click="$set('exportFormat', 'pdf')" class="flex-1 px-3 py-2 rounded-md text-[12px] font-semibold cursor-pointer flex items-center justify-center gap-1.5 {{ $exportFormat === 'pdf' ? 'bg-[#c0392b] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">📕 PDF</button>
                </div>

                <div class="text-[10px] font-bold text-muted uppercase mb-1.5">Kolom yang diexport (boleh beda dari yang tampil di grid)</div>
                <div class="grid grid-cols-[1fr_44px_1fr] gap-2 items-start">
                    <div>
                        <div class="border border-[#e2e7ee] rounded-md h-[190px] overflow-y-auto bg-[#fafbfd]">
                            @foreach ($columnDefs as $key => $def)
                                @continue(in_array($key, $exportColumns, true))
                                <div wire:click="addExportColumn('{{ $key }}')" wire:key="exp-avail-{{ $key }}"
                                    class="px-2.5 py-1.5 text-[12px] cursor-pointer hover:bg-[#eef3fb] border-b border-[#f3f4f6]">{{ $def['label'] }}</div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5 pt-1 items-center">
                        <button type="button" wire:click="addAllExportColumns" title="Semua" class="w-9 h-8 rounded-md border border-[#d7dbe2] hover:bg-[#f4f5f8] cursor-pointer text-[13px]">»</button>
                        <button type="button" wire:click="removeAllExportColumns" title="Kosongkan" class="w-9 h-8 rounded-md border border-[#d7dbe2] hover:bg-[#f4f5f8] cursor-pointer text-[13px]">«</button>
                    </div>
                    <div>
                        <div class="border border-[#e2e7ee] rounded-md h-[190px] overflow-y-auto bg-[#fafbfd]">
                            @foreach ($exportColumns as $key)
                                <div wire:click="removeExportColumn('{{ $key }}')" wire:key="exp-sel-{{ $key }}"
                                    class="group px-2.5 py-1.5 text-[12px] cursor-pointer hover:bg-[#fdecec] border-b border-[#f3f4f6] flex items-center justify-between">
                                    <span>{{ $columnDefs[$key]['label'] ?? $key }}</span>
                                    <span class="text-[#c0392b] opacity-0 group-hover:opacity-100">✕</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 mt-3 border-t border-[#eef1f5]">
                    <button type="button" wire:click="$set('showExportModal', false)" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">Batal</button>
                    <button type="button" wire:click="runExport" wire:loading.attr="disabled" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">⬇ Export</button>
                </div>
            </div>
        </div>
    </div>
@endif
