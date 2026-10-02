<?php

use App\Livewire\Concerns\HasGridStatFilter;
use App\Livewire\Concerns\HasSpreadsheetGrid;
use App\Models\Master\SupCategory;
use App\Models\Master\SupSupplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Master Kategori Supplier (2026-09-23) -- sup_category dulu cuma bisa dipilih,
// tidak bisa ditambah dari layar mana pun (CLAUDE.md §7.12). Keputusan user:
// supplier = semua vendor, dibedakan kategori -- mis. BANK (lookup rekening gaji
// pegawai), EKSPEDISI (vendor truk angkut produk), plus kategori bahan/jasa lama.
new #[Title('Supplier Category Master — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridStatFilter;

    use HasSpreadsheetGrid;

    protected function spreadsheetKey(): string
    {
        return 'master-supplier-category';
    }

    protected function spreadsheetColumns(): array
    {
        return [
            'category_code' => ['label' => t('Category Code'), 'type' => 'text', 'required' => true, 'editableWhenExisting' => false],
            'description' => ['label' => t('Description'), 'type' => 'text'],
            'activate' => ['label' => t('Active'), 'type' => 'boolean'],
        ];
    }

    protected function spreadsheetQuery()
    {
        return $this->applyStatFilter(SupCategory::query())->orderBy('category_code');
    }

    protected function spreadsheetRowId($row): int|string
    {
        return $row->category_code;
    }

    protected function spreadsheetNewRowDefaults(): array
    {
        return ['category_code' => '', 'description' => '', 'activate' => true];
    }

    protected function spreadsheetValidateRow(array $data, int|string|null $existingId): array
    {
        $data['category_code'] = strtoupper(trim((string) $data['category_code']));

        $unique = 'unique:tm2mst.sup_category,category_code';
        if ($existingId) {
            $unique .= ','.$existingId.',category_code';
        }

        return Validator::make($data, [
            'category_code' => ['required', 'string', 'max:50', $unique],
            'description' => ['nullable', 'string', 'max:150'],
            'activate' => ['boolean'],
        ])->validate();
    }

    protected function spreadsheetPersistRow(array $data, int|string|null $existingId): void
    {
        $payload = [
            'description' => $data['description'] ?: null,
            'activate' => (bool) ($data['activate'] ?? false),
            'updated_by' => Auth::user()->login,
        ];

        if ($existingId) {
            SupCategory::where('category_code', $existingId)->update($payload);
        } else {
            SupCategory::create($payload + ['category_code' => $data['category_code'], 'created_by' => Auth::user()->login]);
        }
    }

    #[Computed]
    public function statTotal(): int
    {
        return SupCategory::count();
    }

    #[Computed]
    public function statActive(): int
    {
        return SupCategory::where('activate', true)->count();
    }

    #[Computed]
    public function statBank(): int
    {
        return SupSupplier::where('category', 'BANK')->count();
    }

    protected function statFilterDefs(): array
    {
        return [
            'total' => ['label' => t('Total Categories'), 'value' => $this->statTotal, 'tone' => 'total'],
            'active' => ['label' => t('Active'), 'value' => $this->statActive, 'tone' => 'active',
                'filter' => fn ($q) => $q->where('activate', true)],
            'inactive' => ['label' => t('Inactive'), 'value' => $this->statTotal - $this->statActive, 'tone' => 'critical',
                'filter' => fn ($q) => $q->where('activate', false)],
            'bank' => ['label' => t('Bank Suppliers'), 'value' => $this->statBank, 'tone' => 'extra',
                'subtitle' => t('Used as payroll bank account lookup')],
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
