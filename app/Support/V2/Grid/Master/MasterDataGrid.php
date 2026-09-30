<?php

namespace App\Support\V2\Grid\Master;

use App\Support\V2\Grid\DbGrid;

// Induk Grid Master modul Master Data: hak akses MDATA, keluarga Grid Master (kotak per baris, Edit + Aktif/Nonaktif).
abstract class MasterDataGrid extends DbGrid
{
    public function module(): string
    {
        return 'MDATA';
    }

    public function moduleLabel(): string
    {
        return 'Master Data';
    }

    public function family(): string
    {
        return self::MASTER;
    }
}
