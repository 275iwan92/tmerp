<?php

use App\Livewire\Concerns\HasGridStatFilter;
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
    use HasGridStatFilter;

    use HasSpreadsheetGrid;

    protected function spreadsheetKey(): string
    {
        return 'master-currency';
    }

    protected function spreadsheetColumns(): array
    {
        return [
            'currency_code' => ['label' => t('Code'), 'type' => 'text', 'required' => true, 'editableWhenExisting' => false],
            'currency_name' => ['label' => t('Currency Name'), 'type' => 'text', 'required' => true],
            'symbol' => ['label' => t('Symbol'), 'type' => 'text'],
            'counted_word' => ['label' => t('Counted Word (Amount in Words)'), 'type' => 'text'],
            'activate' => ['label' => t('Active'), 'type' => 'boolean'],
        ];
    }

    protected function spreadsheetQuery()
    {
        return $this->applyStatFilter(FinCurrency::query())->orderBy('currency_code');
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

    // Kotak statistik = filter data (griduistandard.md §2b). Grid ini bergaya
    // spreadsheet (spreadsheetgridstandard.md) -- filternya nempel di query
    // dasar yang sama, jadi perilakunya seragam dgn grid biasa.
    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total Currencies'), 'value' => $this->statTotal, 'tone' => 'total'],
            'active' => ['label' => t('Active'), 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'inactive' => ['label' => t('Inactive'), 'value' => $this->statInactive, 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('activate', false)],
        ];
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />

        <x-grid.spreadsheet-table
            grid-key="{{ $this->spreadsheetKey() }}"
            :columns="$this->spreadsheetColumns()"
            :rows="$this->spreadsheetRows()"
            :new-row-defaults="$this->spreadsheetNewRowDefaults()"
            :errors="$this->spreadsheetErrors"
            :version="$this->spreadsheetVersion" />
    </div>
</div>
