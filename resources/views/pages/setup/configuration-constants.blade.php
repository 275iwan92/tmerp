<?php

use App\Livewire\Concerns\HasSpreadsheetGrid;
use App\Models\Sys\SysConstant;
use App\Models\Sys\SysConstantCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

// Sub-komponen Configuration (docs/configurationstandard.md §3) -- SEMUA
// baris sys_constant, 1 grid (bukan tab/komponen terpisah per kategori --
// HasSpreadsheetGrid cuma bisa 1 grid per komponen, lihat catatan trait).
// "Tabular per kategori" (keputusan user 2026-09-02) diwujudkan lewat kolom
// Kategori yang bisa difilter/diurutkan, BUKAN tab terpisah -- lebih simpel,
// tanpa perlu N komponen Livewire per kategori.
new class extends Component
{
    use HasSpreadsheetGrid;

    protected function spreadsheetKey(): string
    {
        return 'setup-constants';
    }

    protected function spreadsheetColumns(): array
    {
        return [
            // NB: <x-grid.spreadsheet-table> select TIDAK dukung opsi key=>label
            // beda dari value (x-for="opt in col.options", value & teks tampil =
            // opt yg sama) -- ikut kontrak yg sudah dipakai Currency/UOM apa
            // adanya, jadi opsinya kode kategori polos (BRANDING/FINANCE/dst),
            // bukan label ramah "Identitas Aplikasi".
            'category_code' => ['label' => 'Kategori', 'type' => 'select', 'options' => SysConstantCategory::query()->orderBy('sort_order')->pluck('code')->all()],
            'constant_key' => ['label' => 'Key', 'type' => 'text', 'required' => true, 'editableWhenExisting' => false],
            'constant_text' => ['label' => 'Nilai Teks', 'type' => 'text'],
            'constant_number' => ['label' => 'Nilai Angka', 'type' => 'number'],
            'description' => ['label' => 'Keterangan', 'type' => 'text'],
            'activate' => ['label' => 'Aktif', 'type' => 'boolean'],
        ];
    }

    protected function spreadsheetQuery()
    {
        return SysConstant::query()->orderBy('category_code')->orderBy('constant_key');
    }

    protected function spreadsheetRowId($row): int|string
    {
        return $row->id_constant;
    }

    protected function spreadsheetNewRowDefaults(): array
    {
        return ['category_code' => '', 'constant_key' => '', 'constant_text' => '', 'constant_number' => null, 'description' => '', 'activate' => true];
    }

    protected function spreadsheetValidateRow(array $data, int|string|null $existingId): array
    {
        $data['constant_key'] = strtoupper(trim((string) $data['constant_key']));

        $unique = 'unique:tm2sys.sys_constant,constant_key';
        if ($existingId) {
            $unique .= ','.$existingId.',id_constant';
        }

        return Validator::make($data, [
            'category_code' => ['nullable', 'string', 'exists:tm2sys.sys_constant_category,code'],
            'constant_key' => ['required', 'string', 'max:50', $unique],
            'constant_text' => ['nullable', 'string', 'max:255'],
            'constant_number' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
            'activate' => ['boolean'],
        ])->validate();
    }

    protected function spreadsheetPersistRow(array $data, int|string|null $existingId): void
    {
        $payload = [
            'category_code' => $data['category_code'] ?: null,
            'constant_key' => $data['constant_key'],
            'constant_text' => $data['constant_text'] ?: null,
            'constant_number' => $data['constant_number'] !== '' ? $data['constant_number'] : null,
            'description' => $data['description'] ?: null,
            'activate' => (bool) ($data['activate'] ?? false),
            'updated_by' => Auth::user()->login,
        ];

        if ($existingId) {
            SysConstant::whereKey($existingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            SysConstant::create($payload);
        }
    }
};
?>

<div>
    <x-grid.spreadsheet-table
        grid-key="{{ $this->spreadsheetKey() }}"
        :columns="$this->spreadsheetColumns()"
        :rows="$this->spreadsheetRows()"
        :new-row-defaults="$this->spreadsheetNewRowDefaults()"
        :errors="$this->spreadsheetErrors"
        :version="$this->spreadsheetVersion" />
</div>
