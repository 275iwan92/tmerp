<?php

use App\Livewire\Concerns\HasSpreadsheetGrid;
use App\Models\Master\FinCurrency;
use App\Models\Master\FinExchangeRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master Kurs -- docs/configurationstandard.md §2.4 & BACKLOG.md §2 (CRUD
// utk fin_exchange_rate, sebelumnya cuma tabel tanpa layar). Pola versioned
// SAMA PERSIS CLAUDE.md §4: 1 baris activate=1 per currency = kurs berlaku,
// effective_date utk riwayat -- spreadsheetPersistRow() otomatis
// nonaktifkan baris lama saat baris baru diaktifkan utk currency yang sama
// (sama semangat "1 is_default" di Master Bahasa Configuration).
new #[Title('Exchange Rate Master — TELASIH MAKMUR ERP')] class extends Component
{
    use HasSpreadsheetGrid;

    protected function spreadsheetKey(): string
    {
        return 'master-exchange-rate';
    }

    protected function spreadsheetColumns(): array
    {
        return [
            'currency_code' => ['label' => t('Currency'), 'type' => 'select', 'options' => FinCurrency::query()->where('activate', true)->orderBy('currency_code')->pluck('currency_code')->all(), 'required' => true],
            'rate_to_base' => ['label' => t('Rate to IDR'), 'type' => 'number', 'required' => true],
            'effective_date' => ['label' => t('Effective From (YYYY-MM-DD)'), 'type' => 'text'],
            'activate' => ['label' => t('Active'), 'type' => 'boolean'],
        ];
    }

    protected function spreadsheetQuery()
    {
        return FinExchangeRate::query()->with('currency')->orderByDesc('activate')->orderBy('currency_id');
    }

    protected function spreadsheetRowId($row): int|string
    {
        return $row->id_exchange_rate;
    }

    protected function spreadsheetNewRowDefaults(): array
    {
        return ['currency_code' => '', 'rate_to_base' => '', 'effective_date' => now()->format('Y-m-d'), 'activate' => true];
    }

    protected function spreadsheetValidateRow(array $data, int|string|null $existingId): array
    {
        $clean = Validator::make($data, [
            'currency_code' => ['required', 'string', 'exists:tm2mst.fin_currency,currency_code'],
            'rate_to_base' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['nullable', 'date'],
            'activate' => ['boolean'],
        ])->validate();

        $clean['currency_id'] = FinCurrency::where('currency_code', $clean['currency_code'])->value('id_currency');

        return $clean;
    }

    protected function spreadsheetPersistRow(array $data, int|string|null $existingId): void
    {
        $payload = [
            'currency_id' => $data['currency_id'],
            'rate_to_base' => $data['rate_to_base'],
            'effective_date' => $data['effective_date'] ?: null,
            'activate' => (bool) ($data['activate'] ?? false),
            'updated_by' => Auth::user()->login,
        ];

        // 1 baris aktif per currency -- baris lain punya currency yang sama
        // otomatis dinonaktifkan saat baris ini diaktifkan (pola versioned
        // CLAUDE.md §4, sama spt is_default Master Bahasa).
        if ($payload['activate']) {
            FinExchangeRate::where('currency_id', $payload['currency_id'])
                ->when($existingId, fn ($q) => $q->whereKeyNot($existingId))
                ->update(['activate' => false]);
        }

        if ($existingId) {
            FinExchangeRate::whereKey($existingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            FinExchangeRate::create($payload);
        }
    }

    #[Computed]
    public function statTotal(): int
    {
        return FinExchangeRate::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return FinExchangeRate::where('activate', true)->count();
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="2">
            <x-grid.stat-box tone="total" :label="t('Total Rate Rows')" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" :label="t('Active Rates')" :value="$this->statActive" />
        </x-grid.stat-row>

        <p class="text-[11px] text-muted mb-3">{{ t('Rate of 1 currency unit = this many IDR. Activate 1 row per currency to make it the effective rate — older rows for the same currency are deactivated automatically.') }}</p>

        <x-grid.spreadsheet-table
            grid-key="{{ $this->spreadsheetKey() }}"
            :columns="$this->spreadsheetColumns()"
            :rows="$this->spreadsheetRows()"
            :new-row-defaults="$this->spreadsheetNewRowDefaults()"
            :errors="$this->spreadsheetErrors"
            :version="$this->spreadsheetVersion" />
    </div>
</div>
