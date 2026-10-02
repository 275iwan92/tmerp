<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Pegawai aktif per departemen, delapan terbanyak. Referensi: headcount by department (Zoho People).
final class HeadcountByDepartment extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'headcount_by_department', 'title' => 'Headcount by Department', 'type' => 'chart', 'dashboard' => 'HR', 'module' => 'HRM', 'size' => 6,
            'description' => 'Active employees per department, the eight largest.',
            'icon' => 'fa-sitemap', 'phrases' => ['pegawai per departemen', 'karyawan per bagian', 'headcount by department'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = collect($n->headcountByDepartment())->take(8);

        return ['caption' => 'Active employees today', 'source' => 'tm2mst.emp_employee + org_position + org_department',
            'data' => ['chart' => 'hbar', 'format' => 'number', 'height' => '280px', 'legend' => false, 'labels' => $by->keys()->all(),
                'series' => [['name' => t('Employees'), 'data' => $by->values()->all(), 'tone' => 'teal']]], 'open' => $this->open('HRM_EMPLOYEE', 'Master Employee', 'fa-solid fa-users')];
    }
}
