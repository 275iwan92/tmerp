<?php

use App\Livewire\Concerns\HasSpreadsheetGrid;
use App\Models\Master\FinCurrency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master Currency -- layar BARU (sebelumnya fin_currency cuma dipakai lewat
// dropdown di layar lain, belum pernah punya CRUD sendiri). Jadi pilot #1
// docs/spreadsheetgridstandard.md (keputusan user 2026-08-30, adopsi
// perilaku AutoFill DataTables.net versi ringan) -- kolomnya sedikit &
// scalar murni, cocok utk edit ala spreadsheet drpd form modal terpisah.
new #[Title('Master Currency — TELASIH MAKMUR ERP')] class extends Component
{
    use HasSpreadsheetGrid;

    protected function spreadsheetKey(): string
    {
        return 'master-currency';
    }

    protected function spreadsheetColumns(): array
    {
        return [
            'currency_code' => ['label' => 'Kode', 'type' => 'text', 'required' => true, 'editableWhenExisting' => false],
            'currency_name' => ['label' => 'Nama Mata Uang', 'type' => 'text', 'required' => true],
            'symbol' => ['label' => 'Simbol', 'type' => 'text'],
            'counted_word' => ['label' => 'Kata Hitung (Terbilang)', 'type' => 'text'],
            'activate' => ['label' => 'Aktif', 'type' => 'boolean'],
        ];
    }

    protected function spreadsheetQuery()
    {
        return FinCurrency::query()->orderBy('currency_code');
    }

    protected function spreadsheetRowId($row): int|string
    {
        return $row->id_currency;
    }

    protected function spreadsheetNewRowDefaults(): array
    {
        return ['currency_code' => '', 'currency_name' => '', 'symbol' => '', 'counted_word' => '', 'activate' => true];
    }

    protected function spreadsheetValidateRow(array $data, int|string|null $existingId): array
    {
        $data['currency_code'] = strtoupper(trim((string) $data['currency_code']));

        $unique = 'unique:tm2mst.fin_currency,currency_code';
        if ($existingId) {
            $unique .= ','.$existingId.',id_currency';
        }

        return Validator::make($data, [
            'currency_code' => ['required', 'string', 'max:10', $unique],
            'currency_name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'counted_word' => ['nullable', 'string', 'max:100'],
            'activate' => ['boolean'],
        ])->validate();
    }

    protected function spreadsheetPersistRow(array $data, int|string|null $existingId): void
    {
        $payload = [
            'currency_code' => $data['currency_code'],
            'currency_name' => $data['currency_name'],
            'symbol' => $data['symbol'] ?: null,
            'counted_word' => $data['counted_word'] ?: null,
            'activate' => (bool) ($data['activate'] ?? false),
            'updated_by' => Auth::user()->login,
        ];

        if ($existingId) {
            FinCurrency::whereKey($existingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            FinCurrency::create($payload);
        }
    }

    #[Computed]
    public function statTotal(): int
    {
        return FinCurrency::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return FinCurrency::where('activate', true)->count();
    }

    #[Computed]
    public function statInactive(): int
    {
        return FinCurrency::where('activate', false)->count();
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" label="Total Mata Uang" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" label="Aktif" :value="$this->statActive" />
            <x-grid.stat-box tone="critical" label="Nonaktif" :value="$this->statInactive" />
        </x-grid.stat-row>

        <x-grid.spreadsheet-table
            grid-key="{{ $this->spreadsheetKey() }}"
            :columns="$this->spreadsheetColumns()"
            :rows="$this->spreadsheetRows()"
            :new-row-defaults="$this->spreadsheetNewRowDefaults()"
            :errors="$this->spreadsheetErrors"
            :version="$this->spreadsheetVersion" />
    </div>
</div>
