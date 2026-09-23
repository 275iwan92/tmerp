<?php

namespace App\Filament\Clusters\Hrm\Resources\EmpEmployees\Pages;

use App\Filament\Base\TmListRecords;
use App\Filament\Clusters\Hrm\Resources\EmpEmployees\EmpEmployeeResource;

class ListEmpEmployees extends TmListRecords
{
    protected static string $resource = EmpEmployeeResource::class;
}
