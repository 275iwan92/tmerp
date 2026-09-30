<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\SysOfficeHour;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Office Hours: jam kantor Telasih per hari (bukan jam produksi -- produksi 24/7 menurut shift). Slot kirim CPDEL wajib di dalamnya.
class OfficeHoursLookup extends DbLookup
{
    public function slug(): string
    {
        return 'office-hours';
    }

    public function title(): string
    {
        return 'Office Hours';
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
        return 'fa-solid fa-business-time';
    }

    public function guide(): ?string
    {
        return 'Telasih office hours: when the company ships and receives goods. Delivery slots (CPDEL) must fall inside these hours and outside the Holiday Calendar. '
            .'Production is not limited by this table: machines run by shift (Master Shift), up to 24/7.';
    }

    protected function query(): Builder
    {
        return SysOfficeHour::query()->orderBy('day_of_week');
    }

    public function columns(): array
    {
        return [
            ['key' => 'day_of_week', 'label' => 'Day No. (1 = Monday)', 'type' => 'number', 'required' => true, 'unique' => true, 'width' => '140px'],
            ['key' => 'day_name', 'label' => 'Day', 'required' => true, 'width' => '140px'],
            ['key' => 'is_workday', 'label' => 'Working Day', 'type' => 'check', 'width' => '120px'],
            ['key' => 'start_time', 'label' => 'Start', 'type' => 'time', 'width' => '110px'],
            ['key' => 'end_time', 'label' => 'End', 'type' => 'time', 'width' => '110px'],
            ['key' => 'notes', 'label' => 'Notes', 'width' => '260px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '90px'],
        ];
    }

    // Kolom TIME MySQL = 07:30:00; sel jam DevLib memakai HH:MM.
    protected function row(Model $m): array
    {
        $row = parent::row($m);
        foreach (['start_time', 'end_time'] as $k) {
            $row[$k] = $row[$k] ? substr((string) $row[$k], 0, 5) : null;
        }

        return $row;
    }

    protected function rules(): array
    {
        return ['day_of_week' => ['integer', 'between:1,7'], 'start_time' => ['required_if_accepted:is_workday', 'date_format:H:i,H:i:s'],
            'end_time' => ['required_if_accepted:is_workday', 'date_format:H:i,H:i:s', 'after:start_time']];
    }
}
