<?php

namespace App\Support\V2\Grid\Maint;

use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar lembar Daily Check di layar v2 maintenance-daily-check. + New Record = pilih aset, tanggal dan shift lalu lembar dibuka.
class DailyCheckListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'maintenance-daily-check';
    }

    public function title(): string
    {
        return 'Daily Check';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    protected function listId(): string
    {
        return 'mnt-daily-check-list';
    }

    protected function listRows(): array
    {
        return Maintenance::dailyCheckRows();
    }

    protected function sources(): array
    {
        return ['tm2mnt.mnt_daily_check', 'tm2mnt.mnt_daily_check_line', 'tm2mnt.mnt_asset'];
    }

    public function search(): string
    {
        return 'Search sheet, asset...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Sheet / Asset', 'type' => 'doc', 'party' => 'asset'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'shift', 'label' => 'Shift'],
            ['key' => 'by', 'label' => 'Checked By'],
            ['key' => 'lines', 'label' => 'Points', 'type' => 'number'],
            ['key' => 'notok', 'label' => 'Not OK', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Open' => 'warn', 'Submitted' => 'success']],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Open', 'where' => ['status' => 'Open'], 'tone' => 'warn'], ['label' => 'Submitted', 'where' => ['status' => 'Submitted'], 'tone' => 'success']];
    }

    public function filters(): array
    {
        return ['asset' => 'Asset', 'shift' => 'Shift', 'status' => 'Status'];
    }
}
