<?php

namespace App\Filament\Base;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

// Induk grid Resource panel /admin. Turunan cukup menyebut $resource.
abstract class TmListRecords extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
