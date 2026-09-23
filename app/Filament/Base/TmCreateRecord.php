<?php

namespace App\Filament\Base;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

// Induk form Create panel /admin: halaman penuh (bukan modal, UI-10) + label
// tombol baku CLAUDE.md §3. Turunan cukup menyebut $resource.
abstract class TmCreateRecord extends CreateRecord
{
    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Batal');
    }
}
