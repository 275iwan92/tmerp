<?php

use App\Livewire\Concerns\HasSpreadsheetGrid;
use App\Models\Sys\SysLanguage;
use App\Models\Sys\SysTranslation;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

// Sub-komponen Configuration (docs/configurationstandard.md §3) -- daftar
// bahasa (sys_languages, sudah ada tabelnya) + kelola terjemahan
// (sys_translations, juga sudah ada -- dipakai login/forgot-password).
// "+ Baris" bahasa baru otomatis nambah 1 kolom kosong di grid terjemahan
// bawahnya (dibangun dari SysLanguage::all() live, bukan daftar statis) --
// itulah cara "Add new country language" terwujud tanpa infrastruktur baru.
new class extends Component
{
    use HasSpreadsheetGrid;

    // Terjemahan BUKAN spreadsheet 1-kolom-per-field biasa (1 baris trans_key
    // = N kolom, 1 per bahasa aktif) -- bentuknya beda dari kontrak
    // HasSpreadsheetGrid (1 baris = 1 record 1 tabel), jadi ditangani manual
    // di luar trait, disimpan lewat method saveTranslations() sendiri.
    public array $translationEdits = [];

    protected function spreadsheetKey(): string
    {
        return 'setup-languages';
    }

    protected function spreadsheetColumns(): array
    {
        return [
            'code' => ['label' => 'Kode', 'type' => 'text', 'required' => true, 'editableWhenExisting' => false],
            'name' => ['label' => 'Nama Bahasa', 'type' => 'text', 'required' => true],
            'decimal_separator' => ['label' => 'Pemisah Desimal', 'type' => 'text'],
            'thousand_separator' => ['label' => 'Pemisah Ribuan', 'type' => 'text'],
            'is_default' => ['label' => 'Default', 'type' => 'boolean'],
        ];
    }

    protected function spreadsheetQuery()
    {
        return SysLanguage::query()->orderByDesc('is_default')->orderBy('code');
    }

    protected function spreadsheetRowId($row): int|string
    {
        return $row->code;
    }

    protected function spreadsheetNewRowDefaults(): array
    {
        return ['code' => '', 'name' => '', 'decimal_separator' => ',', 'thousand_separator' => '.', 'is_default' => false];
    }

    protected function spreadsheetValidateRow(array $data, int|string|null $existingId): array
    {
        $data['code'] = strtolower(trim((string) $data['code']));

        $unique = 'unique:tm2sys.sys_languages,code';
        if ($existingId) {
            $unique .= ','.$existingId.',code';
        }

        return Validator::make($data, [
            'code' => ['required', 'string', 'max:5', $unique],
            'name' => ['required', 'string', 'max:50'],
            'decimal_separator' => ['required', 'string', 'max:1'],
            'thousand_separator' => ['required', 'string', 'max:1'],
            'is_default' => ['boolean'],
        ])->validate();
    }

    protected function spreadsheetPersistRow(array $data, int|string|null $existingId): void
    {
        if (! empty($data['is_default'])) {
            SysLanguage::query()->update(['is_default' => false]);
        }

        if ($existingId) {
            SysLanguage::whereKey($existingId)->update($data);
        } else {
            SysLanguage::create($data);
        }
    }

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

<div class="flex flex-col gap-4">
    <div>
        <div class="text-xs font-bold text-navy uppercase tracking-wide mb-2">Bahasa Tersedia</div>
        <x-grid.spreadsheet-table
            grid-key="{{ $this->spreadsheetKey() }}"
            :columns="$this->spreadsheetColumns()"
            :rows="$this->spreadsheetRows()"
            :new-row-defaults="$this->spreadsheetNewRowDefaults()"
            :errors="$this->spreadsheetErrors"
            :version="$this->spreadsheetVersion" />
    </div>

    <div x-data="{ edits: {} }">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs font-bold text-navy uppercase tracking-wide">Terjemahan Teks UI</div>
            <button type="button" class="px-3 py-1.5 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer"
                @click="$wire.translationEdits = edits; $wire.saveTranslations(); edits = {}">💾 Simpan Terjemahan</button>
        </div>
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
        <p class="text-[10.5px] text-muted mt-1.5 italic">Tambah bahasa baru di grid atas → kolom baru otomatis muncul di sini, kosong, tinggal diisi.</p>
    </div>
</div>
