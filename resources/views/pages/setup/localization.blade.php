<?php

use App\Models\Sys\SysLanguage;
use App\Models\Sys\SysTranslation;
use Livewire\Attributes\Title;
use Livewire\Component;

// Layar Localization/Terjemahan (dulu bagian bawah "Bahasa" di
// setup.configuration, dipecah dari Language jadi route sendiri 2026-09-07 --
// docs/configurationstandard.md). Kelola sys_translations (dipakai
// login/forgot-password lewat SysTranslation::forLang()). BUKAN spreadsheet
// 1-kolom-per-field biasa (1 baris trans_key = N kolom, 1 per bahasa aktif),
// jadi tabel custom, bukan HasSpreadsheetGrid.
new #[Title('Localization — TELASIH MAKMUR ERP')] class extends Component
{
    public array $translationEdits = [];

    public function getLanguagesProperty()
    {
        return SysLanguage::query()->orderByDesc('is_default')->orderBy('code')->get();
    }

    public function getTranslationRowsProperty(): array
    {
        $languages = $this->languages->pluck('code');
        $flat = SysTranslation::query()->get(['lang_code', 'trans_key', 'trans_value']);

        $keys = $flat->pluck('trans_key')->unique()->sort()->values();

        return $keys->map(function ($key) use ($flat, $languages) {
            $row = ['trans_key' => $key];
            foreach ($languages as $lang) {
                $row[$lang] = $flat->first(fn ($t) => $t->trans_key === $key && $t->lang_code === $lang)?->trans_value ?? '';
            }

            return $row;
        })->all();
    }

    public function saveTranslations(): void
    {
        foreach ($this->translationEdits as $key => $perLang) {
            foreach ($perLang as $langCode => $value) {
                if ($value === null) {
                    continue;
                }

                SysTranslation::updateOrCreate(
                    ['lang_code' => $langCode, 'trans_key' => $key],
                    ['trans_value' => $value]
                );
            }
        }

        $this->translationEdits = [];
        $this->dispatch('app-alert', type: 'success', message: 'Terjemahan tersimpan.');
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5" x-data="{ edits: {} }">
            <div class="flex items-center justify-between mb-1">
                <div class="text-sm font-bold text-navy uppercase tracking-wide">Localization — Terjemahan Teks UI</div>
                <button type="button" class="px-3 py-1.5 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer"
                    @click="$wire.translationEdits = edits; $wire.saveTranslations(); edits = {}">💾 Simpan Terjemahan</button>
            </div>
            <p class="text-[11px] text-muted mb-4">Tambah bahasa baru di layar Language → kolom baru otomatis muncul di sini, kosong, tinggal diisi.</p>

            <div class="overflow-x-auto border border-[#d7dbe2] rounded-md">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-[#fbfcfe]">
                            <th class="text-left px-2.5 py-1.5 text-[10px] text-muted uppercase font-bold border-b border-[#eef1f5]">Key</th>
                            @foreach ($this->languages as $lang)
                                <th class="text-left px-2.5 py-1.5 text-[10px] text-muted uppercase font-bold border-b border-[#eef1f5]">{{ $lang->name }} ({{ $lang->code }})</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->translationRows as $row)
                            <tr class="border-b border-[#f1f5f9]">
                                <td class="px-2.5 py-1.5 font-mono text-[11px] text-muted">{{ $row['trans_key'] }}</td>
                                @foreach ($this->languages as $lang)
                                    <td class="px-1 py-1">
                                        <input type="text" value="{{ $row[$lang->code] ?? '' }}"
                                            x-on:input="edits['{{ $row['trans_key'] }}'] ??= {}; edits['{{ $row['trans_key'] }}']['{{ $lang->code }}'] = $event.target.value"
                                            class="w-full px-2 py-1 border border-[#c7cfda] rounded text-[11px]">
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="99" class="text-center py-6 text-muted">Belum ada key terjemahan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
