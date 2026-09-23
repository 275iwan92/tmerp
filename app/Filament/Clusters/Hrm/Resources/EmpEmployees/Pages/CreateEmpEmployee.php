<?php

namespace App\Filament\Clusters\Hrm\Resources\EmpEmployees\Pages;

use App\Filament\Base\TmCreateRecord;
use App\Filament\Clusters\Hrm\Resources\EmpEmployees\EmpEmployeeResource;

class CreateEmpEmployee extends TmCreateRecord
{
    protected static string $resource = EmpEmployeeResource::class;
}
