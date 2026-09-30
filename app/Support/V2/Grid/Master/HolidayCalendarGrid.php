<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\SysHoliday;
use App\Support\V2\Data\WorkCalendar;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Holiday Calendar (menggantikan Master Hari Libur v1): kalender setahun berwarna + grid per tahun + form.
// Libur nasional & cuti bersama = SKB 3 Menteri; libur perusahaan diisi sendiri. Dibaca CPDEL (slot kirim), jadwal mesin, absensi.
class HolidayCalendarGrid extends MasterDataGrid
{
    public function slug(): string
    {
        return 'holiday-calendar';
    }

    public function title(): string
    {
        return 'Holiday Calendar';
    }

    public function icon(): string
    {
        return 'fa-solid fa-calendar-days';
    }

    public function search(): string
    {
        return 'Search holiday name...';
    }

    protected function query(): Builder
    {
        return SysHoliday::query()->whereYear('holiday_date', WorkCalendar::year())->orderBy('holiday_date');
    }

    public function columns(): array
    {
        $types = array_keys(WorkCalendar::TONES);

        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'day', 'label' => 'Day'],
            ['key' => 'name', 'label' => 'Holiday', 'type' => 'identity', 'avatar' => false],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => array_combine(array_map([WorkCalendar::class, 'typeLabel'], $types), WorkCalendar::TONES)],
        ];
    }

    protected function row(Model $m): array
    {
        return ['date' => $m->holiday_date->toDateString(), 'day' => $m->holiday_date->locale(Locale::lang())->translatedFormat('l'),
            'name' => WorkCalendar::name($m->holiday_name), 'type' => WorkCalendar::typeLabel($m->holiday_type)];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'National holiday', 'where' => ['type' => WorkCalendar::typeLabel('NATIONAL')], 'tone' => 'danger'],
            ['label' => 'Collective leave', 'where' => ['type' => WorkCalendar::typeLabel('COLLECTIVE_LEAVE')], 'tone' => 'warn'],
            ['label' => 'Company holiday', 'where' => ['type' => WorkCalendar::typeLabel('COMPANY')], 'tone' => 'info'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'neutral'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Type'];
    }

    public function openUrl(): ?string
    {
        return $this->url().'?year='.WorkCalendar::year().'&id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?year='.WorkCalendar::year().'&new=1';
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.holiday-calendar'] + parent::screen();
    }
}
