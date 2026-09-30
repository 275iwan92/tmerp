<?php

namespace App\Support\V2\Grid\Prod;

use App\Support\V2\Grid\DbGrid;

// Induk Grid Master modul Production (data mesin & proses = DATA REAL pabrik, grid hanya membaca; ubah lewat form v1).
abstract class ProdGrid extends DbGrid
{
    public function module(): string
    {
        return 'PROD';
    }

    public function moduleLabel(): string
    {
        return 'Production';
    }

    public function family(): string
    {
        return self::MASTER;
    }
}
