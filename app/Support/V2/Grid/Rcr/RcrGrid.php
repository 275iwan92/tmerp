<?php

namespace App\Support\V2\Grid\Rcr;

use App\Support\V2\Grid\Hrm\HrmGrid;

// Induk grid Recruitment (tm2hrr, menu Human Resources > Recruitment). Data kandidat masuk lewat sync portal, bukan diketik.
abstract class RcrGrid extends HrmGrid
{
    protected const APP_TONES = ['APPLIED' => 'info', 'IN_PROGRESS' => 'warn', 'OFFERING' => 'accent', 'HIRED' => 'success',
        'REJECTED' => 'danger', 'WITHDRAWN' => 'neutral'];

    public function group(): string
    {
        return 'Recruitment';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function icon(): string
    {
        return 'fa-solid fa-user-plus';
    }

    protected function appStats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'New', 'where' => ['status' => 'APPLIED'], 'tone' => 'info'],
            ['label' => 'In Process', 'where' => ['status' => ['IN_PROGRESS', 'OFFERING']], 'tone' => 'warn'],
            ['label' => 'Hired', 'where' => ['status' => 'HIRED'], 'tone' => 'success'],
            ['label' => 'Rejected', 'where' => ['status' => ['REJECTED', 'WITHDRAWN']], 'tone' => 'danger'],
        ];
    }
}
