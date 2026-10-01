<?php

namespace App\Support\V2\Grid\Planning;

use App\Models\Master\MatCategoryLeadTime;
use App\Support\V2\Data\MaterialPlanning;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Purchase Lead Time per kategori bahan (hari kerja). Urutan baca Material Planning: item (mat_raw.lead_time_days)
// -> kategori -> induk kategori -> konstanta PURCHASE_LEAD_DAYS_DEFAULT.
class PurchaseLeadTimeLookup extends DbLookup
{
    public function slug(): string
    {
        return 'purchase-lead-time';
    }

    public function title(): string
    {
        return 'Purchase Lead Time';
    }

    public function module(): string
    {
        return 'MDATA';
    }

    public function moduleLabel(): string
    {
        return 'Master Data';
    }

    public function icon(): string
    {
        return 'fa-solid fa-truck-clock';
    }

    public function guide(): ?string
    {
        // Maksimal 255 huruf supaya bisa diterjemahkan lewat kamus.
        return 'Working days from order to goods received, per material category; used for "Order By (latest)" in Material Planning. '
            .'An item can override it in Master Raw Material; an empty category follows its parent, then PURCHASE_LEAD_DAYS_DEFAULT in Constants.';
    }

    protected function query(): Builder
    {
        return MatCategoryLeadTime::query()->where('level', '<=', 2)->where('activate', 1)->orderBy('level')->orderBy('category_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'category_name', 'label' => 'Category', 'readonly' => true, 'width' => '280px'],
            ['key' => 'level', 'label' => 'Level', 'type' => 'number', 'readonly' => true, 'width' => '80px'],
            ['key' => 'stock_class', 'label' => 'Stock Class', 'readonly' => true, 'width' => '120px'],
            ['key' => 'lead_time_days', 'label' => 'Lead Time (working days)', 'type' => 'number', 'decimals' => 0, 'width' => '190px'],
        ];
    }

    protected function rules(): array
    {
        return ['lead_time_days' => ['nullable', 'integer', 'between:0,365']];
    }

    public function save(array $changed): array
    {
        // Baris baru tidak dibuat di sini (kategori = Master Material Category).
        $rows = parent::save(array_values(array_filter($changed, fn ($r) => is_numeric($r['id'] ?? null))));
        MaterialPlanning::changed();

        return $rows;
    }
}
