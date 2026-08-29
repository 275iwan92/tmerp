<?php

namespace App\Filament\Clusters\Hrm\Resources\OrgPositions\Pages;

use App\Filament\Clusters\Hrm\Resources\OrgPositions\OrgPositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOrgPositions extends ManageRecords
{
    protected static string $resource = OrgPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
