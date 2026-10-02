<?php

use App\Livewire\Concerns\HasSpreadsheetGrid;
use App\Models\Master\SysHoliday;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master Hari Libur -- docs/BACKLOG.md item "mudah" #1 (2026-09-02). Seed
// awal cuma tanggal Masehi TETAP (Tahun Baru/Buruh/Pancasila/Kemerdekaan/
// Natal) -- hari libur lunar (Idul Fitri dst, tanggal beda tiap tahun,
// resmi lewat SKB 3 Menteri) SENGAJA belum diisi, tambahkan di sini begitu
// kalender resmi tahun berjalan terbit.
new #[Title('Holiday Master — TELASIH MAKMUR ERP')] class extends Component
{
    use HasSpreadsheetGrid;

    protected function spreadsheetKey(): string
    {
        return 'master-holiday';
    }

    protected function spreadsheetColumns(): array
    {
        return [
            'holiday_date' => ['label' => t('Date (YYYY-MM-DD)'), 'type' => 'text', 'required' => true, 'editableWhenExisting' => false],
            'holiday_name' => ['label' => t('Holiday Name'), 'type' => 'text', 'required' => true],
            'holiday_type' => ['label' => t('Type'), 'type' => 'select', 'options' => ['NATIONAL', 'COMPANY']],
            'activate' => ['label' => t('Active'), 'type' => 'boolean'],
        ];
    }

    protected function spreadsheetQuery()
    {
        return SysHoliday::query()->orderBy('holiday_date');
    }

    protected function spreadsheetRowId($row): int|string
    {
        return $row->id_holiday;
    }

    protected function spreadsheetNewRowDefaults(): array
    {
        return ['holiday_date' => '', 'holiday_name' => '', 'holiday_type' => 'NATIONAL', 'activate' => true];
    }

    protected function spreadsheetValidateRow(array $data, int|string|null $existingId): array
    {
        $unique = 'unique:tm2mst.sys_holiday,holiday_date';
        if ($existingId) {
            $unique .= ','.$existingId.',id_holiday';
        }

        return Validator::make($data, [
            'holiday_date' => ['required', 'date', $unique],
            'holiday_name' => ['required', 'string', 'max:150'],
            'holiday_type' => ['required', 'in:NATIONAL,COMPANY'],
            'activate' => ['boolean'],
        ])->validate();
    }

    protected function spreadsheetPersistRow(array $data, int|string|null $existingId): void
    {
        $payload = [
            'holiday_date' => $data['holiday_date'],
            'holiday_name' => $data['holiday_name'],
            'holiday_type' => $data['holiday_type'],
            'activate' => (bool) ($data['activate'] ?? false),
            'updated_by' => Auth::user()->login,
        ];

        if ($existingId) {
            SysHoliday::whereKey($existingId)->update($payload);
        } else {
            $payload['created_by'] = Auth::user()->login;
            SysHoliday::create($payload);
        }
    }

    #[Computed]
    public function statTotal(): int
    {
        return SysHoliday::count();
    }

    #[Computed]
    public function statNational(): int
    {
        return SysHoliday::where('holiday_type', 'NATIONAL')->count();
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="2">
            <x-grid.stat-box tone="total" :label="t('Total Holidays')" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" :label="t('National')" :value="$this->statNational" />
        </x-grid.stat-row>

        <p class="text-[11px] text-muted mb-3">{{ t('Fixed-date Gregorian holidays are already filled in.') }} {{ t('Lunar holidays (Eid al-Fitr/Eid al-Adha/Nyepi/Chinese New Year/Vesak/etc.) fall on different dates each year — add them by hand via "+ Row" once the official calendar for the current year is published.') }}</p>

        <x-grid.spreadsheet-table
            grid-key="{{ $this->spreadsheetKey() }}"
            :columns="$this->spreadsheetColumns()"
            :rows="$this->spreadsheetRows()"
            :new-row-defaults="$this->spreadsheetNewRowDefaults()"
            :errors="$this->spreadsheetErrors"
            :version="$this->spreadsheetVersion" />
    </div>
</div>
