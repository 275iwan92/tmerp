<?php

namespace App\Support\V2\Grid\Hrm;

use App\Support\V2\Grid\DbGrid;

// Induk grid modul Human Resources: hak akses HRM, tabel HR di tm2mst.
abstract class HrmGrid extends DbGrid
{
    protected const REQUEST_TONES = ['PENDING' => 'warn', 'APPROVED' => 'success', 'AUTO_APPROVED' => 'success', 'REJECTED' => 'danger'];

    public function module(): string
    {
        return 'HRM';
    }

    public function moduleLabel(): string
    {
        return 'Human Resources';
    }

    // Status pengajuan HR (PENDING/APPROVED/REJECTED) -> kotak statistik yang sama di semua layar approval.
    protected function requestStats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Waiting', 'where' => ['status' => 'PENDING'], 'tone' => 'warn'],
            ['label' => 'Approved', 'where' => ['status' => ['APPROVED', 'AUTO_APPROVED']], 'tone' => 'success'],
            ['label' => 'Rejected', 'where' => ['status' => 'REJECTED'], 'tone' => 'danger'],
        ];
    }
}
