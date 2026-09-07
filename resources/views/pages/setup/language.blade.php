<?php

use App\Livewire\Concerns\HasSpreadsheetGrid;
use App\Models\Sys\SysLanguage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Component;

// Layar Language (dulu bagian atas "Bahasa" di setup.configuration, dipecah
// dari Localization/Terjemahan jadi route sendiri 2026-09-07 --
// docs/configurationstandard.md). Daftar bahasa aktif (sys_languages) +
// format angka regional. Menambah baris di sini otomatis menambah kolom baru
// di grid Localization (dibangun dari SysLanguage::all() live).
new #[Title('Language — TELASIH MAKMUR ERP')] class extends Component
{
    use HasSpreadsheetGrid;

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
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Language</div>
            <p class="text-[11px] text-muted mb-4">Daftar bahasa aktif + format angka regional. Tambah baris di sini → kolom baru otomatis muncul di layar Localization.</p>
            <x-grid.spreadsheet-table
                grid-key="{{ $this->spreadsheetKey() }}"
                :columns="$this->spreadsheetColumns()"
                :rows="$this->spreadsheetRows()"
                :new-row-defaults="$this->spreadsheetNewRowDefaults()"
                :errors="$this->spreadsheetErrors"
                :version="$this->spreadsheetVersion" />
        </div>
    </div>
</div>
