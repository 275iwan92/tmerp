<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Pegawai aktif menurut jenis kelamin. Referensi: workforce composition (Zoho People).
final class HeadcountByGender extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'headcount_by_gender', 'title' => 'Headcount by Gender', 'type' => 'chart', 'dashboard' => 'HR', 'module' => 'HRM',
            'description' => 'Active employees split into male and female.',
            'icon' => 'fa-venus-mars', 'tone' => 'violet', 'phrases' => ['pegawai pria wanita', 'jenis kelamin pegawai', 'headcount by gender', 'gender'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->headcountByGender();
        $other = array_sum($by) - ($by['L'] ?? 0) - ($by['P'] ?? 0);

        return ['caption' => 'Active employees today', 'source' => 'tm2mst.emp_employee',
            'data' => ['chart' => 'donut', 'format' => 'number', 'height' => '240px', 'labels' => [t('Male'), t('Female'), t('Not filled in')],
                'series' => [['name' => t('Employees'), 'data' => [(int) ($by['L'] ?? 0), (int) ($by['P'] ?? 0), $other]]]]];
    }
}
