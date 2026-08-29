<?php

namespace App\Filament\Clusters\Hrm\Resources\EmpEmployees\Pages;

use App\Filament\Clusters\Hrm\Resources\EmpEmployees\EmpEmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmpEmployees extends ManageRecords
{
    protected static string $resource = EmpEmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
