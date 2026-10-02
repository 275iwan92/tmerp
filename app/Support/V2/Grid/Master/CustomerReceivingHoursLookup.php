<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\CustCustomer;
use App\Models\Master\CustReceivingHour;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

// Customer Receiving Hours: jam customer menerima barang. Customer kosong = jam default semua customer (jam kantor normal
// Indonesia); customer yang berbeda cukup ditambah barisnya. Dipakai CPDEL (slot kirim) & Proof of Delivery (tiba di luar jam).
class CustomerReceivingHoursLookup extends DbLookup
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function slug(): string
    {
        return 'customer-receiving-hours';
    }

    public function title(): string
    {
        return 'Customer Receiving Hours';
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
        return 'fa-solid fa-door-open';
    }

    public function search(): string
    {
        return 'Search customer or day...';
    }

    public function guide(): ?string
    {
        return 'Rows without a customer are the default for every customer (normal Indonesian office hours). '
            .'Add rows for a customer only when their receiving hours differ; that customer then uses its own row for that day.';
    }

    protected function query(): Builder
    {
        return CustReceivingHour::query()->orderByRaw('customer_id IS NOT NULL')->orderBy('customer_id')->orderBy('day_of_week');
    }

    public function columns(): array
    {
        return [
            ['key' => 'customer_id', 'label' => 'Customer (empty = default)', 'type' => 'select', 'width' => '280px',
                'options' => CustCustomer::query()->where('activate', true)->orderBy('customer_name')->pluck('customer_name', 'id_customer')->all()],
            ['key' => 'day_of_week', 'label' => 'Day', 'type' => 'select', 'required' => true, 'options' => self::DAYS, 'width' => '120px'],
            ['key' => 'is_open', 'label' => 'Open', 'type' => 'check', 'width' => '80px'],
            ['key' => 'open_time', 'label' => 'Open', 'type' => 'time', 'width' => '100px'],
            ['key' => 'close_time', 'label' => 'Close', 'type' => 'time', 'width' => '100px'],
            ['key' => 'break_start', 'label' => 'Break from', 'type' => 'time', 'width' => '110px'],
            ['key' => 'break_end', 'label' => 'Break to', 'type' => 'time', 'width' => '100px'],
            ['key' => 'notes', 'label' => 'Notes', 'width' => '220px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function row(Model $m): array
    {
        $row = parent::row($m);
        foreach (['open_time', 'close_time', 'break_start', 'break_end'] as $k) {
            $row[$k] = $row[$k] ? substr((string) $row[$k], 0, 5) : null;
        }

        return $row;
    }

    protected function rules(): array
    {
        return ['day_of_week' => ['integer', 'between:1,7'], 'open_time' => ['required_if_accepted:is_open'], 'close_time' => ['required_if_accepted:is_open', 'after:open_time'],
            'break_end' => ['nullable', 'required_with:break_start', 'after:break_start']];
    }

    // Satu customer (atau default) hanya boleh satu baris per hari.
    public function save(array $changed): array
    {
        $seen = [];
        foreach ($changed as $i => $in) {
            $key = ($in['customer_id'] ?? '').'|'.($in['day_of_week'] ?? '');
            $id = is_numeric($in['id'] ?? null) ? (int) $in['id'] : 0;
            $dup = isset($seen[$key]) || CustReceivingHour::query()->where('day_of_week', $in['day_of_week'] ?? 0)->whereKeyNot($id)
                ->when(($in['customer_id'] ?? '') === '' || $in['customer_id'] === null, fn ($q) => $q->whereNull('customer_id'), fn ($q) => $q->where('customer_id', $in['customer_id']))
                ->exists();
            if ($dup) {
                throw ValidationException::withMessages(['row' => str_replace(':n', (string) ($i + 1), t('Row :n:')).' '.str_replace(':day', t(self::DAYS[$in['day_of_week'] ?? 0] ?? 'that day'), t('this customer already has hours for :day.'))]);
            }
            $seen[$key] = true;
        }

        return parent::save($changed);
    }
}
