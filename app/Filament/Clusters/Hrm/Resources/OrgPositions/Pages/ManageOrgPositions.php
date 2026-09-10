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
            // Standar tombol aksi LOCKED (CLAUDE.md §3) -- label modal
            // Save/Cancel bawaan Filament di-override manual.
            CreateAction::make()
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batal'),
        ];
    }
}
