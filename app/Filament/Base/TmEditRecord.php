<?php

namespace App\Filament\Base;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

// Induk form Edit panel /admin (lihat TmCreateRecord).
abstract class TmEditRecord extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->modalCancelActionLabel('Batal')];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }
}
