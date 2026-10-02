<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Pegawai aktif per hari ini; baris bawah = yang masuk dan keluar pada periode itu.
// Referensi: headcount, new hires, attrition (Zoho People, NetSuite).
final class Headcount extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'headcount', 'title' => 'Headcount', 'type' => 'kpi', 'dashboard' => 'HR', 'module' => 'HRM',
            'description' => 'Active employees today, with those who joined and left in the period.',
            'icon' => 'fa-users', 'tone' => 'teal', 'phrases' => ['jumlah pegawai', 'jumlah karyawan', 'pegawai aktif', 'headcount', 'employees'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $h = $n->headcount($n->from(), $n->to());

        return ['source' => 'tm2mst.emp_employee', 'data' => ['value' => $h['active'], 'format' => 'number', 'hint' => 'Active employees',
            'note' => raw($this->say('Joined :a · Left :b', ['a' => $h['joined'], 'b' => $h['resigned']]).' · '.$n->rangeText())]];
    }
}
